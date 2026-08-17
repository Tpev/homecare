<?php

namespace App\Services\AiSupport;

use App\Exceptions\Payments\PaymentException;
use App\Models\AiSupportGuidedTask;
use App\Models\AiSupportMessageAction;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AiSupportGuidedTaskService
{
    public const SESSION_TASK_KEY = 'ai_support.guided_task_id';

    public const SESSION_OPEN_CHAT_KEY = 'ai_support.open_chat';

    public function __construct(
        private readonly AiSupportEligibilityService $eligibility,
        private readonly FamilyAccountContext $familyAccounts,
        private readonly FamilyPaymentMethodStatusReader $paymentStatus,
        private readonly NavigationTargetRegistry $navigation,
        private readonly AiSupportEventRecorder $events,
    ) {}

    public function isPaymentMethodIntent(string $message): bool
    {
        $message = mb_strtolower(trim($message));

        return preg_match(
            '/(?:\b(?:add|change|update|replace|switch|new)\b.{0,48}\b(?:credit\s+card|card(?:\s+on\s+file)?|payment\s+method)\b|\b(?:credit\s+card|card(?:\s+on\s+file)?|payment\s+method)\b.{0,48}\b(?:add|change|update|replace|switch|new)\b|\b(?:credit\s+card|card(?:\s+on\s+file)?|payment\s+method)\b.{0,24}\b(?:expired|expiring)\b|\b(?:do\s+i\s+have|is\s+there)\b.{0,40}\b(?:credit\s+card|card|payment\s+method)\b(?:.{0,20}\bon\s+file\b)?|\b(?:what|which)\b.{0,24}\b(?:credit\s+card|card|payment\s+method)\b.{0,20}\b(?:on\s+file|saved|current)\b)/iu',
            $message,
        ) === 1;
    }

    public function offerPaymentMethod(User $actor, SupportTicket $ticket): ?AiSupportGuidedTask
    {
        $this->authorizeAutomation($actor, $ticket);

        try {
            $status = $this->paymentStatus->read($actor);
        } catch (PaymentException) {
            $canManage = $this->familyAccounts->isOwner($actor);
            $facts = [
                'can_manage' => $canManage,
                'attention' => $canManage ? 'unavailable' : 'owner_required',
                'ready' => false,
                'card' => null,
            ];
            $status = [
                ...$facts,
                'checked_at' => now()->toIso8601String(),
                'state_hash' => hash('sha256', json_encode($facts, JSON_THROW_ON_ERROR)),
            ];
        }

        if (! $status['can_manage']) {
            DB::transaction(function () use ($actor, $ticket): void {
                $locked = $this->lockedAutomatedTicket($actor, $ticket);
                $message = $this->createAutomatedMessage(
                    $locked,
                    'Only the Family Account owner can add or change the saved payment method. I have not opened or changed any billing information.',
                );
                $this->events->record($locked, 'payment_status_read', [
                    'support_ticket_message_id' => $message->id,
                    'capability_id' => 'family_context_v1',
                    'tool_id' => 'family-payment-status',
                    'tool_version' => 'v1',
                    'result_code' => 'owner_required',
                ], $actor);
            }, 3);

            return null;
        }

        return DB::transaction(function () use ($actor, $ticket, $status): AiSupportGuidedTask {
            $locked = $this->lockedAutomatedTicket($actor, $ticket);
            $now = now();
            $this->cancelActorTasks($actor, 'superseded_by_new_task', $now);
            AiSupportMessageAction::query()
                ->where('actor_user_id', $actor->id)
                ->where('action_type', AiSupportMessageAction::TYPE_GUIDED_TASK)
                ->whereNull('consumed_at')
                ->whereNull('invalidated_at')
                ->update([
                    'invalidated_at' => $now,
                    'invalidation_reason' => 'superseded_by_new_task',
                ]);

            $mode = $status['ready'] ? 'update' : 'add';
            $instruction = $mode === 'add'
                ? 'Use the highlighted Add card securely button.'
                : 'Use the highlighted Update card button.';
            $message = $this->createAutomatedMessage($locked, $this->paymentOfferMessage($status));
            $task = AiSupportGuidedTask::query()->create([
                'support_ticket_id' => $locked->id,
                'actor_user_id' => $actor->id,
                'family_account_id' => $this->familyAccounts->account($actor)->id,
                'task_type' => AiSupportGuidedTask::TYPE_PAYMENT_METHOD,
                'state' => AiSupportGuidedTask::STATE_OFFERED,
                'navigation_target_id' => 'family.billing.payment_method',
                'payload' => [
                    'mode' => $mode,
                    'instruction' => $instruction,
                ],
                'initial_state_hash' => $status['state_hash'],
                'last_result_code' => $status['attention'],
                'expires_at' => $now->copy()->addMinutes((int) config('ai_support.guided_task_validity_minutes', 60)),
            ]);
            AiSupportMessageAction::query()->create([
                'id' => (string) Str::uuid(),
                'support_ticket_message_id' => $message->id,
                'support_ticket_id' => $locked->id,
                'actor_user_id' => $actor->id,
                'action_type' => AiSupportMessageAction::TYPE_GUIDED_TASK,
                'payload' => [
                    'guided_task_id' => $task->id,
                    'target_id' => $task->navigation_target_id,
                    'label' => $mode === 'add' ? 'Add payment method' : 'Update payment method',
                ],
                'expires_at' => $task->expires_at,
            ]);
            $this->events->record($locked, 'payment_status_read', [
                'support_ticket_message_id' => $message->id,
                'capability_id' => 'family_context_v1',
                'tool_id' => 'family-payment-status',
                'tool_version' => 'v1',
                'result_code' => $status['attention'],
            ], $actor);
            $this->events->record($locked, 'guided_task_offered', [
                'support_ticket_message_id' => $message->id,
                'capability_id' => 'semantic_navigation_v1',
                'navigation_target_id' => $task->navigation_target_id,
                'result_code' => $mode,
            ], $actor);

            return $task;
        }, 3);
    }

    /**
     * Store a deterministic Family-account read result and up to six authorized guide buttons.
     *
     * @param  list<array{task_type:string,target_id:string,label:string,instruction?:string,resource_type?:string|null,resource_id?:int|null}>  $guides
     * @return Collection<int,AiSupportGuidedTask>
     */
    public function offerFamilyReadResult(
        User $actor,
        SupportTicket $ticket,
        string $messageBody,
        string $intent,
        string $resultCode,
        array $guides = [],
    ): Collection {
        $this->authorizeAutomation($actor, $ticket);
        $guides = array_slice($guides, 0, 6);

        foreach ($guides as $guide) {
            $resource = [
                'resource_type' => $guide['resource_type'] ?? null,
                'resource_id' => $guide['resource_id'] ?? null,
            ];
            if (! $this->navigation->allowedFor($actor, (string) $guide['target_id'], $resource)) {
                throw new AuthorizationException('The guided destination is not authorized for this Family account.');
            }
        }

        return DB::transaction(function () use ($actor, $ticket, $messageBody, $intent, $resultCode, $guides): Collection {
            $locked = $this->lockedAutomatedTicket($actor, $ticket);
            $now = now();
            $this->cancelActorTasks($actor, 'superseded_by_new_task', $now);
            AiSupportMessageAction::query()
                ->where('actor_user_id', $actor->id)
                ->where('action_type', AiSupportMessageAction::TYPE_GUIDED_TASK)
                ->whereNull('consumed_at')
                ->whereNull('invalidated_at')
                ->update([
                    'invalidated_at' => $now,
                    'invalidation_reason' => 'superseded_by_new_task',
                ]);

            $message = $this->createAutomatedMessage($locked, $messageBody);
            $tasks = collect();

            foreach ($guides as $guide) {
                $definition = $this->navigation->definition((string) $guide['target_id']) ?? [];
                $task = AiSupportGuidedTask::query()->create([
                    'support_ticket_id' => $locked->id,
                    'actor_user_id' => $actor->id,
                    'family_account_id' => $this->familyAccounts->account($actor)->id,
                    'task_type' => (string) $guide['task_type'],
                    'state' => AiSupportGuidedTask::STATE_OFFERED,
                    'navigation_target_id' => (string) $guide['target_id'],
                    'resource_type' => $guide['resource_type'] ?? null,
                    'resource_id' => $guide['resource_id'] ?? null,
                    'payload' => [
                        'instruction' => (string) ($guide['instruction'] ?? $definition['instruction'] ?? 'Use the highlighted section.'),
                        'checked_at' => $now->toIso8601String(),
                    ],
                    'initial_state_hash' => hash('sha256', json_encode([
                        'intent' => $intent,
                        'result' => $resultCode,
                        'target' => $guide['target_id'],
                        'resource_type' => $guide['resource_type'] ?? null,
                        'resource_id' => $guide['resource_id'] ?? null,
                        'checked_at' => $now->getTimestamp(),
                    ], JSON_THROW_ON_ERROR)),
                    'last_result_code' => $resultCode,
                    'expires_at' => $now->copy()->addMinutes((int) config('ai_support.guided_task_validity_minutes', 60)),
                ]);
                AiSupportMessageAction::query()->create([
                    'id' => (string) Str::uuid(),
                    'support_ticket_message_id' => $message->id,
                    'support_ticket_id' => $locked->id,
                    'actor_user_id' => $actor->id,
                    'action_type' => AiSupportMessageAction::TYPE_GUIDED_TASK,
                    'payload' => [
                        'guided_task_id' => $task->id,
                        'label' => (string) $guide['label'],
                    ],
                    'expires_at' => $task->expires_at,
                ]);
                $this->events->record($locked, 'guided_task_offered', [
                    'support_ticket_message_id' => $message->id,
                    'capability_id' => 'semantic_navigation_v1',
                    'navigation_target_id' => $task->navigation_target_id,
                    'result_code' => $resultCode,
                ], $actor);
                $tasks->push($task);
            }

            $this->events->record($locked, 'family_account_status_read', [
                'support_ticket_message_id' => $message->id,
                'capability_id' => 'family_context_v1',
                'tool_id' => 'family-account-overview',
                'tool_version' => 'v1',
                'result_code' => $intent,
            ], $actor);

            return $tasks;
        }, 3);
    }

    public function startFromAction(User $actor, SupportTicket $ticket, string $actionId): string
    {
        $this->authorizeAutomation($actor, $ticket);

        return DB::transaction(function () use ($actor, $ticket, $actionId): string {
            $lockedTicket = $this->lockedAutomatedTicket($actor, $ticket);
            $action = AiSupportMessageAction::query()
                ->lockForUpdate()
                ->whereKey($actionId)
                ->where('support_ticket_id', $lockedTicket->id)
                ->where('actor_user_id', $actor->id)
                ->where('action_type', AiSupportMessageAction::TYPE_GUIDED_TASK)
                ->firstOrFail();
            if (! $action->isActive()) {
                throw ValidationException::withMessages(['guidedTask' => 'This guided step expired. Ask me to open it again.']);
            }

            $taskId = (string) data_get($action->payload, 'guided_task_id', '');
            $task = AiSupportGuidedTask::query()
                ->lockForUpdate()
                ->whereKey($taskId)
                ->where('support_ticket_id', $lockedTicket->id)
                ->where('actor_user_id', $actor->id)
                ->firstOrFail();
            if (! $task->isOpen() || $task->state !== AiSupportGuidedTask::STATE_OFFERED) {
                throw ValidationException::withMessages(['guidedTask' => 'This guided step is no longer available. Ask me to start again.']);
            }

            $now = now();
            AiSupportGuidedTask::query()
                ->where('actor_user_id', $actor->id)
                ->where('id', '!=', $task->id)
                ->whereIn('state', AiSupportGuidedTask::OPEN_STATES)
                ->update([
                    'state' => AiSupportGuidedTask::STATE_CANCELLED,
                    'cancelled_at' => $now,
                    'last_result_code' => 'superseded_by_started_task',
                    'updated_at' => $now,
                ]);
            $task->forceFill([
                'state' => AiSupportGuidedTask::STATE_NAVIGATING,
                'started_at' => $now,
                'last_result_code' => 'navigation_started',
                'version' => $task->version + 1,
            ])->save();
            $action->forceFill(['consumed_at' => $now])->save();
            AiSupportMessageAction::query()
                ->where('actor_user_id', $actor->id)
                ->where('action_type', AiSupportMessageAction::TYPE_GUIDED_TASK)
                ->where('id', '!=', $action->id)
                ->whereNull('consumed_at')
                ->whereNull('invalidated_at')
                ->update([
                    'invalidated_at' => $now,
                    'invalidation_reason' => 'sibling_task_started',
                ]);
            session()->put(self::SESSION_TASK_KEY, $task->id);
            $this->events->record($lockedTicket, 'guided_task_started', [
                'capability_id' => 'semantic_navigation_v1',
                'navigation_target_id' => $task->navigation_target_id,
                'result_code' => 'navigation_started',
            ], $actor);

            return $this->navigation->urlFor($actor, $task->navigation_target_id, [
                'resource_type' => $task->resource_type,
                'resource_id' => $task->resource_id,
            ]);
        }, 3);
    }

    public function foregroundFor(User $actor): ?AiSupportGuidedTask
    {
        return AiSupportGuidedTask::query()
            ->foreground()
            ->where('actor_user_id', $actor->id)
            ->whereHas('ticket', fn ($query) => $query
                ->where('opener_user_id', $actor->id)
                ->where('responder_mode', SupportTicket::RESPONDER_MODE_AUTOMATED))
            ->latest('started_at')
            ->first();
    }

    public function claimCompletedResult(User $actor): bool
    {
        return DB::transaction(function () use ($actor): bool {
            $task = AiSupportGuidedTask::query()
                ->lockForUpdate()
                ->where('actor_user_id', $actor->id)
                ->where('state', AiSupportGuidedTask::STATE_COMPLETED)
                ->whereNull('presented_at')
                ->latest('completed_at')
                ->first();
            if (! $task) {
                return false;
            }

            $ticket = SupportTicket::query()->find($task->support_ticket_id);
            if (! $ticket || ! Gate::forUser($actor)->allows('view', $ticket)) {
                return false;
            }

            $task->forceFill(['presented_at' => now()])->save();

            return true;
        }, 3);
    }

    /** @return array{id:string,targetId:string,instruction:string,label:string,state:string}|null */
    public function clientPayload(User $actor, ?AiSupportGuidedTask $task): ?array
    {
        if (! $task || (int) $task->actor_user_id !== (int) $actor->id || ! $task->isOpen()) {
            return null;
        }

        $definition = $this->navigation->definition($task->navigation_target_id);
        $clientTarget = trim((string) ($definition['client_target_id'] ?? ''));
        if (! $definition || $clientTarget === '' || ! $this->navigation->allowedFor($actor, $task->navigation_target_id, [
            'resource_type' => $task->resource_type,
            'resource_id' => $task->resource_id,
        ])) {
            return null;
        }

        $payload = (array) $task->payload;

        return [
            'id' => $task->id,
            'targetId' => $clientTarget,
            'instruction' => (string) ($payload['instruction'] ?? $definition['instruction'] ?? 'Use the highlighted control.'),
            'label' => (string) ($definition['label'] ?? 'Guided help'),
            'state' => $task->state,
        ];
    }

    public function reportArrival(User $actor, string $taskId, string $result): AiSupportGuidedTask
    {
        if (! in_array($result, ['arrived', 'target_missing', 'target_disabled'], true)) {
            throw ValidationException::withMessages(['guidedTask' => 'The page returned an invalid guidance result.']);
        }

        $candidate = AiSupportGuidedTask::query()
            ->whereKey($taskId)
            ->where('actor_user_id', $actor->id)
            ->firstOrFail();

        return DB::transaction(function () use ($actor, $candidate, $result): AiSupportGuidedTask {
            $lockedTicket = SupportTicket::query()->lockForUpdate()->findOrFail($candidate->support_ticket_id);
            $this->authorizeAutomation($actor, $lockedTicket);
            $task = AiSupportGuidedTask::query()
                ->lockForUpdate()
                ->whereKey($candidate->id)
                ->where('actor_user_id', $actor->id)
                ->firstOrFail();
            if (! $task->isOpen()) {
                return $task;
            }

            if ($result === 'arrived') {
                if ($task->state === AiSupportGuidedTask::STATE_NAVIGATING) {
                    $task->forceFill([
                        'state' => AiSupportGuidedTask::STATE_ARRIVED,
                        'arrived_at' => now(),
                        'last_result_code' => 'target_arrived',
                        'version' => $task->version + 1,
                    ])->save();
                    $this->events->record($lockedTicket, 'guided_target_arrived', [
                        'capability_id' => 'semantic_navigation_v1',
                        'navigation_target_id' => $task->navigation_target_id,
                        'result_code' => 'target_arrived',
                    ], $actor);
                }

                return $task;
            }

            $task->forceFill([
                'state' => AiSupportGuidedTask::STATE_FAILED,
                'last_result_code' => $result,
                'version' => $task->version + 1,
            ])->save();
            $paymentTask = $task->task_type === AiSupportGuidedTask::TYPE_PAYMENT_METHOD;
            $message = $this->createAutomatedMessage($lockedTicket, match (true) {
                $paymentTask && $result === 'target_disabled' => 'I opened Billing & Payments, but the payment-method button is not available right now. I have not changed anything. You can ask to talk to a person.',
                $paymentTask => 'I opened Billing & Payments, but I could not safely find the exact payment-method button. I have not changed anything. You can ask to talk to a person.',
                $result === 'target_disabled' => 'I opened the right page, but the exact section is not available right now. I have not changed anything. Ask me to check again or talk to a person.',
                default => 'I opened the right page, but I could not safely find the exact section to highlight. I have not changed anything. Ask me to check again or talk to a person.',
            });
            $this->events->record($lockedTicket, 'guided_target_failed', [
                'support_ticket_message_id' => $message->id,
                'capability_id' => 'semantic_navigation_v1',
                'navigation_target_id' => $task->navigation_target_id,
                'result_code' => $result,
            ], $actor);
            session()->forget(self::SESSION_TASK_KEY);

            return $task;
        }, 3);
    }

    public function cancel(User $actor, string $taskId, string $reason = 'user_cancelled'): void
    {
        $candidate = AiSupportGuidedTask::query()
            ->whereKey($taskId)
            ->where('actor_user_id', $actor->id)
            ->firstOrFail();

        DB::transaction(function () use ($actor, $candidate, $reason): void {
            $lockedTicket = SupportTicket::query()->lockForUpdate()->findOrFail($candidate->support_ticket_id);
            $task = AiSupportGuidedTask::query()
                ->lockForUpdate()
                ->whereKey($candidate->id)
                ->where('actor_user_id', $actor->id)
                ->firstOrFail();
            if (! Gate::forUser($actor)->allows('view', $lockedTicket)) {
                throw new AuthorizationException;
            }
            if ($task->isOpen()) {
                $task->forceFill([
                    'state' => AiSupportGuidedTask::STATE_CANCELLED,
                    'cancelled_at' => now(),
                    'last_result_code' => $reason,
                    'version' => $task->version + 1,
                ])->save();
                $this->events->record($lockedTicket, 'guided_task_cancelled', [
                    'capability_id' => 'semantic_navigation_v1',
                    'navigation_target_id' => $task->navigation_target_id,
                    'result_code' => $reason,
                ], $actor);
            }
            session()->forget(self::SESSION_TASK_KEY);
        }, 3);
    }

    public function markPaymentSetupStarted(User $actor): void
    {
        $candidate = $this->sessionPaymentTask($actor);
        if (! $candidate || ! $candidate->isOpen()) {
            return;
        }

        DB::transaction(function () use ($actor, $candidate): void {
            $lockedTicket = SupportTicket::query()->lockForUpdate()->findOrFail($candidate->support_ticket_id);
            $this->authorizeAutomation($actor, $lockedTicket);
            $task = AiSupportGuidedTask::query()->lockForUpdate()->findOrFail($candidate->id);
            if (! $task->isOpen()) {
                return;
            }
            $task->forceFill([
                'state' => AiSupportGuidedTask::STATE_IN_PROGRESS,
                'in_progress_at' => now(),
                'last_result_code' => 'secure_checkout_started',
                'version' => $task->version + 1,
            ])->save();
            $this->events->record($lockedTicket, 'guided_action_started', [
                'capability_id' => 'semantic_navigation_v1',
                'navigation_target_id' => $task->navigation_target_id,
                'result_code' => 'secure_checkout_started',
            ], $actor);
        }, 3);
    }

    public function paymentSetupCompleted(User $actor): void
    {
        $task = $this->sessionPaymentTask($actor);
        if (! $task || ! $task->isOpen()) {
            session()->forget(self::SESSION_TASK_KEY);

            return;
        }

        try {
            $status = $this->paymentStatus->read($actor);
        } catch (PaymentException) {
            $this->paymentSetupFailed($actor, 'verification_unavailable');

            return;
        }
        if (! $status['ready'] || ! $status['card']) {
            $this->paymentSetupFailed($actor, 'payment_method_not_ready');

            return;
        }

        DB::transaction(function () use ($actor, $task, $status): void {
            $lockedTicket = SupportTicket::query()->lockForUpdate()->findOrFail($task->support_ticket_id);
            $locked = AiSupportGuidedTask::query()->lockForUpdate()->findOrFail($task->id);
            if (! $locked->isOpen()) {
                return;
            }
            if (! $this->mayDeliver($actor, $lockedTicket)) {
                $locked->forceFill([
                    'state' => AiSupportGuidedTask::STATE_CANCELLED,
                    'cancelled_at' => now(),
                    'last_result_code' => 'automation_ended_before_verification',
                ])->save();

                return;
            }

            $locked->forceFill([
                'state' => AiSupportGuidedTask::STATE_COMPLETED,
                'completed_at' => now(),
                'result_state_hash' => $status['state_hash'],
                'last_result_code' => 'payment_method_verified',
                'version' => $locked->version + 1,
            ])->save();
            $card = $status['card'];
            $message = $this->createAutomatedMessage(
                $lockedTicket,
                sprintf(
                    'Your %s payment method ending in %s is now on file. It expires %02d/%d.',
                    $this->displayBrand($card['brand']),
                    $card['last4'],
                    $card['exp_month'],
                    $card['exp_year'],
                ),
            );
            $this->events->record($lockedTicket, 'guided_task_completed', [
                'support_ticket_message_id' => $message->id,
                'capability_id' => 'family_context_v1',
                'navigation_target_id' => $locked->navigation_target_id,
                'tool_id' => 'family-payment-status',
                'tool_version' => 'v1',
                'result_code' => 'payment_method_verified',
            ], $actor);
        }, 3);

        session()->forget(self::SESSION_TASK_KEY);
        session()->flash(self::SESSION_OPEN_CHAT_KEY, true);
    }

    public function paymentSetupCancelled(User $actor): void
    {
        $this->recordRecoverablePaymentResult(
            $actor,
            'secure_checkout_cancelled',
            'Your payment method was not changed. The payment-method button is still highlighted if you want to try again.',
        );
    }

    public function paymentSetupFailed(User $actor, string $resultCode = 'verification_failed'): void
    {
        $this->recordRecoverablePaymentResult(
            $actor,
            $resultCode,
            "I couldn't verify a new payment method. I have not marked this as complete. The billing page shows the current error, and you can try again or talk to a person.",
        );
    }

    private function recordRecoverablePaymentResult(User $actor, string $resultCode, string $body): void
    {
        $candidate = $this->sessionPaymentTask($actor);
        if (! $candidate || ! $candidate->isOpen()) {
            return;
        }

        DB::transaction(function () use ($actor, $candidate, $resultCode, $body): void {
            $lockedTicket = SupportTicket::query()->lockForUpdate()->findOrFail($candidate->support_ticket_id);
            $task = AiSupportGuidedTask::query()->lockForUpdate()->findOrFail($candidate->id);
            if (! $task->isOpen()) {
                return;
            }
            if (! $this->mayDeliver($actor, $lockedTicket)) {
                $task->forceFill([
                    'state' => AiSupportGuidedTask::STATE_CANCELLED,
                    'cancelled_at' => now(),
                    'last_result_code' => 'automation_ended_before_result',
                ])->save();

                return;
            }

            $alreadyRecorded = $task->last_result_code === $resultCode;
            $task->forceFill([
                'state' => AiSupportGuidedTask::STATE_ARRIVED,
                'arrived_at' => $task->arrived_at ?? now(),
                'last_result_code' => $resultCode,
                'version' => $task->version + 1,
            ])->save();
            if ($alreadyRecorded) {
                return;
            }
            $message = $this->createAutomatedMessage($lockedTicket, $body);
            $this->events->record($lockedTicket, 'guided_action_recovery', [
                'support_ticket_message_id' => $message->id,
                'capability_id' => 'semantic_navigation_v1',
                'navigation_target_id' => $task->navigation_target_id,
                'result_code' => $resultCode,
            ], $actor);
        }, 3);
        session()->flash(self::SESSION_OPEN_CHAT_KEY, true);
    }

    private function sessionPaymentTask(User $actor): ?AiSupportGuidedTask
    {
        $taskId = (string) session()->get(self::SESSION_TASK_KEY, '');
        $query = AiSupportGuidedTask::query()
            ->where('actor_user_id', $actor->id)
            ->where('task_type', AiSupportGuidedTask::TYPE_PAYMENT_METHOD);
        if ($taskId !== '') {
            $query->whereKey($taskId);
        } else {
            $query->foreground()->latest('started_at');
        }

        return $query->first();
    }

    private function authorizeAutomation(User $actor, SupportTicket $ticket): void
    {
        if ((int) $ticket->opener_user_id !== (int) $actor->id
            || ! Gate::forUser($actor)->allows('view', $ticket)
            || $ticket->responder_mode !== SupportTicket::RESPONDER_MODE_AUTOMATED
            || $actor->role !== 'family'
            || ! $this->familyAccounts->membershipFor($actor, false)
            || ! $this->eligibility->evaluate($actor, 'semantic_navigation_v1', $ticket)->allowed
            || ! $this->eligibility->evaluate($actor, 'family_context_v1', $ticket)->allowed) {
            throw new AuthorizationException;
        }
    }

    private function lockedAutomatedTicket(User $actor, SupportTicket $ticket): SupportTicket
    {
        $locked = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
        $this->authorizeAutomation($actor, $locked);

        return $locked;
    }

    private function mayDeliver(User $actor, SupportTicket $ticket): bool
    {
        try {
            $this->authorizeAutomation($actor, $ticket);

            return true;
        } catch (AuthorizationException) {
            return false;
        }
    }

    private function cancelActorTasks(User $actor, string $reason, mixed $at): void
    {
        AiSupportGuidedTask::query()
            ->where('actor_user_id', $actor->id)
            ->whereIn('state', AiSupportGuidedTask::OPEN_STATES)
            ->update([
                'state' => AiSupportGuidedTask::STATE_CANCELLED,
                'cancelled_at' => $at,
                'last_result_code' => $reason,
                'updated_at' => $at,
            ]);
    }

    private function createAutomatedMessage(SupportTicket $ticket, string $body): SupportTicketMessage
    {
        $message = SupportTicketMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'sender_user_id' => null,
            'kind' => SupportTicketMessage::KIND_PUBLIC,
            'responder_type' => SupportTicketMessage::RESPONDER_AUTOMATED,
            'body' => $body,
            'client_message_id' => (string) Str::uuid(),
        ]);
        $ticket->forceFill([
            'last_public_message_at' => $message->created_at,
            'last_public_message_sender_id' => null,
            'opener_last_read_at' => null,
        ])->save();

        return $message;
    }

    /** @param array<string,mixed> $status */
    private function paymentOfferMessage(array $status): string
    {
        if (! $status['ready'] || ! $status['card']) {
            return $status['attention'] === 'unavailable'
                ? "I couldn't verify the saved payment method right now. I can still take you to Billing & Payments and highlight the secure payment-method button."
                : 'There is no payment method on file yet. I can take you to Billing & Payments and highlight where to add one securely.';
        }

        $card = $status['card'];
        $attention = match ($status['attention']) {
            'expired' => ' This card is expired.',
            'expiring_soon' => ' This card expires soon.',
            default => '',
        };

        return sprintf(
            'Your saved %s payment method ends in %s and expires %02d/%d.%s I can take you to the exact button to update it.',
            $this->displayBrand($card['brand']),
            $card['last4'],
            $card['exp_month'],
            $card['exp_year'],
            $attention,
        );
    }

    private function displayBrand(string $brand): string
    {
        return Str::of($brand)->replace(['_', '-'], ' ')->headline()->value() ?: 'Card';
    }
}
