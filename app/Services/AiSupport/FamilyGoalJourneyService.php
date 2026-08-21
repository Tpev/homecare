<?php

namespace App\Services\AiSupport;

use App\Models\AiSupportGoalJourney;
use App\Models\AiSupportGuidedTask;
use App\Models\AiSupportMessageAction;
use App\Models\AiSupportRequestDraft;
use App\Models\CareRequest;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FamilyGoalJourneyService
{
    public function __construct(
        private readonly FamilyGoalJourneyCatalog $catalog,
        private readonly FamilyCareTypeDecisionService $careTypes,
        private readonly AiSupportRequestDraftService $drafts,
        private readonly FamilyAccountContext $familyAccounts,
        private readonly AiSupportEventRecorder $events,
    ) {}

    public function handleEarly(User $actor, SupportTicket $ticket, string $message): bool
    {
        if ($actor->role !== 'family') {
            return false;
        }
        $this->authorize($actor, $ticket);
        $this->resumeTransferred($actor, $ticket);
        $active = $this->activeFor($actor, $ticket);
        $normalized = mb_strtolower(trim($message));

        if ($active && preg_match('/\b(?:stop|cancel)\s+(?:this|the|my)\s+(?:task|help|journey|process)\b|\bnever mind about this\b/iu', $normalized)) {
            $this->cancelActive($actor, $ticket, 'user_cancelled');
            $this->automatedMessage($ticket, 'I stopped this task. No care, payment, profile, or other app record was changed.');

            return true;
        }

        if ($active && preg_match('/\bstart (?:this )?over\b/iu', $normalized)) {
            if ($active->journey_type === 'care_request') {
                $draft = $this->requestDraft($actor, $ticket);
                if ($draft) {
                    $this->drafts->discard($actor, $ticket);
                }
                $this->cancelActive($actor, $ticket, 'user_restarted');
                $active = $this->begin($actor, $ticket, 'care_request', null, [
                    'plain_goal' => 'Choose care and create a request',
                ]);
                $this->offerCareChoice($actor, $ticket, [
                    'path' => 'clarify',
                    'reason' => 'We are starting the care request again.',
                    'dates' => [],
                ], $message, $active);

                return true;
            }

            $this->cancelActive($actor, $ticket, 'user_restarted');
            $this->automatedMessage($ticket, 'Okay. Tell me what you want to do, and we will start again.');

            return true;
        }

        $draft = $this->requestDraft($actor, $ticket);
        if (! $active && $draft && preg_match('/\b(?:continue|resume|finish)\b.{0,24}\b(?:care|request|draft)\b/iu', $normalized)) {
            $active = $this->begin($actor, $ticket, 'care_request', null, [
                'plain_goal' => 'Finish the saved care request',
                'selected_path' => $draft->request_type,
            ], 'request_draft_resumed');
            $question = $this->drafts->nextQuestion($actor, $draft);
            if ($question === null) {
                $this->automatedMessage($ticket, 'Your saved request is ready to review.');
            } else {
                $this->automatedMessage($ticket, 'I found your saved request. '.$question);
            }

            return true;
        }

        if ($active?->journey_type === 'care_request' && $draft) {
            if ($this->handleRecipientIdentityReply($actor, $ticket, $draft, $message)) {
                return true;
            }

            $decision = $this->careTypes->decide($message, true);
            $explicitChange = $decision
                && in_array($decision['path'], [CareRequest::TYPE_ONE_TIME, CareRequest::TYPE_RECURRING], true)
                && $decision['path'] !== $draft->request_type
                && preg_match('/\b(?:actually|instead|change|switch|make it|it is|it\'s|just this|not weekly|every week)\b/iu', $normalized);
            if ($explicitChange) {
                $updated = $this->drafts->start($actor, $ticket, $decision['path']);
                $this->advance($active, 'collect_request_details', 2, 'care_path_changed', [
                    'selected_path' => $decision['path'],
                    'path_reason' => $decision['reason'],
                ]);
                $question = $this->drafts->nextQuestion($actor, $updated);
                $type = $decision['path'] === CareRequest::TYPE_ONE_TIME ? 'one-time care' : 'regular care';
                $this->automatedMessage(
                    $ticket,
                    'I changed this to '.$type.'. I kept the recipient, care tasks, address, and notes that still apply. '.($question ?: 'The request is ready to review again.'),
                );

                return true;
            }
        }

        $decision = $this->careTypes->decide($message, $active?->journey_type === 'care_request');
        $waitingForCareType = $active?->journey_type === 'care_request'
            && ! $draft
            && in_array($active->step_key, ['choose_care_type', 'clarify_care_type'], true);
        if ($decision && (! $active || $waitingForCareType)) {
            if ($decision['path'] === 'human_24_7') {
                return false;
            }
            $this->offerCareChoice($actor, $ticket, $decision, $message, $active);

            return true;
        }

        return false;
    }

    /**
     * Starts or advances the journey matching a resolved 324-intent record.
     * Returns true only when a different-goal choice was shown and ordinary routing must stop.
     *
     * @param  array<string,mixed>  $intent
     */
    public function coordinateIntent(User $actor, SupportTicket $ticket, array $intent, string $message): bool
    {
        if ($actor->role !== 'family') {
            return false;
        }
        $journeyType = $this->catalog->forIntent($intent);
        if ($journeyType === null) {
            return false;
        }

        $active = $this->activeFor($actor, $ticket);
        if (! $active) {
            $this->begin($actor, $ticket, $journeyType, (string) $intent['intent_id'], [
                'plain_goal' => (string) $intent['intent'],
            ], 'intent_started');

            return false;
        }

        if ($active->journey_type === $journeyType) {
            $this->advance($active, $active->step_key, max(1, $active->progress_current), 'intent_continued', [
                'latest_intent_id' => (string) $intent['intent_id'],
            ], (string) $intent['intent_id']);

            return false;
        }

        if ($active->journey_type === 'care_request'
            && in_array($journeyType, ['care_profile', 'payment_method', 'payment_failure'], true)) {
            $this->advance($active, 'complete_'.$journeyType.'_detour', 2, 'journey_detour_started', [
                'detour_type' => $journeyType,
                'detour_intent_id' => (string) $intent['intent_id'],
                'return_step' => $active->step_key,
                'return_progress' => $active->progress_current,
            ]);

            return false;
        }

        $this->offerDifferentGoalChoice($actor, $ticket, $active, $journeyType, $intent, $message);

        return true;
    }

    /** @return array{result:string,continue_message:?string} */
    public function chooseCarePath(User $actor, SupportTicket $ticket, string $actionId, string $path): array
    {
        $this->authorize($actor, $ticket);

        return DB::transaction(function () use ($actor, $ticket, $actionId, $path): array {
            $action = AiSupportMessageAction::query()
                ->lockForUpdate()
                ->whereKey($actionId)
                ->where('support_ticket_id', $ticket->id)
                ->where('actor_user_id', $actor->id)
                ->where('action_type', AiSupportMessageAction::TYPE_PATH_CHOICES)
                ->firstOrFail();
            $allowed = collect((array) data_get($action->payload, 'choices', []))->pluck('id')->all();
            $active = AiSupportGoalJourney::query()
                ->lockForUpdate()
                ->whereKey((string) data_get($action->payload, 'journey_id', ''))
                ->where('support_ticket_id', $ticket->id)
                ->where('actor_user_id', $actor->id)
                ->where('state', AiSupportGoalJourney::STATE_AWAITING_CHOICE)
                ->where('expires_at', '>', now())
                ->first();
            if (! $action->isActive() || ! $active || ! in_array($path, $allowed, true)) {
                throw ValidationException::withMessages(['path' => 'This care choice expired. Tell me what kind of care you need again.']);
            }

            $action->forceFill(['consumed_at' => now()])->save();
            if ($path === 'human') {
                return ['result' => 'human', 'continue_message' => null];
            }

            if ($path === 'unsure') {
                $this->offerCareChoice($actor, $ticket, [
                    'path' => 'clarify',
                    'reason' => 'We can decide from the schedule you need.',
                    'dates' => [],
                ], 'I am not sure which care type fits.', $active);

                return ['result' => 'clarify', 'continue_message' => null];
            }

            if (! in_array($path, [CareRequest::TYPE_ONE_TIME, CareRequest::TYPE_RECURRING], true)) {
                throw ValidationException::withMessages(['path' => 'Choose one-time care, regular care, or a person.']);
            }

            $sourceMessage = trim((string) data_get($action->payload, 'source_message', ''));
            $dates = (array) data_get($action->payload, 'irregular_dates', []);
            $draft = $this->drafts->start($actor, $ticket, $path);
            $action->forceFill([
                'payload' => [
                    'selected_path' => $path,
                    'journey_id' => $active->id,
                ],
            ])->save();
            $this->advance($active, 'collect_request_details', 2, 'care_path_selected', [
                'selected_path' => $path,
                'remaining_irregular_dates' => array_values(array_slice($dates, 1)),
            ]);

            if ($this->sourceHasDraftDetails($sourceMessage)) {
                return ['result' => 'selected', 'continue_message' => $sourceMessage];
            }

            $question = $this->drafts->nextQuestion($actor, $draft);
            $this->automatedMessage($ticket, $question ?: 'I have enough information to show your request recap.');

            return ['result' => 'selected', 'continue_message' => null];
        }, 3);
    }

    /** @return array{result:string,continue_message:?string} */
    public function chooseJourney(User $actor, SupportTicket $ticket, string $actionId, string $choice): array
    {
        $this->authorize($actor, $ticket);

        return DB::transaction(function () use ($actor, $ticket, $actionId, $choice): array {
            $action = AiSupportMessageAction::query()
                ->lockForUpdate()
                ->whereKey($actionId)
                ->where('support_ticket_id', $ticket->id)
                ->where('actor_user_id', $actor->id)
                ->where('action_type', AiSupportMessageAction::TYPE_JOURNEY_CHOICES)
                ->firstOrFail();
            $allowed = collect((array) data_get($action->payload, 'choices', []))->pluck('id')->all();
            $active = AiSupportGoalJourney::query()
                ->lockForUpdate()
                ->whereKey((string) data_get($action->payload, 'journey_id', ''))
                ->where('support_ticket_id', $ticket->id)
                ->where('actor_user_id', $actor->id)
                ->where('state', AiSupportGoalJourney::STATE_AWAITING_CHOICE)
                ->where('expires_at', '>', now())
                ->first();
            if (! $action->isActive() || ! $active || ! in_array($choice, $allowed, true)) {
                throw ValidationException::withMessages(['journey' => 'This choice expired. Tell me which task you want to continue.']);
            }
            $payload = (array) $action->payload;
            $action->forceFill(['consumed_at' => now()])->save();

            if ($choice === 'continue_current') {
                $this->advance($active, (string) data_get($active->context, 'step_before_new_goal', $active->step_key), max(1, $active->progress_current), 'current_goal_kept', [
                    'pending_goal_type' => null,
                    'pending_goal_intent_id' => null,
                ]);
                $this->automatedMessage($ticket, 'Okay. We will continue '.$this->catalog->label($active->journey_type).'.');

                return ['result' => 'continued', 'continue_message' => null];
            }

            if ($choice === 'human') {
                return ['result' => 'human', 'continue_message' => null];
            }

            if ($choice === 'unsure') {
                $this->automatedMessage($ticket, 'That is okay. Tell me which result matters most right now, or choose Talk to a person.');

                return ['result' => 'clarify', 'continue_message' => null];
            }

            $newType = (string) ($payload['pending_goal_type'] ?? '');
            if (! $this->catalog->find($newType)) {
                throw ValidationException::withMessages(['journey' => 'The new task is no longer available.']);
            }
            $this->cancelJourney($active, 'user_started_different_goal');
            $this->begin(
                $actor,
                $ticket,
                $newType,
                filled($payload['pending_goal_intent_id'] ?? null) ? (string) $payload['pending_goal_intent_id'] : null,
                ['plain_goal' => (string) ($payload['pending_goal_label'] ?? $this->catalog->label($newType))],
                'different_goal_started',
            );

            return ['result' => 'started_new', 'continue_message' => (string) ($payload['source_message'] ?? '')];
        }, 3);
    }

    /** @param array<string,mixed> $intent */
    public function offerDifferentGoalChoice(
        User $actor,
        SupportTicket $ticket,
        AiSupportGoalJourney $active,
        string $newType,
        array $intent,
        string $message,
    ): void {
        DB::transaction(function () use ($actor, $ticket, $active, $newType, $intent, $message): void {
            $locked = AiSupportGoalJourney::query()->lockForUpdate()->findOrFail($active->id);
            $context = (array) $locked->context;
            $context['step_before_new_goal'] = $locked->step_key;
            $context['pending_goal_type'] = $newType;
            $context['pending_goal_intent_id'] = (string) $intent['intent_id'];
            $locked->forceFill([
                'state' => AiSupportGoalJourney::STATE_AWAITING_CHOICE,
                'step_key' => 'choose_goal',
                'context' => $context,
                'last_result_code' => 'different_goal_detected',
                'last_activity_at' => now(),
                'version' => $locked->version + 1,
            ])->save();
            $sent = $this->automatedMessage(
                $ticket,
                'We were working on '.$this->catalog->label($active->journey_type).'. Do you want to continue that, or start '.$this->catalog->label($newType).'?',
            );
            AiSupportMessageAction::query()->create([
                'id' => (string) Str::uuid(),
                'support_ticket_message_id' => $sent->id,
                'support_ticket_id' => $ticket->id,
                'actor_user_id' => $actor->id,
                'action_type' => AiSupportMessageAction::TYPE_JOURNEY_CHOICES,
                'payload' => [
                    'journey_id' => $locked->id,
                    'pending_goal_type' => $newType,
                    'pending_goal_intent_id' => (string) $intent['intent_id'],
                    'pending_goal_label' => $this->catalog->label($newType),
                    'source_message' => $message,
                    'choices' => [
                        ['id' => 'continue_current', 'label' => 'Continue what we were doing'],
                        ['id' => 'start_new', 'label' => 'Start the new task'],
                        ['id' => 'unsure', 'label' => "I'm not sure"],
                        ['id' => 'human', 'label' => 'Talk to a person'],
                    ],
                ],
                'expires_at' => now()->addMinutes(30),
            ]);
            $this->events->record($ticket, 'journey_new_goal_offered', [
                'capability_id' => 'support_answers_v1',
                'result_code' => 'explicit_choice_required',
                'safe_metadata' => [
                    'journey_type' => $active->journey_type,
                    'new_journey_type' => $newType,
                ],
            ], $actor);
        }, 3);
    }

    /**
     * @param  array{path:string,reason:string,dates:list<string>}  $decision
     */
    public function offerCareChoice(
        User $actor,
        SupportTicket $ticket,
        array $decision,
        string $sourceMessage,
        ?AiSupportGoalJourney $journey = null,
    ): AiSupportMessageAction {
        $this->authorize($actor, $ticket);
        $journey ??= $this->activeFor($actor, $ticket)
            ?: $this->begin($actor, $ticket, 'care_request', null, ['plain_goal' => trim($sourceMessage)]);
        $path = $decision['path'];
        $body = match ($path) {
            CareRequest::TYPE_ONE_TIME => 'Based on what you told me, one-time care looks like the best fit because this is one specific visit or date.',
            CareRequest::TYPE_RECURRING => 'Based on what you told me, regular care looks like the best fit because the help repeats every week.',
            'irregular_dates' => 'These dates do not follow a weekly pattern. They should be separate one-time visits. I can help with the first one and keep the remaining dates here.',
            default => 'Is this help for one specific date, or will it repeat every week?',
        };
        $recommended = in_array($path, [CareRequest::TYPE_ONE_TIME, CareRequest::TYPE_RECURRING], true)
            ? $path
            : ($path === 'irregular_dates' ? CareRequest::TYPE_ONE_TIME : null);
        $choices = $recommended === CareRequest::TYPE_RECURRING
            ? [
                ['id' => CareRequest::TYPE_RECURRING, 'label' => 'Continue with regular care'],
                ['id' => CareRequest::TYPE_ONE_TIME, 'label' => 'Choose one-time care instead'],
            ]
            : [
                ['id' => CareRequest::TYPE_ONE_TIME, 'label' => $path === 'irregular_dates' ? 'Start the first one-time request' : ($recommended ? 'Continue with one-time care' : 'One specific date')],
                ['id' => CareRequest::TYPE_RECURRING, 'label' => $recommended ? 'Choose regular care instead' : 'Repeats every week'],
            ];
        $choices[] = ['id' => 'unsure', 'label' => "I'm still not sure"];
        $choices[] = ['id' => 'human', 'label' => 'Talk to a person'];

        return DB::transaction(function () use ($actor, $ticket, $journey, $body, $sourceMessage, $decision, $recommended, $choices): AiSupportMessageAction {
            $locked = AiSupportGoalJourney::query()->lockForUpdate()->findOrFail($journey->id);
            $context = (array) $locked->context;
            $context['source_message'] = Str::limit($sourceMessage, 3000, '');
            $context['recommended_path'] = $recommended;
            $context['irregular_dates'] = $decision['dates'];
            $locked->forceFill([
                'state' => AiSupportGoalJourney::STATE_AWAITING_CHOICE,
                'step_key' => $recommended ? 'choose_care_type' : 'clarify_care_type',
                'progress_current' => 1,
                'context' => $context,
                'last_result_code' => $recommended ? 'care_path_recommended' : 'care_path_clarification_needed',
                'last_activity_at' => now(),
                'expires_at' => now()->addDays((int) config('ai_support.goal_journey_retention_days', 7)),
                'version' => $locked->version + 1,
            ])->save();
            AiSupportMessageAction::query()
                ->where('support_ticket_id', $ticket->id)
                ->where('actor_user_id', $actor->id)
                ->where('action_type', AiSupportMessageAction::TYPE_PATH_CHOICES)
                ->whereNull('consumed_at')
                ->whereNull('invalidated_at')
                ->update(['invalidated_at' => now(), 'invalidation_reason' => 'new_care_choice']);
            $sent = $this->automatedMessage($ticket, $body);
            $action = AiSupportMessageAction::query()->create([
                'id' => (string) Str::uuid(),
                'support_ticket_message_id' => $sent->id,
                'support_ticket_id' => $ticket->id,
                'actor_user_id' => $actor->id,
                'action_type' => AiSupportMessageAction::TYPE_PATH_CHOICES,
                'payload' => [
                    'journey_id' => $locked->id,
                    'recommended_path' => $recommended,
                    'source_message' => Str::limit($sourceMessage, 3000, ''),
                    'irregular_dates' => $decision['dates'],
                    'choices' => $choices,
                ],
                'expires_at' => now()->addMinutes(30),
            ]);
            $this->events->record($ticket, 'care_path_recommended', [
                'support_ticket_message_id' => $sent->id,
                'capability_id' => 'care_intake_v1',
                'result_code' => $recommended ?: 'clarify',
                'safe_metadata' => [
                    'journey_type' => 'care_request',
                    'irregular_date_count' => count($decision['dates']),
                ],
            ], $actor);

            return $action;
        }, 3);
    }

    public function activeFor(User $actor, SupportTicket $ticket): ?AiSupportGoalJourney
    {
        $this->expireOld($actor, $ticket);
        $journey = AiSupportGoalJourney::query()
            ->where('support_ticket_id', $ticket->id)
            ->where('actor_user_id', $actor->id)
            ->resumable()
            ->latest('last_activity_at')
            ->first();
        if (! $journey) {
            return null;
        }

        if ($journey->journey_type === 'care_request') {
            $draft = AiSupportRequestDraft::query()
                ->where('support_ticket_id', $ticket->id)
                ->where('actor_user_id', $actor->id)
                ->whereNotNull('published_at')
                ->latest('published_at')
                ->first();
            if ($draft && $draft->published_at?->gte($journey->started_at)) {
                $this->markCompleted($actor, $ticket, 'care_request_published');

                return null;
            }
        }

        return $journey;
    }

    /** @return array{id:string,goal:string,progress:string,instruction:string,state:string,canCancel:bool,hasGuidedTarget:bool}|null */
    public function clientPayload(User $actor, SupportTicket $ticket): ?array
    {
        $journey = $this->activeFor($actor, $ticket);
        if (! $journey) {
            return null;
        }
        $guided = AiSupportGuidedTask::query()
            ->where('support_ticket_id', $ticket->id)
            ->where('actor_user_id', $actor->id)
            ->open()
            ->latest('created_at')
            ->first();
        $instruction = $this->stepInstruction($actor, $ticket, $journey, $guided);
        $current = max(1, min((int) $journey->progress_current, (int) $journey->progress_total));

        return [
            'id' => $journey->id,
            'goal' => $this->catalog->label($journey->journey_type),
            'progress' => $journey->state === AiSupportGoalJourney::STATE_TRANSFERRED
                ? 'A LoLo Support person has the conversation'
                : 'Step '.$current.' of '.$journey->progress_total,
            'instruction' => $instruction,
            'state' => $journey->state,
            'canCancel' => $journey->state !== AiSupportGoalJourney::STATE_TRANSFERRED,
            'hasGuidedTarget' => $guided !== null,
        ];
    }

    public function shouldExposeRequestDraft(User $actor, SupportTicket $ticket): bool
    {
        $active = $this->activeFor($actor, $ticket);

        return ! $active || $active->journey_type === 'care_request';
    }

    /** @return array<string,mixed>|null */
    public function providerContext(User $actor, SupportTicket $ticket): ?array
    {
        $journey = $this->activeFor($actor, $ticket);
        if (! $journey) {
            return null;
        }
        $context = (array) $journey->context;

        return [
            'journey_type' => $journey->journey_type,
            'step' => $journey->step_key,
            'selected_path' => $context['selected_path'] ?? null,
            'detour_type' => $context['detour_type'] ?? null,
            'remaining_irregular_dates' => array_values((array) ($context['remaining_irregular_dates'] ?? [])),
        ];
    }

    public function cancelActive(User $actor, SupportTicket $ticket, string $reason = 'user_cancelled'): void
    {
        $journey = $this->activeFor($actor, $ticket);
        if ($journey) {
            if ($journey->journey_type === 'care_request'
                && in_array($reason, ['user_cancelled', 'superseded_by_continuous_coverage'], true)
                && $this->requestDraft($actor, $ticket)) {
                $this->drafts->discard($actor, $ticket);
            }
            $this->cancelJourney($journey, $reason);
            $now = now();
            AiSupportGuidedTask::query()
                ->where('support_ticket_id', $ticket->id)
                ->where('actor_user_id', $actor->id)
                ->whereIn('state', AiSupportGuidedTask::OPEN_STATES)
                ->update([
                    'state' => AiSupportGuidedTask::STATE_CANCELLED,
                    'cancelled_at' => $now,
                    'last_result_code' => $reason,
                    'updated_at' => $now,
                ]);
            AiSupportMessageAction::query()
                ->where('support_ticket_id', $ticket->id)
                ->where('actor_user_id', $actor->id)
                ->whereIn('action_type', [
                    AiSupportMessageAction::TYPE_PATH_CHOICES,
                    AiSupportMessageAction::TYPE_JOURNEY_CHOICES,
                    AiSupportMessageAction::TYPE_GUIDED_TASK,
                ])
                ->whereNull('consumed_at')
                ->whereNull('invalidated_at')
                ->update(['invalidated_at' => $now, 'invalidation_reason' => $reason]);
            $this->events->record($ticket, 'journey_cancelled', [
                'capability_id' => 'support_answers_v1',
                'result_code' => $reason,
                'safe_metadata' => ['journey_type' => $journey->journey_type],
            ], $actor);
        }
    }

    public function markCompleted(User $actor, SupportTicket $ticket, string $resultCode): void
    {
        $journey = AiSupportGoalJourney::query()
            ->where('support_ticket_id', $ticket->id)
            ->where('actor_user_id', $actor->id)
            ->whereIn('state', [AiSupportGoalJourney::STATE_ACTIVE, AiSupportGoalJourney::STATE_AWAITING_CHOICE])
            ->latest('last_activity_at')
            ->first();
        if (! $journey) {
            return;
        }
        $journey->forceFill([
            'state' => AiSupportGoalJourney::STATE_COMPLETED,
            'step_key' => 'completed',
            'progress_current' => $journey->progress_total,
            'last_result_code' => $resultCode,
            'last_activity_at' => now(),
            'completed_at' => now(),
            'version' => $journey->version + 1,
        ])->save();
        $this->events->record($ticket, 'journey_completed', [
            'capability_id' => 'support_answers_v1',
            'result_code' => $resultCode,
            'safe_metadata' => ['journey_type' => $journey->journey_type],
        ], $actor);
    }

    public function syncAfterVerifiedStep(User $actor, SupportTicket $ticket, string $resultCode): void
    {
        $journey = $this->activeFor($actor, $ticket);
        if (! $journey) {
            return;
        }
        $context = (array) $journey->context;
        if ($journey->journey_type === 'care_request' && filled($context['detour_type'] ?? null)) {
            $this->advance($journey, (string) ($context['return_step'] ?? 'collect_request_details'), (int) ($context['return_progress'] ?? 2), 'journey_detour_completed', [
                'detour_type' => null,
                'detour_intent_id' => null,
                'return_step' => null,
                'return_progress' => null,
            ]);
            $draft = $this->requestDraft($actor, $ticket);
            if ($draft) {
                $question = $this->drafts->nextQuestion($actor, $draft);
                $this->automatedMessage($ticket, 'We can continue your care request. '.($question ?: 'Your request is ready to review.'));
            }

            return;
        }

        $this->markCompleted($actor, $ticket, $resultCode);
    }

    public function syncCareDraft(User $actor, SupportTicket $ticket, bool $readyForRecap): void
    {
        $journey = $this->activeFor($actor, $ticket);
        if (! $journey || $journey->journey_type !== 'care_request') {
            return;
        }

        $this->advance(
            $journey,
            $readyForRecap ? 'review_request' : 'collect_request_details',
            $readyForRecap ? 3 : 2,
            $readyForRecap ? 'request_ready_for_recap' : 'request_detail_collected',
        );
    }

    public function markTransferred(User $actor, SupportTicket $ticket, string $reasonCode): void
    {
        if ($actor->role !== 'family') {
            return;
        }
        $journey = AiSupportGoalJourney::query()
            ->where('support_ticket_id', $ticket->id)
            ->where('actor_user_id', $actor->id)
            ->whereIn('state', [AiSupportGoalJourney::STATE_ACTIVE, AiSupportGoalJourney::STATE_AWAITING_CHOICE])
            ->latest('last_activity_at')
            ->first();
        if (! $journey) {
            $type = 'human_help';
            $journey = $this->begin($actor, $ticket, $type, null, ['plain_goal' => $this->catalog->label($type)], 'journey_transferred');
        }
        $journey->forceFill([
            'state' => AiSupportGoalJourney::STATE_TRANSFERRED,
            'step_key' => 'human_support',
            'last_result_code' => $reasonCode,
            'last_activity_at' => now(),
            'transferred_at' => now(),
            'version' => $journey->version + 1,
        ])->save();
    }

    public function resumeTransferred(User $actor, SupportTicket $ticket): void
    {
        if ($ticket->responder_mode !== SupportTicket::RESPONDER_MODE_AUTOMATED) {
            return;
        }
        $journey = AiSupportGoalJourney::query()
            ->where('support_ticket_id', $ticket->id)
            ->where('actor_user_id', $actor->id)
            ->where('state', AiSupportGoalJourney::STATE_TRANSFERRED)
            ->where('expires_at', '>', now())
            ->latest('last_activity_at')
            ->first();
        if (! $journey) {
            return;
        }
        $context = (array) $journey->context;
        $journey->forceFill([
            'state' => AiSupportGoalJourney::STATE_ACTIVE,
            'step_key' => (string) ($context['return_step'] ?? ($journey->journey_type === 'care_request' ? 'collect_request_details' : 'review_current_state')),
            'last_result_code' => 'returned_to_automation',
            'last_activity_at' => now(),
            'transferred_at' => null,
            'version' => $journey->version + 1,
        ])->save();
        AiSupportRequestDraft::query()
            ->where('support_ticket_id', $ticket->id)
            ->where('actor_user_id', $actor->id)
            ->where('state', AiSupportRequestDraft::STATE_TRANSFERRED)
            ->whereNull('discarded_at')
            ->whereNull('published_at')
            ->update(['state' => AiSupportRequestDraft::STATE_COLLECTING, 'updated_at' => now()]);
        $this->events->record($ticket, 'journey_resumed', [
            'capability_id' => 'support_answers_v1',
            'result_code' => 'returned_to_automation',
            'safe_metadata' => ['journey_type' => $journey->journey_type],
        ], $actor);
    }

    /** @param array<string,mixed> $context */
    private function begin(
        User $actor,
        SupportTicket $ticket,
        string $journeyType,
        ?string $intentId = null,
        array $context = [],
        string $resultCode = 'journey_started',
    ): AiSupportGoalJourney {
        $definition = $this->catalog->find($journeyType);
        if (! $definition) {
            throw new \InvalidArgumentException('Unknown Family journey type.');
        }
        $account = $this->familyAccounts->account($actor);

        return DB::transaction(function () use ($actor, $ticket, $journeyType, $intentId, $context, $resultCode, $definition, $account): AiSupportGoalJourney {
            $now = now();
            AiSupportGoalJourney::query()
                ->where('support_ticket_id', $ticket->id)
                ->where('actor_user_id', $actor->id)
                ->whereIn('state', AiSupportGoalJourney::RESUMABLE_STATES)
                ->update([
                    'state' => AiSupportGoalJourney::STATE_CANCELLED,
                    'last_result_code' => 'superseded_by_new_journey',
                    'cancelled_at' => $now,
                    'last_activity_at' => $now,
                    'updated_at' => $now,
                ]);
            $journey = AiSupportGoalJourney::query()->create([
                'id' => (string) Str::uuid(),
                'support_ticket_id' => $ticket->id,
                'actor_user_id' => $actor->id,
                'family_account_id' => $account->id,
                'journey_type' => $journeyType,
                'journey_version' => FamilyGoalJourneyCatalog::VERSION,
                'intent_id' => $intentId,
                'state' => AiSupportGoalJourney::STATE_ACTIVE,
                'step_key' => (string) $definition['default_step'],
                'progress_current' => 1,
                'progress_total' => (int) $definition['progress_total'],
                'context' => $context,
                'last_result_code' => $resultCode,
                'version' => 1,
                'started_at' => $now,
                'last_activity_at' => $now,
                'expires_at' => $now->copy()->addDays((int) config('ai_support.goal_journey_retention_days', 7)),
            ]);
            $this->events->record($ticket, 'journey_started', [
                'capability_id' => 'support_answers_v1',
                'result_code' => $resultCode,
                'safe_metadata' => ['journey_type' => $journeyType, 'intent_id' => $intentId],
            ], $actor);

            return $journey;
        }, 3);
    }

    /** @param array<string,mixed> $contextPatch */
    private function advance(
        AiSupportGoalJourney $journey,
        string $step,
        int $progress,
        string $resultCode,
        array $contextPatch = [],
        ?string $intentId = null,
    ): void {
        $context = array_replace((array) $journey->context, $contextPatch);
        $journey->forceFill([
            'state' => AiSupportGoalJourney::STATE_ACTIVE,
            'intent_id' => $intentId ?: $journey->intent_id,
            'step_key' => $step,
            'progress_current' => min(max(1, $progress), (int) $journey->progress_total),
            'context' => $context,
            'last_result_code' => $resultCode,
            'last_activity_at' => now(),
            'expires_at' => now()->addDays((int) config('ai_support.goal_journey_retention_days', 7)),
            'version' => $journey->version + 1,
        ])->save();
    }

    private function cancelJourney(AiSupportGoalJourney $journey, string $reason): void
    {
        $journey->forceFill([
            'state' => AiSupportGoalJourney::STATE_CANCELLED,
            'last_result_code' => $reason,
            'last_activity_at' => now(),
            'cancelled_at' => now(),
            'version' => $journey->version + 1,
        ])->save();
    }

    private function expireOld(User $actor, SupportTicket $ticket): void
    {
        $expired = AiSupportGoalJourney::query()
            ->where('support_ticket_id', $ticket->id)
            ->where('actor_user_id', $actor->id)
            ->whereIn('state', AiSupportGoalJourney::RESUMABLE_STATES)
            ->where('expires_at', '<=', now())
            ->update([
                'state' => AiSupportGoalJourney::STATE_EXPIRED,
                'context' => null,
                'last_result_code' => 'journey_expired',
                'last_activity_at' => now(),
                'updated_at' => now(),
            ]);
        if ($expired > 0) {
            AiSupportMessageAction::query()
                ->where('support_ticket_id', $ticket->id)
                ->where('actor_user_id', $actor->id)
                ->whereIn('action_type', [
                    AiSupportMessageAction::TYPE_PATH_CHOICES,
                    AiSupportMessageAction::TYPE_JOURNEY_CHOICES,
                ])
                ->whereNull('consumed_at')
                ->update([
                    'payload' => null,
                    'invalidated_at' => now(),
                    'invalidation_reason' => 'journey_expired',
                ]);
        }
    }

    private function requestDraft(User $actor, SupportTicket $ticket): ?AiSupportRequestDraft
    {
        $draft = AiSupportRequestDraft::query()
            ->where('support_ticket_id', $ticket->id)
            ->where('actor_user_id', $actor->id)
            ->first();

        return $draft?->isUsable() ? $draft : null;
    }

    private function handleRecipientIdentityReply(
        User $actor,
        SupportTicket $ticket,
        AiSupportRequestDraft $draft,
        string $message,
    ): bool {
        $payload = (array) $draft->payload;
        if (filled($payload['recipient_full_name'] ?? null)) {
            return false;
        }

        $normalized = mb_strtolower(trim($message));
        $isSelf = preg_match('/^(?:it(?:\s+is|\'s)\s+)?(?:me|myself)|^i\s+(?:need|am\s+the\s+one\s+who\s+needs?)\s+care\b|\bcare\s+is\s+for\s+me\b/iu', $normalized) === 1;
        $relationship = match (true) {
            preg_match('/\b(?:my\s+)?(?:mother|mom|mum)\b/iu', $normalized) === 1 => 'Mother',
            preg_match('/\b(?:my\s+)?(?:father|dad)\b/iu', $normalized) === 1 => 'Father',
            preg_match('/\b(?:my\s+)?(?:wife|spouse|partner)\b/iu', $normalized) === 1 => 'Spouse',
            preg_match('/\b(?:my\s+)?husband\b/iu', $normalized) === 1 => 'Spouse',
            preg_match('/\b(?:my\s+)?(?:grandmother|grandma)\b/iu', $normalized) === 1 => 'Grandmother',
            preg_match('/\b(?:my\s+)?(?:grandfather|grandpa)\b/iu', $normalized) === 1 => 'Grandfather',
            preg_match('/\b(?:my\s+)?(?:sister|brother|sibling)\b/iu', $normalized) === 1 => 'Sibling',
            preg_match('/\b(?:my\s+)?(?:son|daughter|child)\b/iu', $normalized) === 1 => 'Child',
            default => null,
        };
        $isOther = $relationship !== null
            || preg_match('/\b(?:someone|somebody)\s+else\b|\banother\s+person\b|\bnot\s+(?:me|myself)\b/iu', $normalized) === 1;

        if (! $isSelf && ! $isOther) {
            return false;
        }

        $patch = $isSelf
            ? [
                'patch_fields' => ['recipient_is_requester', 'recipient_full_name', 'recipient_relationship'],
                'recipient_is_requester' => true,
                'recipient_full_name' => $actor->name,
                'recipient_relationship' => 'Self',
            ]
            : [
                'patch_fields' => array_values(array_filter([
                    'recipient_is_requester',
                    $relationship !== null ? 'recipient_relationship' : null,
                ])),
                'recipient_is_requester' => false,
                'recipient_relationship' => $relationship,
            ];
        $updated = $this->drafts->applyPatch($actor, $ticket, $patch, $draft->version);
        $question = $this->drafts->nextQuestion($actor, $updated);
        $this->automatedMessage($ticket, $question ?: 'I have enough information to show your request recap.');

        return true;
    }

    private function sourceHasDraftDetails(string $message): bool
    {
        return preg_match(
            '/\b(?:for\s+(?:my|me|our)|mother|father|mom|dad|companionship|meal|housekeep|bathing|grooming|transport|\d{1,2}(?::\d{2})?\s*(?:am|pm)|hours?|minutes?|today|tomorrow|monday|tuesday|wednesday|thursday|friday|saturday|sunday|street|road|avenue|drive|boulevard|\b[A-Z]{2}\s+\d{5})\b/iu',
            $message,
        ) === 1;
    }

    private function stepInstruction(
        User $actor,
        SupportTicket $ticket,
        AiSupportGoalJourney $journey,
        ?AiSupportGuidedTask $guided,
    ): string {
        if ($journey->state === AiSupportGoalJourney::STATE_TRANSFERRED) {
            return 'You can keep writing here. You do not need to repeat your goal.';
        }
        if ($guided) {
            return (string) data_get($guided->payload, 'instruction', 'Use the highlighted step, then come back here.');
        }
        if ($journey->state === AiSupportGoalJourney::STATE_AWAITING_CHOICE) {
            return $journey->step_key === 'choose_goal'
                ? 'Next: choose which task to continue'
                : 'Next: choose one-time or regular care';
        }
        if ($journey->journey_type === 'care_request') {
            $draft = $this->requestDraft($actor, $ticket);
            if ($draft) {
                $recapReady = AiSupportMessageAction::query()
                    ->where('support_ticket_id', $ticket->id)
                    ->where('actor_user_id', $actor->id)
                    ->where('action_type', AiSupportMessageAction::TYPE_RECAP)
                    ->whereNull('consumed_at')
                    ->whereNull('invalidated_at')
                    ->where('expires_at', '>', now())
                    ->exists();
                if ($recapReady) {
                    return 'Next: review and confirm your request';
                }
                $question = $this->drafts->nextQuestion($actor, $draft);

                return $question ? 'Next: '.Str::lcfirst(rtrim($question, '.')) : 'Next: review your request';
            }

            return 'Next: choose one-time or regular care';
        }

        return match ($journey->step_key) {
            'review_profile' => 'Next: review the care profile',
            'review_payment_method' => 'Next: open the secure payment method step',
            'read_payment_problem' => 'Next: review the payment problem and recovery choice',
            'review_caregivers' => 'Next: review the caregiver choices',
            'review_visit' => 'Next: review the visit or submitted hours',
            'review_regular_care' => 'Next: review the regular-care details',
            'find_past_care' => 'Next: open the correct past care record',
            'open_messages' => 'Next: open the right conversation or notification setting',
            default => 'Next: use the action in the latest support message',
        };
    }

    private function authorize(User $actor, SupportTicket $ticket): void
    {
        if ($actor->role !== 'family'
            || (int) $ticket->opener_user_id !== (int) $actor->id
            || $ticket->responder_mode !== SupportTicket::RESPONDER_MODE_AUTOMATED) {
            throw new AuthorizationException;
        }
        $account = $this->familyAccounts->account($actor);
        if ((int) $ticket->family_account_id !== (int) $account->id) {
            throw new AuthorizationException;
        }
    }

    private function automatedMessage(SupportTicket $ticket, string $body): SupportTicketMessage
    {
        return DB::transaction(function () use ($ticket, $body): SupportTicketMessage {
            $locked = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            if ($locked->responder_mode !== SupportTicket::RESPONDER_MODE_AUTOMATED
                || $locked->status === SupportTicket::STATUS_CLOSED
                || $locked->transcript_deleted_at) {
                throw new \RuntimeException('Automated ownership ended before journey delivery.');
            }
            $message = SupportTicketMessage::query()->create([
                'support_ticket_id' => $locked->id,
                'sender_user_id' => null,
                'kind' => SupportTicketMessage::KIND_PUBLIC,
                'responder_type' => SupportTicketMessage::RESPONDER_AUTOMATED,
                'body' => Str::limit(trim($body), 1000, ''),
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
