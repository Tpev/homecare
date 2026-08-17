<?php

namespace App\Services\AiSupport;

use App\Models\AiSupportAdminAuditEvent;
use App\Models\AiSupportReleaseDecision;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AiSupportInitialPilotReleaseService
{
    /** @return list<int> */
    public function approvedUserIds(): array
    {
        return collect((array) config('ai_support.initial_pilot.approved_user_ids', []))
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function effectiveApproval(): ?AiSupportReleaseDecision
    {
        $decision = AiSupportReleaseDecision::query()
            ->current()
            ->where('scope', AiSupportReadinessService::SCOPE_INITIAL_PILOT)
            ->latest('created_at')
            ->first();

        if (! $decision?->isEffective()) {
            return null;
        }

        if ($decision->release_commit !== $this->currentReleaseCommit()) {
            return null;
        }

        $decisionIds = collect((array) $decision->approved_user_ids)->map(fn (mixed $id): int => (int) $id)->sort()->values()->all();

        return $decisionIds === $this->approvedUserIds() ? $decision : null;
    }

    public function assertEffectiveApproval(): AiSupportReleaseDecision
    {
        $decision = $this->effectiveApproval();
        if (! $decision) {
            throw ValidationException::withMessages([
                'releaseDecision' => 'Record an effective explicit DEC-070 initial-pilot release approval before opening controls or creating a grant.',
            ]);
        }

        return $decision;
    }

    public function approve(
        User $actor,
        string $reason,
        string $releaseCommit,
        AiSupportReadinessService $readiness,
        AiSupportControlService $controls,
    ): AiSupportReleaseDecision {
        if (! $actor->canManageAiSupportControls()) {
            throw new AuthorizationException;
        }
        if (! (bool) config('ai_support.initial_pilot.enforced', true)) {
            throw ValidationException::withMessages(['releaseDecision' => 'The enforced DEC-070 initial-pilot policy must be on.']);
        }

        $reason = trim($reason);
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => 'Enter a content-free release reason between 10 and 500 characters.']);
        }
        $releaseCommit = strtolower(trim($releaseCommit));
        if (preg_match('/\A[0-9a-f]{40}\z/', $releaseCommit) !== 1) {
            throw ValidationException::withMessages(['releaseCommit' => 'Provide the exact 40-character deployed release commit.']);
        }
        if ($this->currentReleaseCommit() !== $releaseCommit) {
            throw ValidationException::withMessages(['releaseCommit' => 'Release commit must equal the exact current deployed HEAD.']);
        }

        $snapshot = $readiness->snapshot($controls, AiSupportReadinessService::SCOPE_INITIAL_PILOT);
        if (! $snapshot['ready']) {
            throw ValidationException::withMessages(['releaseDecision' => 'Initial-pilot preflight is not ready for explicit approval.']);
        }

        $approvedIds = $this->approvedUserIds();
        if ($approvedIds !== [19, 282]) {
            throw ValidationException::withMessages(['releaseDecision' => 'The exact DEC-070 user boundary is not configured.']);
        }

        $now = CarbonImmutable::now();
        $expiresAt = CarbonImmutable::parse((string) config('ai_support.initial_pilot.expires_on'))->endOfDay();
        if (! $expiresAt->isFuture()) {
            throw ValidationException::withMessages(['releaseDecision' => 'The accepted initial-pilot window has expired.']);
        }

        $safeSnapshot = [
            'scope' => $snapshot['scope'],
            'policy_version' => $snapshot['policy_version'],
            'checks' => collect($snapshot['checks'])->map(fn (array $check): array => [
                'id' => $check['id'],
                'state' => $check['state'],
                'satisfied' => $check['satisfied'],
            ])->all(),
            'open_incidents' => $snapshot['open_incidents'],
            'open_warnings' => $snapshot['open_warnings'],
            'deferred_count' => $snapshot['deferred_count'],
        ];
        $snapshotHash = hash('sha256', json_encode($safeSnapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return DB::transaction(function () use (
            $actor,
            $reason,
            $releaseCommit,
            $snapshot,
            $snapshotHash,
            $approvedIds,
            $now,
            $expiresAt,
        ): AiSupportReleaseDecision {
            $current = AiSupportReleaseDecision::query()
                ->where('scope', AiSupportReadinessService::SCOPE_INITIAL_PILOT)
                ->whereNull('superseded_at')
                ->lockForUpdate()
                ->latest('created_at')
                ->first();
            if ($current?->isEffective()
                && $current->release_commit === $releaseCommit
                && $current->snapshot_sha256 === $snapshotHash) {
                return $current;
            }
            if ($current) {
                $current->forceFill(['superseded_at' => $now])->save();
            }

            $decision = AiSupportReleaseDecision::query()->create([
                'id' => (string) Str::uuid(),
                'scope' => AiSupportReadinessService::SCOPE_INITIAL_PILOT,
                'policy_version' => $snapshot['policy_version'],
                'status' => AiSupportReleaseDecision::STATUS_APPROVED,
                'decided_by_user_id' => $actor->id,
                'reason' => $reason,
                'release_commit' => $releaseCommit,
                'snapshot_sha256' => $snapshotHash,
                'approved_user_ids' => $approvedIds,
                'starts_at' => $now,
                'expires_at' => $expiresAt,
                'retain_until' => $expiresAt->addMonths((int) config('ai_support.readiness_evidence_months', 24)),
                'created_at' => $now,
            ]);

            AiSupportAdminAuditEvent::query()->create([
                'id' => (string) Str::uuid(),
                'event_family' => 'readiness',
                'action' => 'initial_pilot_release_approved',
                'actor_user_id' => $actor->id,
                'subject_type' => AiSupportReleaseDecision::class,
                'subject_id' => $decision->id,
                'result' => 'succeeded',
                'reason_code' => 'explicit_decision',
                'reason' => $reason,
                'metadata' => [
                    'scope' => $decision->scope,
                    'policy_version' => $decision->policy_version,
                    'release_commit' => $releaseCommit,
                    'snapshot_sha256' => $snapshotHash,
                    'approved_user_ids' => $approvedIds,
                    'expires_at' => $expiresAt->toIso8601String(),
                ],
                'policy_version' => (string) config('ai_support.policy_version'),
                'retain_until' => $expiresAt->addMonths((int) config('ai_support.readiness_evidence_months', 24)),
                'occurred_at' => $now,
                'created_at' => $now,
            ]);

            return $decision;
        }, 3);
    }

    private function currentReleaseCommit(): ?string
    {
        $metadataCommit = $this->releaseCommitFromGitMetadata();
        if ($metadataCommit) {
            return $metadataCommit;
        }

        $head = Process::path(base_path())->timeout(10)->run(['git', 'rev-parse', 'HEAD']);

        if (! $head->successful()) {
            return null;
        }

        $commit = strtolower(trim($head->output()));

        return preg_match('/\A[0-9a-f]{40}\z/', $commit) === 1 ? $commit : null;
    }

    private function releaseCommitFromGitMetadata(): ?string
    {
        $gitPath = base_path('.git');
        if (is_file($gitPath)) {
            $pointer = trim((string) @file_get_contents($gitPath));
            if (! str_starts_with($pointer, 'gitdir:')) {
                return null;
            }

            $gitDirectory = trim(substr($pointer, strlen('gitdir:')));
            if (! $this->isAbsolutePath($gitDirectory)) {
                $gitDirectory = base_path($gitDirectory);
            }
        } else {
            $gitDirectory = $gitPath;
        }

        if (! is_dir($gitDirectory)) {
            return null;
        }

        $head = trim((string) @file_get_contents($gitDirectory.DIRECTORY_SEPARATOR.'HEAD'));
        if ($this->isCommitHash($head)) {
            return strtolower($head);
        }
        if (! str_starts_with($head, 'ref:')) {
            return null;
        }

        $reference = trim(substr($head, strlen('ref:')));
        if (! str_starts_with($reference, 'refs/')
            || str_contains($reference, '..')
            || preg_match('/\Arefs\/[A-Za-z0-9._\/-]+\z/', $reference) !== 1) {
            return null;
        }

        $looseReference = $gitDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $reference);
        $commit = trim((string) @file_get_contents($looseReference));
        if ($this->isCommitHash($commit)) {
            return strtolower($commit);
        }

        $packedReferences = @file($gitDirectory.DIRECTORY_SEPARATOR.'packed-refs', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (! is_array($packedReferences)) {
            return null;
        }

        foreach ($packedReferences as $line) {
            if (str_starts_with($line, '#') || str_starts_with($line, '^')) {
                continue;
            }

            [$packedCommit, $packedReference] = array_pad(preg_split('/\s+/', trim($line), 2) ?: [], 2, null);
            if ($packedReference === $reference && is_string($packedCommit) && $this->isCommitHash($packedCommit)) {
                return strtolower($packedCommit);
            }
        }

        return null;
    }

    private function isCommitHash(string $value): bool
    {
        return preg_match('/\A[0-9a-f]{40}\z/i', $value) === 1;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/\A[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}
