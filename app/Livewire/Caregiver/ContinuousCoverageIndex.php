<?php

namespace App\Livewire\Caregiver;

use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\ContinuousCoverageLaneRequest;
use App\Models\ContinuousCoveragePlan;
use App\Models\ContinuousCoverageRosterMember;
use App\Models\ContinuousCoverageShift;
use App\Models\ContinuousCoverageShiftOffer;
use App\Models\ContinuousCoverageShiftTemplate;
use App\Services\ContinuousCoverage\ContinuousCoverageAccess;
use App\Services\ContinuousCoverage\ContinuousCoverageHandoffService;
use App\Services\ContinuousCoverage\ContinuousCoverageLaneRequestService;
use App\Services\ContinuousCoverage\ContinuousCoveragePricingService;
use App\Services\ContinuousCoverage\ContinuousCoverageReplacementService;
use App\Services\ContinuousCoverage\ContinuousCoverageRosterService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ContinuousCoverageIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $tab = 'schedule';

    public array $releaseReasons = [];

    public array $handoffNotes = [];

    public array $laneRequestSelections = [];

    #[Url]
    public string $historyStatus = '';

    #[Url]
    public string $historyFrom = '';

    #[Url]
    public string $historyThrough = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->role === 'caregiver', 403);
    }

    public function setTab(string $tab): void
    {
        abort_unless(in_array($tab, ['schedule', 'offers', 'commitments', 'history'], true), 404);
        $this->tab = $tab;
        $this->resetPage('coverageHistoryPage');
    }

    public function clearHistoryFilters(): void
    {
        $this->historyStatus = '';
        $this->historyFrom = '';
        $this->historyThrough = '';
        $this->resetPage('coverageHistoryPage');
    }

    public function updatedHistoryStatus(): void
    {
        $this->resetPage('coverageHistoryPage');
    }

    public function updatedHistoryFrom(): void
    {
        $this->resetPage('coverageHistoryPage');
    }

    public function updatedHistoryThrough(): void
    {
        $this->resetPage('coverageHistoryPage');
    }

    public function acceptTeam(int $memberId, ContinuousCoverageRosterService $roster): void
    {
        $member = $this->ownedMember($memberId, ContinuousCoverageRosterMember::STATUS_FAMILY_APPROVED);
        $roster->caregiverAccept($member, auth()->user());
        session()->flash('status', 'You joined this family-approved care team. You still choose which recurring coverage to accept.');
    }

    public function declineTeam(int $memberId, ContinuousCoverageRosterService $roster): void
    {
        $member = $this->ownedMember($memberId, ContinuousCoverageRosterMember::STATUS_FAMILY_APPROVED);
        $roster->caregiverDecline($member, auth()->user());
        session()->flash('status', 'Care-team invitation declined.');
    }

    public function applyToPlan(int $planId, ContinuousCoverageRosterService $roster): void
    {
        $plan = ContinuousCoveragePlan::query()
            ->where('status', ContinuousCoveragePlan::STATUS_ACTIVE)
            ->where('marketplace_applications_enabled', true)
            ->findOrFail($planId);
        $roster->apply($plan, auth()->user());
        session()->flash('status', 'Application sent. The family must approve you before you can join the care team or receive coverage assignments.');
    }

    public function acceptLane(int $templateId, ContinuousCoverageRosterService $roster): void
    {
        $template = $this->ownedTemplate($templateId);
        $roster->acceptLane($template, auth()->user());
        session()->flash('status', 'Recurring coverage accepted. Your confirmed shifts are now on the calendar.');
    }

    public function declineLane(int $templateId, ContinuousCoverageRosterService $roster): void
    {
        $template = $this->ownedTemplate($templateId);
        $roster->declineLane($template, auth()->user());
        session()->flash('status', 'Recurring coverage declined. The family can offer this lane to another approved caregiver.');
    }

    public function requestOpenLanes(int $planId, ContinuousCoverageLaneRequestService $requests): void
    {
        $selected = collect((array) ($this->laneRequestSelections[$planId] ?? []))
            ->filter(fn ($checked): bool => filter_var($checked, FILTER_VALIDATE_BOOLEAN))
            ->keys()
            ->map(fn ($laneId): int => (int) $laneId)
            ->values()
            ->all();
        $plan = ContinuousCoveragePlan::query()
            ->where('status', ContinuousCoveragePlan::STATUS_ACTIVE)
            ->whereHas('rosterMembers', fn ($query) => $query
                ->where('caregiver_user_id', auth()->id())
                ->where('status', ContinuousCoverageRosterMember::STATUS_ACTIVE))
            ->findOrFail($planId);
        $created = $requests->request($plan, auth()->user(), $selected);
        unset($this->laneRequestSelections[$planId]);
        session()->flash('status', $created->count() === 1
            ? 'Lane requested. The family will review it before anything is added to your schedule.'
            : $created->count().' lanes requested. The family will review them before anything is added to your schedule.');
    }

    public function withdrawLaneRequest(int $requestId, ContinuousCoverageLaneRequestService $requests): void
    {
        $request = ContinuousCoverageLaneRequest::query()
            ->where('caregiver_user_id', auth()->id())
            ->where('status', ContinuousCoverageLaneRequest::STATUS_PENDING)
            ->findOrFail($requestId);
        $requests->withdraw($request, auth()->user());
        session()->flash('status', 'Lane request withdrawn. No schedule assignment was changed.');
    }

    public function acceptReplacement(int $offerId, ContinuousCoverageReplacementService $replacements): void
    {
        $offer = $this->ownedOffer($offerId);
        $replacements->respond($offer, auth()->user(), true);
        session()->flash('status', $offer->shift->plan->replacementRequiresFamilyConfirmation()
            ? 'Backup offer accepted. The family will confirm the final assignment.'
            : 'Backup shift accepted and confirmed under the family’s approved-backup rule.');
    }

    public function declineReplacement(int $offerId, ContinuousCoverageReplacementService $replacements): void
    {
        $replacements->decline($this->ownedOffer($offerId), auth()->user());
        session()->flash('status', 'Backup offer declined.');
    }

    public function releaseShift(int $shiftId, ContinuousCoverageReplacementService $replacements): void
    {
        $reason = trim((string) ($this->releaseReasons[$shiftId] ?? ''));
        $this->validateOnly('releaseReasons.'.$shiftId, [
            'releaseReasons.'.$shiftId => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $shift = ContinuousCoverageShift::query()
            ->where('assigned_caregiver_user_id', auth()->id())
            ->findOrFail($shiftId);
        $replacements->release($shift, auth()->user(), $reason);
        unset($this->releaseReasons[$shiftId]);
        session()->flash('status', 'Shift released. The family can see the gap, and eligible approved backups were invited.');
    }

    public function saveHandoff(
        int $shiftId,
        ContinuousCoverageHandoffService $handoffs,
    ): void {
        $note = trim((string) ($this->handoffNotes[$shiftId] ?? ''));
        $this->validateOnly('handoffNotes.'.$shiftId, [
            'handoffNotes.'.$shiftId => ['required', 'string', 'min:3', 'max:2000'],
        ]);
        $shift = ContinuousCoverageShift::query()
            ->where('assigned_caregiver_user_id', auth()->id())
            ->findOrFail($shiftId);
        $handoffs->record($shift, auth()->user(), $note);
        unset($this->handoffNotes[$shiftId]);
        session()->flash('status', 'Handoff note saved for this coverage shift.');
    }

    public function render(
        ContinuousCoverageAccess $access,
        ContinuousCoveragePricingService $pricing,
        ContinuousCoverageLaneRequestService $laneRequestService,
        ContinuousCoverageRosterService $rosterService,
    ) {
        $caregiverId = (int) auth()->id();
        $caregiver = auth()->user()->loadMissing('caregiverProfile');
        $invitations = ContinuousCoverageRosterMember::query()
            ->with('plan.family:id,name')
            ->where('caregiver_user_id', $caregiverId)
            ->where('status', ContinuousCoverageRosterMember::STATUS_FAMILY_APPROVED)
            ->latest()
            ->get();
        $applications = ContinuousCoverageRosterMember::query()
            ->with('plan:id,title,timezone,starts_on,coverage_pattern')
            ->where('caregiver_user_id', $caregiverId)
            ->where('status', ContinuousCoverageRosterMember::STATUS_APPLIED)
            ->latest()
            ->get();
        $memberships = ContinuousCoverageRosterMember::query()
            ->with(['plan.family:id,name', 'caregiver.caregiverProfile.availabilities'])
            ->where('caregiver_user_id', $caregiverId)
            ->whereIn('status', [ContinuousCoverageRosterMember::STATUS_ACTIVE, ContinuousCoverageRosterMember::STATUS_PAUSED])
            ->latest()
            ->get();
        $membershipsByPlan = $memberships->where('status', ContinuousCoverageRosterMember::STATUS_ACTIVE)
            ->keyBy('continuous_coverage_plan_id');
        $requestableLanes = ContinuousCoverageShiftTemplate::query()
            ->with([
                'plan.family:id,name',
                'laneRequests' => fn ($query) => $query->where('caregiver_user_id', $caregiverId),
            ])
            ->whereHas('plan', fn ($query) => $query->where('status', ContinuousCoveragePlan::STATUS_ACTIVE))
            ->whereIn('continuous_coverage_plan_id', $membershipsByPlan->keys())
            ->whereIn('status', [
                ContinuousCoverageShiftTemplate::STATUS_UNCOVERED,
                ContinuousCoverageShiftTemplate::STATUS_DECLINED,
                ContinuousCoverageShiftTemplate::STATUS_EXPIRED,
            ])
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>=', now()->toDateString()))
            ->orderBy('day_of_week')
            ->orderBy('starts_at')
            ->get()
            ->filter(function (ContinuousCoverageShiftTemplate $template) use ($membershipsByPlan, $rosterService): bool {
                $alreadyPending = $template->laneRequests->contains(
                    fn (ContinuousCoverageLaneRequest $request): bool => $request->status === ContinuousCoverageLaneRequest::STATUS_PENDING,
                );

                return ! $alreadyPending && $rosterService->matchesTemplateEligibility(
                    $membershipsByPlan->get($template->continuous_coverage_plan_id),
                    $template,
                );
            })
            ->values();
        $requestableLaneAvailability = $requestableLanes->mapWithKeys(function (ContinuousCoverageShiftTemplate $template) use ($membershipsByPlan, $laneRequestService): array {
            $member = $membershipsByPlan->get($template->continuous_coverage_plan_id);

            return [$template->id => $laneRequestService->profileAvailabilityMatchesTemplate($member, $template)];
        });
        $pendingLaneRequests = ContinuousCoverageLaneRequest::query()
            ->with(['plan.family:id,name', 'template'])
            ->where('caregiver_user_id', $caregiverId)
            ->where('status', ContinuousCoverageLaneRequest::STATUS_PENDING)
            ->latest('requested_at')
            ->get();
        $opportunities = ContinuousCoveragePlan::query()
            ->with('family:id,name')
            ->where('status', ContinuousCoveragePlan::STATUS_ACTIVE)
            ->where('marketplace_applications_enabled', true)
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', now()->toDateString()))
            ->whereDoesntHave('rosterMembers', fn ($query) => $query->where('caregiver_user_id', $caregiverId))
            ->orderBy('starts_on')
            ->limit(50)
            ->get()
            ->filter(fn (ContinuousCoveragePlan $plan) => $access->allows($plan->family))
            ->take(20)
            ->values();
        $lanes = ContinuousCoverageShiftTemplate::query()
            ->with('plan.family:id,name')
            ->whereHas('rosterMember', fn ($query) => $query->where('caregiver_user_id', $caregiverId))
            ->whereIn('status', [ContinuousCoverageShiftTemplate::STATUS_OFFERED, ContinuousCoverageShiftTemplate::STATUS_ACTIVE])
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>=', now()->toDateString()))
            ->orderBy('day_of_week')
            ->orderBy('starts_at')
            ->get();
        $offers = ContinuousCoverageShiftOffer::query()
            ->with(['shift.plan.family:id,name'])
            ->where('caregiver_user_id', $caregiverId)
            ->where('status', ContinuousCoverageShiftOffer::STATUS_PENDING)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderBy('expires_at')
            ->get();
        $upcoming = ContinuousCoverageShift::query()
            ->with(['plan.family:id,name', 'booking.payment', 'handoffs.caregiver:id,name'])
            ->where('assigned_caregiver_user_id', $caregiverId)
            ->where('scheduled_start_at', '>=', now())
            ->whereIn('status', [
                ContinuousCoverageShift::STATUS_CONFIRMED,
                ContinuousCoverageShift::STATUS_PAYMENT_ATTENTION,
                ContinuousCoverageShift::STATUS_IN_PROGRESS,
            ])
            ->orderBy('scheduled_start_at')
            ->limit(60)
            ->get();
        $historyFrom = $this->validDate($this->historyFrom);
        $historyThrough = $this->validDate($this->historyThrough);
        $history = ContinuousCoverageShift::query()
            ->with(['plan.family:id,name', 'booking.payment', 'replacementCases'])
            ->where(function ($query) use ($caregiverId): void {
                $query
                    ->where('assigned_caregiver_user_id', $caregiverId)
                    ->orWhere('released_by_user_id', $caregiverId)
                    ->orWhereHas('replacementCases', fn ($cases) => $cases
                        ->where('original_caregiver_user_id', $caregiverId))
                    ->orWhereHas('booking', fn ($booking) => $booking
                        ->where('caregiver_user_id', $caregiverId));
            })
            ->whereNull('metadata->superseded_by_schedule_version')
            ->where(function ($query) use ($caregiverId): void {
                $query
                    ->where('scheduled_start_at', '<', now())
                    ->orWhereIn('status', [
                        ContinuousCoverageShift::STATUS_COMPLETED,
                        ContinuousCoverageShift::STATUS_CANCELLED,
                    ])
                    ->orWhere('released_by_user_id', $caregiverId)
                    ->orWhereHas('replacementCases', fn ($cases) => $cases
                        ->where('original_caregiver_user_id', $caregiverId));
            })
            ->when($this->historyStatus === 'released', fn ($query) => $query
                ->where(function ($released) use ($caregiverId): void {
                    $released
                        ->where('released_by_user_id', $caregiverId)
                        ->orWhereHas('replacementCases', fn ($cases) => $cases
                            ->where('original_caregiver_user_id', $caregiverId));
                }))
            ->when($this->historyStatus !== '' && $this->historyStatus !== 'released', fn ($query) => $query->where('status', $this->historyStatus))
            ->when($historyFrom, fn ($query) => $query->where('scheduled_start_at', '>=', $historyFrom->copy()->startOfDay()))
            ->when($historyThrough, fn ($query) => $query->where('scheduled_start_at', '<=', $historyThrough->copy()->endOfDay()))
            ->latest('scheduled_start_at')
            ->paginate(20, ['*'], 'coverageHistoryPage');

        $releasedBookingIds = ContinuousCoverageShift::query()
            ->where(function ($query) use ($caregiverId): void {
                $query
                    ->where('released_by_user_id', $caregiverId)
                    ->orWhereHas('replacementCases', fn ($cases) => $cases
                        ->where('original_caregiver_user_id', $caregiverId));
            })
            ->whereNull('metadata->superseded_by_schedule_version')
            ->get(['metadata'])
            ->flatMap(fn (ContinuousCoverageShift $shift) => (array) data_get($shift->metadata, 'released_booking_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
        $currentBookingIds = ContinuousCoverageShift::query()
            ->whereNotNull('care_booking_id')
            ->whereNull('metadata->superseded_by_schedule_version')
            ->whereHas('booking', fn ($booking) => $booking->where('caregiver_user_id', $caregiverId))
            ->pluck('care_booking_id');
        $coverageBookingIds = $currentBookingIds->merge($releasedBookingIds)->unique()->values();
        $earningsCents = (int) CareBookingPayment::query()
            ->whereIn('care_booking_id', $coverageBookingIds)
            ->whereIn('status', [
                CareBookingPayment::STATUS_CAPTURED,
                CareBookingPayment::STATUS_TRANSFERRED,
                CareBookingPayment::STATUS_TRANSFER_FAILED,
                CareBookingPayment::STATUS_PARTIALLY_REFUNDED,
            ])
            ->sum('caregiver_amount_cents');

        $pageReleasedBookingIds = $history->getCollection()
            ->flatMap(fn (ContinuousCoverageShift $shift) => (array) data_get($shift->metadata, 'released_booking_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
        $releasedHistoryBookings = CareBooking::query()
            ->with('payment')
            ->where('caregiver_user_id', $caregiverId)
            ->whereIn('id', $pageReleasedBookingIds)
            ->get()
            ->keyBy('id');
        $historyBookings = $history->getCollection()->mapWithKeys(function (ContinuousCoverageShift $shift) use ($caregiverId, $releasedHistoryBookings): array {
            if ($shift->booking && (int) $shift->booking->caregiver_user_id === $caregiverId) {
                return [$shift->id => $shift->booking];
            }

            $booking = collect((array) data_get($shift->metadata, 'released_booking_ids', []))
                ->map(fn ($id) => $releasedHistoryBookings->get((int) $id))
                ->filter()
                ->first();

            return [$shift->id => $booking];
        });

        $planEarningEstimates = $opportunities
            ->merge($invitations->pluck('plan'))
            ->unique('id')
            ->mapWithKeys(fn (ContinuousCoveragePlan $plan): array => [
                $plan->id => $pricing->caregiverEarningsLabel($plan, $caregiver, $plan->shift_length_minutes),
            ]);
        $laneEarningEstimates = $lanes->mapWithKeys(fn (ContinuousCoverageShiftTemplate $lane): array => [
            $lane->id => $pricing->caregiverEarningsLabel($lane->plan, $caregiver, $lane->duration_minutes),
        ]);
        $offerEarningEstimates = $offers->mapWithKeys(fn (ContinuousCoverageShiftOffer $offer): array => [
            $offer->id => $pricing->caregiverEarningsLabel($offer->shift->plan, $caregiver, $offer->shift->scheduled_minutes),
        ]);
        $requestableLaneEarningEstimates = $requestableLanes->mapWithKeys(fn (ContinuousCoverageShiftTemplate $lane): array => [
            $lane->id => $pricing->caregiverEarningsLabel($lane->plan, $caregiver, $lane->duration_minutes),
        ]);

        return view('livewire.caregiver.continuous-coverage-index', compact(
            'invitations', 'applications', 'memberships', 'opportunities', 'lanes', 'offers', 'upcoming', 'history',
            'earningsCents', 'historyBookings', 'planEarningEstimates', 'laneEarningEstimates', 'offerEarningEstimates',
            'requestableLanes', 'requestableLaneAvailability', 'pendingLaneRequests', 'requestableLaneEarningEstimates'
        ));
    }

    private function ownedMember(int $id, string $status): ContinuousCoverageRosterMember
    {
        return ContinuousCoverageRosterMember::query()
            ->where('caregiver_user_id', auth()->id())
            ->where('status', $status)
            ->findOrFail($id);
    }

    private function ownedTemplate(int $id): ContinuousCoverageShiftTemplate
    {
        return ContinuousCoverageShiftTemplate::query()
            ->whereHas('rosterMember', fn ($query) => $query->where('caregiver_user_id', auth()->id()))
            ->findOrFail($id);
    }

    private function ownedOffer(int $id): ContinuousCoverageShiftOffer
    {
        return ContinuousCoverageShiftOffer::query()
            ->with('shift.plan')
            ->where('caregiver_user_id', auth()->id())
            ->findOrFail($id);
    }

    private function validDate(string $value): ?\Illuminate\Support\Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            $date = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $value, config('app.timezone'))->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        return $date->toDateString() === $value ? $date : null;
    }
}
