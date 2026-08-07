<?php

namespace App\Services\ContinuousCoverage;

use App\Models\CareBooking;
use App\Models\ContinuousCoverageLaneRequest;
use App\Models\ContinuousCoveragePlan;
use App\Models\ContinuousCoverageRosterMember;
use App\Models\ContinuousCoverageShift;
use App\Models\ContinuousCoverageShiftOffer;
use App\Models\ContinuousCoverageShiftTemplate;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ContinuousCoveragePlanLifecycleService
{
    public function __construct(
        private readonly ContinuousCoverageEventRecorder $events,
        private readonly ContinuousCoverageNotificationService $notifications,
    ) {}

    public function deletionBlocker(ContinuousCoveragePlan $plan): ?string
    {
        if ($plan->shifts()->whereNotNull('care_booking_id')->exists()) {
            return 'This plan has a prepared or historical visit. End coverage instead so its care and billing records remain available.';
        }

        if ($plan->shifts()->whereIn('status', [
            ContinuousCoverageShift::STATUS_IN_PROGRESS,
            ContinuousCoverageShift::STATUS_COMPLETED,
        ])->exists()) {
            return 'This plan contains care activity that must remain in history. End coverage instead.';
        }

        if ($plan->shifts()->where(function ($query): void {
            $query->whereNotNull('released_at')
                ->orWhereHas('handoffs')
                ->orWhere(function ($past): void {
                    $past->where('scheduled_end_at', '<', now())
                        ->whereNotNull('assigned_caregiver_user_id')
                        ->where('status', '!=', ContinuousCoverageShift::STATUS_CANCELLED);
                });
        })->exists()) {
            return 'This plan has caregiver activity or past commitments that must remain in history. End coverage instead.';
        }

        return null;
    }

    public function deleteUnbilledPlan(ContinuousCoveragePlan $plan, User $family): void
    {
        $this->assertFamilyOwns($plan, $family);
        $plan->loadMissing('family');
        $caregivers = $this->affectedCaregivers($plan);

        DB::transaction(function () use ($plan): void {
            $lockedPlan = ContinuousCoveragePlan::query()->lockForUpdate()->findOrFail($plan->id);
            $blocker = $this->deletionBlocker($lockedPlan);
            if ($blocker) {
                throw ValidationException::withMessages(['planLifecycle' => $blocker]);
            }

            $lockedPlan->delete();
        });

        $this->notifyCaregivers($plan, $caregivers, deleted: true);
    }

    public function endPlan(ContinuousCoveragePlan $plan, User $family): ContinuousCoveragePlan
    {
        $this->assertFamilyOwns($plan, $family);
        if ($plan->status === ContinuousCoveragePlan::STATUS_ENDED) {
            return $plan;
        }

        $plan->loadMissing('family');
        $caregivers = $this->affectedCaregivers($plan);
        $ended = DB::transaction(function () use ($plan, $family): ContinuousCoveragePlan {
            $lockedPlan = ContinuousCoveragePlan::query()->lockForUpdate()->findOrFail($plan->id);
            if ($lockedPlan->status === ContinuousCoveragePlan::STATUS_ENDED) {
                return $lockedPlan;
            }

            $activePreparedVisitExists = $lockedPlan->shifts()
                ->whereNotNull('care_booking_id')
                ->whereHas('booking', fn ($query) => $query->whereIn('status', [
                    CareBooking::STATUS_SCHEDULED,
                    CareBooking::STATUS_IN_PROGRESS,
                    CareBooking::STATUS_PAUSED,
                ]))
                ->exists();
            if ($activePreparedVisitExists) {
                throw ValidationException::withMessages([
                    'planLifecycle' => 'A prepared visit is still active. Cancel or finish that visit through its normal visit workflow before ending coverage.',
                ]);
            }

            ContinuousCoverageShiftOffer::query()
                ->whereIn('continuous_coverage_shift_id', $lockedPlan->shifts()->select('id'))
                ->where('status', ContinuousCoverageShiftOffer::STATUS_PENDING)
                ->update([
                    'status' => ContinuousCoverageShiftOffer::STATUS_CLOSED,
                    'responded_at' => now(),
                    'updated_at' => now(),
                ]);

            $lockedPlan->shifts()
                ->whereNotNull('care_booking_id')
                ->whereHas('booking', fn ($query) => $query->where('status', CareBooking::STATUS_CANCELLED))
                ->whereNotIn('status', [
                    ContinuousCoverageShift::STATUS_COMPLETED,
                    ContinuousCoverageShift::STATUS_CANCELLED,
                ])
                ->update([
                    'status' => ContinuousCoverageShift::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'updated_at' => now(),
                ]);

            $futureUnpreparedShiftCount = $lockedPlan->shifts()
                ->whereNull('care_booking_id')
                ->where('scheduled_start_at', '>=', now())
                ->count();
            $lockedPlan->shifts()
                ->whereNull('care_booking_id')
                ->where('scheduled_start_at', '>=', now())
                ->delete();

            $today = now($lockedPlan->timezone)->toDateString();
            $lockedPlan->templates()
                ->where('status', '!=', ContinuousCoverageShiftTemplate::STATUS_SUPERSEDED)
                ->update([
                    'status' => ContinuousCoverageShiftTemplate::STATUS_SUPERSEDED,
                    'effective_until' => $today,
                    'offer_expires_at' => null,
                    'updated_at' => now(),
                ]);
            $lockedPlan->laneRequests()
                ->where('status', ContinuousCoverageLaneRequest::STATUS_PENDING)
                ->update([
                    'status' => ContinuousCoverageLaneRequest::STATUS_UNAVAILABLE,
                    'responded_at' => now(),
                    'responded_by_user_id' => $family->id,
                    'updated_at' => now(),
                ]);
            $lockedPlan->rosterMembers()
                ->where('status', '!=', ContinuousCoverageRosterMember::STATUS_REMOVED)
                ->update([
                    'status' => ContinuousCoverageRosterMember::STATUS_REMOVED,
                    'replacement_opt_in' => false,
                    'removed_at' => now(),
                    'updated_at' => now(),
                ]);

            $lockedPlan->forceFill([
                'status' => ContinuousCoveragePlan::STATUS_ENDED,
                'ends_on' => $today,
                'marketplace_applications_enabled' => false,
                'metadata' => array_merge((array) $lockedPlan->metadata, [
                    'ended_at' => now()->toIso8601String(),
                    'ended_by_user_id' => $family->id,
                ]),
            ])->save();

            $this->events->record($lockedPlan, 'plan_ended', $family, payload: [
                'future_unprepared_shifts_removed' => $futureUnpreparedShiftCount,
            ]);

            return $lockedPlan->fresh();
        });

        $this->notifyCaregivers($ended, $caregivers, deleted: false);

        return $ended;
    }

    /** @return Collection<int, User> */
    private function affectedCaregivers(ContinuousCoveragePlan $plan): Collection
    {
        $ids = $plan->rosterMembers()->pluck('caregiver_user_id')
            ->merge($plan->shifts()->whereNotNull('assigned_caregiver_user_id')->pluck('assigned_caregiver_user_id'))
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        return User::query()->whereIn('id', $ids)->get();
    }

    /** @param Collection<int, User> $caregivers */
    private function notifyCaregivers(ContinuousCoveragePlan $plan, Collection $caregivers, bool $deleted): void
    {
        try {
            $this->notifications->planEnded($plan, $caregivers, $deleted);
        } catch (\Throwable $exception) {
            Log::warning('Continuous Coverage plan lifecycle notification failed.', [
                'continuous_coverage_plan_id' => $plan->id,
                'deleted' => $deleted,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function assertFamilyOwns(ContinuousCoveragePlan $plan, User $family): void
    {
        abort_unless($family->role === 'family' && app(FamilyAccountContext::class)->canAccessRecord($family, $plan), 403);
    }
}
