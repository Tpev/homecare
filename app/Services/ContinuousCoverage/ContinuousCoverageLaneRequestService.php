<?php

namespace App\Services\ContinuousCoverage;

use App\Models\CareBooking;
use App\Models\ContinuousCoverageLaneRequest;
use App\Models\ContinuousCoveragePlan;
use App\Models\ContinuousCoverageRosterMember;
use App\Models\ContinuousCoverageShift;
use App\Models\ContinuousCoverageShiftTemplate;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ContinuousCoverageLaneRequestService
{
    /** @var list<string> */
    private const OPEN_TEMPLATE_STATUSES = [
        ContinuousCoverageShiftTemplate::STATUS_UNCOVERED,
        ContinuousCoverageShiftTemplate::STATUS_DECLINED,
        ContinuousCoverageShiftTemplate::STATUS_EXPIRED,
    ];

    public function __construct(
        private readonly ContinuousCoverageScheduleService $schedule,
        private readonly ContinuousCoverageRosterService $roster,
        private readonly ContinuousCoverageNotificationService $notifications,
        private readonly ContinuousCoverageEventRecorder $events,
        private readonly ContinuousCoverageAccess $access,
    ) {}

    /**
     * @param  list<int|string>  $templateIds
     * @return Collection<int, ContinuousCoverageLaneRequest>
     */
    public function request(ContinuousCoveragePlan $plan, User $caregiver, array $templateIds): Collection
    {
        $this->assertEnabledFor($caregiver);
        if ($plan->status !== ContinuousCoveragePlan::STATUS_ACTIVE
            || ($plan->ends_on && $plan->ends_on->lt(now($plan->timezone)->startOfDay()))) {
            throw ValidationException::withMessages(['laneRequestSelections' => 'This Continuous Coverage plan is no longer active.']);
        }
        $ids = collect($templateIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty() || $ids->count() > 42) {
            throw ValidationException::withMessages(['laneRequestSelections' => 'Choose one or more available recurring lanes.']);
        }

        $member = $plan->rosterMembers()
            ->where('caregiver_user_id', $caregiver->id)
            ->where('status', ContinuousCoverageRosterMember::STATUS_ACTIVE)
            ->first();
        if (! $member?->isActive()) {
            throw ValidationException::withMessages(['laneRequestSelections' => 'Join this family-approved care team before requesting coverage.']);
        }

        $batchUuid = (string) Str::uuid();
        [$requests, $changedIds] = DB::transaction(function () use ($plan, $caregiver, $member, $ids, $batchUuid): array {
            $lockedMember = ContinuousCoverageRosterMember::query()->lockForUpdate()->findOrFail($member->id);
            if (! $lockedMember->isActive() || (int) $lockedMember->caregiver_user_id !== (int) $caregiver->id) {
                throw ValidationException::withMessages(['laneRequestSelections' => 'This care-team membership is no longer active.']);
            }

            $templates = ContinuousCoverageShiftTemplate::query()
                ->lockForUpdate()
                ->where('continuous_coverage_plan_id', $plan->id)
                ->whereIn('id', $ids)
                ->get()
                ->keyBy('id');
            if ($templates->count() !== $ids->count()) {
                throw ValidationException::withMessages(['laneRequestSelections' => 'One or more selected lanes are no longer available.']);
            }

            $requests = collect();
            $changedIds = [];
            foreach ($ids as $id) {
                $template = $templates->get($id);
                if (! in_array($template->status, self::OPEN_TEMPLATE_STATUSES, true)
                    || ($template->effective_until && $template->effective_until->lt(now($plan->timezone)->startOfDay()))
                    || ! $this->roster->matchesTemplateEligibility($lockedMember, $template)) {
                    throw ValidationException::withMessages(['laneRequestSelections' => 'One or more selected lanes are outside your approved options or no longer available.']);
                }
                if ($this->hasScheduleConflict($lockedMember, $template)) {
                    throw ValidationException::withMessages(['laneRequestSelections' => 'One or more selected lanes conflict with an existing confirmed visit.']);
                }

                $request = ContinuousCoverageLaneRequest::query()
                    ->lockForUpdate()
                    ->where('shift_template_id', $template->id)
                    ->where('roster_member_id', $lockedMember->id)
                    ->first();
                if (! $request) {
                    $request = new ContinuousCoverageLaneRequest([
                        'continuous_coverage_plan_id' => $plan->id,
                        'shift_template_id' => $template->id,
                        'roster_member_id' => $lockedMember->id,
                        'caregiver_user_id' => $caregiver->id,
                    ]);
                }
                if ($request->status !== ContinuousCoverageLaneRequest::STATUS_PENDING) {
                    $request->forceFill([
                        'batch_uuid' => $batchUuid,
                        'status' => ContinuousCoverageLaneRequest::STATUS_PENDING,
                        'requested_at' => now(),
                        'responded_at' => null,
                        'responded_by_user_id' => null,
                    ])->save();
                    $changedIds[] = $request->id;
                    $this->events->record($plan, 'recurring_lane_requested', $caregiver, payload: [
                        'lane_request_id' => $request->id,
                        'shift_template_id' => $template->id,
                        'roster_member_id' => $lockedMember->id,
                        'batch_uuid' => $batchUuid,
                    ]);
                }
                $requests->push($request);
            }

            return [$requests, $changedIds];
        });

        if ($changedIds !== []) {
            $this->notifications->laneRequested(
                ContinuousCoverageLaneRequest::query()->whereIn('id', $changedIds)->get(),
            );
        }

        return $requests->map->fresh(['plan.family', 'template', 'rosterMember.caregiver']);
    }

    public function approve(ContinuousCoverageLaneRequest $request, User $family): bool
    {
        $this->assertEnabledFor($family);
        $request->loadMissing('plan.family');
        $this->assertFamilyOwns($request->plan, $family);

        [$approved, $approvedRequestId, $notSelectedIds] = DB::transaction(function () use ($request, $family): array {
            $lockedRequest = ContinuousCoverageLaneRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($lockedRequest->status !== ContinuousCoverageLaneRequest::STATUS_PENDING) {
                return [false, $lockedRequest->id, []];
            }

            $template = ContinuousCoverageShiftTemplate::query()->lockForUpdate()->findOrFail($lockedRequest->shift_template_id);
            $member = ContinuousCoverageRosterMember::query()->lockForUpdate()->findOrFail($lockedRequest->roster_member_id);
            User::query()->lockForUpdate()->findOrFail($lockedRequest->caregiver_user_id);

            if ($request->plan->status !== ContinuousCoveragePlan::STATUS_ACTIVE
                || ($request->plan->ends_on && $request->plan->ends_on->lt(now($request->plan->timezone)->startOfDay()))
                || ! in_array($template->status, self::OPEN_TEMPLATE_STATUSES, true)) {
                $this->closeRequest($lockedRequest, ContinuousCoverageLaneRequest::STATUS_NOT_SELECTED, $family);

                return [false, $lockedRequest->id, []];
            }
            if (! $member->isActive()
                || (int) $member->caregiver_user_id !== (int) $lockedRequest->caregiver_user_id
                || (int) $member->continuous_coverage_plan_id !== (int) $template->continuous_coverage_plan_id
                || ! $this->roster->matchesTemplateEligibility($member, $template)
                || $this->hasScheduleConflict($member, $template)) {
                $this->closeRequest($lockedRequest, ContinuousCoverageLaneRequest::STATUS_UNAVAILABLE, $family);

                return [false, $lockedRequest->id, []];
            }

            $acceptedAt = now();
            $template->forceFill([
                'roster_member_id' => $member->id,
                'status' => ContinuousCoverageShiftTemplate::STATUS_ACTIVE,
                'offered_at' => $lockedRequest->requested_at,
                'offer_expires_at' => null,
                'accepted_at' => $acceptedAt,
                'declined_at' => null,
            ])->save();
            $this->closeRequest($lockedRequest, ContinuousCoverageLaneRequest::STATUS_APPROVED, $family, $acceptedAt);

            $notSelectedIds = ContinuousCoverageLaneRequest::query()
                ->where('shift_template_id', $template->id)
                ->where('status', ContinuousCoverageLaneRequest::STATUS_PENDING)
                ->where('id', '!=', $lockedRequest->id)
                ->pluck('id')
                ->all();
            if ($notSelectedIds !== []) {
                ContinuousCoverageLaneRequest::query()->whereKey($notSelectedIds)->update([
                    'status' => ContinuousCoverageLaneRequest::STATUS_NOT_SELECTED,
                    'responded_at' => $acceptedAt,
                    'responded_by_user_id' => $family->id,
                    'updated_at' => $acceptedAt,
                ]);
            }

            $this->schedule->activateTemplate($template);
            $this->events->record($request->plan, 'recurring_lane_request_approved', $family, payload: [
                'lane_request_id' => $lockedRequest->id,
                'shift_template_id' => $template->id,
                'roster_member_id' => $member->id,
                'caregiver_user_id' => $member->caregiver_user_id,
                'not_selected_request_ids' => $notSelectedIds,
            ]);

            return [true, $lockedRequest->id, $notSelectedIds];
        });

        $approvedRequest = ContinuousCoverageLaneRequest::query()->findOrFail($approvedRequestId);
        if ($approved) {
            $this->notifications->laneRequestApproved($approvedRequest);
            if ($notSelectedIds !== []) {
                ContinuousCoverageLaneRequest::query()->whereIn('id', $notSelectedIds)->get()
                    ->each(fn (ContinuousCoverageLaneRequest $other) => $this->notifications->laneRequestNotSelected($other));
            }
        } elseif ($approvedRequest->status === ContinuousCoverageLaneRequest::STATUS_UNAVAILABLE) {
            $this->notifications->laneRequestUnavailable($approvedRequest);
        } elseif ($approvedRequest->status === ContinuousCoverageLaneRequest::STATUS_NOT_SELECTED) {
            $this->notifications->laneRequestNotSelected($approvedRequest);
        }

        return $approved;
    }

    public function decline(ContinuousCoverageLaneRequest $request, User $family): void
    {
        $this->assertEnabledFor($family);
        $request->loadMissing('plan.family');
        $this->assertFamilyOwns($request->plan, $family);

        $declined = DB::transaction(function () use ($request, $family): ContinuousCoverageLaneRequest {
            $locked = ContinuousCoverageLaneRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($locked->status !== ContinuousCoverageLaneRequest::STATUS_PENDING) {
                throw ValidationException::withMessages(['laneRequest' => 'This lane request is no longer waiting for a decision.']);
            }
            $this->closeRequest($locked, ContinuousCoverageLaneRequest::STATUS_DECLINED, $family);
            $this->events->record($request->plan, 'recurring_lane_request_declined', $family, payload: [
                'lane_request_id' => $locked->id,
                'shift_template_id' => $locked->shift_template_id,
                'caregiver_user_id' => $locked->caregiver_user_id,
            ]);

            return $locked->fresh();
        });
        $this->notifications->laneRequestDeclined($declined);
    }

    public function withdraw(ContinuousCoverageLaneRequest $request, User $caregiver): void
    {
        $this->assertEnabledFor($caregiver);
        $withdrawn = DB::transaction(function () use ($request, $caregiver): ContinuousCoverageLaneRequest {
            $locked = ContinuousCoverageLaneRequest::query()->lockForUpdate()->with('plan')->findOrFail($request->id);
            if ((int) $locked->caregiver_user_id !== (int) $caregiver->id
                || $locked->status !== ContinuousCoverageLaneRequest::STATUS_PENDING) {
                throw ValidationException::withMessages(['laneRequest' => 'This lane request can no longer be withdrawn.']);
            }
            $this->closeRequest($locked, ContinuousCoverageLaneRequest::STATUS_WITHDRAWN, $caregiver);
            $this->events->record($locked->plan, 'recurring_lane_request_withdrawn', $caregiver, payload: [
                'lane_request_id' => $locked->id,
                'shift_template_id' => $locked->shift_template_id,
            ]);

            return $locked->fresh();
        });
        $this->notifications->laneRequestWithdrawn($withdrawn);
    }

    public function profileAvailabilityMatchesTemplate(ContinuousCoverageRosterMember $member, ContinuousCoverageShiftTemplate $template): bool
    {
        $member->loadMissing('caregiver.caregiverProfile.availabilities');
        $availability = $member->caregiver?->caregiverProfile?->availabilities;
        if (! $availability || $availability->isEmpty()) {
            return true;
        }

        $weekMinutes = 7 * 1440;
        $intervals = [];
        foreach ($availability as $slot) {
            $slotStartTime = $this->timeToMinutes((string) $slot->start_time);
            $slotEndTime = $this->timeToMinutes((string) $slot->end_time);
            $slotStart = ((int) $slot->day_of_week * 1440) + $slotStartTime;
            $slotEnd = ((int) $slot->day_of_week * 1440) + $slotEndTime;
            if ($slotEndTime <= $slotStartTime) {
                $slotEnd += 1440;
            }
            foreach ([-$weekMinutes, 0, $weekMinutes] as $offset) {
                $intervals[] = [$slotStart + $offset, $slotEnd + $offset];
            }
        }

        usort($intervals, fn (array $left, array $right): int => $left[0] <=> $right[0]);
        $merged = [];
        foreach ($intervals as [$start, $end]) {
            $last = array_key_last($merged);
            if ($last === null || $start > $merged[$last][1] + 1) {
                $merged[] = [$start, $end];
            } else {
                $merged[$last][1] = max($merged[$last][1], $end);
            }
        }

        $templateStart = ((int) $template->day_of_week * 1440) + $this->timeToMinutes((string) $template->starts_at);
        $templateEnd = $templateStart + (int) $template->duration_minutes;

        return collect($merged)->contains(
            fn (array $interval): bool => $interval[0] <= $templateStart && $interval[1] >= $templateEnd,
        );
    }

    private function hasScheduleConflict(ContinuousCoverageRosterMember $member, ContinuousCoverageShiftTemplate $template): bool
    {
        $candidateShifts = $template->shifts()
            ->where('scheduled_start_at', '>=', now())
            ->whereNull('care_booking_id')
            ->get(['id', 'scheduled_start_at', 'scheduled_end_at']);

        foreach ($candidateShifts as $candidate) {
            if (ContinuousCoverageShift::query()
                ->where('shift_template_id', '!=', $template->id)
                ->where('assigned_caregiver_user_id', $member->caregiver_user_id)
                ->whereIn('status', [
                    ContinuousCoverageShift::STATUS_CONFIRMED,
                    ContinuousCoverageShift::STATUS_IN_PROGRESS,
                    ContinuousCoverageShift::STATUS_PAYMENT_ATTENTION,
                ])
                ->where('scheduled_start_at', '<', $candidate->scheduled_end_at)
                ->where('scheduled_end_at', '>', $candidate->scheduled_start_at)
                ->exists()) {
                return true;
            }

            if (CareBooking::query()
                ->where('caregiver_user_id', $member->caregiver_user_id)
                ->whereIn('status', [CareBooking::STATUS_SCHEDULED, CareBooking::STATUS_IN_PROGRESS, CareBooking::STATUS_PAUSED])
                ->where('scheduled_start_at', '<', $candidate->scheduled_end_at)
                ->where('scheduled_end_at', '>', $candidate->scheduled_start_at)
                ->exists()) {
                return true;
            }
        }

        return false;
    }

    private function closeRequest(
        ContinuousCoverageLaneRequest $request,
        string $status,
        User $actor,
        mixed $at = null,
    ): void {
        $request->forceFill([
            'status' => $status,
            'responded_at' => $at ?: now(),
            'responded_by_user_id' => $actor->id,
        ])->save();
    }

    private function assertFamilyOwns(ContinuousCoveragePlan $plan, User $family): void
    {
        abort_unless($family->role === 'family' && (int) $plan->family_user_id === (int) $family->id, 403);
    }

    private function assertEnabledFor(User $user): void
    {
        if (! $this->access->allows($user)) {
            throw ValidationException::withMessages(['coverage' => 'Continuous Coverage is not currently available for this account.']);
        }
    }

    private function timeToMinutes(string $value): int
    {
        return ((int) substr($value, 0, 2) * 60) + (int) substr($value, 3, 2);
    }
}
