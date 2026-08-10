<?php

namespace App\Services\CareRecipientProfiles;

use App\Models\CarePlan;
use App\Models\CareRecipientProfile;
use App\Models\CareRecipientProfileVersion;
use App\Models\CareRecipientProfileView;
use App\Models\CareRequest;
use App\Models\ContinuousCoveragePlan;
use App\Models\ContinuousCoverageRosterMember;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;

class CareRecipientProfilePresenter
{
    public function __construct(
        private readonly FamilyAccountContext $familyAccounts,
        private readonly CareRecipientProfileSnapshotBuilder $snapshots,
    ) {}

    /** @return array<string, mixed> */
    public function familyPreview(CareRecipientProfile $profile, bool $assigned = false): array
    {
        return $assigned ? $this->snapshots->assigned($profile) : $this->snapshots->candidate($profile);
    }

    /** @return array<string, mixed>|null */
    public function forCareRequest(User $viewer, CareRequest $request): ?array
    {
        $request->loadMissing(['recipient.careRecipientProfileVersion', 'booking']);
        $version = $request->recipient?->careRecipientProfileVersion;
        if (! $version) {
            return null;
        }

        if ($viewer->isAdministrator()
            || ($viewer->role === 'family' && $this->familyAccounts->canAccessRecord($viewer, $request))) {
            return $version->assigned_snapshot;
        }

        if ($viewer->role !== 'caregiver') {
            return null;
        }

        $booking = $request->booking;
        if ($booking
            && (int) $booking->caregiver_user_id === (int) $viewer->id
            && ! in_array($booking->status, ['cancelled', 'disputed'], true)) {
            return $this->assigned($viewer, $version);
        }

        if ($request->status === CareRequest::STATUS_OPEN && $viewer->caregiverProfile?->isMarketplaceReady()) {
            return $version->candidate_snapshot;
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public function forCarePlan(User $viewer, CarePlan $plan): ?array
    {
        $plan->loadMissing('careRecipientProfileVersion');
        $version = $plan->careRecipientProfileVersion;
        if (! $version) {
            return null;
        }

        if ($viewer->isAdministrator()
            || ($viewer->role === 'family' && $this->familyAccounts->canAccessRecord($viewer, $plan))) {
            return $version->assigned_snapshot;
        }
        if ($viewer->role !== 'caregiver' || (int) $plan->caregiver_user_id !== (int) $viewer->id) {
            return null;
        }

        if (in_array($plan->status, [CarePlan::STATUS_PENDING_CAREGIVER, CarePlan::STATUS_COUNTERED], true)) {
            return $version->candidate_snapshot;
        }
        if (in_array($plan->status, [CarePlan::STATUS_ACTIVE, CarePlan::STATUS_PAYMENT_ATTENTION, CarePlan::STATUS_PAUSED], true)) {
            return $this->assigned($viewer, $version);
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public function forCoveragePlan(User $viewer, ContinuousCoveragePlan $plan): ?array
    {
        $plan->loadMissing('careRecipientProfileVersion');
        $version = $plan->careRecipientProfileVersion;
        if (! $version) {
            return null;
        }

        if ($viewer->isAdministrator()
            || ($viewer->role === 'family' && $this->familyAccounts->canAccessRecord($viewer, $plan))) {
            return $version->assigned_snapshot;
        }
        if ($viewer->role !== 'caregiver') {
            return null;
        }

        $roster = $plan->rosterMembers()->where('caregiver_user_id', $viewer->id)->first();
        if ($roster && in_array($roster->status, [ContinuousCoverageRosterMember::STATUS_ACTIVE, ContinuousCoverageRosterMember::STATUS_PAUSED], true)) {
            return $this->assigned($viewer, $version);
        }
        if ($roster && in_array($roster->status, [
            ContinuousCoverageRosterMember::STATUS_INVITED,
            ContinuousCoverageRosterMember::STATUS_APPLIED,
            ContinuousCoverageRosterMember::STATUS_FAMILY_APPROVED,
        ], true)) {
            return $version->candidate_snapshot;
        }
        if ($roster) {
            return null;
        }
        if ($plan->status === ContinuousCoveragePlan::STATUS_ACTIVE
            && $plan->marketplace_applications_enabled
            && $viewer->caregiverProfile?->isMarketplaceReady()) {
            return $version->candidate_snapshot;
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function assigned(User $viewer, CareRecipientProfileVersion $version): array
    {
        $previous = CareRecipientProfileView::query()
            ->where('care_recipient_profile_version_id', $version->id)
            ->where('user_id', $viewer->id)
            ->first();
        $hasSeenEarlierVersion = $previous === null
            && CareRecipientProfileView::query()
                ->where('user_id', $viewer->id)
                ->whereHas('version', fn ($query) => $query
                    ->where('care_recipient_profile_id', $version->care_recipient_profile_id))
                ->exists();
        $snapshot = $version->assigned_snapshot;
        $snapshot['_is_updated'] = $hasSeenEarlierVersion;

        CareRecipientProfileView::query()->updateOrCreate(
            ['care_recipient_profile_version_id' => $version->id, 'user_id' => $viewer->id],
            ['viewed_at' => now()],
        );

        return $snapshot;
    }
}
