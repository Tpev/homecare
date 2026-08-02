<?php

namespace App\Policies;

use App\Models\ContinuousCoveragePlan;
use App\Models\ContinuousCoverageRosterMember;
use App\Models\User;

class ContinuousCoveragePlanPolicy
{
    public function view(User $user, ContinuousCoveragePlan $plan): bool
    {
        return $user->isAdministrator()
            || ($user->role === 'family' && (int) $plan->family_user_id === (int) $user->id)
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
        return $user->role === 'family' && (int) $plan->family_user_id === (int) $user->id;
    }
}
