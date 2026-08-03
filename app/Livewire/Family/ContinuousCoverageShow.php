<?php

namespace App\Livewire\Family;

use App\Models\CareBooking;
use App\Models\CaregiverProfile;
use App\Models\ContinuousCoverageLaneRequest;
use App\Models\ContinuousCoveragePlan;
use App\Models\ContinuousCoverageReplacementCase;
use App\Models\ContinuousCoverageRosterMember;
use App\Models\ContinuousCoverageShift;
use App\Models\ContinuousCoverageShiftTemplate;
use App\Models\FamilyCaregiverFavorite;
use App\Services\ContinuousCoverage\ContinuousCoverageLaneRequestService;
use App\Services\ContinuousCoverage\ContinuousCoveragePlanLifecycleService;
use App\Services\ContinuousCoverage\ContinuousCoveragePricingService;
use App\Services\ContinuousCoverage\ContinuousCoverageReplacementService;
use App\Services\ContinuousCoverage\ContinuousCoverageRosterService;
use App\Services\ContinuousCoverage\ContinuousCoverageScheduleService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ContinuousCoverageShow extends Component
{
    use WithPagination;

    public ContinuousCoveragePlan $plan;

    #[Url]
    public string $tab = 'overview';

    #[Url]
    public string $week = '';

    #[Url]
    public string $day = '';

    #[Url]
    public string $calendarStatus = '';

    #[Url]
    public string $calendarCaregiver = '';

    #[Url]
    public string $historyStatus = '';

    #[Url]
    public string $historyBillingStatus = '';

    #[Url]
    public string $historyCaregiver = '';

    #[Url]
    public string $historyFrom = '';

    #[Url]
    public string $historyThrough = '';

    #[Url]
    public string $billingStatus = '';

    #[Url]
    public string $billingPeriod = 'month';

    #[Url]
    public string $selectedShift = '';

    public string $caregiverSearch = '';

    public bool $showCaregiverSearchModal = false;

    public ?int $selectedCaregiverId = null;

    public ?string $caregiverSearchFeedback = null;

    public array $laneSelections = [];

    public string $inviteRole = ContinuousCoverageRosterMember::ROLE_BACKUP;

    public bool $inviteReplacementOptIn = true;

    public array $inviteEligibleDays = [0, 1, 2, 3, 4, 5, 6];

    public array $inviteEligibleShiftTypes = ['daytime', 'overnight', '6_hour', '8_hour', '12_hour'];

    public array $memberPreferences = [];

    public bool $marketplaceApplicationsEnabled = false;

    public string $scheduleEffectiveOn = '';

    public string $scheduleCoveragePattern = ContinuousCoveragePlan::PATTERN_AROUND_THE_CLOCK;

    public int $scheduleShiftLengthMinutes = 720;

    public string $scheduleShiftLengthChoice = '720';

    public string $scheduleCustomShiftLengthHours = '4';

    public string $scheduleStartTime = '07:00';

    public string $scheduleEndTime = '07:00';

    public array $scheduleWindows = [['day' => 1, 'start' => '07:00', 'end' => '19:00']];

    public string $deleteConfirmation = '';

    public function mount(int $coveragePlan): void
    {
        abort_unless(auth()->user()?->role === 'family', 403);

        $plan = ContinuousCoveragePlan::query()
            ->where('family_user_id', auth()->id())
            ->find($coveragePlan);

        abort_if($plan === null, 404);

        $this->plan = $plan;
        $week = $this->localDate($this->week) ?: now($this->plan->timezone);
        $this->week = $week->startOfWeek()->toDateString();
        $this->day = ($this->localDate($this->day) ?: now($this->plan->timezone))->toDateString();
        $this->scheduleEffectiveOn = now($this->plan->timezone)->addWeek()->toDateString();
        $this->scheduleCoveragePattern = $this->plan->coverage_pattern;
        $this->scheduleShiftLengthMinutes = $this->plan->shift_length_minutes;
        if (in_array($this->plan->shift_length_minutes, [720, 480, 360], true)) {
            $this->scheduleShiftLengthChoice = (string) $this->plan->shift_length_minutes;
        } else {
            $this->scheduleShiftLengthChoice = 'custom';
            $this->scheduleCustomShiftLengthHours = rtrim(rtrim(number_format($this->plan->shift_length_minutes / 60, 4, '.', ''), '0'), '.');
        }
        $this->scheduleStartTime = (string) data_get($this->plan->metadata, 'coverage_start_time', '07:00');
        $this->scheduleEndTime = (string) data_get($this->plan->metadata, 'coverage_end_time', '07:00');
        $this->marketplaceApplicationsEnabled = $this->plan->marketplace_applications_enabled;
        if ($this->plan->coverage_pattern === ContinuousCoveragePlan::PATTERN_CUSTOM && $this->plan->weekly_schedule) {
            $this->scheduleWindows = array_values($this->plan->weekly_schedule);
        }
        $this->plan->rosterMembers()
            ->where('status', '!=', ContinuousCoverageRosterMember::STATUS_APPLIED)
            ->get()
            ->each(fn (ContinuousCoverageRosterMember $member) => $this->syncMemberPreferences($member));
    }

    public function setTab(string $tab): void
    {
        abort_unless(in_array($tab, ['overview', 'calendar', 'team', 'history', 'billing', 'settings'], true), 404);
        if ($this->plan->status === ContinuousCoveragePlan::STATUS_ENDED && in_array($tab, ['calendar', 'team'], true)) {
            $tab = 'overview';
        }
        $this->tab = $tab;
        if ($tab !== 'team') {
            $this->closeCaregiverSearchModal();
        }
        $this->resetPage();
    }

    public function openCaregiverSearchModal(): void
    {
        $this->showCaregiverSearchModal = true;
        $this->selectedCaregiverId = null;
        $this->caregiverSearch = '';
        $this->caregiverSearchFeedback = null;
        $this->inviteRole = ContinuousCoverageRosterMember::ROLE_BACKUP;
        $this->inviteReplacementOptIn = true;
        $this->inviteEligibleDays = range(0, 6);
        $this->inviteEligibleShiftTypes = ['daytime', 'overnight', '6_hour', '8_hour', '12_hour'];
        $this->resetValidation();
        $this->dispatch('coverage-caregiver-modal-opened');
    }

    public function closeCaregiverSearchModal(): void
    {
        $this->showCaregiverSearchModal = false;
        $this->selectedCaregiverId = null;
        $this->caregiverSearch = '';
        $this->caregiverSearchFeedback = null;
        $this->resetValidation();
        $this->dispatch('coverage-caregiver-modal-closed');
    }

    public function clearCaregiverSearch(): void
    {
        $this->caregiverSearch = '';
        $this->caregiverSearchFeedback = null;
    }

    public function selectCaregiverForRoster(int $caregiverId): void
    {
        $profile = $this->eligibleCaregiverProfilesQuery()
            ->where('user_id', $caregiverId)
            ->first();

        if (! $profile) {
            $this->caregiverSearchFeedback = 'This caregiver does not currently have an active profile.';

            return;
        }

        if (! $profile->is_accepting_new_clients) {
            $this->caregiverSearchFeedback = 'This caregiver is not accepting new clients right now.';

            return;
        }

        $existing = $this->plan->rosterMembers()
            ->where('caregiver_user_id', $caregiverId)
            ->first();
        if ($existing && $existing->status !== ContinuousCoverageRosterMember::STATUS_REMOVED) {
            $this->caregiverSearchFeedback = match ($existing->status) {
                ContinuousCoverageRosterMember::STATUS_APPLIED => 'This caregiver already applied. Review their application in the care-team page.',
                ContinuousCoverageRosterMember::STATUS_FAMILY_APPROVED => 'This caregiver already has an invitation waiting for their response.',
                ContinuousCoverageRosterMember::STATUS_ACTIVE => 'This caregiver is already on this care team.',
                ContinuousCoverageRosterMember::STATUS_PAUSED => 'This caregiver is already on this care team and is currently paused.',
                default => 'This caregiver already has a care-team status for this plan.',
            };

            return;
        }

        $this->selectedCaregiverId = $caregiverId;
        $this->caregiverSearchFeedback = null;
        $this->resetValidation();
        $this->dispatch('coverage-caregiver-content-top');
    }

    public function backToCaregiverSearch(): void
    {
        $this->selectedCaregiverId = null;
        $this->caregiverSearchFeedback = null;
        $this->resetValidation();
        $this->dispatch('coverage-caregiver-content-top');
    }

    public function previousWeek(): void
    {
        $this->week = Carbon::parse($this->week, $this->plan->timezone)->subWeek()->startOfWeek()->toDateString();
        $this->day = $this->week;
    }

    public function nextWeek(): void
    {
        $this->week = Carbon::parse($this->week, $this->plan->timezone)->addWeek()->startOfWeek()->toDateString();
        $this->day = $this->week;
    }

    public function currentWeek(): void
    {
        $this->week = now($this->plan->timezone)->startOfWeek()->toDateString();
        $this->day = now($this->plan->timezone)->toDateString();
    }

    public function selectDay(string $date): void
    {
        $candidate = $this->localDate($date);
        abort_unless($candidate, 404);
        $weekStart = Carbon::parse($this->week, $this->plan->timezone)->startOfWeek();
        abort_unless($candidate->betweenIncluded($weekStart, $weekStart->copy()->addDays(6)->endOfDay()), 404);
        $this->day = $candidate->toDateString();
    }

    public function openShift(int $shiftId): void
    {
        $this->plan->shifts()->findOrFail($shiftId);
        $this->selectedShift = (string) $shiftId;
    }

    public function closeShift(): void
    {
        $this->selectedShift = '';
    }

    public function clearCalendarFilters(): void
    {
        $this->calendarStatus = '';
        $this->calendarCaregiver = '';
    }

    public function clearHistoryFilters(): void
    {
        $this->historyStatus = '';
        $this->historyBillingStatus = '';
        $this->historyCaregiver = '';
        $this->historyFrom = '';
        $this->historyThrough = '';
        $this->resetPage('historyPage');
    }

    public function updatedHistoryStatus(): void
    {
        $this->resetPage('historyPage');
    }

    public function updatedHistoryBillingStatus(): void
    {
        $this->resetPage('historyPage');
    }

    public function updatedHistoryCaregiver(): void
    {
        $this->resetPage('historyPage');
    }

    public function updatedHistoryFrom(): void
    {
        $this->resetPage('historyPage');
    }

    public function updatedHistoryThrough(): void
    {
        $this->resetPage('historyPage');
    }

    public function addScheduleWindow(): void
    {
        $this->scheduleWindows[] = ['day' => 1, 'start' => '07:00', 'end' => '19:00'];
    }

    public function removeScheduleWindow(int $index): void
    {
        if (count($this->scheduleWindows) <= 1) {
            return;
        }
        unset($this->scheduleWindows[$index]);
        $this->scheduleWindows = array_values($this->scheduleWindows);
    }

    public function updatedScheduleCoveragePattern(string $pattern): void
    {
        if ($pattern === ContinuousCoveragePlan::PATTERN_OVERNIGHT && $this->scheduleStartTime === $this->scheduleEndTime) {
            $this->scheduleStartTime = '19:00';
            $this->scheduleEndTime = '07:00';
        }
    }

    public function saveFutureSchedule(ContinuousCoverageScheduleService $schedule): void
    {
        $this->assertPlanActive();
        $rules = [
            'scheduleEffectiveOn' => ['required', 'date', 'after:today'],
            'scheduleCoveragePattern' => ['required', \Illuminate\Validation\Rule::in([
                ContinuousCoveragePlan::PATTERN_AROUND_THE_CLOCK,
                ContinuousCoveragePlan::PATTERN_OVERNIGHT,
                ContinuousCoveragePlan::PATTERN_CUSTOM,
            ])],
            'scheduleStartTime' => ['required', 'date_format:H:i'],
            'scheduleEndTime' => ['required', 'date_format:H:i'],
        ] + $this->futureShiftStructureRules();
        if ($this->scheduleCoveragePattern === ContinuousCoveragePlan::PATTERN_CUSTOM) {
            $rules += [
                'scheduleWindows' => ['required', 'array', 'min:1', 'max:42'],
                'scheduleWindows.*.day' => ['required', 'integer', 'between:0,6'],
                'scheduleWindows.*.start' => ['required', 'date_format:H:i'],
                'scheduleWindows.*.end' => ['required', 'date_format:H:i'],
            ];
        }
        $this->validate($rules);
        $this->scheduleShiftLengthMinutes = $this->resolvedFutureShiftLengthMinutes()
            ?? throw new \LogicException('Shift length was applied before validation.');

        $this->plan = $schedule->replaceFutureSchedule($this->plan, auth()->user(), $this->scheduleEffectiveOn, [
            'coverage_pattern' => $this->scheduleCoveragePattern,
            'shift_length_minutes' => $this->scheduleShiftLengthMinutes,
            'coverage_start_time' => $this->scheduleStartTime,
            'coverage_end_time' => $this->scheduleEndTime,
            'custom_windows' => $this->scheduleCoveragePattern === ContinuousCoveragePlan::PATTERN_CUSTOM ? $this->scheduleWindows : [],
        ]);
        session()->flash('status', 'The new schedule starts '.$this->scheduleEffectiveOn.'. Existing visits and history were preserved.');
    }

    public function endPlan(ContinuousCoveragePlanLifecycleService $lifecycle): void
    {
        $lifecycle->endPlan($this->plan, auth()->user());
        session()->flash('status', 'Continuous Coverage ended. Future unprepared shifts were removed and care and billing history was preserved.');
        $this->redirectRoute('family.continuous-coverage.index', navigate: true);
    }

    public function deletePlan(ContinuousCoveragePlanLifecycleService $lifecycle): void
    {
        $this->validate([
            'deleteConfirmation' => ['required', 'in:DELETE'],
        ], [
            'deleteConfirmation.in' => 'Type DELETE exactly to permanently remove this test plan.',
        ]);

        $lifecycle->deleteUnbilledPlan($this->plan, auth()->user());
        session()->flash('status', 'The unbilled Continuous Coverage test plan was permanently deleted.');
        $this->redirectRoute('family.continuous-coverage.index', navigate: true);
    }

    private function futureShiftStructureRules(): array
    {
        if ($this->scheduleCoveragePattern !== ContinuousCoveragePlan::PATTERN_AROUND_THE_CLOCK) {
            return [];
        }

        $rules = [
            'scheduleShiftLengthChoice' => ['required', \Illuminate\Validation\Rule::in(['720', '480', '360', 'custom'])],
        ];

        if ($this->scheduleShiftLengthChoice === 'custom') {
            $rules['scheduleCustomShiftLengthHours'] = [
                'bail',
                'required',
                'numeric',
                'between:1,12',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $minutes = (float) $value * 60;
                    $roundedMinutes = (int) round($minutes);
                    if ($roundedMinutes < 60 || abs($minutes - $roundedMinutes) > 0.0001 || 1440 % $roundedMinutes !== 0) {
                        $fail('Choose a shift length that divides 24 hours evenly, such as 4, 3, 2, or 1.5 hours.');
                    }
                },
            ];
        }

        return $rules;
    }

    private function resolvedFutureShiftLengthMinutes(): ?int
    {
        if ($this->scheduleCoveragePattern !== ContinuousCoveragePlan::PATTERN_AROUND_THE_CLOCK) {
            return $this->scheduleShiftLengthMinutes >= 60 && $this->scheduleShiftLengthMinutes <= 720
                ? $this->scheduleShiftLengthMinutes
                : 720;
        }

        if (in_array($this->scheduleShiftLengthChoice, ['720', '480', '360'], true)) {
            return (int) $this->scheduleShiftLengthChoice;
        }

        if ($this->scheduleShiftLengthChoice !== 'custom' || ! is_numeric($this->scheduleCustomShiftLengthHours)) {
            return null;
        }

        $minutes = (float) $this->scheduleCustomShiftLengthHours * 60;
        $roundedMinutes = (int) round($minutes);

        if ($roundedMinutes < 60 || $roundedMinutes > 720 || abs($minutes - $roundedMinutes) > 0.0001 || 1440 % $roundedMinutes !== 0) {
            return null;
        }

        return $roundedMinutes;
    }

    public function approveCaregiver(int $caregiverId, ContinuousCoverageRosterService $roster): void
    {
        $this->assertPlanActive();
        if (! $this->showCaregiverSearchModal || $this->selectedCaregiverId !== $caregiverId) {
            $this->addError('caregiver', 'Choose a caregiver from the search results before sending an invitation.');

            return;
        }

        $this->validate([
            'inviteRole' => ['required', \Illuminate\Validation\Rule::in([
                ContinuousCoverageRosterMember::ROLE_PRIMARY,
                ContinuousCoverageRosterMember::ROLE_BACKUP,
            ])],
            'inviteReplacementOptIn' => ['boolean'],
            'inviteEligibleDays' => ['required', 'array', 'min:1'],
            'inviteEligibleDays.*' => ['integer', 'between:0,6'],
            'inviteEligibleShiftTypes' => ['array'],
            'inviteEligibleShiftTypes.*' => [\Illuminate\Validation\Rule::in(['daytime', 'overnight', '6_hour', '8_hour', '12_hour'])],
        ]);
        $profile = $this->eligibleCaregiverProfilesQuery()
            ->where('user_id', $caregiverId)
            ->where('is_accepting_new_clients', true)
            ->first();
        if (! $profile?->user) {
            $this->addError('caregiver', 'This caregiver is no longer available to invite. Return to the search results and choose another caregiver.');

            return;
        }
        $caregiver = $profile->user;
        $member = $roster->familyApprove(
            $this->plan,
            auth()->user(),
            $caregiver,
            $this->inviteRole,
            $this->inviteReplacementOptIn,
            $this->inviteEligibleDays,
            $this->inviteEligibleShiftTypes,
        );
        $this->syncMemberPreferences($member);
        $this->closeCaregiverSearchModal();
        session()->flash('status', $caregiver->name.' was approved and invited to join your care team.');
    }

    public function approveApplicant(int $memberId, ContinuousCoverageRosterService $roster): void
    {
        $this->assertPlanActive();
        $member = $this->plan->rosterMembers()
            ->where('status', ContinuousCoverageRosterMember::STATUS_APPLIED)
            ->with('caregiver')
            ->findOrFail($memberId);
        $approved = $roster->familyApprove(
            $this->plan,
            auth()->user(),
            $member->caregiver,
            ContinuousCoverageRosterMember::ROLE_BACKUP,
            false,
            range(0, 6),
            [],
        );
        $this->syncMemberPreferences($approved);
        session()->flash('status', $member->caregiver->name.' was approved and invited to join your care team. You can review their future-offer preferences now; they still choose whether to join.');
    }

    public function declineApplicant(int $memberId, ContinuousCoverageRosterService $roster): void
    {
        $this->assertPlanActive();
        $member = $this->plan->rosterMembers()
            ->where('status', ContinuousCoverageRosterMember::STATUS_APPLIED)
            ->findOrFail($memberId);
        $roster->declineApplicant($member, auth()->user());
        session()->flash('status', 'The application was declined. No assignment or invitation was created.');
    }

    public function saveMarketplaceApplications(ContinuousCoverageRosterService $roster): void
    {
        $this->assertPlanActive();
        $this->validate(['marketplaceApplicationsEnabled' => ['boolean']]);
        $this->plan = $roster->setMarketplaceApplications(
            $this->plan,
            auth()->user(),
            $this->marketplaceApplicationsEnabled,
        );
        session()->flash('status', $this->marketplaceApplicationsEnabled
            ? 'Caregivers can now apply. Your family must approve each applicant before they can join or accept coverage.'
            : 'New caregiver applications are closed. Existing care-team decisions and history were preserved.');
    }

    public function saveMemberPreferences(int $memberId, ContinuousCoverageRosterService $roster): void
    {
        $this->assertPlanActive();
        $member = $this->ownedRosterMember($memberId);
        $path = 'memberPreferences.'.$memberId;
        $this->validate([
            $path.'.role' => ['required', \Illuminate\Validation\Rule::in([
                ContinuousCoverageRosterMember::ROLE_PRIMARY,
                ContinuousCoverageRosterMember::ROLE_BACKUP,
            ])],
            $path.'.replacement_opt_in' => ['boolean'],
            $path.'.eligible_days' => ['required', 'array', 'min:1'],
            $path.'.eligible_days.*' => ['integer', 'between:0,6'],
            $path.'.eligible_shift_types' => ['array'],
            $path.'.eligible_shift_types.*' => [\Illuminate\Validation\Rule::in(['daytime', 'overnight', '6_hour', '8_hour', '12_hour'])],
        ]);
        $preferences = $this->memberPreferences[$memberId];
        $updated = $roster->updatePreferences(
            $member,
            auth()->user(),
            (string) $preferences['role'],
            (bool) $preferences['replacement_opt_in'],
            array_values((array) $preferences['eligible_days']),
            array_values((array) $preferences['eligible_shift_types']),
        );
        $this->syncMemberPreferences($updated);
        session()->flash('status', 'Future coverage eligibility was updated. Existing shifts and history were not changed.');
    }

    public function offerLane(int $templateId, ContinuousCoverageRosterService $roster): void
    {
        $this->assertPlanActive();
        $template = $this->ownedTemplate($templateId);
        $memberId = (int) ($this->laneSelections[$templateId] ?? 0);
        $member = $this->plan->rosterMembers()->where('status', ContinuousCoverageRosterMember::STATUS_ACTIVE)->findOrFail($memberId);
        $roster->offerLane($template, $member, auth()->user());
        session()->flash('status', 'Recurring coverage offered to '.$member->caregiver()->value('name').'.');
    }

    public function approveLaneRequest(int $requestId, ContinuousCoverageLaneRequestService $requests): void
    {
        $this->assertPlanActive();
        $request = $this->ownedLaneRequest($requestId);
        $approved = $requests->approve($request, auth()->user());
        session()->flash('status', $approved
            ? 'Recurring lane approved. Future visits are now confirmed with this caregiver.'
            : 'That lane was no longer available, so no assignment was changed.');
    }

    public function approveLaneRequestsForMember(int $memberId, ContinuousCoverageLaneRequestService $requests): void
    {
        $this->assertPlanActive();
        $member = $this->ownedRosterMember($memberId);
        $pending = $this->plan->laneRequests()
            ->where('roster_member_id', $member->id)
            ->where('status', ContinuousCoverageLaneRequest::STATUS_PENDING)
            ->orderBy('requested_at')
            ->get();
        $approved = 0;
        foreach ($pending as $request) {
            $approved += $requests->approve($request, auth()->user()) ? 1 : 0;
        }
        session()->flash('status', $approved > 0
            ? $approved.' recurring lane'.($approved === 1 ? ' was' : 's were').' approved and added to the confirmed schedule.'
            : 'None of those requested lanes were still available. No assignment was changed.');
    }

    public function declineLaneRequest(int $requestId, ContinuousCoverageLaneRequestService $requests): void
    {
        $this->assertPlanActive();
        $request = $this->ownedLaneRequest($requestId);
        $requests->decline($request, auth()->user());
        session()->flash('status', 'Lane request declined. The caregiver was not assigned to it.');
    }

    public function pauseMember(int $memberId, ContinuousCoverageRosterService $roster): void
    {
        $this->assertPlanActive();
        $member = $this->ownedRosterMember($memberId);
        $roster->pause($member, auth()->user());
        session()->flash('status', 'This care-team member is paused. Existing confirmed shifts remain visible.');
    }

    public function resumeMember(int $memberId, ContinuousCoverageRosterService $roster): void
    {
        $this->assertPlanActive();
        $member = $this->ownedRosterMember($memberId);
        $roster->resume($member, auth()->user());
        session()->flash('status', 'This care-team member can receive eligible future offers again.');
    }

    public function removeMember(int $memberId, ContinuousCoverageRosterService $roster): void
    {
        $this->assertPlanActive();
        $member = $this->ownedRosterMember($memberId);
        $roster->remove($member, auth()->user());
        session()->flash('status', 'This caregiver was removed from future care-team offers. Existing history was preserved.');
    }

    public function confirmReplacement(int $caseId, ContinuousCoverageReplacementService $replacements): void
    {
        $this->assertPlanActive();
        $case = ContinuousCoverageReplacementCase::query()
            ->whereHas('shift', fn ($query) => $query->where('continuous_coverage_plan_id', $this->plan->id))
            ->findOrFail($caseId);
        $replacements->familyConfirm($case, auth()->user());
        session()->flash('status', 'Replacement confirmed from your family-approved care team.');
    }

    public function declineReplacement(int $caseId, ContinuousCoverageReplacementService $replacements): void
    {
        $this->assertPlanActive();
        $case = ContinuousCoverageReplacementCase::query()
            ->whereHas('shift', fn ($query) => $query->where('continuous_coverage_plan_id', $this->plan->id))
            ->findOrFail($caseId);
        $replacements->familyDecline($case, auth()->user());
        session()->flash('status', 'That caregiver was not selected for this shift. Other eligible approved backups can respond, or the gap remains visible.');
    }

    public function retryReplacement(int $caseId, ContinuousCoverageReplacementService $replacements): void
    {
        $this->assertPlanActive();
        $case = ContinuousCoverageReplacementCase::query()
            ->whereHas('shift', fn ($query) => $query->where('continuous_coverage_plan_id', $this->plan->id))
            ->findOrFail($caseId);
        $offers = $replacements->retryMatching($case, auth()->user());
        session()->flash('status', $offers->isNotEmpty()
            ? $offers->count().' newly eligible family-approved backup'.($offers->count() === 1 ? ' was' : 's were').' invited.'
            : 'No newly eligible approved backup is available yet. The gap remains visible.');
    }

    public function render(
        ContinuousCoverageScheduleService $schedule,
        ContinuousCoverageRosterService $rosterService,
        ContinuousCoveragePricingService $pricing,
        ContinuousCoveragePlanLifecycleService $lifecycle,
    ) {
        if ($this->plan->status === ContinuousCoveragePlan::STATUS_ENDED && in_array($this->tab, ['calendar', 'team'], true)) {
            $this->tab = 'overview';
        }

        $weekStartLocal = Carbon::parse($this->week, $this->plan->timezone)->startOfWeek();
        $weekEndLocal = $weekStartLocal->copy()->addWeek();
        $from = $weekStartLocal->copy()->setTimezone(config('app.timezone'));
        $through = $weekEndLocal->copy()->setTimezone(config('app.timezone'));
        $weekShifts = $this->plan->shifts()
            ->with(['assignedCaregiver:id,name', 'replacementCase.winningOffer.caregiver:id,name', 'booking.payment', 'handoffs.caregiver:id,name'])
            ->whereNull('metadata->superseded_by_schedule_version')
            ->where('scheduled_start_at', '<', $through)
            ->where('scheduled_end_at', '>', $from)
            ->when($this->calendarStatus !== '', fn ($query) => $query->where('status', $this->calendarStatus))
            ->when(ctype_digit($this->calendarCaregiver), fn ($query) => $query->where('assigned_caregiver_user_id', (int) $this->calendarCaregiver))
            ->orderBy('scheduled_start_at')
            ->get();
        $days = collect(range(0, 6))->map(function (int $offset) use ($weekStartLocal, $weekShifts): array {
            $date = $weekStartLocal->copy()->addDays($offset);

            return [
                'date' => $date,
                'shifts' => $weekShifts->filter(function (ContinuousCoverageShift $shift) use ($date): bool {
                    $dayStartsAt = $date->copy()->startOfDay();
                    $dayEndsAt = $dayStartsAt->copy()->addDay();
                    $shiftStartsAt = $shift->scheduled_start_at->copy()->setTimezone($this->plan->timezone);
                    $shiftEndsAt = $shift->scheduled_end_at->copy()->setTimezone($this->plan->timezone);

                    return $shiftStartsAt->lt($dayEndsAt) && $shiftEndsAt->gt($dayStartsAt);
                })->values(),
            ];
        });
        $summary = $schedule->coverageSummary($this->plan, $from, $through);
        $nextShift = $this->plan->shifts()
            ->with('assignedCaregiver:id,name')
            ->whereNull('metadata->superseded_by_schedule_version')
            ->whereIn('status', [
                ContinuousCoverageShift::STATUS_CONFIRMED,
                ContinuousCoverageShift::STATUS_IN_PROGRESS,
                ContinuousCoverageShift::STATUS_PAYMENT_ATTENTION,
            ])
            ->where('scheduled_start_at', '>=', now())
            ->orderBy('scheduled_start_at')
            ->first();
        $attention = $this->plan->shifts()
            ->whereNull('metadata->superseded_by_schedule_version')
            ->whereIn('status', [
                ContinuousCoverageShift::STATUS_UNCOVERED,
                ContinuousCoverageShift::STATUS_REPLACEMENT_NEEDED,
                ContinuousCoverageShift::STATUS_AWAITING_FAMILY,
                ContinuousCoverageShift::STATUS_PAYMENT_ATTENTION,
            ])
            ->where('scheduled_start_at', '<', $through)
            ->where('scheduled_end_at', '>', $from)
            ->count();

        $allRoster = $this->plan->rosterMembers()->with([
            'caregiver:id,name,email,city,state',
            'caregiver.caregiverProfile:id,user_id,slug,profile_photo_path,years_experience,average_rating',
        ])->orderBy('role')->get();
        $applicants = $allRoster->where('status', ContinuousCoverageRosterMember::STATUS_APPLIED)->values();
        $roster = $allRoster->where('status', '!=', ContinuousCoverageRosterMember::STATUS_APPLIED)->values();
        $activeRoster = $roster->filter->isActive();
        $currentScheduleVersion = (int) $this->plan->templates()->max('schedule_version');
        $templates = $this->plan->templates()
            ->with('rosterMember.caregiver:id,name')
            ->where('schedule_version', $currentScheduleVersion)
            ->where('status', '!=', ContinuousCoverageShiftTemplate::STATUS_SUPERSEDED)
            ->orderBy('day_of_week')
            ->orderBy('starts_at')
            ->get();
        $pendingLaneRequests = $this->plan->laneRequests()
            ->with(['caregiver:id,name', 'template'])
            ->where('status', ContinuousCoverageLaneRequest::STATUS_PENDING)
            ->oldest('requested_at')
            ->get();
        $pendingLaneRequestsByTemplate = $pendingLaneRequests->groupBy('shift_template_id');
        $eligibleRosterByTemplate = $templates->mapWithKeys(fn (ContinuousCoverageShiftTemplate $template): array => [
            $template->id => $activeRoster
                ->filter(fn (ContinuousCoverageRosterMember $member): bool => $rosterService->matchesTemplateEligibility($member, $template))
                ->values(),
        ]);
        $rosterByCaregiver = $allRoster->keyBy('caregiver_user_id');
        $searchResults = collect();
        $caregiverInitialSections = [];
        $selectedCaregiver = null;
        if ($this->showCaregiverSearchModal) {
            $search = trim($this->caregiverSearch);
            $safeSearch = str_replace(['%', '_'], '', $search);

            if ($search === '') {
                $caregiverInitialSections = $this->caregiverInitialSections();
            } elseif (mb_strlen($safeSearch) >= 2) {
                $searchResults = $this->eligibleCaregiverProfilesQuery()
                    ->whereHas('user', function (Builder $query) use ($safeSearch): void {
                        $query->where(function (Builder $userQuery) use ($safeSearch): void {
                            $userQuery->where('name', 'like', '%'.$safeSearch.'%')
                                ->orWhere('city', 'like', '%'.$safeSearch.'%');
                        });
                    })
                    ->orderByDesc('top_caregiver')
                    ->orderByDesc('average_rating')
                    ->orderByDesc('reviews_count')
                    ->limit(12)
                    ->get();
            }

            if ($this->selectedCaregiverId) {
                $selectedCaregiver = $this->eligibleCaregiverProfilesQuery()
                    ->where('user_id', $this->selectedCaregiverId)
                    ->first();
            }
        }

        $historyFrom = $this->localDate($this->historyFrom);
        $historyThrough = $this->localDate($this->historyThrough);
        $history = $this->plan->shifts()
            ->with(['assignedCaregiver:id,name', 'booking.payment', 'replacementCase.originalCaregiver:id,name'])
            ->whereNull('metadata->superseded_by_schedule_version')
            ->where(fn ($query) => $query->where('scheduled_start_at', '<', now())->orWhereIn('status', [
                ContinuousCoverageShift::STATUS_COMPLETED,
                ContinuousCoverageShift::STATUS_CANCELLED,
            ]))
            ->when($this->historyStatus === 'disputed', fn ($query) => $query->whereHas('booking', fn ($booking) => $booking
                ->where('status', CareBooking::STATUS_DISPUTED)
                ->orWhereNotNull('dispute_opened_at')))
            ->when($this->historyStatus === 'missed', fn ($query) => $query->whereHas('booking', fn ($booking) => $booking->where('no_show_flag', true)))
            ->when($this->historyStatus === 'replaced', fn ($query) => $query->whereHas('replacementCase', fn ($case) => $case->where('status', ContinuousCoverageReplacementCase::STATUS_RESOLVED)))
            ->when($this->historyStatus !== '' && ! in_array($this->historyStatus, ['disputed', 'missed', 'replaced'], true), fn ($query) => $query->where('status', $this->historyStatus))
            ->when($this->historyBillingStatus !== '', fn ($query) => $query->whereHas('booking.payment', fn ($payment) => $payment->where('status', $this->historyBillingStatus)))
            ->when(ctype_digit($this->historyCaregiver), fn ($query) => $query->where('assigned_caregiver_user_id', (int) $this->historyCaregiver))
            ->when($historyFrom, fn ($query) => $query->where('scheduled_start_at', '>=', $historyFrom->copy()->startOfDay()->setTimezone(config('app.timezone'))))
            ->when($historyThrough, fn ($query) => $query->where('scheduled_start_at', '<=', $historyThrough->copy()->endOfDay()->setTimezone(config('app.timezone'))))
            ->latest('scheduled_start_at')
            ->paginate(20, ['*'], 'historyPage');

        $billingQuery = $this->plan->shifts()
            ->with('booking.payment')
            ->whereNotNull('care_booking_id')
            ->when($this->billingStatus !== '', fn ($query) => $query->whereHas('booking.payment', fn ($payment) => $payment->where('status', $this->billingStatus)));
        $periodStart = match ($this->billingPeriod) {
            'week' => now($this->plan->timezone)->startOfWeek(),
            'all' => null,
            default => now($this->plan->timezone)->startOfMonth(),
        };
        $periodEnd = match ($this->billingPeriod) {
            'week' => now($this->plan->timezone)->endOfWeek(),
            'all' => null,
            default => now($this->plan->timezone)->endOfMonth(),
        };
        if ($periodStart && $periodEnd) {
            $billingQuery
                ->where('scheduled_start_at', '>=', $periodStart->copy()->setTimezone(config('app.timezone')))
                ->where('scheduled_start_at', '<=', $periodEnd->copy()->setTimezone(config('app.timezone')));
        }
        $billingShiftsForTotals = (clone $billingQuery)->get();
        $netBilledCents = $billingShiftsForTotals->sum(fn (ContinuousCoverageShift $shift) => max(0,
            (int) ($shift->booking?->payment?->amount_captured_cents ?? 0)
            - (int) ($shift->booking?->payment?->amount_refunded_cents ?? 0)
        ));
        $billingShifts = $billingQuery->latest('scheduled_start_at')->paginate(15, ['*'], 'billingPage');
        $upcomingShiftsForEstimate = $this->plan->shifts()
            ->with([
                'plan.family',
                'assignedCaregiver.caregiverProfile',
                'booking.application',
                'booking.family',
                'booking.caregiver.caregiverProfile',
            ])
            ->whereIn('status', [ContinuousCoverageShift::STATUS_CONFIRMED, ContinuousCoverageShift::STATUS_PAYMENT_ATTENTION])
            ->whereNotNull('assigned_caregiver_user_id')
            ->where('scheduled_start_at', '>=', now())
            ->where('scheduled_start_at', '<', now()->addWeek())
            ->get();
        $upcomingEstimate = $upcomingShiftsForEstimate
            ->sum(fn (ContinuousCoverageShift $shift): int => $pricing->quoteForShift($shift)['total_charge_cents']) / 100;

        $selectedShiftItem = null;
        $selectedShiftEvents = collect();
        $selectedReleasedBookings = collect();
        if (ctype_digit($this->selectedShift)) {
            $selectedShiftItem = $this->plan->shifts()
                ->with([
                    'template', 'assignedCaregiver:id,name', 'booking.payment', 'booking.careRequest', 'booking.timeCorrections',
                    'booking.taskChecks', 'replacementCase.originalCaregiver:id,name',
                    'replacementCase.offers.caregiver:id,name', 'replacementCase.winningOffer.caregiver:id,name',
                    'replacementCases.originalCaregiver:id,name', 'replacementCases.offers.caregiver:id,name',
                    'replacementCases.winningOffer.caregiver:id,name',
                    'handoffs.caregiver:id,name',
                ])
                ->find((int) $this->selectedShift);
            if (! $selectedShiftItem) {
                $this->selectedShift = '';
            } else {
                $selectedShiftEvents = $this->plan->events()
                    ->with('actor:id,name')
                    ->where('continuous_coverage_shift_id', $selectedShiftItem->id)
                    ->limit(30)
                    ->get();
                $releasedBookingIds = array_values(array_filter(array_map(
                    'intval',
                    (array) data_get($selectedShiftItem->metadata, 'released_booking_ids', []),
                )));
                if ($releasedBookingIds !== []) {
                    $selectedReleasedBookings = CareBooking::query()
                        ->with(['caregiver:id,name', 'payment'])
                        ->where('family_user_id', $this->plan->family_user_id)
                        ->whereKey($releasedBookingIds)
                        ->get();
                }
            }
        }

        $selectedDay = $days->first(fn (array $item) => $item['date']->toDateString() === $this->day) ?: $days->first();
        $deletionBlocker = $lifecycle->deletionBlocker($this->plan);

        return view('livewire.family.continuous-coverage-show', compact(
            'days', 'summary', 'nextShift', 'attention', 'roster', 'applicants', 'activeRoster',
            'templates', 'eligibleRosterByTemplate', 'searchResults', 'caregiverInitialSections',
            'selectedCaregiver', 'rosterByCaregiver', 'history', 'billingShifts', 'netBilledCents',
            'upcomingEstimate', 'weekStartLocal', 'weekEndLocal', 'selectedShiftItem',
            'selectedShiftEvents', 'selectedReleasedBookings', 'selectedDay', 'pendingLaneRequests',
            'pendingLaneRequestsByTemplate', 'deletionBlocker'
        ));
    }

    private function ownedTemplate(int $id): ContinuousCoverageShiftTemplate
    {
        return $this->plan->templates()->findOrFail($id);
    }

    private function assertPlanActive(): void
    {
        if ($this->plan->status !== ContinuousCoveragePlan::STATUS_ACTIVE) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'planLifecycle' => 'This coverage plan has ended and can no longer be changed.',
            ]);
        }
    }

    private function ownedRosterMember(int $id): ContinuousCoverageRosterMember
    {
        return $this->plan->rosterMembers()->findOrFail($id);
    }

    private function ownedLaneRequest(int $id): ContinuousCoverageLaneRequest
    {
        return $this->plan->laneRequests()
            ->where('status', ContinuousCoverageLaneRequest::STATUS_PENDING)
            ->findOrFail($id);
    }

    private function syncMemberPreferences(ContinuousCoverageRosterMember $member): void
    {
        $this->memberPreferences[$member->id] = [
            'role' => $member->role,
            'replacement_opt_in' => (bool) $member->replacement_opt_in,
            'eligible_days' => array_map('intval', (array) ($member->eligible_days ?: range(0, 6))),
            'eligible_shift_types' => array_values((array) $member->eligible_shift_types),
        ];
    }

    private function eligibleCaregiverProfilesQuery(): Builder
    {
        return CaregiverProfile::query()
            ->select([
                'caregiver_profiles.id', 'caregiver_profiles.user_id', 'caregiver_profiles.slug',
                'caregiver_profiles.profile_photo_path', 'caregiver_profiles.status', 'caregiver_profiles.bio',
                'caregiver_profiles.years_experience', 'caregiver_profiles.service_area_zip',
                'caregiver_profiles.is_accepting_new_clients', 'caregiver_profiles.identity_verified_at',
                'caregiver_profiles.identity_verification_status', 'caregiver_profiles.background_check_verified_at',
                'caregiver_profiles.top_caregiver', 'caregiver_profiles.average_rating',
                'caregiver_profiles.reviews_count', 'caregiver_profiles.reliability_score',
                'caregiver_profiles.completed_bookings_count',
            ])
            ->with([
                'user:id,name,role,city,state',
                'skills:id,name',
            ])
            ->where('status', 'active')
            ->whereHas('user', fn (Builder $query) => $query->where('role', 'caregiver'));
    }

    /**
     * @return array<int, array{key:string,title:string,description:string,caregivers:Collection}>
     */
    private function caregiverInitialSections(): array
    {
        $familyId = (int) auth()->id();
        $previousIds = CareBooking::query()
            ->where('family_user_id', $familyId)
            ->where('status', '!=', CareBooking::STATUS_CANCELLED)
            ->selectRaw('caregiver_user_id, MAX(id) as latest_booking_id')
            ->groupBy('caregiver_user_id')
            ->orderByDesc('latest_booking_id')
            ->limit(6)
            ->pluck('caregiver_user_id');
        $favoriteIds = FamilyCaregiverFavorite::query()
            ->where('family_user_id', $familyId)
            ->whereNotIn('caregiver_user_id', $previousIds)
            ->latest('created_at')
            ->limit(6)
            ->pluck('caregiver_user_id');
        $representedIds = $previousIds
            ->merge($favoriteIds)
            ->merge($this->plan->rosterMembers()->pluck('caregiver_user_id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $browseCaregivers = $this->browseCaregiverProfiles($representedIds);
        $serviceCity = trim((string) data_get($this->plan->address_snapshot, 'city'));

        return [
            [
                'key' => 'previous',
                'title' => 'Caregivers you hired before',
                'description' => 'People who have already provided care for your family.',
                'caregivers' => $this->caregiverProfilesForIds($previousIds),
            ],
            [
                'key' => 'favorites',
                'title' => 'Saved caregivers',
                'description' => 'Caregivers you saved while browsing profiles.',
                'caregivers' => $this->caregiverProfilesForIds($favoriteIds),
            ],
            [
                'key' => 'browse',
                'title' => $serviceCity !== '' ? 'Caregivers near '.$serviceCity : 'Browse caregivers on LoLo Care',
                'description' => 'Active caregivers are ordered by service-area match, then ratings, reviews, and completed care.',
                'caregivers' => $browseCaregivers,
            ],
        ];
    }

    /**
     * @param  Collection<int, int>  $excludedIds
     */
    private function browseCaregiverProfiles(Collection $excludedIds): Collection
    {
        $zip = trim((string) data_get($this->plan->address_snapshot, 'zip'));
        $city = mb_strtolower(trim((string) data_get($this->plan->address_snapshot, 'city')));
        $state = mb_strtolower(trim((string) data_get($this->plan->address_snapshot, 'state')));

        return $this->eligibleCaregiverProfilesQuery()
            ->join('users as coverage_browse_users', 'coverage_browse_users.id', '=', 'caregiver_profiles.user_id')
            ->where('caregiver_profiles.is_accepting_new_clients', true)
            ->whereNotNull('caregiver_profiles.bio')
            ->where('caregiver_profiles.bio', '!=', '')
            ->whereNotNull('caregiver_profiles.platform_hourly_rate')
            ->whereNotNull('caregiver_profiles.years_experience')
            ->whereNotNull('caregiver_profiles.service_area_zip')
            ->whereNotNull('caregiver_profiles.service_radius_miles')
            ->whereHas('skills')
            ->whereHas('languages')
            ->whereHas('availabilities')
            ->when($excludedIds->isNotEmpty(), fn (Builder $query) => $query->whereNotIn('caregiver_profiles.user_id', $excludedIds->all()))
            ->when($zip !== '', fn (Builder $query) => $query->orderByRaw(
                'CASE WHEN caregiver_profiles.service_area_zip = ? THEN 0 ELSE 1 END',
                [$zip],
            ))
            ->when($city !== '' && $state !== '', fn (Builder $query) => $query->orderByRaw(
                "CASE WHEN LOWER(COALESCE(coverage_browse_users.city, '')) = ? AND LOWER(COALESCE(coverage_browse_users.state, '')) = ? THEN 0 ELSE 1 END",
                [$city, $state],
            ))
            ->when($state !== '', fn (Builder $query) => $query->orderByRaw(
                "CASE WHEN LOWER(COALESCE(coverage_browse_users.state, '')) = ? THEN 0 ELSE 1 END",
                [$state],
            ))
            ->orderByDesc('caregiver_profiles.top_caregiver')
            ->orderByDesc('caregiver_profiles.average_rating')
            ->orderByDesc('caregiver_profiles.reviews_count')
            ->orderByDesc('caregiver_profiles.completed_bookings_count')
            ->orderBy('coverage_browse_users.name')
            ->limit(12)
            ->get();
    }

    /**
     * @param  Collection<int, int|string>  $ids
     */
    private function caregiverProfilesForIds(Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        $profiles = $this->eligibleCaregiverProfilesQuery()
            ->whereIn('user_id', $ids->all())
            ->get()
            ->keyBy('user_id');

        return $ids
            ->map(fn ($id) => $profiles->get((int) $id))
            ->filter()
            ->values();
    }

    private function localDate(string $value): ?Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $value, $this->plan->timezone)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        return $date->toDateString() === $value ? $date : null;
    }
}
