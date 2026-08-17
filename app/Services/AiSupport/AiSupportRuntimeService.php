<?php

namespace App\Services\AiSupport;

use App\Models\AiSupportInteractionEvent;
use App\Models\AiSupportMessageAction;
use App\Models\AiSupportRequestDraft;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class AiSupportRuntimeService
{
    public function __construct(
        private readonly AiSupportEligibilityService $eligibility,
        private readonly KnowledgeBaseRetrievalService $knowledge,
        private readonly AiSupportFamilyContextService $familyContext,
        private readonly AiSupportRequestDraftService $drafts,
        private readonly AiSupportRecapService $recaps,
        private readonly AiSupportGuidedTaskService $guidedTasks,
        private readonly FamilyGuidedAssistanceService $familyGuidance,
        private readonly AiSupportHandoffService $handoff,
        private readonly NavigationTargetRegistry $navigation,
        private readonly AiSupportRuntimePromptBuilder $prompt,
        private readonly AiSupportOpenAiClient $client,
        private readonly AiSupportEventRecorder $events,
        private readonly AiSupportControlService $controls,
    ) {}

    public function respond(User $actor, SupportTicket $ticket, string $newestMessage): void
    {
        $ticket = $ticket->fresh(['opener']);
        if (! $ticket || $ticket->responder_mode !== SupportTicket::RESPONDER_MODE_AUTOMATED) {
            return;
        }
        if (! $this->eligibility->evaluate($actor, 'support_answers_v1', $ticket)->allowed) {
            $this->handoff->transfer($actor, $ticket, 'capability_unavailable');

            return;
        }

        $guard = $this->deterministicGuard($newestMessage);
        if ($guard === 'emergency') {
            $this->automatedMessage($ticket, 'LoLo is not an emergency service. If someone is in immediate danger, call 911 now.');
            $this->handoff->transfer($actor, $ticket, 'emergency');

            return;
        }
        if ($guard === 'medical') {
            $this->automatedMessage($ticket, 'LoLo provides non-medical care and cannot give medical advice or arrange a clinical procedure.');
            $this->handoff->transfer($actor, $ticket, 'medical_boundary');

            return;
        }
        if ($guard === 'continuous_coverage') {
            $this->handoff->transfer($actor, $ticket, 'continuous_coverage');

            return;
        }
        if ($guard === 'human_requested') {
            $this->handoff->transfer($actor, $ticket, 'user_requested');

            return;
        }

        if ($actor->role === 'family' && $this->guidedTasks->isPaymentMethodIntent($newestMessage)) {
            try {
                $this->guidedTasks->offerPaymentMethod($actor, $ticket);
            } catch (Throwable $exception) {
                report($exception);
                $this->handoff->transfer($actor, $ticket, 'guided_payment_unavailable');
            }

            return;
        }

        $familyIntent = $actor->role === 'family'
            ? $this->familyGuidance->intentFor($newestMessage)
            : null;
        if ($familyIntent !== null) {
            try {
                $this->familyGuidance->respond($actor, $ticket, $familyIntent);
            } catch (Throwable $exception) {
                report($exception);
                $this->handoff->transfer($actor, $ticket, 'family_status_unavailable');
            }

            return;
        }

        if (! config('ai_support.provider_enabled', false)) {
            $this->handoff->transfer($actor, $ticket, 'capability_unavailable');

            return;
        }

        $dayStartedAt = now('UTC')->startOfDay();
        $dailyTurns = AiSupportInteractionEvent::query()
            ->where('actor_user_id', $actor->id)
            ->whereIn('event_type', ['model_turn_completed', 'model_turn_failed'])
            ->where('occurred_at', '>=', $dayStartedAt)
            ->count();
        if ($dailyTurns >= (int) config('ai_support.pilot_daily_model_turn_limit', 50)) {
            $this->handoff->transfer($actor, $ticket, 'daily_turn_limit');

            return;
        }

        $dailySpent = (int) AiSupportInteractionEvent::query()
            ->where('occurred_at', '>=', $dayStartedAt)
            ->sum('cost_microunits');
        if ($dailySpent >= (int) config('ai_support.pilot_daily_cost_stop_microunits', 5_000_000)) {
            $this->handoff->transfer($actor, $ticket, 'daily_cost_limit');

            return;
        }

        $spent = (int) AiSupportInteractionEvent::query()
            ->where('support_ticket_id', $ticket->id)
            ->sum('cost_microunits');
        if ($spent >= (int) config('ai_support.conversation_cost_stop_microunits', 50_000)) {
            $this->handoff->transfer($actor, $ticket, 'cost_limit');

            return;
        }

        $knowledge = $this->knowledge->relevant($actor, 'support_answers_v1', $newestMessage, 'active');
        $draft = AiSupportRequestDraft::query()
            ->where('support_ticket_id', $ticket->id)
            ->where('actor_user_id', $actor->id)
            ->first();
        if ($draft && ! $draft->isUsable()) {
            $draft = null;
        }

        $familyContext = null;
        $careRelevant = $draft !== null || preg_match('/\b(care|caregiver|request|visit|weekly|recurring|regular|one[- ]time|recipient|address|schedule)\b/i', $newestMessage);
        if ($actor->role === 'family' && $careRelevant
            && $this->eligibility->evaluate($actor, 'family_context_v1', $ticket)->allowed) {
            $familyContext = $this->familyContext->read(
                $actor,
                $ticket,
                (bool) preg_match('/\b(same as (last|before)|previous request|last request)\b/i', $newestMessage),
            );
        }

        try {
            $provider = $this->client->respond(
                $this->prompt->instructions(),
                $this->prompt->input($actor, $ticket, $newestMessage, $knowledge, $familyContext, $draft),
                (int) $actor->id,
            );
            $result = $provider['result'];
            $message = $this->safeMessage($result['message'] ?? '');
            $kbIds = $knowledge->filter(fn ($version): bool => in_array(
                $version->entry?->stable_id,
                (array) ($result['kb_stable_ids'] ?? []),
                true,
            ))->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
            $this->events->record($ticket, 'model_turn_completed', [
                'pilot_grant_id' => $this->eligibility->evaluate($actor, 'support_answers_v1', $ticket)->grantId,
                'capability_id' => 'support_answers_v1',
                'model_configuration_version' => (string) config('ai_support.model_configuration_version'),
                'prompt_schema_version' => AiSupportRuntimePromptBuilder::VERSION,
                'knowledge_version_ids' => $kbIds,
                'result_code' => (string) ($result['operation'] ?? 'invalid'),
                'latency_ms' => $provider['latency_ms'],
                'input_tokens' => $provider['usage']['input_tokens'],
                'output_tokens' => $provider['usage']['output_tokens'],
                'cost_microunits' => $provider['cost_microunits'],
                'safe_metadata' => [
                    'cached_input_tokens' => $provider['usage']['cached_input_tokens'],
                    'provider_price_version' => $provider['price_version'],
                ],
            ], $actor);

            if ($spent + $provider['cost_microunits'] >= (int) config('ai_support.conversation_cost_stop_microunits', 50_000)) {
                $this->handoff->transfer($actor, $ticket, 'cost_limit');

                return;
            }

            if ($this->unsafeSuccessOrHeldClaim($message)) {
                $this->handoff->transfer($actor, $ticket, 'unsafe_model_claim_suppressed');
                $this->controls->systemStop(
                    'capability.support_answers_v1',
                    'unsafe_model_claim',
                    'Automatic stop after a fabricated-success, pricing-hold, or support-time claim.',
                );

                return;
            }
            $this->deliver($actor, $ticket->fresh(), $draft, $result, $message, $kbIds !== [], $newestMessage);

            if ($spent + $provider['cost_microunits'] >= (int) config('ai_support.conversation_cost_alert_microunits', 30_000)) {
                $this->events->record($ticket, 'conversation_cost_alert', [
                    'capability_id' => 'support_answers_v1',
                    'result_code' => 'alert_threshold_reached',
                ], $actor);
            }
        } catch (Throwable $exception) {
            report($exception);
            if (! $this->handoff->mayDeliverAutomatedReply($ticket)) {
                return;
            }
            $recentFailures = AiSupportInteractionEvent::query()
                ->where('support_ticket_id', $ticket->id)
                ->where('event_type', 'model_turn_failed')
                ->where('occurred_at', '>=', now()->subHour())
                ->count();
            $this->events->record($ticket, 'model_turn_failed', [
                'capability_id' => 'support_answers_v1',
                'reason_code' => 'provider_or_contract_failure',
                'result_code' => 'safe_fallback',
            ], $actor);
            if ($recentFailures >= 1) {
                $this->handoff->transfer($actor, $ticket, 'repeated_provider_instability');
                $this->controls->systemStop(
                    'capability.support_answers_v1',
                    'repeated_provider_instability',
                    'Automatic stop after repeated provider or structured-contract failures within one hour.',
                );

                return;
            }
            $this->automatedMessage(
                $ticket,
                $draft
                    ? 'I saved your request details, but I am having trouble continuing. You can talk to a person or use the regular request form.'
                    : 'I am having trouble answering right now. You can talk to a person or use the Support Center.',
            );
        }
    }

    /** @param array<string,mixed> $result */
    private function deliver(
        User $actor,
        SupportTicket $ticket,
        ?AiSupportRequestDraft $draft,
        array $result,
        string $message,
        bool $hasGroundedKnowledge,
        string $newestMessage,
    ): void {
        $operation = (string) ($result['operation'] ?? '');
        if ($operation === 'handoff') {
            $this->handoff->transfer($actor, $ticket, 'model_policy_handoff');

            return;
        }

        if ($operation === 'draft_patch' && $actor->role === 'family' && $draft
            && $this->eligibility->evaluate($actor, 'care_request_draft_v1', $ticket)->allowed) {
            $updated = $this->drafts->applyPatch($actor, $ticket, (array) $result['draft_patch'], $draft->version);
            $question = $this->drafts->nextQuestion($actor, $updated);
            if ($question !== null) {
                $this->automatedMessage($ticket, $question);
            } else {
                $this->recaps->issue($actor, $ticket, $updated);
            }

            return;
        }

        if ($operation === 'care_path' && $actor->role === 'family'
            && $this->eligibility->evaluate($actor, 'care_intake_v1', $ticket)->allowed) {
            if (($result['care_path'] ?? null) === 'human_24_7') {
                $this->handoff->transfer($actor, $ticket, 'continuous_coverage');

                return;
            }
            $body = $message !== '' ? $message : ((string) ($result['clarifying_question'] ?? 'Is this one visit, or will care repeat each week?'));
            DB::transaction(function () use ($actor, $ticket, $body, $result): void {
                $sent = $this->automatedMessage($ticket, $body);
                AiSupportMessageAction::query()->create([
                    'id' => (string) Str::uuid(),
                    'support_ticket_message_id' => $sent->id,
                    'support_ticket_id' => $ticket->id,
                    'actor_user_id' => $actor->id,
                    'action_type' => AiSupportMessageAction::TYPE_PATH_CHOICES,
                    'payload' => [
                        'recommended_path' => $result['care_path'],
                        'choices' => [
                            ['id' => 'one_time', 'label' => 'One-time care'],
                            ['id' => 'recurring', 'label' => 'Regular care'],
                        ],
                    ],
                ]);
            }, 3);

            return;
        }

        if ($operation === 'navigate' && preg_match('/\b(open|show|take me|go to|find)\b/i', $newestMessage)) {
            $target = (string) ($result['navigation_target_id'] ?? '');
            if ($this->eligibility->evaluate($actor, 'semantic_navigation_v1', $ticket)->allowed
                && $this->navigation->allowedFor($actor, $target)) {
                DB::transaction(function () use ($actor, $ticket, $message, $target): void {
                    $sent = $this->automatedMessage($ticket, $message !== '' ? $message : 'I found the page for you.');
                    AiSupportMessageAction::query()->create([
                        'id' => (string) Str::uuid(),
                        'support_ticket_message_id' => $sent->id,
                        'support_ticket_id' => $ticket->id,
                        'actor_user_id' => $actor->id,
                        'action_type' => AiSupportMessageAction::TYPE_NAVIGATE,
                        'payload' => [
                            'target_id' => $target,
                            'url' => $this->navigation->urlFor($actor, $target),
                            'label' => $this->navigationLabel($target),
                        ],
                    ]);
                }, 3);

                return;
            }
        }

        if ($operation === 'answer' && ($hasGroundedKnowledge || $draft !== null)) {
            $this->automatedMessage($ticket, $message !== '' ? $message : 'What would you like help with?');

            return;
        }

        $this->handoff->transfer($actor, $ticket, 'knowledge_or_scope_gap');
    }

    private function deterministicGuard(string $message): ?string
    {
        if (preg_match('/\b(not breathing|cannot breathe|can\'t breathe|unconscious|severe bleeding|immediate danger|suicid(?:e|al)|call (?:an )?ambulance|chest pain)\b/i', $message)) {
            return 'emergency';
        }
        if (preg_match('/\b(24\s*\/\s*7|round[- ]the[- ]clock|all day and all night|continuous day and night)\b/i', $message)) {
            return 'continuous_coverage';
        }
        if (preg_match('/\b(injection|inject insulin|wound care|medical advice|clinical procedure|administer medication|change a catheter)\b/i', $message)) {
            return 'medical';
        }
        if (preg_match('/\b(talk|speak|connect|transfer)\b.{0,24}\b(person|human|agent|support team)\b/i', $message)) {
            return 'human_requested';
        }

        return null;
    }

    private function safeMessage(mixed $message): string
    {
        $message = trim(strip_tags((string) $message));

        return Str::limit($message, 1000, '');
    }

    private function unsafeSuccessOrHeldClaim(string $message): bool
    {
        return (bool) preg_match(
            '/(?:\bI (?:created|published|submitted|changed|updated|hired|authorized|charged)\b|\$\s*\d|\b30 dollars? per hour\b|\bqueue position is\b|\breply (?:in|within) \d+\b)/i',
            $message,
        );
    }

    private function automatedMessage(SupportTicket $ticket, string $body): SupportTicketMessage
    {
        return DB::transaction(function () use ($ticket, $body): SupportTicketMessage {
            $locked = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            if ($locked->responder_mode !== SupportTicket::RESPONDER_MODE_AUTOMATED
                || $locked->status === SupportTicket::STATUS_CLOSED
                || $locked->transcript_deleted_at) {
                throw new \RuntimeException('Automated ownership ended before delivery.');
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
            $this->events->record($locked, 'answer_delivered', [
                'support_ticket_message_id' => $message->id,
                'capability_id' => 'support_answers_v1',
                'result_code' => 'delivered',
            ]);

            return $message;
        }, 3);
    }

    private function navigationLabel(string $target): string
    {
        return match ($target) {
            'family.new_care_request' => 'Open care request form',
            'family.care_requests' => 'View care requests',
            'caregiver.work_inbox' => 'Open Work Inbox',
            'caregiver.shifts' => 'View visits',
            'account.profile' => 'Open profile',
            'support.center' => 'Open Support Center',
            default => 'Open page',
        };
    }
}
