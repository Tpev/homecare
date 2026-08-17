<?php

namespace App\Services\AiSupport;

use App\Models\AiSupportActionPreview;
use App\Models\AiSupportAdminAuditEvent;
use App\Models\AiSupportControlVersion;
use App\Models\AiSupportGuidedTask;
use App\Models\AiSupportMessageAction;
use App\Models\AiSupportPilotGrant;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AiSupportControlService
{
    /** @return array{enabled: bool, source: string, version_id: int|null, version: int|null} */
    public function state(string $controlKey): array
    {
        $this->ensureKnownControl($controlKey);

        $current = AiSupportControlVersion::query()
            ->current()
            ->where('control_key', $controlKey)
            ->latest('version')
            ->first();

        if ($current) {
            return [
                'enabled' => $current->enabled,
                'source' => 'stored',
                'version_id' => $current->id,
                'version' => $current->version,
            ];
        }

        return [
            'enabled' => $this->defaultEnabled($controlKey),
            'source' => 'default',
            'version_id' => null,
            'version' => null,
        ];
    }

    public function enabled(string $controlKey): bool
    {
        return $this->state($controlKey)['enabled'];
    }

    public function enableForEveryone(User $actor): void
    {
        $reason = 'Enable AI Support for every supported Family and Caregiver user.';

        foreach ((array) config('ai_support.general_release_controls', []) as $controlKey) {
            $this->set($actor, (string) $controlKey, true, $reason);
        }

        $this->set($actor, 'general_release_enabled', true, $reason);
        $this->set($actor, 'human_only', false, $reason);
    }

    public function usePilotOnly(User $actor): void
    {
        if (! $this->enabled('general_release_enabled')) {
            return;
        }

        $this->set(
            $actor,
            'general_release_enabled',
            false,
            'Limit AI Support to users with an active pilot grant.',
        );
    }

    public function stopAllAutomation(User $actor): void
    {
        $this->set($actor, 'human_only', true, 'Stop AI Support automation and keep human chat available.');
    }

    public function resumeAutomation(User $actor): void
    {
        $this->set($actor, 'human_only', false, 'Resume the selected AI Support availability mode.');
    }

    public function set(User $actor, string $controlKey, bool $enabled, string $reason): AiSupportControlVersion
    {
        if (! $actor->canManageAiSupportControls()) {
            $this->recordDeniedAttempt($actor, $controlKey);

            throw new AuthorizationException;
        }

        $this->ensureKnownControl($controlKey);
        $reason = $this->validatedReason($reason);

        if ($controlKey === 'shadow_enabled' && $enabled && ! config('ai_support.shadow_mutations_allowed', false)) {
            throw ValidationException::withMessages([
                'controlKey' => 'Shadow mode is intentionally disabled under DEC-047.',
            ]);
        }

        return DB::transaction(function () use ($actor, $controlKey, $enabled, $reason): AiSupportControlVersion {
            $current = AiSupportControlVersion::query()
                ->where('control_key', $controlKey)
                ->whereNull('replaced_at')
                ->lockForUpdate()
                ->latest('version')
                ->first();

            if ($current && $current->enabled === $enabled) {
                return $current;
            }

            $now = now();
            if ($current) {
                $current->forceFill([
                    'replaced_at' => $now,
                    'retain_until' => $now->copy()->addMonths((int) config('ai_support.control_history_months', 24)),
                ])->save();
            }

            $version = AiSupportControlVersion::query()->create([
                'control_key' => $controlKey,
                'version' => ((int) ($current?->version ?? 0)) + 1,
                'enabled' => $enabled,
                'configuration' => null,
                'changed_by_user_id' => $actor->id,
                'reason' => $reason,
                'effective_at' => $now,
                'replaced_at' => null,
                'retain_until' => null,
                'created_at' => $now,
            ]);

            AiSupportAdminAuditEvent::query()->create([
                'id' => (string) Str::uuid(),
                'event_family' => 'control',
                'action' => 'control_state_changed',
                'actor_user_id' => $actor->id,
                'subject_type' => AiSupportControlVersion::class,
                'subject_id' => (string) $version->id,
                'result' => 'succeeded',
                'reason_code' => $enabled ? 'enabled' : 'disabled',
                'reason' => $reason,
                'metadata' => [
                    'control_key' => $controlKey,
                    'previous_enabled' => $current?->enabled ?? $this->defaultEnabled($controlKey),
                    'effective_enabled' => $enabled,
                    'version' => $version->version,
                ],
                'policy_version' => (string) config('ai_support.policy_version'),
                'retain_until' => $now->copy()->addMonths((int) config('ai_support.control_history_months', 24)),
                'occurred_at' => $now,
                'created_at' => $now,
            ]);
            $this->applyStopSideEffects($controlKey, $enabled, $now);

            return $version;
        }, 3);
    }

    public function systemStop(string $controlKey, string $reasonCode, string $reason): ?AiSupportControlVersion
    {
        $this->ensureKnownControl($controlKey);
        $reason = $this->validatedReason($reason);
        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,119}\z/', $reasonCode) !== 1) {
            throw ValidationException::withMessages(['reasonCode' => 'Use a compact automatic-stop reason code.']);
        }

        $stopped = false;
        $version = DB::transaction(function () use ($controlKey, $reasonCode, $reason, &$stopped): ?AiSupportControlVersion {
            $current = AiSupportControlVersion::query()
                ->where('control_key', $controlKey)
                ->whereNull('replaced_at')
                ->lockForUpdate()
                ->latest('version')
                ->first();
            if (! ($current?->enabled ?? $this->defaultEnabled($controlKey))) {
                return $current;
            }

            $now = now();
            if ($current) {
                $current->forceFill([
                    'replaced_at' => $now,
                    'retain_until' => $now->copy()->addMonths((int) config('ai_support.control_history_months', 24)),
                ])->save();
            }
            $version = AiSupportControlVersion::query()->create([
                'control_key' => $controlKey,
                'version' => ((int) ($current?->version ?? 0)) + 1,
                'enabled' => false,
                'configuration' => ['automatic_stop' => true, 'reason_code' => $reasonCode],
                'changed_by_user_id' => null,
                'reason' => $reason,
                'effective_at' => $now,
                'created_at' => $now,
            ]);
            $stopped = true;
            AiSupportAdminAuditEvent::query()->create([
                'id' => (string) Str::uuid(),
                'event_family' => 'control',
                'action' => 'automatic_capability_stop',
                'actor_user_id' => null,
                'subject_type' => AiSupportControlVersion::class,
                'subject_id' => (string) $version->id,
                'result' => 'succeeded',
                'reason_code' => $reasonCode,
                'reason' => $reason,
                'metadata' => ['control_key' => $controlKey, 'effective_enabled' => false, 'version' => $version->version],
                'policy_version' => (string) config('ai_support.policy_version'),
                'retain_until' => $now->copy()->addMonths((int) config('ai_support.control_history_months', 24)),
                'occurred_at' => $now,
                'created_at' => $now,
            ]);
            $this->applyStopSideEffects($controlKey, false, $now);

            return $version;
        }, 3);

        if ($stopped) {
            app(AiSupportIncidentService::class)->open(
                $reasonCode,
                $reason,
                $controlKey,
            );
        }

        return $version;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys((array) config('ai_support.controls', []));
    }

    private function ensureKnownControl(string $controlKey): void
    {
        if (! array_key_exists($controlKey, (array) config('ai_support.controls', []))) {
            throw ValidationException::withMessages(['controlKey' => 'Unknown AI support control.']);
        }
    }

    private function defaultEnabled(string $controlKey): bool
    {
        return (bool) (((array) config('ai_support.controls', []))[$controlKey] ?? false);
    }

    private function validatedReason(string $reason): string
    {
        $reason = trim($reason);
        $max = (int) config('ai_support.reason_max_length', 500);

        if (mb_strlen($reason) < 5 || mb_strlen($reason) > $max) {
            throw ValidationException::withMessages([
                'controlReason' => "Enter a concise reason between 5 and {$max} characters.",
            ]);
        }

        return $reason;
    }

    private function recordDeniedAttempt(User $actor, string $controlKey): void
    {
        try {
            $now = now();
            AiSupportAdminAuditEvent::query()->create([
                'id' => (string) Str::uuid(),
                'event_family' => 'control',
                'action' => 'control_state_change_denied',
                'actor_user_id' => $actor->id,
                'result' => 'denied',
                'reason_code' => 'not_control_manager',
                'metadata' => ['control_key' => $controlKey],
                'policy_version' => (string) config('ai_support.policy_version'),
                'retain_until' => $now->copy()->addMonths((int) config('ai_support.control_history_months', 24)),
                'occurred_at' => $now,
                'created_at' => $now,
            ]);
        } catch (\Throwable) {
            // Authorization still fails closed if audit storage is unavailable.
        }
    }

    private function applyStopSideEffects(string $controlKey, bool $enabled, $now): void
    {
        if ($controlKey === 'general_release_enabled') {
            if (! $enabled) {
                $this->returnNonPilotConversationsToHuman($now);
            }

            return;
        }

        $isStop = $controlKey === 'human_only' ? $enabled : ! $enabled;
        if (! $isStop) {
            return;
        }

        AiSupportActionPreview::query()->whereNull('content_deleted_at')->update([
            'preview_payload' => null,
            'invalidated_at' => $now,
            'invalidation_reason' => 'control_stop',
            'content_deleted_at' => $now,
        ]);
        AiSupportMessageAction::query()
            ->where('action_type', '!=', AiSupportMessageAction::TYPE_RECEIPT)
            ->whereNull('consumed_at')
            ->whereNull('invalidated_at')
            ->update([
                'payload' => null,
                'invalidated_at' => $now,
                'invalidation_reason' => 'control_stop',
            ]);
        AiSupportGuidedTask::query()
            ->whereIn('state', AiSupportGuidedTask::OPEN_STATES)
            ->update([
                'state' => AiSupportGuidedTask::STATE_CANCELLED,
                'cancelled_at' => $now,
                'last_result_code' => 'control_stop',
                'updated_at' => $now,
            ]);

        $stopsAllReplies = in_array($controlKey, [
            'master_enabled', 'user_visible_enabled', 'human_only',
            'capability.support_answers_v1',
        ], true) || str_starts_with($controlKey, 'role.');
        if (! $stopsAllReplies) {
            return;
        }

        SupportTicket::query()
            ->where('responder_mode', SupportTicket::RESPONDER_MODE_AUTOMATED)
            ->whereNotIn('status', [SupportTicket::STATUS_CLOSED])
            ->when(str_starts_with($controlKey, 'role.'), function ($query) use ($controlKey): void {
                $query->whereHas('opener', fn ($users) => $users->where('role', substr($controlKey, 5)));
            })
            ->orderBy('id')
            ->get()
            ->each(function (SupportTicket $ticket) use ($now): void {
                $message = SupportTicketMessage::query()->create([
                    'support_ticket_id' => $ticket->id,
                    'sender_user_id' => null,
                    'kind' => SupportTicketMessage::KIND_PUBLIC,
                    'responder_type' => SupportTicketMessage::RESPONDER_SYSTEM,
                    'body' => 'This conversation is now with LoLo Support. You can keep using this chat.',
                    'client_message_id' => (string) Str::uuid(),
                ]);
                $ticket->forceFill([
                    'responder_mode' => SupportTicket::RESPONDER_MODE_HUMAN_ONLY,
                    'transferred_to_human_at' => $now,
                    'handoff_reason_code' => 'control_stop',
                    'last_public_message_at' => $message->created_at,
                    'last_public_message_sender_id' => null,
                    'opener_last_read_at' => null,
                    'admin_last_read_at' => null,
                ])->save();
            });
    }

    private function returnNonPilotConversationsToHuman($now): void
    {
        $pilotUserIds = AiSupportPilotGrant::query()
            ->effectiveAt()
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->all();

        $affectedUserIds = SupportTicket::query()
            ->where('responder_mode', SupportTicket::RESPONDER_MODE_AUTOMATED)
            ->whereNotIn('status', [SupportTicket::STATUS_CLOSED])
            ->when($pilotUserIds !== [], fn ($query) => $query->whereNotIn('opener_user_id', $pilotUserIds))
            ->pluck('opener_user_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($affectedUserIds === []) {
            return;
        }

        AiSupportActionPreview::query()
            ->whereIn('actor_user_id', $affectedUserIds)
            ->whereNull('content_deleted_at')
            ->update([
                'preview_payload' => null,
                'invalidated_at' => $now,
                'invalidation_reason' => 'general_release_ended',
                'content_deleted_at' => $now,
            ]);
        AiSupportMessageAction::query()
            ->whereIn('actor_user_id', $affectedUserIds)
            ->where('action_type', '!=', AiSupportMessageAction::TYPE_RECEIPT)
            ->whereNull('consumed_at')
            ->whereNull('invalidated_at')
            ->update([
                'payload' => null,
                'invalidated_at' => $now,
                'invalidation_reason' => 'general_release_ended',
            ]);
        AiSupportGuidedTask::query()
            ->whereIn('actor_user_id', $affectedUserIds)
            ->whereIn('state', AiSupportGuidedTask::OPEN_STATES)
            ->update([
                'state' => AiSupportGuidedTask::STATE_CANCELLED,
                'cancelled_at' => $now,
                'last_result_code' => 'general_release_ended',
                'updated_at' => $now,
            ]);

        SupportTicket::query()
            ->whereIn('opener_user_id', $affectedUserIds)
            ->where('responder_mode', SupportTicket::RESPONDER_MODE_AUTOMATED)
            ->whereNotIn('status', [SupportTicket::STATUS_CLOSED])
            ->orderBy('id')
            ->get()
            ->each(function (SupportTicket $ticket) use ($now): void {
                $message = SupportTicketMessage::query()->create([
                    'support_ticket_id' => $ticket->id,
                    'sender_user_id' => null,
                    'kind' => SupportTicketMessage::KIND_PUBLIC,
                    'responder_type' => SupportTicketMessage::RESPONDER_SYSTEM,
                    'body' => 'This conversation is now with LoLo Support. You can keep using this chat.',
                    'client_message_id' => (string) Str::uuid(),
                ]);
                $ticket->forceFill([
                    'responder_mode' => SupportTicket::RESPONDER_MODE_HUMAN_ONLY,
                    'transferred_to_human_at' => $now,
                    'handoff_reason_code' => 'general_release_ended',
                    'last_public_message_at' => $message->created_at,
                    'last_public_message_sender_id' => null,
                    'opener_last_read_at' => null,
                    'admin_last_read_at' => null,
                ])->save();
            });
    }
}
