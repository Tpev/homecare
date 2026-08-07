<?php

namespace App\Policies;

use App\Models\ContinuousCoveragePlan;
use App\Models\ContinuousCoverageRosterMember;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;

class ContinuousCoveragePlanPolicy
{
    public function __construct(private readonly FamilyAccountContext $familyAccounts) {}

    public function view(User $user, ContinuousCoveragePlan $plan): bool
    {
        return $user->isAdministrator()
            || ($user->role === 'family' && $this->familyAccounts->canAccessRecord($user, $plan))
            || ($user->role === 'caregiver' && $plan->rosterMembers()
                ->where('caregiver_user_id', $user->id)
                ->whereIn('status', [
                    ContinuousCoverageRosterMember::STATUS_ACTIVE,
                    ContinuousCoverageRosterMember::STATUS_PAUSED,
                ])
                ->exists());
    }

    public function update(User $user, ContinuousCoveragePlan $plan): bool
    {
        return $user->role === 'family' && $this->familyAccounts->canAccessRecord($user, $plan);
    }
}
