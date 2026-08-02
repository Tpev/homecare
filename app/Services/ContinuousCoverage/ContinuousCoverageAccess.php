<?php

namespace App\Services\ContinuousCoverage;

use App\Models\ContinuousCoverageRosterMember;
use App\Models\ContinuousCoverageShift;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ContinuousCoverageAccess
{
    public function enabled(): bool
    {
        return (bool) config('marketplace.continuous_coverage.enabled', false);
    }

    public function allows(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isAdministrator()) {
            // Keep authorized recovery access to already-created records even
            // while all new Continuous Coverage activity is disabled.
            return true;
        }

        if (! $this->enabled()) {
            return false;
        }

        $pilots = collect((array) config('marketplace.continuous_coverage.pilot_emails', []))
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter();

        if ($pilots->isEmpty() || $pilots->contains(strtolower((string) $user->email))) {
            return true;
        }

        if ($user->role !== 'caregiver') {
            return false;
        }

        if (ContinuousCoverageRosterMember::query()
            ->where('caregiver_user_id', $user->id)
            ->whereIn('status', [
                ContinuousCoverageRosterMember::STATUS_INVITED,
                ContinuousCoverageRosterMember::STATUS_APPLIED,
                ContinuousCoverageRosterMember::STATUS_FAMILY_APPROVED,
                ContinuousCoverageRosterMember::STATUS_ACTIVE,
                ContinuousCoverageRosterMember::STATUS_PAUSED,
            ])
            ->whereHas('plan.family', fn ($query) => $query->whereIn(DB::raw('LOWER(email)'), $pilots->all()))
            ->exists()) {
            return true;
        }

        return ContinuousCoverageShift::query()
            ->where(function ($query) use ($user): void {
                $query
                    ->where('assigned_caregiver_user_id', $user->id)
                    ->orWhere('released_by_user_id', $user->id)
                    ->orWhereHas('replacementCases', fn ($cases) => $cases
                        ->where('original_caregiver_user_id', $user->id))
                    ->orWhereHas('booking', fn ($booking) => $booking
                        ->where('caregiver_user_id', $user->id));
            })
            ->whereHas('plan.family', fn ($query) => $query->whereIn(DB::raw('LOWER(email)'), $pilots->all()))
            ->exists();
    }

    public function visibleInNavigation(?User $user): bool
    {
        return $this->enabled() && $this->allows($user);
    }
}
