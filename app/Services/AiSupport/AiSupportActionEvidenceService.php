<?php

namespace App\Services\AiSupport;

use App\Models\AiSupportActionPreview;
use App\Models\AiSupportConfirmedActionEvidence;
use App\Models\SupportTicket;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AiSupportActionEvidenceService
{
    public function __construct(
        private readonly AiSupportEligibilityService $eligibility,
        private readonly AiSupportEventRecorder $events,
    ) {}

    /**
     * @param  array<string, mixed>  $normalizedPayload
     * @return array{preview: AiSupportActionPreview, confirmation_reference: string}
     */
    public function createPreview(
        User $actor,
        SupportTicket $ticket,
        string $capabilityId,
        string $toolId,
        string $toolVersion,
        array $normalizedPayload,
        CarbonInterface $expiresAt,
    ): array {
        $tool = $this->toolDefinition($capabilityId, $toolId, $toolVersion);
        if (! Gate::forUser($actor)->allows('view', $ticket)) {
            throw new AuthorizationException;
        }

        $absoluteMaximum = now()->addHours((int) config('ai_support.preview_content_max_hours', 24));
        $capabilityMaximum = now()->addMinutes((int) $tool['preview_validity_minutes']);
        $maximum = $capabilityMaximum->isBefore($absoluteMaximum) ? $capabilityMaximum : $absoluteMaximum;
        if (! $expiresAt->isFuture() || $expiresAt->greaterThan($maximum)) {
            throw ValidationException::withMessages([
                'expiresAt' => 'Preview expiration exceeds the registered safety-validity window.',
            ]);
        }

        return DB::transaction(function () use ($actor, $ticket, $capabilityId, $toolId, $toolVersion, $normalizedPayload, $expiresAt): array {
            $lockedTicket = SupportTicket::query()->with('opener')->lockForUpdate()->findOrFail($ticket->id);
            $eligibility = $this->eligibility->evaluate($actor, $capabilityId, $lockedTicket, $toolId);
            if ($lockedTicket->responder_mode !== SupportTicket::RESPONDER_MODE_AUTOMATED
                || $lockedTicket->status === SupportTicket::STATUS_CLOSED
                || $lockedTicket->transcript_deleted_at
                || ! $eligibility->allowed) {
                throw new AuthorizationException;
            }

            $canonical = $this->canonicalJson($normalizedPayload);
            $reference = Str::random(64);
            $preview = AiSupportActionPreview::query()->create([
                'id' => (string) Str::uuid(),
                'support_ticket_id' => $lockedTicket->id,
                'actor_user_id' => $actor->id,
                'capability_id' => $capabilityId,
                'tool_id' => $toolId,
                'tool_version' => $toolVersion,
                'preview_payload' => $normalizedPayload,
                'material_hash' => hash('sha256', $canonical),
                'confirmation_reference_hash' => hash('sha256', $reference),
                'expires_at' => $expiresAt,
                'created_at' => now(),
            ]);
            $this->events->record($lockedTicket, 'action_previewed', [
                'pilot_grant_id' => $eligibility->grantId,
                'capability_id' => $capabilityId,
                'tool_id' => $toolId,
                'tool_version' => $toolVersion,
                'result_code' => 'preview_created',
                'safe_metadata' => [
                    'confirmation_reference' => $preview->confirmation_reference_hash,
                    'policy_result' => 'authorized_and_bound',
                ],
            ], $actor);

            return ['preview' => $preview, 'confirmation_reference' => $reference];
        }, 3);
    }

    /**
     * The callback must perform its authoritative database write using the current
     * transaction and return only compact receipt references.
     *
     * @param  callable(array<string, mixed>): array{outcome_code:string,domain_reference_type:string,domain_reference_id:string,receipt_reference:string}  $commit
     */
    public function commitConfirmedAction(
        User $actor,
        string $confirmationReference,
        string $idempotencyKey,
        string $confirmationAction,
        callable $commit,
    ): AiSupportConfirmedActionEvidence {
        if (! Str::isUuid($idempotencyKey)) {
            throw ValidationException::withMessages(['idempotencyKey' => 'A valid idempotency key is required.']);
        }
        $this->ensureIdentifier($confirmationAction, 'confirmation action');

        return DB::transaction(function () use ($actor, $confirmationReference, $idempotencyKey, $confirmationAction, $commit): AiSupportConfirmedActionEvidence {
            $existing = AiSupportConfirmedActionEvidence::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                $sameActor = (int) $existing->actor_user_id === (int) $actor->id;
                $sameAction = hash_equals($existing->confirmation_action, $confirmationAction);
                $sameReference = hash_equals(
                    $existing->confirmation_reference_hash,
                    hash('sha256', $confirmationReference),
                );
                if (! $sameActor || ! $sameAction || ! $sameReference) {
                    throw new AuthorizationException;
                }

                return $existing;
            }

            $candidate = AiSupportActionPreview::query()
                ->where('confirmation_reference_hash', hash('sha256', $confirmationReference))
                ->first();

            if (! $candidate || (int) $candidate->actor_user_id !== (int) $actor->id) {
                throw new AuthorizationException;
            }

            $ticket = SupportTicket::query()->lockForUpdate()->find($candidate->support_ticket_id);
            $preview = AiSupportActionPreview::query()->lockForUpdate()->find($candidate->id);
            if (! $preview || (int) $preview->actor_user_id !== (int) $actor->id) {
                throw new AuthorizationException;
            }

            if ($preview->invalidated_at || $preview->content_deleted_at || ! $preview->expires_at->isFuture()) {
                throw ValidationException::withMessages(['confirmation' => 'This action preview is no longer valid.']);
            }

            if (! $ticket || $ticket->responder_mode !== SupportTicket::RESPONDER_MODE_AUTOMATED) {
                throw ValidationException::withMessages(['confirmation' => 'The conversation is now owned by LoLo Support.']);
            }
            $this->toolDefinition($preview->capability_id, $preview->tool_id, $preview->tool_version);
            $eligibility = $this->eligibility->evaluate($actor, $preview->capability_id, $ticket, $preview->tool_id);
            if (! $eligibility->allowed) {
                throw new AuthorizationException;
            }

            $receipt = $commit((array) $preview->preview_payload);
            foreach (['outcome_code', 'domain_reference_type', 'domain_reference_id', 'receipt_reference'] as $field) {
                if (! isset($receipt[$field]) || ! is_scalar($receipt[$field])) {
                    throw ValidationException::withMessages(['receipt' => 'The authoritative action receipt is incomplete.']);
                }
                $this->ensureIdentifier((string) $receipt[$field], 'receipt '.$field);
            }

            $now = now();
            $evidence = AiSupportConfirmedActionEvidence::query()->create([
                'id' => (string) Str::uuid(),
                'preview_id' => $preview->id,
                'support_ticket_id' => $ticket->id,
                'actor_user_id' => $actor->id,
                'pilot_grant_id' => $eligibility->grantId,
                'capability_id' => $preview->capability_id,
                'tool_id' => $preview->tool_id,
                'tool_version' => $preview->tool_version,
                'material_hash' => $preview->material_hash,
                'confirmation_reference_hash' => $preview->confirmation_reference_hash,
                'idempotency_key' => $idempotencyKey,
                'confirmation_action' => $confirmationAction,
                'policy_result' => 'authorized_and_bound',
                'outcome_code' => (string) $receipt['outcome_code'],
                'domain_reference_type' => (string) $receipt['domain_reference_type'],
                'domain_reference_id' => (string) $receipt['domain_reference_id'],
                'receipt_reference' => (string) $receipt['receipt_reference'],
                'confirmed_at' => $now,
                'committed_at' => $now,
                'retain_until' => $now->copy()->addMonths((int) config('ai_support.confirmed_action_months', 24)),
                'created_at' => $now,
            ]);
            $preview->forceFill([
                'preview_payload' => null,
                'invalidated_at' => $now,
                'invalidation_reason' => 'committed',
                'content_deleted_at' => $now,
            ])->save();
            $this->events->record($ticket, 'action_committed', [
                'pilot_grant_id' => $eligibility->grantId,
                'capability_id' => $preview->capability_id,
                'tool_id' => $preview->tool_id,
                'tool_version' => $preview->tool_version,
                'result_code' => (string) $receipt['outcome_code'],
                'safe_metadata' => [
                    'confirmation_reference' => $preview->confirmation_reference_hash,
                    'policy_result' => 'authorized_and_bound',
                ],
            ], $actor);

            return $evidence;
        }, 3);
    }

    public function invalidate(AiSupportActionPreview $preview, string $reason): void
    {
        $reason = trim($reason);
        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,119}\z/', $reason) !== 1) {
            throw ValidationException::withMessages(['reason' => 'Use a compact invalidation reason code.']);
        }

        $preview->forceFill([
            'preview_payload' => null,
            'invalidated_at' => now(),
            'invalidation_reason' => $reason,
            'content_deleted_at' => now(),
        ])->save();
    }

    /** @param array<string, mixed> $payload */
    private function canonicalJson(array $payload): string
    {
        $sort = function (mixed $value) use (&$sort): mixed {
            if (! is_array($value)) {
                return $value;
            }

            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map($sort, $value);
        };

        return json_encode($sort($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @return array{capability_id: string, versions: list<string>, preview_validity_minutes: int} */
    private function toolDefinition(string $capabilityId, string $toolId, string $toolVersion): array
    {
        $this->ensureIdentifier($capabilityId, 'capability ID');
        $this->ensureIdentifier($toolId, 'tool ID');
        $this->ensureIdentifier($toolVersion, 'tool version');
        $definition = ((array) config('ai_support.tools', []))[$toolId] ?? null;
        if (! is_array($definition)
            || ($definition['capability_id'] ?? null) !== $capabilityId
            || ! in_array($toolVersion, (array) ($definition['versions'] ?? []), true)
            || (int) ($definition['preview_validity_minutes'] ?? 0) < 1) {
            throw new AuthorizationException;
        }

        return $definition;
    }

    private function ensureIdentifier(string $value, string $label): void
    {
        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,119}\z/', $value) !== 1) {
            throw ValidationException::withMessages([
                'evidence' => "The {$label} must be a compact content-free identifier.",
            ]);
        }
    }
}
