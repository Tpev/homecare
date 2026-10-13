<?php

namespace App\Services\Screening;

use App\Jobs\RunCaregiverScreening;
use App\Models\CaregiverProfile;
use App\Models\CaregiverScreeningCase;
use App\Models\ScreeningBookingReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CaregiverScreeningService
{
    public function __construct(private DiditVerifiedIdentityService $identity, private ScreeningAuditService $audit) {}

    public function enqueue(CaregiverProfile $profile, ?string $kind = null): CaregiverScreeningCase
    {
        return DB::transaction(function () use ($profile, $kind): CaregiverScreeningCase {
            $profile = CaregiverProfile::lockForUpdate()->findOrFail($profile->id);
            $case = CaregiverScreeningCase::where('open_profile_id', $profile->id)->lockForUpdate()->first();
            $identity = $this->identity->snapshot($profile);
            if ($case) {
                if ($case->status === 'awaiting_kyc' && $identity) {
                    $case->update(['status' => 'queued', 'identity_verification_id' => $identity['verification_id'],
                        'identity_snapshot' => $identity, 'lock_version' => $case->lock_version + 1]);
                    $this->audit->record($case->id, null, 'identity_ready', ['identity' => $identity]);
                    RunCaregiverScreening::dispatch($case->id)->afterCommit();
                }

                return $case;
            }
            $kind ??= $profile->screening_initial_approved_at ? 'recurring' : ($profile->screening_legacy_at ? 'legacy' : 'initial');
            $case = CaregiverScreeningCase::create([
                'caregiver_profile_id' => $profile->id, 'caregiver_user_id' => $profile->user_id,
                'open_profile_id' => $profile->id, 'kind' => $kind,
                'status' => $identity ? 'queued' : 'awaiting_kyc',
                'identity_verification_id' => $identity['verification_id'] ?? null,
                'identity_snapshot' => $identity,
                'due_at' => $kind === 'recurring' ? ($profile->screening_next_due_at ?: now()) : now(),
            ]);
            $this->audit->record($case->id, null, 'case_enqueued', [
                'caregiver_profile_id' => $profile->id, 'caregiver_user_id' => $profile->user_id,
                'kind' => $kind, 'legacy_access' => (bool) $profile->screening_legacy_access,
                'account_status' => $profile->status, 'identity' => $identity,
            ]);
            if ($identity) {
                RunCaregiverScreening::dispatch($case->id)->afterCommit();
            }

            return $case;
        }, 3);
    }

    public function sync(CaregiverProfile $profile): ?CaregiverScreeningCase
    {
        return DB::transaction(function () use ($profile): ?CaregiverScreeningCase {
            // A scheduler-loaded profile may predate an admin decision. Check the
            // refreshed due date under the same lock used to complete reviews.
            $profile = CaregiverProfile::lockForUpdate()->findOrFail($profile->id);
            if (CaregiverScreeningCase::where('open_profile_id', $profile->id)->lockForUpdate()->first(['id'])
                || ! CaregiverScreeningCase::where('caregiver_profile_id', $profile->id)->lockForUpdate()->first(['id'])) {
                return $this->enqueue($profile);
            }
            if ($profile->screening_next_due_at?->lte(now())) {
                return $this->enqueue($profile, 'recurring');
            }

            return null;
        }, 3);
    }

    public function claim(int $caseId, User $admin): CaregiverScreeningCase
    {
        $this->authorize($admin);

        return DB::transaction(function () use ($caseId, $admin): CaregiverScreeningCase {
            $case = CaregiverScreeningCase::lockForUpdate()->findOrFail($caseId);
            $this->assertOpen($case);
            if ($case->claimed_by && (int) $case->claimed_by !== $admin->id && $case->claimed_until?->isFuture()) {
                $this->fail('Another administrator is reviewing this case.');
            }
            $case->update(['claimed_by' => $admin->id, 'claimed_until' => now()->addMinutes(20),
                'status' => $case->status === 'awaiting_kyc' ? 'awaiting_kyc' : 'in_review',
                'lock_version' => $case->lock_version + 1]);
            $this->audit->record($case->id, $admin->id, 'case_claimed');

            return $case;
        }, 3);
    }

    public function search(int $caseId, User $admin): CaregiverScreeningCase
    {
        $this->authorize($admin);

        return $this->performSearch($caseId, $admin);
    }

    public function runSearch(int $caseId): CaregiverScreeningCase
    {
        return $this->performSearch($caseId, null);
    }

    private function performSearch(int $caseId, ?User $admin): CaregiverScreeningCase
    {
        $profileId = CaregiverScreeningCase::findOrFail($caseId)->caregiver_profile_id;

        return DB::transaction(function () use ($caseId, $admin, $profileId): CaregiverScreeningCase {
            // All decision/new-work paths lock the profile first.
            $profile = CaregiverProfile::lockForUpdate()->findOrFail($profileId);
            $case = CaregiverScreeningCase::lockForUpdate()->findOrFail($caseId);
            if (! $admin && ($case->status === 'completed' || $case->search_status === 'completed'
                || ($case->claimed_by && $case->claimed_until?->isFuture()))) {
                return $case;
            }
            $this->assertOpen($case);
            if ($admin) {
                $this->assertClaim($case, $admin);
            }
            $identity = $this->identity->snapshot($profile);
            if (! $identity) {
                $case->update(['status' => 'awaiting_kyc', 'search_status' => 'pending',
                    'search_error' => 'Confirmed successful Didit KYC and a verified legal name are required.',
                    'lock_version' => $case->lock_version + 1]);
                $this->audit->record($case->id, $admin?->id, 'search_waiting_for_identity');

                return $case;
            }
            try {
                $result = app(ScreeningMatcher::class)->search($identity);
            } catch (Throwable $error) {
                $case->update(['search_status' => 'error', 'search_error' => mb_substr($error->getMessage(), 0, 1000),
                    'lock_version' => $case->lock_version + 1]);
                $this->audit->record($case->id, $admin?->id, 'search_failed', ['reason' => $case->search_error]);

                return $case;
            }
            // Prior search evidence remains in the append-only audit event below.
            $case->candidates()->delete();
            foreach ($result['candidates'] as $candidate) {
                $case->candidates()->create($candidate);
            }
            $case->update(['identity_snapshot' => $identity, 'identity_verification_id' => $identity['verification_id'],
                'status' => $admin ? 'in_review' : 'queued', 'search_status' => 'completed',
                'search_error' => null, 'searched_at' => $result['searched_at'], 'source_versions' => $result['source_versions'],
                'lock_version' => $case->lock_version + 1]);
            $this->audit->record($case->id, $admin?->id, 'search_completed', [
                'identity' => $identity, 'source_versions' => $result['source_versions'],
                'source_coverage' => $result['source_coverage'] ?? [],
                'matching_version' => 1, 'candidates' => $result['candidates'],
            ]);

            return $case;
        }, 3);
    }

    public function decide(int $caseId, User $admin, string $outcome, string $decision, string $note, int $expectedVersion): CaregiverScreeningCase
    {
        $this->authorize($admin);
        if (! in_array($outcome, ['no_match', 'potential_match', 'match'], true)
            || ! in_array($decision, ['approve', 'suspend'], true)) {
            $this->fail('Choose a screening outcome and an account decision.');
        }
        $note = trim($note);
        if (mb_strlen($note) > 5000 || (($decision === 'suspend' || $outcome !== 'no_match') && mb_strlen($note) < 10)) {
            $this->fail('Provide a reason and supporting evidence (10–5,000 characters).');
        }

        $profileId = CaregiverScreeningCase::findOrFail($caseId)->caregiver_profile_id;

        return DB::transaction(function () use ($caseId, $admin, $outcome, $decision, $note, $expectedVersion, $profileId): CaregiverScreeningCase {
            $profile = CaregiverProfile::lockForUpdate()->findOrFail($profileId);
            $case = CaregiverScreeningCase::lockForUpdate()->findOrFail($caseId);
            $this->assertOpen($case);
            $this->assertClaim($case, $admin, $expectedVersion);
            $identity = $this->identity->snapshot($profile);
            if (! $identity || $identity !== $case->identity_snapshot || $case->search_status !== 'completed') {
                $this->fail('Complete a search using the current verified Didit identity before deciding.');
            }
            try {
                app(ScreeningMatcher::class)->assertUsableVersions($case->source_versions ?? []);
            } catch (\RuntimeException $error) {
                $this->fail($error->getMessage());
            }
            $hasCandidates = $case->candidates()->exists();
            if ($outcome !== 'no_match' && ! $hasCandidates) {
                $this->fail('A potential or confirmed match must reference a source candidate.');
            }
            if ($outcome === 'no_match' && $hasCandidates && mb_strlen($note) < 10) {
                $this->fail('Explain why the candidate records were ruled out.');
            }
            if ($decision === 'approve' && ! $profile->screening_legacy_access
                && collect($profile->marketplaceReadinessChecks())->except('screening')->contains(false)) {
                $this->fail('Complete the required caregiver profile information before approval.');
            }
            // Lifting a pre-rollout restriction is an explicit, explained admin decision.
            if ($decision === 'approve' && $profile->status === 'suspended' && ! $profile->screening_suspended_at) {
                if (mb_strlen($note) < 10) {
                    $this->fail('Explain why the existing account suspension is being lifted.');
                }
            }
            $before = $this->accessSnapshot($profile);
            $case->update(['status' => 'completed', 'open_profile_id' => null, 'outcome' => $outcome,
                'decision' => $decision, 'note' => $note, 'decided_by' => $admin->id, 'completed_at' => now(),
                'claimed_by' => null, 'claimed_until' => null, 'lock_version' => $case->lock_version + 1]);
            $changes = ['screening_next_due_at' => now()->addMonthsNoOverflow(6)];
            if ($decision === 'approve') {
                $changes += ['status' => 'active', 'screening_initial_approved_at' => $profile->screening_initial_approved_at ?: now(),
                    'screening_suspended_at' => null, 'screening_suspension_case_id' => null, 'reviewed_at' => now(), 'reviewed_by' => $admin->id,
                    'rejection_reason' => null, 'background_check_verified_at' => now()];
            } else {
                $changes += ['status' => 'suspended', 'screening_suspended_at' => now(), 'screening_suspension_case_id' => $case->id, 'background_check_verified_at' => null];
            }
            $profile->forceFill($changes)->save();
            $this->audit->record($case->id, $admin->id, 'screening_decided', [
                'caregiver_profile_id' => $profile->id, 'outcome' => $outcome, 'decision' => $decision,
                'note' => $note, 'before' => $before, 'after' => $this->accessSnapshot($profile),
                'identity' => $identity, 'source_versions' => $case->source_versions,
                'candidate_ids' => $case->candidates()->pluck('id')->all(),
            ]);
            if ($decision === 'suspend') {
                app(ScreeningBookingReviewService::class)->flagExistingCommitments($profile, $case, $admin->id);
            }

            return $case;
        }, 3);
    }

    public function defer(int $caseId, User $admin, string $note, int $expectedVersion): CaregiverScreeningCase
    {
        $this->authorize($admin);
        if (mb_strlen(trim($note)) < 5 || mb_strlen($note) > 5000) {
            $this->fail('Explain what needs investigation (5–5,000 characters).');
        }

        return DB::transaction(function () use ($caseId, $admin, $note, $expectedVersion): CaregiverScreeningCase {
            $case = CaregiverScreeningCase::lockForUpdate()->findOrFail($caseId);
            $this->assertOpen($case);
            $this->assertClaim($case, $admin, $expectedVersion);
            $case->update(['status' => $case->status === 'awaiting_kyc' ? 'awaiting_kyc' : 'awaiting_investigation',
                'note' => trim($note), 'claimed_by' => null, 'claimed_until' => null, 'lock_version' => $case->lock_version + 1]);
            $this->audit->record($case->id, $admin->id, 'case_deferred', ['note' => trim($note)]);

            return $case;
        }, 3);
    }

    public function suspendNewWork(int $caseId, User $admin, string $note, int $expectedVersion): CaregiverScreeningCase
    {
        $this->authorize($admin);
        if (mb_strlen(trim($note)) < 10 || mb_strlen($note) > 5000) {
            $this->fail('Explain why new work is being suspended (10–5,000 characters).');
        }
        $profileId = CaregiverScreeningCase::findOrFail($caseId)->caregiver_profile_id;

        return DB::transaction(function () use ($caseId, $admin, $note, $expectedVersion, $profileId): CaregiverScreeningCase {
            $profile = CaregiverProfile::lockForUpdate()->findOrFail($profileId);
            $case = CaregiverScreeningCase::lockForUpdate()->findOrFail($caseId);
            $this->assertOpen($case);
            $this->assertClaim($case, $admin, $expectedVersion);
            $before = $this->accessSnapshot($profile);
            $profile->forceFill(['status' => 'suspended', 'screening_suspended_at' => now(),
                'screening_suspension_case_id' => $case->id, 'background_check_verified_at' => null])->save();
            // Restrict work without pretending a search has completed or an identity matched.
            $case->update(['note' => trim($note), 'lock_version' => $case->lock_version + 1]);
            $this->audit->record($case->id, $admin->id, 'new_work_suspended', [
                'caregiver_profile_id' => $profile->id, 'note' => trim($note),
                'before' => $before, 'after' => $this->accessSnapshot($profile),
            ]);
            app(ScreeningBookingReviewService::class)->flagExistingCommitments($profile, $case, $admin->id);

            return $case;
        }, 3);
    }

    public function resolveBookingReview(int $reviewId, User $admin, string $resolution, string $note): void
    {
        $this->authorize($admin);
        if (! in_array($resolution, ['reviewed_no_change', 'action_taken'], true) || mb_strlen(trim($note)) < 5 || mb_strlen($note) > 5000) {
            $this->fail('Choose a resolution and describe the booking review (5–5,000 characters).');
        }
        $caseId = ScreeningBookingReview::findOrFail($reviewId)->screening_case_id;
        DB::transaction(function () use ($reviewId, $admin, $resolution, $note, $caseId): void {
            // Match suspension's case-before-review order, including the audit FK lock.
            CaregiverScreeningCase::lockForUpdate()->findOrFail($caseId);
            $review = ScreeningBookingReview::lockForUpdate()->findOrFail($reviewId);
            if ($review->status !== 'open') {
                $this->fail('This booking review has already been resolved.');
            }
            $review->update(['status' => 'resolved', 'resolution' => $resolution, 'note' => trim($note),
                'resolved_by' => $admin->id, 'resolved_at' => now()]);
            $this->audit->record($review->screening_case_id, $admin->id, 'booking_review_resolved', [
                'review_id' => $review->id, 'subject_type' => $review->subject_type, 'subject_id' => $review->subject_id,
                'resolution' => $resolution, 'note' => trim($note),
            ]);
        }, 3);
    }

    private function authorize(User $admin): void
    {
        abort_unless($admin->isAdministrator(), 403);
    }

    private function assertOpen(CaregiverScreeningCase $case): void
    {
        if ($case->status === 'completed' || ! $case->caregiver_profile_id) {
            $this->fail('This case is closed or its account no longer exists.');
        }
    }

    private function assertClaim(CaregiverScreeningCase $case, User $admin, ?int $version = null): void
    {
        if ((int) $case->claimed_by !== $admin->id || ! $case->claimed_until?->isFuture()) {
            $this->fail('Claim this case before reviewing it.');
        }
        if ($version !== null && $case->lock_version !== $version) {
            $this->fail('This case changed. Reload it before making a decision.');
        }
    }

    private function accessSnapshot(CaregiverProfile $profile): array
    {
        return ['status' => $profile->status, 'legacy_access' => (bool) $profile->screening_legacy_access,
            'initial_approved_at' => $profile->screening_initial_approved_at?->toIso8601String(),
            'suspended_at' => $profile->screening_suspended_at?->toIso8601String(),
            'next_due_at' => $profile->screening_next_due_at?->toIso8601String()];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['screening' => $message]);
    }
}
