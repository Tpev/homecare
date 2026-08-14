<?php

namespace App\Services\AiSupport;

use App\Models\AiSupportConfirmedActionEvidence;
use App\Models\AiSupportMessageAction;
use App\Models\AiSupportRequestDraft;
use App\Models\CareRequest;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AiSupportRecapService
{
    public function __construct(
        private readonly AiSupportEligibilityService $eligibility,
        private readonly AiSupportControlService $controls,
        private readonly AiSupportRequestDraftService $drafts,
        private readonly AiSupportActionEvidenceService $evidence,
        private readonly AiSupportCareRequestPublisher $publisher,
        private readonly AiSupportEventRecorder $events,
    ) {}

    public function issue(User $actor, SupportTicket $ticket, AiSupportRequestDraft $draft): AiSupportMessageAction
    {
        if (! $this->eligibility->evaluate($actor, 'care_request_recap_v1', $ticket)->allowed
            || (int) $draft->actor_user_id !== (int) $actor->id
            || (int) $draft->support_ticket_id !== (int) $ticket->id) {
            throw new AuthorizationException;
        }

        $recap = $this->drafts->recap($actor, $draft);
        $toolId = $draft->request_type === CareRequest::TYPE_ONE_TIME
            ? 'care-request.publish.one-time'
            : 'care-request.publish.recurring';
        $commitControl = $draft->request_type === CareRequest::TYPE_ONE_TIME ? 'commit.one_time' : 'commit.recurring';
        $canConfirm = $this->controls->enabled($commitControl)
            && $this->eligibility->evaluate($actor, 'care_request_publish_v1', $ticket, $toolId)->allowed;

        return DB::transaction(function () use ($actor, $ticket, $draft, $recap, $toolId, $canConfirm): AiSupportMessageAction {
            $lockedTicket = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            if ($lockedTicket->responder_mode !== SupportTicket::RESPONDER_MODE_AUTOMATED
                || $lockedTicket->status === SupportTicket::STATUS_CLOSED
                || $lockedTicket->transcript_deleted_at) {
                throw new AuthorizationException;
            }
            $confirmationReference = null;
            if ($canConfirm) {
                $created = $this->evidence->createPreview(
                    $actor,
                    $lockedTicket,
                    'care_request_publish_v1',
                    $toolId,
                    'v1',
                    [
                        'draft_id' => $draft->id,
                        'draft_version' => $draft->version,
                        'material_hash' => $draft->material_hash,
                        'request_type' => $draft->request_type,
                    ],
                    now()->addMinutes((int) config('ai_support.confirmation_validity_minutes', 30)),
                );
                $confirmationReference = $created['confirmation_reference'];
            }

            AiSupportMessageAction::query()->where('support_ticket_id', $lockedTicket->id)
                ->where('actor_user_id', $actor->id)
                ->where('action_type', AiSupportMessageAction::TYPE_RECAP)
                ->whereNull('invalidated_at')
                ->update(['payload' => null, 'invalidated_at' => now(), 'invalidation_reason' => 'superseded_recap']);

            $message = $this->automatedMessage(
                $lockedTicket,
                $canConfirm
                    ? 'Your private draft is ready. Review every detail below before you create the request.'
                    : 'Your private draft is ready to review. Creating it in chat is not enabled for this pilot yet.',
            );
            $action = AiSupportMessageAction::query()->create([
                'id' => (string) Str::uuid(),
                'support_ticket_message_id' => $message->id,
                'support_ticket_id' => $lockedTicket->id,
                'actor_user_id' => $actor->id,
                'action_type' => AiSupportMessageAction::TYPE_RECAP,
                'payload' => [
                    'recap' => $recap,
                    'confirmation_reference' => $confirmationReference,
                    'idempotency_key' => (string) Str::uuid(),
                    'confirmation_action' => 'publish_confirmed_care_request',
                    'can_confirm' => $canConfirm,
                ],
                'expires_at' => $canConfirm
                    ? now()->addMinutes((int) config('ai_support.confirmation_validity_minutes', 30))
                    : null,
            ]);
            $this->events->record($lockedTicket, 'request_recap_generated', [
                'support_ticket_message_id' => $message->id,
                'capability_id' => 'care_request_recap_v1',
                'result_code' => $canConfirm ? 'confirmation_issued' : 'recap_only',
            ], $actor);

            return $action;
        }, 3);
    }

    public function confirm(User $actor, SupportTicket $ticket, string $actionId): CareRequest
    {
        $action = AiSupportMessageAction::query()->whereKey($actionId)
            ->where('support_ticket_id', $ticket->id)
            ->where('actor_user_id', $actor->id)
            ->where('action_type', AiSupportMessageAction::TYPE_RECAP)
            ->first();
        if (! $action) {
            throw new AuthorizationException;
        }
        $payload = (array) $action->payload;

        if ($action->consumed_at) {
            $existing = AiSupportConfirmedActionEvidence::query()
                ->where('idempotency_key', $payload['idempotency_key'] ?? '')
                ->where('actor_user_id', $actor->id)
                ->first();
            if ($existing) {
                return CareRequest::query()->findOrFail($existing->domain_reference_id);
            }
        }
        if ($action->invalidated_at || ! $action->expires_at?->isFuture() || ! ($payload['can_confirm'] ?? false)) {
            throw ValidationException::withMessages([
                'confirmation' => 'This confirmation expired or changed. Review the current draft and confirm again.',
            ]);
        }

        $confirmed = $this->evidence->commitConfirmedAction(
            $actor,
            (string) $payload['confirmation_reference'],
            (string) $payload['idempotency_key'],
            (string) $payload['confirmation_action'],
            fn (array $preview): array => $this->publisher->publish($actor, $ticket, $preview),
        );
        $careRequest = CareRequest::query()->findOrFail($confirmed->domain_reference_id);

        DB::transaction(function () use ($actor, $ticket, $action, $confirmed, $careRequest): void {
            $lockedAction = AiSupportMessageAction::query()->lockForUpdate()->findOrFail($action->id);
            if ($lockedAction->consumed_at) {
                return;
            }
            $lockedAction->forceFill([
                'payload' => [
                    'idempotency_key' => $confirmed->idempotency_key,
                    'confirmed_action_evidence_id' => $confirmed->id,
                    'can_confirm' => false,
                ],
                'consumed_at' => now(),
            ])->save();
            $careRequest->forceFill(['ai_support_action_evidence_id' => $confirmed->id])->save();
            $message = $this->automatedMessage(
                $ticket,
                'Your care request is live. Eligible caregivers can now see it. No caregiver has been hired yet, and no payment has been authorized yet.',
            );
            AiSupportMessageAction::query()->create([
                'id' => (string) Str::uuid(),
                'support_ticket_message_id' => $message->id,
                'support_ticket_id' => $ticket->id,
                'actor_user_id' => $actor->id,
                'action_type' => AiSupportMessageAction::TYPE_RECEIPT,
                'payload' => [
                    'care_request_id' => $careRequest->id,
                    'request_reference' => 'care-request-'.$careRequest->id,
                    'recipient' => $careRequest->recipient?->full_name,
                    'url' => route('family.requests.show', $careRequest),
                ],
            ]);
            $this->events->record($ticket, 'request_receipt_delivered', [
                'support_ticket_message_id' => $message->id,
                'capability_id' => 'care_request_publish_v1',
                'tool_id' => $confirmed->tool_id,
                'tool_version' => $confirmed->tool_version,
                'result_code' => 'care_request_live',
            ], $actor);
        }, 3);

        return $careRequest->fresh(['recipient']);
    }

    public function renew(User $actor, SupportTicket $ticket, string $actionId): AiSupportMessageAction
    {
        $old = AiSupportMessageAction::query()->whereKey($actionId)
            ->where('support_ticket_id', $ticket->id)
            ->where('actor_user_id', $actor->id)
            ->where('action_type', AiSupportMessageAction::TYPE_RECAP)
            ->firstOrFail();
        $recap = (array) data_get($old->payload, 'recap', []);
        $draft = filled($recap['draft_id'] ?? null)
            ? AiSupportRequestDraft::query()->findOrFail($recap['draft_id'])
            : AiSupportRequestDraft::query()
                ->where('support_ticket_id', $ticket->id)
                ->where('actor_user_id', $actor->id)
                ->latest('updated_at')
                ->firstOrFail();

        return $this->issue($actor, $ticket, $draft);
    }

    private function automatedMessage(SupportTicket $ticket, string $body): SupportTicketMessage
    {
        return DB::transaction(function () use ($ticket, $body): SupportTicketMessage {
            $locked = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            if ($locked->responder_mode !== SupportTicket::RESPONDER_MODE_AUTOMATED
                || $locked->status === SupportTicket::STATUS_CLOSED
                || $locked->transcript_deleted_at) {
                throw new AuthorizationException;
            }
            $message = SupportTicketMessage::query()->create([
                'support_ticket_id' => $locked->id,
                'sender_user_id' => null,
                'kind' => SupportTicketMessage::KIND_PUBLIC,
                'responder_type' => SupportTicketMessage::RESPONDER_AUTOMATED,
                'body' => $body,
                'client_message_id' => (string) Str::uuid(),
            ]);
            $locked->forceFill([
                'last_public_message_at' => $message->created_at,
                'last_public_message_sender_id' => null,
                'opener_last_read_at' => null,
            ])->save();

            return $message;
        }, 3);
    }
}
