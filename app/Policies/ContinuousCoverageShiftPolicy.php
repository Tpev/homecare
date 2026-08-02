<?php

namespace App\Policies;

use App\Models\ContinuousCoverageShift;
use App\Models\User;

class ContinuousCoverageShiftPolicy
{
    public function view(User $user, ContinuousCoverageShift $shift): bool
    {
        return $user->isAdministrator()
            || ($user->role === 'family' && (int) $shift->plan()->value('family_user_id') === (int) $user->id)
            || ($user->role === 'caregiver' && (
                (int) $shift->assigned_caregiver_user_id === (int) $user->id
                || (int) $shift->released_by_user_id === (int) $user->id
                || $shift->replacementCases()->where('original_caregiver_user_id', $user->id)->exists()
                || $shift->offers()->where('caregiver_user_id', $user->id)->exists()
                || $shift->booking()->where('caregiver_user_id', $user->id)->exists()
            ));
    }

    public function release(User $user, ContinuousCoverageShift $shift): bool
    {
        return $user->role === 'caregiver'
            && (int) $shift->assigned_caregiver_user_id === (int) $user->id;
    }
}
