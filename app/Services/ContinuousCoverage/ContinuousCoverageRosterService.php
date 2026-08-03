<?php

namespace App\Services\ContinuousCoverage;

use App\Models\ContinuousCoverageLaneRequest;
use App\Models\ContinuousCoveragePlan;
use App\Models\ContinuousCoverageRosterMember;
use App\Models\ContinuousCoverageShift;
use App\Models\ContinuousCoverageShiftTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContinuousCoverageRosterService
{
    /** @var list<string> */
    private const ELIGIBLE_SHIFT_TYPES = ['daytime', 'overnight', '6_hour', '8_hour', '12_hour'];

    public function __construct(
        private readonly ContinuousCoverageScheduleService $schedule,
        private readonly ContinuousCoverageNotificationService $notifications,
        private readonly ContinuousCoverageEventRecorder $events,
        private readonly ContinuousCoverageAccess $access,
    ) {}

    /**
     * @param  list<int>  $eligibleDays
     * @param  list<string>  $eligibleShiftTypes
     */
    public function familyApprove(
        ContinuousCoveragePlan $plan,
        User $family,
        User $caregiver,
        string $role = ContinuousCoverageRosterMember::ROLE_BACKUP,
        bool $replacementOptIn = true,
        array $eligibleDays = [],
        array $eligibleShiftTypes = [],
    ): ContinuousCoverageRosterMember {
        $this->assertFamilyOwns($plan, $family);
        $this->assertEnabledFor($family);
        $caregiver->loadMissing('caregiverProfile');
        if ($caregiver->role !== 'caregiver' || $caregiver->caregiverProfile?->status !== 'active') {
            throw ValidationException::withMessages(['caregiver' => 'Choose an active caregiver account.']);
        }

        [$normalizedRole, $normalizedDays, $normalizedShiftTypes] = $this->normalizePreferences(
            $role,
            $eligibleDays,
            $eligibleShiftTypes,
        );
        $existing = ContinuousCoverageRosterMember::query()
            ->where('continuous_coverage_plan_id', $plan->id)
            ->where('caregiver_user_id', $caregiver->id)
            ->first();
        $wasApplication = $existing?->status === ContinuousCoverageRosterMember::STATUS_APPLIED;
        $preserveAcceptedMembership = $existing
            && in_array($existing->status, [
                ContinuousCoverageRosterMember::STATUS_ACTIVE,
                ContinuousCoverageRosterMember::STATUS_PAUSED,
            ], true)
            && $existing->caregiver_accepted_at;

        $member = ContinuousCoverageRosterMember::query()->updateOrCreate(
            [
                'continuous_coverage_plan_id' => $plan->id,
                'caregiver_user_id' => $caregiver->id,
            ],
            [
                'invited_by_user_id' => $family->id,
                'status' => $preserveAcceptedMembership
                    ? $existing->status
                    : ContinuousCoverageRosterMember::STATUS_FAMILY_APPROVED,
                'role' => $normalizedRole,
                'replacement_opt_in' => $replacementOptIn,
                'eligible_days' => $normalizedDays,
                'eligible_shift_types' => $normalizedShiftTypes,
                'family_approved_at' => now(),
                'caregiver_accepted_at' => $preserveAcceptedMembership ? $existing->caregiver_accepted_at : null,
                'paused_at' => $preserveAcceptedMembership ? $existing->paused_at : null,
                'removed_at' => null,
            ],
        );

        $this->events->record($plan, 'caregiver_family_approved', $family, payload: [
            'caregiver_user_id' => $caregiver->id,
            'roster_member_id' => $member->id,
            'role' => $member->role,
        ]);
        if (! $preserveAcceptedMembership) {
            if ($wasApplication) {
                $this->notifications->applicantApproved($member);
            } else {
                $this->notifications->teamInvitation($member);
            }
        }

        return $member->fresh(['plan', 'caregiver']);
    }

    public function apply(ContinuousCoveragePlan $plan, User $caregiver): ContinuousCoverageRosterMember
    {
        $this->assertEnabledFor($caregiver);
        $plan->loadMissing('family');
        if (! $this->access->allows($plan->family)) {
            throw ValidationException::withMessages(['application' => 'This coverage plan is not accepting applications.']);
        }
        $caregiver->loadMissing('caregiverProfile');
        if ($caregiver->role !== 'caregiver' || $caregiver->caregiverProfile?->status !== 'active') {
            throw ValidationException::withMessages(['application' => 'Only an active caregiver profile can apply.']);
        }
        if ($plan->status !== ContinuousCoveragePlan::STATUS_ACTIVE || ! $plan->marketplace_applications_enabled) {
            throw ValidationException::withMessages(['application' => 'This coverage plan is not accepting applications.']);
        }

        $existing = $plan->rosterMembers()
            ->where('caregiver_user_id', $caregiver->id)
            ->first();
        if ($existing) {
            throw ValidationException::withMessages(['application' => 'You already have a care-team decision for this coverage plan.']);
        }

        $member = DB::transaction(function () use ($plan, $caregiver): ContinuousCoverageRosterMember {
            $lockedPlan = ContinuousCoveragePlan::query()->lockForUpdate()->findOrFail($plan->id);
            if ($lockedPlan->status !== ContinuousCoveragePlan::STATUS_ACTIVE || ! $lockedPlan->marketplace_applications_enabled) {
                throw ValidationException::withMessages(['application' => 'This coverage plan is not accepting applications.']);
            }

            $member = ContinuousCoverageRosterMember::query()->firstOrCreate(
                [
                    'continuous_coverage_plan_id' => $lockedPlan->id,
                    'caregiver_user_id' => $caregiver->id,
                ],
                [
                    'status' => ContinuousCoverageRosterMember::STATUS_APPLIED,
                    'role' => ContinuousCoverageRosterMember::ROLE_BACKUP,
                    'replacement_opt_in' => false,
                    'eligible_days' => range(0, 6),
                    'eligible_shift_types' => [],
                ],
            );
            if (! $member->wasRecentlyCreated) {
                throw ValidationException::withMessages(['application' => 'You already have a care-team decision for this coverage plan.']);
            }

            $this->events->record($lockedPlan, 'coverage_application_received', $caregiver, payload: [
                'roster_member_id' => $member->id,
                'caregiver_user_id' => $caregiver->id,
            ]);

            return $member->fresh(['plan.family', 'caregiver']);
        });

        $this->notifications->applicationReceived($member);

        return $member;
    }

    public function declineApplicant(ContinuousCoverageRosterMember $member, User $family): ContinuousCoverageRosterMember
    {
        $member->loadMissing('plan');
        $this->assertFamilyOwns($member->plan, $family);
        $this->assertEnabledFor($family);
        if ($member->status !== ContinuousCoverageRosterMember::STATUS_APPLIED) {
            throw ValidationException::withMessages(['application' => 'This application is no longer waiting for a decision.']);
        }

        $member->forceFill([
            'status' => ContinuousCoverageRosterMember::STATUS_REMOVED,
            'removed_at' => now(),
        ])->save();
        $this->events->record($member->plan, 'coverage_application_declined', $family, payload: [
            'roster_member_id' => $member->id,
            'caregiver_user_id' => $member->caregiver_user_id,
        ]);

        return $member->fresh();
    }

    public function setMarketplaceApplications(
        ContinuousCoveragePlan $plan,
        User $family,
        bool $enabled,
    ): ContinuousCoveragePlan {
        $this->assertFamilyOwns($plan, $family);
        $this->assertEnabledFor($family);
        if ($plan->status !== ContinuousCoveragePlan::STATUS_ACTIVE) {
            throw ValidationException::withMessages(['applications' => 'Only an active coverage plan can accept applications.']);
        }

        $plan->forceFill(['marketplace_applications_enabled' => $enabled])->save();
        $this->events->record($plan, 'marketplace_applications_changed', $family, payload: [
            'enabled' => $enabled,
        ]);

        return $plan->fresh();
    }

    /**
     * Update only future-offer eligibility. Existing shifts, bookings, payments, and
     * historical assignments deliberately remain unchanged.
     *
     * @param  list<int>  $eligibleDays
     * @param  list<string>  $eligibleShiftTypes
     */
    public function updatePreferences(
        ContinuousCoverageRosterMember $member,
        User $family,
        string $role,
        bool $replacementOptIn,
        array $eligibleDays,
        array $eligibleShiftTypes,
    ): ContinuousCoverageRosterMember {
        $member->loadMissing('plan');
        $this->assertFamilyOwns($member->plan, $family);
        $this->assertEnabledFor($family);
        if (! in_array($member->status, [
            ContinuousCoverageRosterMember::STATUS_FAMILY_APPROVED,
            ContinuousCoverageRosterMember::STATUS_ACTIVE,
            ContinuousCoverageRosterMember::STATUS_PAUSED,
        ], true)) {
            throw ValidationException::withMessages(['member' => 'This care-team membership can no longer be changed.']);
        }

        [$normalizedRole, $normalizedDays, $normalizedShiftTypes] = $this->normalizePreferences(
            $role,
            $eligibleDays,
            $eligibleShiftTypes,
        );
        $before = [
            'role' => $member->role,
            'replacement_opt_in' => $member->replacement_opt_in,
            'eligible_days' => $member->eligible_days,
            'eligible_shift_types' => $member->eligible_shift_types,
        ];

        $member->forceFill([
            'role' => $normalizedRole,
            'replacement_opt_in' => $replacementOptIn,
            'eligible_days' => $normalizedDays,
            'eligible_shift_types' => $normalizedShiftTypes,
        ])->save();

        $this->events->record($member->plan, 'caregiver_eligibility_updated', $family, payload: [
            'roster_member_id' => $member->id,
            'caregiver_user_id' => $member->caregiver_user_id,
            'before' => $before,
            'after' => [
                'role' => $member->role,
                'replacement_opt_in' => $member->replacement_opt_in,
                'eligible_days' => $member->eligible_days,
                'eligible_shift_types' => $member->eligible_shift_types,
            ],
        ]);

        return $member->fresh(['plan', 'caregiver']);
    }

    public function caregiverAccept(ContinuousCoverageRosterMember $member, User $caregiver): ContinuousCoverageRosterMember
    {
        $this->assertEnabledFor($caregiver);
        $accepted = DB::transaction(function () use ($member, $caregiver): ContinuousCoverageRosterMember {
            $locked = ContinuousCoverageRosterMember::query()
                ->lockForUpdate()
                ->with('plan')
                ->findOrFail($member->id);
            if ((int) $locked->caregiver_user_id !== (int) $caregiver->id
                || $locked->status !== ContinuousCoverageRosterMember::STATUS_FAMILY_APPROVED) {
                throw ValidationException::withMessages(['invitation' => 'This care-team invitation is no longer available.']);
            }

            $locked->forceFill([
                'status' => ContinuousCoverageRosterMember::STATUS_ACTIVE,
                'caregiver_accepted_at' => now(),
            ])->save();
            $this->events->record($locked->plan, 'caregiver_joined_team', $caregiver, payload: ['roster_member_id' => $locked->id]);

            return $locked->fresh(['plan', 'caregiver']);
        });
        $this->notifications->teamAccepted($accepted);

        return $accepted;
    }

    public function caregiverDecline(ContinuousCoverageRosterMember $member, User $caregiver): ContinuousCoverageRosterMember
    {
        $this->assertEnabledFor($caregiver);

        return DB::transaction(function () use ($member, $caregiver): ContinuousCoverageRosterMember {
            $locked = ContinuousCoverageRosterMember::query()
                ->lockForUpdate()
                ->with('plan')
                ->findOrFail($member->id);
            if ((int) $locked->caregiver_user_id !== (int) $caregiver->id
                || $locked->status !== ContinuousCoverageRosterMember::STATUS_FAMILY_APPROVED) {
                throw ValidationException::withMessages(['invitation' => 'This care-team invitation is no longer available.']);
            }
            $locked->forceFill([
                'status' => ContinuousCoverageRosterMember::STATUS_REMOVED,
                'removed_at' => now(),
            ])->save();
            $this->events->record($locked->plan, 'caregiver_declined_team', $caregiver, payload: [
                'roster_member_id' => $locked->id,
            ]);

            return $locked->fresh();
        });
    }

    public function offerLane(
        ContinuousCoverageShiftTemplate $template,
        ContinuousCoverageRosterMember $member,
        User $family,
    ): ContinuousCoverageShiftTemplate {
        $this->assertEnabledFor($family);
        $offered = DB::transaction(function () use ($template, $member, $family): ContinuousCoverageShiftTemplate {
            $lockedTemplate = ContinuousCoverageShiftTemplate::query()
                ->lockForUpdate()
                ->with('plan')
                ->findOrFail($template->id);
            $lockedMember = ContinuousCoverageRosterMember::query()
                ->lockForUpdate()
                ->findOrFail($member->id);
            $this->assertFamilyOwns($lockedTemplate->plan, $family);
            if (! in_array($lockedTemplate->status, [
                ContinuousCoverageShiftTemplate::STATUS_UNCOVERED,
                ContinuousCoverageShiftTemplate::STATUS_DECLINED,
                ContinuousCoverageShiftTemplate::STATUS_EXPIRED,
            ], true)) {
                throw ValidationException::withMessages(['lane' => 'This recurring lane already has an active or pending decision.']);
            }
            if ((int) $lockedMember->continuous_coverage_plan_id !== (int) $lockedTemplate->continuous_coverage_plan_id
                || ! $lockedMember->isActive()) {
                throw ValidationException::withMessages(['caregiver' => 'Choose a caregiver who accepted this family-approved care team.']);
            }
            if (! $this->matchesTemplateEligibility($lockedMember, $lockedTemplate)) {
                throw ValidationException::withMessages(['caregiver' => 'This lane is outside the days or shift types approved for this caregiver.']);
            }

            $lockedTemplate->forceFill([
                'roster_member_id' => $lockedMember->id,
                'status' => ContinuousCoverageShiftTemplate::STATUS_OFFERED,
                'offered_at' => now(),
                'offer_expires_at' => now()->addHours((int) config('marketplace.continuous_coverage.lane_offer_expires_hours', 72)),
                'accepted_at' => null,
                'declined_at' => null,
            ])->save();
            $lockedTemplate->shifts()
                ->where('scheduled_start_at', '>=', now())
                ->whereNull('care_booking_id')
                ->where('status', ContinuousCoverageShift::STATUS_UNCOVERED)
                ->update(['status' => ContinuousCoverageShift::STATUS_OFFER_PENDING, 'updated_at' => now()]);
            $this->events->record($lockedTemplate->plan, 'recurring_lane_offered', $family, payload: [
                'shift_template_id' => $lockedTemplate->id,
                'roster_member_id' => $lockedMember->id,
            ]);

            return $lockedTemplate->fresh(['rosterMember.caregiver', 'plan']);
        });
        $this->notifications->laneOffered($offered);

        return $offered;
    }

    public function acceptLane(ContinuousCoverageShiftTemplate $template, User $caregiver): ContinuousCoverageShiftTemplate
    {
        $this->assertEnabledFor($caregiver);
        $approvedRequestId = null;
        $notSelectedRequestIds = [];
        $accepted = DB::transaction(function () use ($template, $caregiver, &$approvedRequestId, &$notSelectedRequestIds): ContinuousCoverageShiftTemplate {
            $locked = ContinuousCoverageShiftTemplate::query()->lockForUpdate()->with('rosterMember')->findOrFail($template->id);
            if ($locked->status !== ContinuousCoverageShiftTemplate::STATUS_OFFERED
                || ($locked->offer_expires_at && $locked->offer_expires_at->isPast())
                || (int) $locked->rosterMember?->caregiver_user_id !== (int) $caregiver->id
                || ! $locked->rosterMember?->isActive()
                || ! $this->matchesTemplateEligibility($locked->rosterMember, $locked)) {
                throw ValidationException::withMessages(['lane' => 'This recurring coverage offer is no longer available.']);
            }

            $locked->forceFill([
                'status' => ContinuousCoverageShiftTemplate::STATUS_ACTIVE,
                'accepted_at' => now(),
                'offer_expires_at' => null,
                'declined_at' => null,
            ])->save();

            $respondedAt = now();
            $approvedRequestId = ContinuousCoverageLaneRequest::query()
                ->where('shift_template_id', $locked->id)
                ->where('caregiver_user_id', $caregiver->id)
                ->where('status', ContinuousCoverageLaneRequest::STATUS_PENDING)
                ->value('id');
            if ($approvedRequestId) {
                ContinuousCoverageLaneRequest::query()->whereKey($approvedRequestId)->update([
                    'status' => ContinuousCoverageLaneRequest::STATUS_APPROVED,
                    'responded_at' => $respondedAt,
                    'responded_by_user_id' => $locked->plan->family_user_id,
                    'updated_at' => $respondedAt,
                ]);
            }
            $notSelectedRequestIds = ContinuousCoverageLaneRequest::query()
                ->where('shift_template_id', $locked->id)
                ->where('status', ContinuousCoverageLaneRequest::STATUS_PENDING)
                ->pluck('id')
                ->all();
            if ($notSelectedRequestIds !== []) {
                ContinuousCoverageLaneRequest::query()->whereKey($notSelectedRequestIds)->update([
                    'status' => ContinuousCoverageLaneRequest::STATUS_NOT_SELECTED,
                    'responded_at' => $respondedAt,
                    'responded_by_user_id' => $locked->plan->family_user_id,
                    'updated_at' => $respondedAt,
                ]);
            }
            $this->schedule->activateTemplate($locked);
            $this->events->record($locked->plan, 'recurring_lane_accepted', $caregiver, payload: ['shift_template_id' => $locked->id]);

            return $locked->fresh(['rosterMember.caregiver']);
        });

        $this->notifications->laneResponded($accepted, true);
        if ($approvedRequestId) {
            $this->notifications->laneRequestApproved(ContinuousCoverageLaneRequest::query()->findOrFail($approvedRequestId));
        }
        ContinuousCoverageLaneRequest::query()->whereIn('id', $notSelectedRequestIds)->get()
            ->each(fn (ContinuousCoverageLaneRequest $request) => $this->notifications->laneRequestNotSelected($request));

        return $accepted;
    }

    public function declineLane(ContinuousCoverageShiftTemplate $template, User $caregiver): ContinuousCoverageShiftTemplate
    {
        $this->assertEnabledFor($caregiver);
        $declined = DB::transaction(function () use ($template, $caregiver): ContinuousCoverageShiftTemplate {
            $locked = ContinuousCoverageShiftTemplate::query()->lockForUpdate()->with('rosterMember')->findOrFail($template->id);
            if ($locked->status !== ContinuousCoverageShiftTemplate::STATUS_OFFERED
                || ($locked->offer_expires_at && $locked->offer_expires_at->isPast())
                || (int) $locked->rosterMember?->caregiver_user_id !== (int) $caregiver->id) {
                throw ValidationException::withMessages(['lane' => 'This recurring coverage offer is no longer available.']);
            }
            $respondingMember = $locked->rosterMember;
            $locked->forceFill([
                'status' => ContinuousCoverageShiftTemplate::STATUS_DECLINED,
                'declined_at' => now(),
                'offer_expires_at' => null,
            ])->save();
            $locked->shifts()
                ->where('scheduled_start_at', '>=', now())
                ->whereNull('care_booking_id')
                ->update(['status' => 'uncovered', 'assigned_caregiver_user_id' => null, 'updated_at' => now()]);
            $this->events->record($locked->plan, 'recurring_lane_declined', $caregiver, payload: ['shift_template_id' => $locked->id]);
            $locked->setRelation('rosterMember', $respondingMember);
            $locked->forceFill(['roster_member_id' => null])->save();

            return $locked->fresh()->setRelation('rosterMember', $respondingMember);
        });

        $this->notifications->laneResponded($declined, false);

        return $declined->fresh();
    }

    public function pause(ContinuousCoverageRosterMember $member, User $family): void
    {
        $this->assertEnabledFor($family);
        $unavailableRequestIds = DB::transaction(function () use ($member, $family): array {
            $locked = ContinuousCoverageRosterMember::query()
                ->lockForUpdate()
                ->with('plan')
                ->findOrFail($member->id);
            $this->assertFamilyOwns($locked->plan, $family);
            if ($locked->status !== ContinuousCoverageRosterMember::STATUS_ACTIVE) {
                throw ValidationException::withMessages(['member' => 'Only an active care-team member can be paused.']);
            }
            $locked->forceFill([
                'status' => ContinuousCoverageRosterMember::STATUS_PAUSED,
                'paused_at' => now(),
            ])->save();
            $this->events->record($locked->plan, 'caregiver_paused', $family, payload: [
                'roster_member_id' => $locked->id,
            ]);

            return $this->markPendingRequestsUnavailable($locked->id, $family->id);
        });
        $this->notifyUnavailableRequests($unavailableRequestIds);
    }

    public function resume(ContinuousCoverageRosterMember $member, User $family): void
    {
        $this->assertEnabledFor($family);
        DB::transaction(function () use ($member, $family): void {
            $locked = ContinuousCoverageRosterMember::query()
                ->lockForUpdate()
                ->with('plan')
                ->findOrFail($member->id);
            $this->assertFamilyOwns($locked->plan, $family);
            if ($locked->status !== ContinuousCoverageRosterMember::STATUS_PAUSED) {
                throw ValidationException::withMessages(['member' => 'Only a paused care-team member can be resumed.']);
            }
            $locked->forceFill([
                'status' => ContinuousCoverageRosterMember::STATUS_ACTIVE,
                'paused_at' => null,
            ])->save();
            $this->events->record($locked->plan, 'caregiver_resumed', $family, payload: [
                'roster_member_id' => $locked->id,
            ]);
        });
    }

    public function remove(ContinuousCoverageRosterMember $member, User $family): void
    {
        $this->assertEnabledFor($family);
        $unavailableRequestIds = DB::transaction(function () use ($member, $family): array {
            $locked = ContinuousCoverageRosterMember::query()
                ->lockForUpdate()
                ->with('plan')
                ->findOrFail($member->id);
            $this->assertFamilyOwns($locked->plan, $family);
            if ($locked->status === ContinuousCoverageRosterMember::STATUS_REMOVED) {
                throw ValidationException::withMessages(['member' => 'This caregiver is already removed from future offers.']);
            }
            $locked->forceFill([
                'status' => ContinuousCoverageRosterMember::STATUS_REMOVED,
                'removed_at' => now(),
            ])->save();
            $this->events->record($locked->plan, 'caregiver_removed', $family, payload: [
                'roster_member_id' => $locked->id,
            ]);

            return $this->markPendingRequestsUnavailable($locked->id, $family->id);
        });
        $this->notifyUnavailableRequests($unavailableRequestIds);
    }

    /** @return list<int> */
    private function markPendingRequestsUnavailable(int $memberId, int $responderId): array
    {
        $ids = ContinuousCoverageLaneRequest::query()
            ->where('roster_member_id', $memberId)
            ->where('status', ContinuousCoverageLaneRequest::STATUS_PENDING)
            ->pluck('id')
            ->all();
        if ($ids !== []) {
            ContinuousCoverageLaneRequest::query()->whereKey($ids)->update([
                'status' => ContinuousCoverageLaneRequest::STATUS_UNAVAILABLE,
                'responded_at' => now(),
                'responded_by_user_id' => $responderId,
                'updated_at' => now(),
            ]);
        }

        return $ids;
    }

    /** @param list<int> $requestIds */
    private function notifyUnavailableRequests(array $requestIds): void
    {
        ContinuousCoverageLaneRequest::query()->whereIn('id', $requestIds)->get()
            ->each(fn (ContinuousCoverageLaneRequest $request) => $this->notifications->laneRequestUnavailable($request));
    }

    private function assertFamilyOwns(ContinuousCoveragePlan $plan, User $family): void
    {
        abort_unless($family->role === 'family' && (int) $plan->family_user_id === (int) $family->id, 403);
        if ($plan->status !== ContinuousCoveragePlan::STATUS_ACTIVE) {
            throw ValidationException::withMessages(['coverage' => 'This Continuous Coverage plan has ended and can no longer be changed.']);
        }
    }

    /**
     * @param  list<int>  $eligibleDays
     * @param  list<string>  $eligibleShiftTypes
     * @return array{0:string,1:list<int>,2:list<string>}
     */
    private function normalizePreferences(string $role, array $eligibleDays, array $eligibleShiftTypes): array
    {
        if (! in_array($role, [ContinuousCoverageRosterMember::ROLE_PRIMARY, ContinuousCoverageRosterMember::ROLE_BACKUP], true)) {
            throw ValidationException::withMessages(['role' => 'Choose primary or backup.']);
        }

        $days = array_values(array_unique(array_map('intval', $eligibleDays === [] ? range(0, 6) : $eligibleDays)));
        sort($days);
        if ($days === [] || array_diff($days, range(0, 6)) !== []) {
            throw ValidationException::withMessages(['eligibleDays' => 'Choose one or more valid coverage days.']);
        }

        $shiftTypes = array_values(array_unique(array_map('strval', $eligibleShiftTypes)));
        if (array_diff($shiftTypes, self::ELIGIBLE_SHIFT_TYPES) !== []) {
            throw ValidationException::withMessages(['eligibleShiftTypes' => 'Choose valid coverage shift types.']);
        }

        return [$role, $days, $shiftTypes];
    }

    public function matchesTemplateEligibility(
        ContinuousCoverageRosterMember $member,
        ContinuousCoverageShiftTemplate $template,
    ): bool {
        $eligibleDays = array_map('intval', (array) $member->eligible_days);
        if ($eligibleDays !== [] && ! in_array((int) $template->day_of_week, $eligibleDays, true)) {
            return false;
        }

        $eligibleShiftTypes = array_map('strval', (array) $member->eligible_shift_types);
        if ($eligibleShiftTypes === []) {
            return true;
        }

        $startHour = (int) substr((string) $template->starts_at, 0, 2);
        $templateTypes = [
            ($template->spans_next_day || $startHour >= 18 || $startHour < 6) ? 'overnight' : 'daytime',
            ((int) round($template->duration_minutes / 60)).'_hour',
        ];

        return array_intersect($eligibleShiftTypes, $templateTypes) !== [];
    }

    private function assertEnabledFor(User $user): void
    {
        if (! $this->access->allows($user)) {
            throw ValidationException::withMessages(['coverage' => 'Continuous Coverage is not currently available for this account.']);
        }
    }
}
