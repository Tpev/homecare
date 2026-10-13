<?php

namespace App\Services\Screening;

use App\Models\CareBooking;
use App\Models\CaregiverProfile;
use App\Models\CaregiverScreeningCase;
use App\Models\CarePlan;
use App\Models\ContinuousCoverageShift;
use App\Models\ScreeningBookingReview;
use Illuminate\Support\Facades\DB;

class ScreeningBookingReviewService
{
    public function flagExistingCommitments(CaregiverProfile $profile, CaregiverScreeningCase $case, ?int $actorId = null): void
    {
        // Current reads include work committed while suspension waited for the
        // profile lock, even under MySQL's REPEATABLE READ isolation level.
        CarePlan::where('caregiver_user_id', $profile->user_id)->whereNotNull('accepted_at')
            ->whereIn('status', ['active', 'paused', 'payment_attention'])
            ->lockForUpdate()
            ->each(fn ($plan) => $this->flag($case, 'care_plan', $plan->id, $actorId));
        ContinuousCoverageShift::where('assigned_caregiver_user_id', $profile->user_id)
            ->whereIn('status', ['confirmed', 'in_progress', 'payment_attention'])
            ->lockForUpdate()
            ->each(fn ($shift) => $this->flag($case, 'coverage_shift', $shift->id, $actorId));
        CareBooking::where('caregiver_user_id', $profile->user_id)
            ->whereIn('status', ['scheduled', 'in_progress', 'paused'])
            ->lockForUpdate()
            ->each(fn ($booking) => $this->flag($case, 'booking', $booking->id, $actorId));
    }

    public function flagBooking(CareBooking $booking): void
    {
        if (! in_array($booking->status, [CareBooking::STATUS_SCHEDULED, CareBooking::STATUS_IN_PROGRESS, CareBooking::STATUS_PAUSED], true)) {
            return;
        }
        $this->withSuspension((int) $booking->caregiver_user_id, fn ($case) => $this->flag($case, 'booking', $booking->id));
    }

    public function flagCoverageShift(ContinuousCoverageShift $shift): void
    {
        if (! in_array($shift->status, [ContinuousCoverageShift::STATUS_CONFIRMED, ContinuousCoverageShift::STATUS_IN_PROGRESS, ContinuousCoverageShift::STATUS_PAYMENT_ATTENTION], true)) {
            return;
        }
        if ($shift->assigned_caregiver_user_id) {
            $this->withSuspension((int) $shift->assigned_caregiver_user_id, fn ($case) => $this->flag($case, 'coverage_shift', $shift->id));
        }
    }

    private function withSuspension(int $userId, callable $callback): void
    {
        DB::transaction(function () use ($userId, $callback): void {
            $profile = CaregiverProfile::where('user_id', $userId)->lockForUpdate()->first();
            if (! $profile?->screening_suspended_at) {
                return;
            }
            $case = CaregiverScreeningCase::where('caregiver_profile_id', $profile->id)
                ->when($profile->screening_suspension_case_id,
                    fn ($query, $id) => $query->whereKey($id),
                    fn ($query) => $query->where('decision', 'suspend')->where('status', 'completed'))
                ->latest('id')->lockForUpdate()->first();
            if ($case) {
                $callback($case);
            }
        });
    }

    private function flag(CaregiverScreeningCase $case, string $type, int $id, ?int $actorId = null): void
    {
        $review = ScreeningBookingReview::query()->lockForUpdate()->firstOrCreate([
            'screening_case_id' => $case->id, 'subject_type' => $type, 'subject_id' => $id,
        ], ['reason' => 'Caregiver suspended from new work; review this existing commitment.', 'status' => 'open']);
        if ($review->wasRecentlyCreated) {
            app(ScreeningAuditService::class)->record($case->id, $actorId, 'booking_review_opened', [
                'review_id' => $review->id, 'subject_type' => $type, 'subject_id' => $id,
            ]);
        }
    }
}
