<?php

namespace App\Services\AiSupport;

use App\Models\AiSupportAdminAuditEvent;
use App\Models\AiSupportControlVersion;
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
                'controlKey' => 'Shadow mode remains locked until DEC-014 privacy and retention decisions are approved.',
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

            return $version;
        }, 3);
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
}
