<?php

namespace App\Services\AiSupport;

use App\Models\AiSupportGuidedTask;
use App\Models\AiSupportMessageAction;
use App\Models\CareRecipientProfile;
use App\Models\CareRequest;
use App\Models\CareRequestConversation;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FamilyIntentJourneyService
{
    public function __construct(
        private readonly FamilyIntentCatalog $catalog,
        private readonly KnowledgeBaseRetrievalService $knowledge,
        private readonly AiSupportGuidedTaskService $guidedTasks,
        private readonly AiSupportPreparationService $preparations,
        private readonly AiSupportHandoffService $handoff,
        private readonly AiSupportPricingTruth $pricing,
        private readonly NavigationTargetRegistry $navigation,
        private readonly FamilyAccountContext $familyAccounts,
        private readonly AiSupportEventRecorder $events,
    ) {}

    /** @param array<string,mixed> $record */
    public function respond(User $actor, SupportTicket $ticket, array $record, string $message, string $source = 'catalog'): void
    {
        $intentId = (string) $record['intent_id'];
        $this->record($ticket, 'intent_recognized', $intentId, 'recognized', $actor, $source);

        $prefill = (string) data_get($record, 'contracts.prefill', '');
        if (in_array($prefill, array_keys(app(AiSupportPreparationContractRegistry::class)->all()), true)) {
            if ($this->prepare($actor, $ticket, $record, $message, $prefill)) {
                return;
            }
        }

        if (data_get($record, 'contracts.human_transfer') === 'SUP-HANDOFF-001') {
            $this->record($ticket, 'intent_transferred', $intentId, 'catalog_human_path', $actor, $source);
            $this->handoff->transfer($actor, $ticket, 'intent_'.$intentId);

            return;
        }

        $unsupported = (string) data_get($record, 'disposition.unsupported_behavior', 'This exact action is not available in chat.');
        $versions = $this->knowledge->forIntent(
            $actor,
            'support_answers_v1',
            (array) $record['kb_stable_ids'],
            'active',
        );
        if ($versions->isEmpty()) {
            $body = $unsupported;
            $this->automatedMessage($ticket, Str::limit($body, 900, ''));
            $this->record($ticket, 'intent_failed', $intentId, 'unsupported_or_unpublished', $actor, $source);

            return;
        }

        $answer = $intentId === 'FAM-PAY-029'
            ? $this->pricing->familyAnswer($message)
            : trim((string) $versions->first()->answer_body);
        $destination = collect((array) data_get($record, 'contracts.destinations', []))
            ->first(fn (string $target): bool => $this->navigation->allowedFor($actor, $target));
        if ($destination) {
            $definition = $this->navigation->definition($destination) ?? [];
            if (filled($definition['client_target_id'] ?? null)) {
                $this->guidedTasks->offerFamilyReadResult(
                    $actor,
                    $ticket,
                    Str::limit($answer, 1000, ''),
                    $intentId,
                    'explicit_kb_mapping',
                    [[
                        'task_type' => $this->taskType((string) $record['domain']),
                        'target_id' => $destination,
                        'label' => (string) ($definition['label'] ?? 'Open the right page'),
                        'verifier_id' => (string) (data_get($record, 'contracts.verifier') ?: 'unavailable_v1'),
                    ]],
                );
                $this->record($ticket, 'intent_action_offered', $intentId, 'guided_target', $actor, $source);

                return;
            }

            $this->navigateMessage($actor, $ticket, $answer, $destination, $intentId);

            return;
        }

        $this->automatedMessage($ticket, Str::limit($answer, 1000, ''));
    }

    /** @param list<string> $candidateIds */
    public function clarify(User $actor, SupportTicket $ticket, array $candidateIds): void
    {
        $choices = collect($candidateIds)->take(2)->map(function (string $intentId): ?array {
            $record = $this->catalog->find($intentId);

            return $record ? ['id' => $intentId, 'label' => (string) $record['intent']] : null;
        })->filter()->values()->all();
        if ($choices === []) {
            $this->automatedMessage($ticket, 'I am not sure which task you mean. Please say what you want to check or change, or ask for a person.');

            return;
        }

        DB::transaction(function () use ($actor, $ticket, $choices): void {
            $message = $this->automatedMessage($ticket, 'I want to take you to the correct help. Which of these do you mean?');
            AiSupportMessageAction::query()->create([
                'id' => (string) Str::uuid(),
                'support_ticket_message_id' => $message->id,
                'support_ticket_id' => $ticket->id,
                'actor_user_id' => $actor->id,
                'action_type' => AiSupportMessageAction::TYPE_INTENT_CHOICES,
                'payload' => ['choices' => $choices],
                'expires_at' => now()->addMinutes(30),
            ]);
            $this->events->record($ticket, 'intent_clarified', [
                'support_ticket_message_id' => $message->id,
                'capability_id' => 'support_answers_v1',
                'result_code' => 'close_neighbor_choice',
            ], $actor);
        }, 3);
    }

    public function choose(User $actor, SupportTicket $ticket, string $actionId, string $intentId): void
    {
        $action = AiSupportMessageAction::query()
            ->whereKey($actionId)
            ->where('support_ticket_id', $ticket->id)
            ->where('actor_user_id', $actor->id)
            ->where('action_type', AiSupportMessageAction::TYPE_INTENT_CHOICES)
            ->firstOrFail();
        $allowed = collect((array) data_get($action->payload, 'choices', []))->pluck('id')->all();
        if (! $action->isActive() || ! in_array($intentId, $allowed, true)) {
            throw ValidationException::withMessages(['intent' => 'This choice expired. Please ask again.']);
        }
        $record = $this->catalog->find($intentId);
        if (! $record) {
            throw ValidationException::withMessages(['intent' => 'This help choice is no longer available.']);
        }
        $action->forceFill(['consumed_at' => now()])->save();
        $this->respond($actor, $ticket, $record, (string) $record['intent'], 'user_clarified');
    }

    /** @param array<string,mixed> $record */
    private function prepare(User $actor, SupportTicket $ticket, array $record, string $message, string $contractId): bool
    {
        $account = $this->familyAccounts->account($actor);
        $context = ['intent_id' => (string) $record['intent_id']];
        $fields = [];

        if ($contractId === 'care_profile_v1') {
            $profile = CareRecipientProfile::query()->forFamilyAccount($account)->where('status', '!=', 'archived')->latest('updated_at')->first();
            $create = (string) $record['intent_id'] === 'FAM-PROFILE-003';
            if (! $create && ! $profile) {
                $this->automatedMessage($ticket, 'I could not find an editable care receiver profile. I can open Care profiles so you can choose or create one.');

                return false;
            }
            $field = match (true) {
                str_contains(mb_strtolower($message), 'mobility') => 'mobility_notes',
                str_contains(mb_strtolower($message), 'communicat') => 'communication_notes',
                str_contains(mb_strtolower($message), 'routine') => 'routine_notes',
                str_contains(mb_strtolower($message), 'food'), str_contains(mb_strtolower($message), 'allerg') => 'food_and_drink_notes',
                str_contains(mb_strtolower($message), 'safety') => 'safety_notes',
                str_contains(mb_strtolower($message), 'name') => 'preferred_name',
                default => $create ? 'preferred_name' : 'about_them',
            };
            $value = $field === 'preferred_name'
                ? $this->profileNameValue($message)
                : $this->detailValue($message);
            if ($field === 'preferred_name' && $value === '') {
                $this->automatedMessage($ticket, 'I can prepare that care receiver profile change, but I could not tell which name to use. Please say, for example, "Create a care receiver profile for Maria."');

                return true;
            }
            $fields[$field] = $value;
            if ($profile) {
                $context += ['target_id' => 'family.care_profile.edit', 'resource_type' => 'care_profile', 'resource_id' => $profile->id];
            }
        } elseif ($contractId === 'care_request_reuse_v1') {
            $request = CareRequest::query()->forFamilyAccount($account)->with(['recipient', 'tasks'])->latest('id')->first();
            if (! $request) {
                $this->automatedMessage($ticket, 'I could not find an earlier request to copy. I can still help you start a new care request.');

                return false;
            }
            $fields = [
                'source_request_id' => (string) $request->id,
                'recipient_name' => (string) ($request->recipient?->full_name ?? ''),
                'task_ids' => $request->tasks->pluck('id')->map(fn ($id) => (string) $id)->all(),
                'task_notes' => $request->tasks->mapWithKeys(fn ($task) => [(string) $task->id => (string) ($task->pivot?->task_note ?? '')])->all(),
                'additional_info' => (string) $request->additional_info,
                'address_line1' => (string) $request->address_line1,
                'address_line2' => (string) $request->address_line2,
                'city' => (string) $request->city,
                'state' => (string) $request->state,
                'postal_code' => (string) $request->zip,
                'home_access_notes' => (string) $request->home_access_notes,
                'request_type' => (string) $request->request_type,
            ];
        } elseif ($contractId === 'caregiver_message_v1') {
            $conversation = CareRequestConversation::query()->forUser($actor)->latest('last_message_at')->first();
            if (! $conversation) {
                $this->automatedMessage($ticket, 'I could not find an authorized caregiver conversation. I have not prepared or sent a message.');

                return false;
            }
            $fields = ['conversation_id' => (string) $conversation->id, 'message' => $this->detailValue($message)];
            $context += ['resource_type' => 'conversation', 'resource_id' => $conversation->id];
        } elseif ($contractId === 'submitted_hours_correction_v1') {
            $request = CareRequest::query()->forFamilyAccount($account)->whereHas('booking')->latest('updated_at')->first();
            if (! $request) {
                $this->automatedMessage($ticket, 'I could not find a visit with submitted hours. I have not prepared or submitted a correction.');

                return false;
            }
            $fields = [
                'care_request_id' => (string) $request->id,
                'issue_type' => str_contains(mb_strtolower($message), 'dispute') ? 'dispute' : 'correction',
                'reason' => $this->correctionReasonValue($message),
            ];
            $context += ['resource_type' => 'care_request', 'resource_id' => $request->id];
        } elseif ($contractId === 'support_intake_v1') {
            $lower = mb_strtolower($message);
            $category = match (true) {
                str_contains($lower, 'billing'), str_contains($lower, 'charge') => 'billing',
                str_contains($lower, 'hour'), str_contains($lower, 'time') => 'time_correction',
                str_contains($lower, 'cancel') => 'cancellation',
                str_contains($lower, 'incident'), str_contains($lower, 'unsafe') => 'incident',
                str_contains($lower, 'dispute') => 'dispute',
                default => 'general',
            };
            $fields = [
                'category' => $category,
                'subject' => Str::limit(Str::headline((string) $record['intent']), 120, ''),
                'description' => $message,
                'route_name' => (string) request()->route()?->getName(),
            ];
        }

        if ($fields === []) {
            return false;
        }
        $this->preparations->prepare($actor, $ticket, $contractId, $fields, $context);

        return true;
    }

    private function navigateMessage(User $actor, SupportTicket $ticket, string $answer, string $destination, string $intentId): void
    {
        DB::transaction(function () use ($actor, $ticket, $answer, $destination, $intentId): void {
            $message = $this->automatedMessage($ticket, Str::limit($answer, 1000, ''));
            AiSupportMessageAction::query()->create([
                'id' => (string) Str::uuid(), 'support_ticket_message_id' => $message->id,
                'support_ticket_id' => $ticket->id, 'actor_user_id' => $actor->id,
                'action_type' => AiSupportMessageAction::TYPE_NAVIGATE,
                'payload' => [
                    'target_id' => $destination,
                    'url' => $this->navigation->urlFor($actor, $destination),
                    'label' => (string) (($this->navigation->definition($destination)['label'] ?? null) ?: 'Open the right page'),
                ],
                'expires_at' => now()->addHour(),
            ]);
            $this->record($ticket, 'intent_action_offered', $intentId, 'navigation_target', $actor, 'catalog');
        }, 3);
    }

    private function taskType(string $domain): string
    {
        return match ($domain) {
            'care_profiles' => AiSupportGuidedTask::TYPE_FAMILY_CARE_PROFILE,
            'visits_timesheets' => AiSupportGuidedTask::TYPE_FAMILY_TIMESHEET,
            'communications' => AiSupportGuidedTask::TYPE_FAMILY_MESSAGE,
            'care_history' => AiSupportGuidedTask::TYPE_FAMILY_HISTORY,
            default => AiSupportGuidedTask::TYPE_FAMILY_REQUEST,
        };
    }

    private function detailValue(string $message): string
    {
        $value = preg_replace('/^.*?\b(?:that|to|saying|message|because|reason is)\b\s*/iu', '', trim($message), 1);

        return Str::limit(trim((string) $value), 3000, '');
    }

    private function correctionReasonValue(string $message): string
    {
        $trimmed = trim($message);
        if (preg_match('/\b(?:because|reason is)\b\s*(.+)\z/iu', $trimmed, $matches) === 1) {
            return Str::limit(trim((string) $matches[1]), 3000, '');
        }

        return $this->detailValue($trimmed);
    }

    private function profileNameValue(string $message): string
    {
        $value = trim($message);
        $patterns = [
            '/\bcare[\s-]*(?:receiver|recipient)\s+profile\s+(?:for|named)\s+([\p{L}][\p{L}\p{M}\'’ .-]{0,79}?)(?=\s*(?:[?.!,]|$))/iu',
            '/\b(?:preferred\s+)?name\s+(?:is|to|should\s+be)\s+([\p{L}][\p{L}\p{M}\'’ .-]{0,79}?)(?=\s*(?:[?.!,]|$))/iu',
            '/\bprofile\s+(?:for|named)\s+([\p{L}][\p{L}\p{M}\'’ .-]{0,79}?)(?=\s*(?:[?.!,]|$))/iu',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value, $matches) === 1) {
                return Str::limit(trim((string) $matches[1]), 80, '');
            }
        }

        return '';
    }

    private function record(SupportTicket $ticket, string $event, string $intentId, string $result, User $actor, string $source): void
    {
        $this->events->record($ticket, $event, [
            'capability_id' => 'support_answers_v1',
            'result_code' => $result,
            'safe_metadata' => ['intent_id' => $intentId, 'resolution_source' => $source],
        ], $actor);
    }

    private function automatedMessage(SupportTicket $ticket, string $body): SupportTicketMessage
    {
        return DB::transaction(function () use ($ticket, $body): SupportTicketMessage {
            $locked = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            if ($locked->responder_mode !== SupportTicket::RESPONDER_MODE_AUTOMATED) {
                throw new \RuntimeException('Automated ownership ended before delivery.');
            }
            $message = SupportTicketMessage::query()->create([
                'support_ticket_id' => $locked->id, 'sender_user_id' => null,
                'kind' => SupportTicketMessage::KIND_PUBLIC,
                'responder_type' => SupportTicketMessage::RESPONDER_AUTOMATED,
                'body' => $body, 'client_message_id' => (string) Str::uuid(),
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
