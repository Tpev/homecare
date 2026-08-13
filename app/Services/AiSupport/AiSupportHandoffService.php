<?php

namespace App\Services\AiSupport;

use App\Models\AiSupportActionPreview;
use App\Models\SupportTicket;
use App\Models\SupportTicketActivity;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Services\Notifications\NotificationChannels;
use App\Support\MarketplaceEvent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AiSupportHandoffService
{
    public function __construct(
        private readonly AiSupportEventRecorder $events,
        private readonly AiSupportEligibilityService $eligibility,
        private readonly MarketplaceNotificationService $notifications,
    ) {}

    public function transfer(User $actor, SupportTicket $ticket, string $reasonCode = 'user_requested'): SupportTicket
    {
        if (! Gate::forUser($actor)->allows('view', $ticket)) {
            throw new AuthorizationException;
        }

        [$fresh, $message, $changed] = DB::transaction(function () use ($actor, $ticket, $reasonCode): array {
            $locked = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            if ($locked->isHumanOnly()) {
                return [$locked, null, false];
            }

            $body = $reasonCode === 'user_requested'
                ? "I've sent this conversation to LoLo Support. You can keep using this chat, and you won't need to repeat what you already told me."
                : "I don't want to give you the wrong help. I'm sending this conversation to LoLo Support.";
            $message = SupportTicketMessage::query()->create([
                'support_ticket_id' => $locked->id,
                'sender_user_id' => null,
                'kind' => SupportTicketMessage::KIND_PUBLIC,
                'responder_type' => SupportTicketMessage::RESPONDER_AUTOMATED,
                'body' => $body,
                'client_message_id' => (string) Str::uuid(),
            ]);
            $locked->forceFill([
                'responder_mode' => SupportTicket::RESPONDER_MODE_HUMAN_ONLY,
                'transferred_to_human_at' => $message->created_at,
                'returned_to_automation_at' => null,
                'handoff_reason_code' => $reasonCode,
                'status' => $locked->status === SupportTicket::STATUS_RESOLVED
                    ? SupportTicket::STATUS_OPEN
                    : $locked->status,
                'resolved_at' => $locked->status === SupportTicket::STATUS_RESOLVED ? null : $locked->resolved_at,
                'last_public_message_at' => $message->created_at,
                'last_public_message_sender_id' => null,
                'opener_last_read_at' => null,
                'admin_last_read_at' => null,
            ])->save();
            AiSupportActionPreview::query()
                ->where('support_ticket_id', $locked->id)
                ->whereNull('content_deleted_at')
                ->update([
                    'preview_payload' => null,
                    'invalidated_at' => $message->created_at,
                    'invalidation_reason' => 'human_handoff',
                    'content_deleted_at' => $message->created_at,
                ]);
            SupportTicketActivity::query()->create([
                'support_ticket_id' => $locked->id,
                'actor_user_id' => $actor->id,
                'action' => 'transferred_to_human',
                'metadata' => [
                    'reason_code' => $reasonCode,
                    'responder_mode' => ['from' => SupportTicket::RESPONDER_MODE_AUTOMATED, 'to' => SupportTicket::RESPONDER_MODE_HUMAN_ONLY],
                ],
                'created_at' => now(),
            ]);
            $this->events->record($locked, 'transferred_to_human', [
                'support_ticket_message_id' => $message->id,
                'reason_code' => $reasonCode,
                'result_code' => 'human_only',
                'safe_metadata' => [
                    'ownership_from' => 'automated',
                    'ownership_to' => 'human_only',
                ],
            ], $actor);

            return [$locked->fresh(), $message, true];
        }, 3);

        if (! $changed) {
            return $fresh;
        }

        $admins = User::query()->where('role', 'admin')->get();
        if ($admins->isNotEmpty()) {
            try {
                $this->notifications->notify(
                    recipients: $admins,
                    eventKey: MarketplaceEvent::SUPPORT_TICKET_REPLY,
                    title: 'Support conversation transferred to a person',
                    body: 'Support request #'.$fresh->id.' is now human-only and needs review.',
                    url: route('admin.support.tickets.show', $fresh),
                    payload: ['support_ticket_id' => $fresh->id, 'reason_code' => $reasonCode],
                    subject: $fresh,
                    dedupeKey: 'support-handoff-'.$fresh->id.'-'.$message->id,
                    channelOverrides: [NotificationChannels::EMAIL => false],
                );
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $fresh;
    }

    public function returnToAutomation(User $admin, SupportTicket $ticket, string $reason): SupportTicket
    {
        if (! $admin->isAdministrator()) {
            throw new AuthorizationException;
        }

        $reason = trim($reason);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['returnReason' => 'Enter a concise reason between 5 and 500 characters.']);
        }

        return DB::transaction(function () use ($admin, $ticket): SupportTicket {
            $locked = SupportTicket::query()->with('opener')->lockForUpdate()->findOrFail($ticket->id);
            if (! $locked->opener || ! $this->eligibility->evaluate($locked->opener)->allowed) {
                throw ValidationException::withMessages(['return' => 'The exact user is not currently eligible for automation.']);
            }
            if ($locked->status === SupportTicket::STATUS_CLOSED || $locked->transcript_deleted_at) {
                throw ValidationException::withMessages(['return' => 'Closed or content-deleted conversations cannot return to automation.']);
            }
            if (! $locked->isHumanOnly()) {
                return $locked;
            }

            $message = SupportTicketMessage::query()->create([
                'support_ticket_id' => $locked->id,
                'sender_user_id' => null,
                'kind' => SupportTicketMessage::KIND_PUBLIC,
                'responder_type' => SupportTicketMessage::RESPONDER_AUTOMATED,
                'body' => 'The LoLo Support assistant is available in this conversation again. You can ask for a person at any time.',
                'client_message_id' => (string) Str::uuid(),
            ]);
            $locked->forceFill([
                'responder_mode' => SupportTicket::RESPONDER_MODE_AUTOMATED,
                'returned_to_automation_at' => $message->created_at,
                'handoff_reason_code' => null,
                'last_public_message_at' => $message->created_at,
                'last_public_message_sender_id' => null,
                'opener_last_read_at' => null,
            ])->save();
            $this->events->record($locked, 'returned_to_automation', [
                'support_ticket_message_id' => $message->id,
                'reason_code' => 'admin_deliberate_return',
                'result_code' => 'automated',
                'safe_metadata' => ['ownership_from' => 'human_only', 'ownership_to' => 'automated'],
            ], $admin);

            return $locked->fresh();
        }, 3);
    }

    public function mayDeliverAutomatedReply(SupportTicket $ticket): bool
    {
        $fresh = SupportTicket::query()
            ->with('opener')
            ->whereKey($ticket->id)
            ->where('responder_mode', SupportTicket::RESPONDER_MODE_AUTOMATED)
            ->whereNotIn('status', [SupportTicket::STATUS_CLOSED])
            ->whereNull('transcript_deleted_at')
            ->first();

        return $fresh?->opener !== null
            && $this->eligibility->evaluate($fresh->opener, 'support_answers_v1', $fresh)->allowed;
    }
}
