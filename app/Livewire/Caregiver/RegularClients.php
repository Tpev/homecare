<?php

namespace App\Livewire\Caregiver;

use App\Models\CarePlan;
use App\Models\CarePlanScheduleChange;
use App\Models\CompletedExtraVisitRequest;
use App\Services\CareRecipientProfiles\CareRecipientProfilePresenter;
use App\Services\RegularCare\CarePlanService;
use App\Services\RegularCare\CompletedExtraVisitService;
use App\Support\CaregiverPrelaunch;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class RegularClients extends Component
{
    public ?int $counterPlanId = null;

    public array $counterScheduleDays = [];

    public string $counterStartTime = '';

    public string $counterEndTime = '';

    public string $counterStartsOn = '';

    public string $counterNote = '';

    public ?int $reportPlanId = null;

    public ?int $reportSupersedesId = null;

    public string $reportDate = '';

    public string $reportStartTime = '';

    public string $reportEndTime = '';

    public int $reportBreakMinutes = 0;

    public string $reportReason = CompletedExtraVisitRequest::REASON_FAMILY_REQUESTED;

    public string $reportExplanation = '';

    public string $reportCareNotes = '';

    public bool $reportAttested = false;

    public bool $reviewingReport = false;

    /** @var array<string,mixed> */
    public array $reportPreview = [];

    public string $reportClientRequestId = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->role === 'caregiver', 403);
    }

    public function acceptOffer(int $planId): void
    {
        if (CaregiverPrelaunch::enabled()) {
            session()->flash('status', CaregiverPrelaunch::message());

            return;
        }

        $plan = $this->findOwnPlan($planId);

        try {
            app(CarePlanService::class)->acceptOffer($plan, auth()->user());
        } catch (ValidationException $exception) {
            throw $exception;
        }

        session()->flash('status', 'Regular care accepted. The next visit is booked.');
    }

    public function declineOffer(int $planId): void
    {
        $plan = $this->findOwnPlan($planId);
        app(CarePlanService::class)->declineOffer($plan, auth()->user());
        session()->flash('status', 'Regular care offer declined.');
    }

    public function respondToChange(int $changeId, bool $accept): void
    {
        $change = CarePlanScheduleChange::query()
            ->whereHas('plan', fn ($query) => $query->where('caregiver_user_id', auth()->id()))
            ->where('status', CarePlanScheduleChange::STATUS_PENDING)
            ->findOrFail($changeId);

        app(CarePlanService::class)->respondToScheduleChange($change, auth()->user(), $accept);
        session()->flash('status', $accept
            ? 'Change accepted. Your visit list has been updated.'
            : 'Change declined. The family has been notified and the current schedule remains.');
    }

    public function openCounter(int $planId): void
    {
        $plan = $this->findOwnPlan($planId);

        if ($plan->status !== CarePlan::STATUS_PENDING_CAREGIVER) {
            return;
        }

        $this->counterPlanId = $plan->id;
        $this->counterScheduleDays = array_map('strval', $plan->schedule_days ?? []);
        $this->counterStartTime = substr((string) $plan->schedule_start_time, 0, 5);
        $this->counterEndTime = substr((string) $plan->schedule_end_time, 0, 5);
        $this->counterStartsOn = $plan->starts_on?->toDateString() ?: now()->addDay()->toDateString();
        $this->counterNote = '';
    }

    public function cancelCounter(): void
    {
        $this->resetCounterForm();
    }

    public function sendCounter(): void
    {
        if (! $this->counterPlanId) {
            return;
        }

        $this->validate([
            'counterScheduleDays' => ['required', 'array', 'min:1'],
            'counterScheduleDays.*' => ['integer', 'between:0,6'],
            'counterStartTime' => ['required', 'date_format:H:i'],
            'counterEndTime' => ['required', 'date_format:H:i'],
            'counterStartsOn' => ['required', 'date', 'after_or_equal:today'],
            'counterNote' => ['nullable', 'string', 'max:1000'],
        ]);

        $plan = $this->findOwnPlan($this->counterPlanId);
        app(CarePlanService::class)->counterOffer($plan, auth()->user(), [
            'schedule_days' => $this->counterScheduleDays,
            'schedule_start_time' => $this->counterStartTime,
            'schedule_end_time' => $this->counterEndTime,
            'starts_on' => $this->counterStartsOn,
            'counter_note' => $this->counterNote,
        ]);

        $this->resetCounterForm();
        session()->flash('status', 'Counter schedule sent to the family.');
    }

    public function openCompletedExtraVisit(int $planId, ?int $requestId = null): void
    {
        $plan = $this->findOwnPlan($planId);
        $service = app(CompletedExtraVisitService::class);
        abort_unless($service->canReport($plan, auth()->user()), 403);

        $this->resetReportForm();
        $this->reportPlanId = $plan->id;
        $this->reportClientRequestId = (string) Str::uuid();
        $timezone = $service->timezoneFor($plan);
        $this->reportDate = now($timezone)->toDateString();
        $this->reportStartTime = substr((string) $plan->schedule_start_time, 0, 5) ?: '09:00';
        $this->reportEndTime = substr((string) $plan->schedule_end_time, 0, 5) ?: '11:00';

        if ($requestId) {
            $request = CompletedExtraVisitRequest::query()
                ->where('caregiver_user_id', auth()->id())
                ->where('care_plan_id', $plan->id)
                ->where('status', CompletedExtraVisitRequest::STATUS_CHANGES_REQUESTED)
                ->findOrFail($requestId);
            $localStart = $request->proposed_started_at->copy()->setTimezone($request->timezone);
            $localEnd = $request->proposed_completed_at->copy()->setTimezone($request->timezone);
            $this->reportSupersedesId = $request->id;
            $this->reportDate = $localStart->toDateString();
            $this->reportStartTime = $localStart->format('H:i');
            $this->reportEndTime = $localEnd->format('H:i');
            $this->reportBreakMinutes = (int) $request->proposed_break_minutes;
            $this->reportReason = $request->reason_code;
            $this->reportExplanation = $request->explanation;
            $this->reportCareNotes = (string) $request->care_notes;
        }
    }

    public function reviewCompletedExtraVisit(): void
    {
        $plan = $this->reportPlan();
        $this->validateReportInput();
        $preview = app(CompletedExtraVisitService::class)->preview(
            $plan,
            auth()->user(),
            $this->reportInput(),
            $this->reportSupersedesId,
        );
        $start = $preview['start']->copy()->setTimezone($preview['timezone']);
        $end = $preview['end']->copy()->setTimezone($preview['timezone']);
        $financial = $preview['financial'];
        $this->reportPreview = [
            'when' => $start->format('l, F j, Y'),
            'time' => $start->format('g:i A').' to '.$end->format('g:i A').' '.$start->format('T'),
            'worked_minutes' => $preview['worked_minutes'],
            'family_charge' => number_format(((int) $financial['total_charge_cents']) / 100, 2),
            'caregiver_payout' => number_format(((int) $financial['caregiver_amount_cents']) / 100, 2),
        ];
        $this->reviewingReport = true;
    }

    public function editCompletedExtraVisit(): void
    {
        $this->reviewingReport = false;
    }

    public function submitCompletedExtraVisit(): void
    {
        if (! $this->reviewingReport) {
            $this->reviewCompletedExtraVisit();

            return;
        }

        $request = app(CompletedExtraVisitService::class)->submit(
            $this->reportPlan(),
            auth()->user(),
            $this->reportInput(),
            $this->reportClientRequestId !== '' ? $this->reportClientRequestId : (string) Str::uuid(),
            $this->reportSupersedesId,
        );
        $this->resetReportForm();
        session()->flash('status', 'Extra visit sent to the family for approval. No payment happens until they approve it.');
        $this->dispatch('completed-extra-visit-submitted', requestId: $request->id);
    }

    public function withdrawCompletedExtraVisit(int $requestId): void
    {
        $request = CompletedExtraVisitRequest::query()->where('caregiver_user_id', auth()->id())->findOrFail($requestId);
        app(CompletedExtraVisitService::class)->withdraw($request, auth()->user());
        session()->flash('status', 'Extra visit report withdrawn. The family was notified and no payment was made.');
    }

    public function closeCompletedExtraVisit(): void
    {
        $this->resetReportForm();
    }

    public function render(CarePlanService $plans, CompletedExtraVisitService $completedExtraVisits)
    {
        $userId = auth()->id();

        $offers = CarePlan::query()
            ->with(['family:id,name,email,city,state', 'nextBooking'])
            ->where('caregiver_user_id', $userId)
            ->whereIn('status', [
                CarePlan::STATUS_PENDING_CAREGIVER,
                CarePlan::STATUS_COUNTERED,
            ])
            ->latest()
            ->get();

        $activePlans = CarePlan::query()
            ->with([
                'family:id,name,email,city,state',
                'sourceCareRequest:id,title',
                'nextBooking:id,care_request_id,status,scheduled_start_at,scheduled_end_at',
                'nextBooking.payment:id,care_booking_id,status',
                'pendingScheduleChanges.requestedBy:id,name',
                'completedExtraVisitRequests' => fn ($query) => $query
                    ->where('status', '!=', CompletedExtraVisitRequest::STATUS_SUPERSEDED)
                    ->with(['booking:id,care_request_id,status', 'booking.payment:id,care_booking_id,status,caregiver_amount_cents'])
                    ->latest('version')
                    ->limit(8),
            ])
            ->where('caregiver_user_id', $userId)
            ->where(function ($query): void {
                $query->whereIn('status', [CarePlan::STATUS_ACTIVE, CarePlan::STATUS_PAYMENT_ATTENTION, CarePlan::STATUS_PAUSED])
                    ->orWhere(function ($ended): void {
                        $ended->where('status', CarePlan::STATUS_ENDED)
                            ->where('ended_at', '>=', now()->subDays(max(0, (int) config('marketplace.completed_extra_visits.ended_plan_grace_days', 30))));
                    });
            })
            ->latest()
            ->get();

        $pendingChanges = CarePlanScheduleChange::query()
            ->with(['plan.family:id,name', 'requestedBy:id,name'])
            ->whereHas('plan', fn ($query) => $query->where('caregiver_user_id', $userId))
            ->where('status', CarePlanScheduleChange::STATUS_PENDING)
            ->oldest()
            ->get();

        $presenter = app(CareRecipientProfilePresenter::class);
        $careProfileSnapshots = $offers->concat($activePlans)
            ->mapWithKeys(fn (CarePlan $plan) => [$plan->id => $presenter->forCarePlan(auth()->user(), $plan)])
            ->filter(fn ($snapshot) => $snapshot !== null)
            ->all();

        return view('livewire.caregiver.regular-clients', [
            'offers' => $offers,
            'activePlans' => $activePlans,
            'pendingChanges' => $pendingChanges,
            'scheduleService' => $plans,
            'prelaunchMode' => CaregiverPrelaunch::enabled(),
            'completedExtraVisitService' => $completedExtraVisits,
            'careProfileSnapshots' => $careProfileSnapshots,
        ]);
    }

    private function findOwnPlan(int $planId): CarePlan
    {
        return CarePlan::query()
            ->with(['family:id,name,email', 'caregiver:id,name,email'])
            ->where('caregiver_user_id', auth()->id())
            ->findOrFail($planId);
    }

    private function resetCounterForm(): void
    {
        $this->counterPlanId = null;
        $this->counterScheduleDays = [];
        $this->counterStartTime = '';
        $this->counterEndTime = '';
        $this->counterStartsOn = '';
        $this->counterNote = '';
    }

    private function reportPlan(): CarePlan
    {
        if (! $this->reportPlanId) {
            throw ValidationException::withMessages(['reportSubmit' => 'Choose a regular-care client first.']);
        }

        return $this->findOwnPlan($this->reportPlanId);
    }

    private function validateReportInput(): void
    {
        $this->validate([
            'reportDate' => ['required', 'date'],
            'reportStartTime' => ['required', 'date_format:H:i'],
            'reportEndTime' => ['required', 'date_format:H:i'],
            'reportBreakMinutes' => ['required', 'integer', 'min:0', 'max:480'],
            'reportReason' => ['required', Rule::in(CompletedExtraVisitRequest::reasonCodes())],
            'reportExplanation' => ['required', 'string', 'min:8', 'max:2000'],
            'reportCareNotes' => ['nullable', 'string', 'max:2000'],
            'reportAttested' => ['accepted'],
        ]);
    }

    /** @return array<string,mixed> */
    private function reportInput(): array
    {
        return [
            'date' => $this->reportDate,
            'start_time' => $this->reportStartTime,
            'end_time' => $this->reportEndTime,
            'break_minutes' => $this->reportBreakMinutes,
            'reason_code' => $this->reportReason,
            'explanation' => $this->reportExplanation,
            'care_notes' => $this->reportCareNotes,
            'attested' => $this->reportAttested,
        ];
    }

    private function resetReportForm(): void
    {
        $this->reportPlanId = null;
        $this->reportSupersedesId = null;
        $this->reportDate = '';
        $this->reportStartTime = '';
        $this->reportEndTime = '';
        $this->reportBreakMinutes = 0;
        $this->reportReason = CompletedExtraVisitRequest::REASON_FAMILY_REQUESTED;
        $this->reportExplanation = '';
        $this->reportCareNotes = '';
        $this->reportAttested = false;
        $this->reviewingReport = false;
        $this->reportPreview = [];
        $this->reportClientRequestId = '';
        $this->resetValidation();
    }
}
