<?php

namespace App\Policies;

use App\Models\CareRequest;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Support\CaregiverPrelaunch;

class CareRequestPolicy
{
    public function __construct(private readonly FamilyAccountContext $familyAccounts) {}

    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'family', 'caregiver'], true);
    }

    public function view(User $user, CareRequest $careRequest): bool
    {
        if ($user->role === 'family' && $this->familyAccounts->canAccessRecord($user, $careRequest)) {
            return true;
        }

        if ($user->role === 'caregiver') {
            if ($careRequest->status === CareRequest::STATUS_OPEN
                && CareRequest::query()->whereKey($careRequest->id)->visibleToCaregiver($user)->exists()) {
                return true;
            }

            return $careRequest->applications()
                ->where('caregiver_user_id', $user->id)
                ->exists();
        }

        return $user->isAdministrator();
    }

    public function create(User $user): bool
    {
        return $user->role === 'family';
    }

    public function update(User $user, CareRequest $careRequest): bool
    {
        return $user->role === 'family' && $this->familyAccounts->canAccessRecord($user, $careRequest)
            && in_array($careRequest->status, [CareRequest::STATUS_DRAFT, CareRequest::STATUS_OPEN], true);
    }

    public function delete(User $user, CareRequest $careRequest): bool
    {
        return $user->role === 'family' && $this->familyAccounts->canAccessRecord($user, $careRequest)
            && $careRequest->status === CareRequest::STATUS_DRAFT;
    }

    public function restore(User $user, CareRequest $careRequest): bool
    {
        return false;
    }

    public function forceDelete(User $user, CareRequest $careRequest): bool
    {
        return false;
    }

    public function manageApplicants(User $user, CareRequest $careRequest): bool
    {
        return $user->role === 'family' && $this->familyAccounts->canAccessRecord($user, $careRequest);
    }

    public function apply(User $user, CareRequest $careRequest): bool
    {
        $profile = $user->caregiverProfile;

        return $user->role === 'caregiver'
            && ! CaregiverPrelaunch::enabled()
            && $careRequest->status === CareRequest::STATUS_OPEN
            && $profile?->status === 'active'
            && $profile->isMarketplaceReady()
            && CareRequest::query()->whereKey($careRequest->id)->visibleToCaregiver($user)->exists();
    }
}
