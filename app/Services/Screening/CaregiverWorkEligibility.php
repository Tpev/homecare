<?php

namespace App\Services\Screening;

use App\Models\CareBooking;
use App\Models\CareBookingChangeRequest;
use App\Models\CaregiverProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class CaregiverWorkEligibility
{
    public const UNAVAILABLE_MESSAGE = 'This caregiver is currently unavailable for new work.';

    public function allowsNewWork(CaregiverProfile $profile): bool
    {
        return $profile->status === 'active'
            && ! $profile->screening_suspended_at
            && ((bool) $profile->screening_legacy_access || (bool) $profile->screening_initial_approved_at);
    }

    public function constrain(Builder $query): Builder
    {
        return $query->where('caregiver_profiles.status', 'active')
            ->whereNull('caregiver_profiles.screening_suspended_at')
            ->where(fn (Builder $query) => $query
                ->where('caregiver_profiles.screening_legacy_access', true)
                ->orWhereNotNull('caregiver_profiles.screening_initial_approved_at'));
    }

    /**
     * Call inside the transaction that creates or accepts new work. Locking the
     * same profile as the screening decision serializes hiring with suspension.
     * Recurring review dates and review outcomes deliberately have no role here.
     */
    public function assertCanStartNewWork(CaregiverProfile $profile): void
    {
        $current = CaregiverProfile::query()->lockForUpdate()->find($profile->getKey());
        if (! $current || ! $this->allowsNewWork($current)) {
            throw ValidationException::withMessages(['caregiver' => self::UNAVAILABLE_MESSAGE]);
        }
    }

    public function assertUserCanStartNewWork(int $caregiverUserId): void
    {
        $profile = CaregiverProfile::query()->where('user_id', $caregiverUserId)->lockForUpdate()->first();
        if (! $profile || ! $this->allowsNewWork($profile)) {
            throw ValidationException::withMessages(['caregiver' => self::UNAVAILABLE_MESSAGE]);
        }
    }

    /** Existing visits may be moved or shortened, but adding hours is new work. */
    public function assertBookingChangeAllowed(CareBooking $booking, CareBookingChangeRequest $change): void
    {
        $profile = CaregiverProfile::query()->where('user_id', $booking->caregiver_user_id)->lockForUpdate()->first();
        if ($change->type !== CareBookingChangeRequest::TYPE_RESCHEDULE) {
            return;
        }

        $current = CareBooking::query()->lockForUpdate()->findOrFail($booking->id);
        $currentSeconds = $current->scheduled_start_at && $current->scheduled_end_at
            ? $current->scheduled_start_at->diffInSeconds($current->scheduled_end_at, false) : 0;
        $proposedSeconds = $change->proposed_start_at && $change->proposed_end_at
            ? $change->proposed_start_at->diffInSeconds($change->proposed_end_at, false) : 0;

        if ($proposedSeconds > $currentSeconds && (! $profile || ! $this->allowsNewWork($profile))) {
            throw ValidationException::withMessages(['caregiver' => self::UNAVAILABLE_MESSAGE]);
        }
    }
}
