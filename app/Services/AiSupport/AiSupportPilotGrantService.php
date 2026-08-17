<?php

namespace App\Services\AiSupport;

use App\Models\AiSupportActionPreview;
use App\Models\AiSupportAdminAuditEvent;
use App\Models\AiSupportMessageAction;
use App\Models\AiSupportPilotGrant;
use App\Models\SupportTicket;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AiSupportPilotGrantService
{
    public function __construct(private readonly AiSupportHandoffService $handoff) {}

    public function grant(
        User $actor,
        User $target,
        string $bundleKey,
        CarbonInterface $startsAt,
        ?CarbonInterface $expiresAt,
        string $reason,
        string $requestKey,
        bool $noExpiryAcknowledged = false,
    ): AiSupportPilotGrant {
        $this->ensureManager($actor, $target, 'pilot_grant_denied');
        $bundle = $this->validatedBundle($target, $bundleKey);
        $reason = $this->validatedReason($reason, 'grantReason');

        if (! Str::isUuid($requestKey)) {
            throw ValidationException::withMessages(['grantRequestKey' => 'Refresh the page and try again.']);
        }

        if ($expiresAt && ! $expiresAt->greaterThan($startsAt)) {
            throw ValidationException::withMessages(['grantExpiresAt' => 'Expiry must be after activation.']);
        }

        if (! $expiresAt && ! $noExpiryAcknowledged) {
            throw ValidationException::withMessages([
                'noExpiryAcknowledged' => 'Acknowledge that access continues until manual revocation.',
            ]);
        }

        return DB::transaction(function () use (
            $actor,
            $target,
            $bundleKey,
            $bundle,
            $startsAt,
            $expiresAt,
            $reason,
            $requestKey,
        ): AiSupportPilotGrant {
            User::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();

            $idempotent = AiSupportPilotGrant::query()->where('request_key', $requestKey)->first();
            if ($idempotent) {
                if ((int) $idempotent->user_id !== (int) $target->id) {
                    throw new AuthorizationException;
                }

                return $idempotent;
            }

            $existing = AiSupportPilotGrant::query()
                ->where('user_id', $target->id)
                ->notRevoked()
                ->where(fn ($window) => $window
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now()))
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'grant' => 'This exact user already has an active or scheduled grant.',
                ]);
            }

            $activeOrScheduled = AiSupportPilotGrant::query()
                ->notRevoked()
                ->where(fn ($window) => $window
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now()))
                ->lockForUpdate()
                ->count();
            if ($activeOrScheduled >= (int) config('ai_support.pilot.maximum_active_users', 2)) {
                throw ValidationException::withMessages([
                    'grant' => 'The two-user pilot is full. Revoke one pilot user before adding another.',
                ]);
            }

            $now = now();
            $grant = AiSupportPilotGrant::query()->create([
                'id' => (string) Str::uuid(),
                'request_key' => $requestKey,
                'user_id' => $target->id,
                'bundle_key' => $bundleKey,
                'capability_ids' => array_values((array) $bundle['capabilities']),
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'granted_by_user_id' => $actor->id,
                'grant_reason' => $reason,
                'retain_until' => $expiresAt?->copy()->addMonths((int) config('ai_support.grant_history_months', 24)),
                'created_at' => $now,
            ]);

            AiSupportAdminAuditEvent::query()->create([
                'id' => (string) Str::uuid(),
                'event_family' => 'pilot_access',
                'action' => 'pilot_grant_created',
                'actor_user_id' => $actor->id,
                'target_user_id' => $target->id,
                'subject_type' => AiSupportPilotGrant::class,
                'subject_id' => $grant->id,
                'result' => 'succeeded',
                'reason_code' => $startsAt->isFuture() ? 'scheduled' : 'granted',
                'reason' => $reason,
                'metadata' => [
                    'bundle_key' => $bundleKey,
                    'capability_ids' => $grant->capability_ids,
                    'starts_at' => $grant->starts_at->toIso8601String(),
                    'expires_at' => $grant->expires_at?->toIso8601String(),
                ],
                'policy_version' => (string) config('ai_support.policy_version'),
                'retain_until' => ($expiresAt ?? $now)->copy()->addMonths((int) config('ai_support.grant_history_months', 24)),
                'occurred_at' => $now,
                'created_at' => $now,
            ]);

            return $grant;
        }, 3);
    }

    public function revoke(User $actor, AiSupportPilotGrant $grant, string $reason): AiSupportPilotGrant
    {
        $this->ensureManager($actor, $grant->user, 'pilot_revocation_denied');
        $reason = $this->validatedReason($reason, 'revocationReason');

        $revoked = DB::transaction(function () use ($actor, $grant, $reason): AiSupportPilotGrant {
            $locked = AiSupportPilotGrant::query()->lockForUpdate()->findOrFail($grant->id);
            if ($locked->revoked_at) {
                return $locked;
            }

            $now = now();
            $locked->forceFill([
                'revoked_at' => $now,
                'revoked_by_user_id' => $actor->id,
                'revocation_reason' => $reason,
                'retain_until' => $now->copy()->addMonths((int) config('ai_support.grant_history_months', 24)),
            ])->save();
            AiSupportActionPreview::query()
                ->where('actor_user_id', $locked->user_id)
                ->whereNull('content_deleted_at')
                ->update([
                    'preview_payload' => null,
                    'invalidated_at' => $now,
                    'invalidation_reason' => 'pilot_grant_revoked',
                    'content_deleted_at' => $now,
                ]);
            AiSupportMessageAction::query()
                ->where('actor_user_id', $locked->user_id)
                ->where('action_type', '!=', AiSupportMessageAction::TYPE_RECEIPT)
                ->whereNull('consumed_at')
                ->whereNull('invalidated_at')
                ->update([
                    'payload' => null,
                    'invalidated_at' => $now,
                    'invalidation_reason' => 'pilot_grant_revoked',
                ]);

            AiSupportAdminAuditEvent::query()->create([
                'id' => (string) Str::uuid(),
                'event_family' => 'pilot_access',
                'action' => 'pilot_grant_revoked',
                'actor_user_id' => $actor->id,
                'target_user_id' => $locked->user_id,
                'subject_type' => AiSupportPilotGrant::class,
                'subject_id' => $locked->id,
                'result' => 'succeeded',
                'reason_code' => 'revoked_immediately',
                'reason' => $reason,
                'metadata' => [
                    'bundle_key' => $locked->bundle_key,
                    'revoked_at' => $now->toIso8601String(),
                ],
                'policy_version' => (string) config('ai_support.policy_version'),
                'retain_until' => $now->copy()->addMonths((int) config('ai_support.grant_history_months', 24)),
                'occurred_at' => $now,
                'created_at' => $now,
            ]);

            return $locked;
        }, 3);

        $target = $revoked->user_id ? User::query()->find($revoked->user_id) : null;
        if ($target) {
            SupportTicket::query()
                ->where('opener_user_id', $target->id)
                ->where('responder_mode', SupportTicket::RESPONDER_MODE_AUTOMATED)
                ->whereNotIn('status', [SupportTicket::STATUS_CLOSED])
                ->get()
                ->each(function (SupportTicket $ticket) use ($target): void {
                    try {
                        $this->handoff->transfer($target, $ticket, 'pilot_grant_revoked');
                    } catch (\Throwable $exception) {
                        report($exception);
                        $ticket->forceFill([
                            'responder_mode' => SupportTicket::RESPONDER_MODE_HUMAN_ONLY,
                            'transferred_to_human_at' => now(),
                            'handoff_reason_code' => 'pilot_grant_revoked',
                        ])->save();
                    }
                });
        }

        return $revoked;
    }

    /** @return array<string, mixed> */
    private function validatedBundle(User $target, string $bundleKey): array
    {
        $bundle = config('ai_support.bundles.'.$bundleKey);
        if (! is_array($bundle) || ! in_array($target->role, (array) ($bundle['roles'] ?? []), true)) {
            throw ValidationException::withMessages([
                'grantBundleKey' => 'Select a released pilot bundle that supports this user role.',
            ]);
        }

        return $bundle;
    }

    private function validatedReason(string $reason, string $field): string
    {
        $reason = trim($reason);
        $max = (int) config('ai_support.reason_max_length', 500);

        if (mb_strlen($reason) < 5 || mb_strlen($reason) > $max) {
            throw ValidationException::withMessages([
                $field => "Enter a concise reason between 5 and {$max} characters without care details.",
            ]);
        }

        return $reason;
    }

    private function ensureManager(User $actor, User $target, string $action): void
    {
        if ($actor->canManageAiSupportPilot()) {
            return;
        }

        try {
            $now = now();
            AiSupportAdminAuditEvent::query()->create([
                'id' => (string) Str::uuid(),
                'event_family' => 'pilot_access',
                'action' => $action,
                'actor_user_id' => $actor->id,
                'target_user_id' => $target->id,
                'result' => 'denied',
                'reason_code' => 'not_pilot_manager',
                'policy_version' => (string) config('ai_support.policy_version'),
                'retain_until' => $now->copy()->addMonths((int) config('ai_support.grant_history_months', 24)),
                'occurred_at' => $now,
                'created_at' => $now,
            ]);
        } catch (\Throwable) {
            // Authorization remains denied if audit storage is unavailable.
        }

        throw new AuthorizationException;
    }
}
