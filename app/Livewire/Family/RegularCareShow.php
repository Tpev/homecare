<?php

namespace App\Livewire\Family;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareBookingTimeCorrection;
use App\Models\CarePlan;
use App\Models\CompletedExtraVisitRequest;
use App\Services\CareRecipientProfiles\CareRecipientProfilePresenter;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\RegularCare\CarePlanService;
use App\Services\RegularCare\CompletedExtraVisitService;
use App\Support\WeeklySchedule;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class RegularCareShow extends Component
{
    public CarePlan $plan;

    public string $managePanel = '';

    public array $scheduleDays = [];

    public string $scheduleStartTime = '';

    public string $scheduleEndTime = '';

    public array $scheduleSlots = [];

    public string $scheduleEffectiveOn = '';

    public string $scheduleNote = '';

    public string $extraVisitDate = '';

    public string $extraVisitTime = '';

    public int $extraVisitDuration = 120;

    public string $extraVisitNote = '';

    public string $pauseFrom = '';

    public string $resumeOn = '';

    public bool $cancelNextWhenEnding = false;

    /** @var array<int,string> */
    public array $completedExtraVisitResponseNotes = [];

    public function mount(int $carePlan): void
    {
        $this->plan = $this->loadPlan($carePlan);
        $this->fillManagementDefaults();
    }

    public function acceptCounter(): void
    {
        try {
            app(CarePlanService::class)->acceptCounter($this->plan, auth()->user());
        } catch (PaymentException $exception) {
            session()->flash('status', $exception->userMessage);

            return;
        }

        $this->plan = $this->loadPlan($this->plan->id);
        session()->flash('status', 'Counter schedule accepted. The next visit is now booked.');
    }

    public function openManagePanel(string $panel): void
    {
        $this->managePanel = $this->managePanel === $panel ? '' : $panel;
        $this->resetValidation();
    }

    public function requestScheduleChange(): void
    {
        $this->syncScheduleSlots();
        $rules = [
            'scheduleDays' => ['required', 'array', 'min:1'],
            'scheduleDays.*' => ['integer', 'between:0,6'],
            'scheduleEffectiveOn' => ['required', 'date', 'after:today'],
            'scheduleNote' => ['nullable', 'string', 'max:1000'],
        ];
        foreach ($this->normalizedScheduleDays() as $day) {
            $rules['scheduleSlots.'.$day.'.start_time'] = ['required', 'date_format:H:i'];
            $rules['scheduleSlots.'.$day.'.end_time'] = ['required', 'date_format:H:i', 'after:scheduleSlots.'.$day.'.start_time'];
        }
        $this->validate($rules);

        app(CarePlanService::class)->requestScheduleChange($this->plan, auth()->user(), [
            'schedule_days' => $this->scheduleDays,
            'schedule_start_time' => $this->scheduleStartTime,
            'schedule_end_time' => $this->scheduleEndTime,
            'schedule_slots' => $this->normalizedScheduleSlots(),
            'starts_on' => $this->scheduleEffectiveOn,
            'effective_on' => $this->scheduleEffectiveOn,
            'ends_on' => $this->plan->ends_on?->toDateString(),
            'note' => $this->scheduleNote,
        ]);

        $this->reloadPlan();
        $this->managePanel = '';
        session()->flash('status', 'Schedule change sent to '.$this->plan->caregiver?->name.'. Current visits stay unchanged until they accept.');
    }

    public function requestExtraVisit(): void
    {
        $this->validate([
            'extraVisitDate' => ['required', 'date', 'after_or_equal:today'],
            'extraVisitTime' => ['required', 'date_format:H:i'],
            'extraVisitDuration' => ['required', 'integer', 'between:60,480'],
            'extraVisitNote' => ['nullable', 'string', 'max:1000'],
        ]);

        $timezone = $this->plan->timezone ?: config('app.timezone');
        $start = Carbon::parse($this->extraVisitDate.' '.$this->extraVisitTime, $timezone)
            ->setTimezone(config('app.timezone'));
        $end = $start->copy()->addMinutes($this->extraVisitDuration);
        app(CarePlanService::class)->requestExtraVisit($this->plan, auth()->user(), $start, $end, $this->extraVisitNote);

        $this->reloadPlan();
        $this->managePanel = '';
        session()->flash('status', 'Extra visit request sent. It will appear as a visit after the caregiver accepts.');
    }

    public function skipVisit(int $bookingId): void
    {
        $booking = $this->plan->generatedBookings()->whereKey($bookingId)->firstOrFail();
        $skipped = app(CarePlanService::class)->skipVisit($this->plan, $booking, auth()->user());
        $this->reloadPlan();
        session()->flash('status', $skipped->late_cancel_flag
            ? 'That visit was skipped inside the 24-hour cancellation window. Your regular schedule continues.'
            : 'That visit was skipped. Your regular schedule continues.');
    }

    public function pausePlan(): void
    {
        $this->validate([
            'pauseFrom' => ['required', 'date', 'after_or_equal:today'],
            'resumeOn' => ['nullable', 'date', 'after:pauseFrom'],
        ]);

        app(CarePlanService::class)->pausePlan(
            $this->plan,
            auth()->user(),
            Carbon::parse($this->pauseFrom)->startOfDay(),
            $this->resumeOn !== '' ? Carbon::parse($this->resumeOn)->startOfDay() : null
        );
        $this->reloadPlan();
        $this->managePanel = '';
        session()->flash('status', $this->resumeOn !== '' ? 'Regular care paused until '.$this->plan->resumes_on?->format('F j').'.' : 'Regular care paused.');
    }

    public function resumePlan(): void
    {
        app(CarePlanService::class)->resumePlan($this->plan, auth()->user());
        $this->reloadPlan();
        session()->flash('status', 'Regular care resumed. Your upcoming visits are ready.');
    }

    public function endPlan(): void
    {
        app(CarePlanService::class)->endPlan($this->plan, auth()->user(), $this->cancelNextWhenEnding);
        $this->reloadPlan();
        $this->managePanel = '';
        session()->flash('status', $this->cancelNextWhenEnding
            ? 'Regular care ended and the next visit was cancelled.'
            : 'Regular care ended. The next confirmed visit remains scheduled.');
    }

    public function approveCompletedExtraVisit(int $requestId): void
    {
        $request = $this->ownedCompletedExtraVisit($requestId);
        $result = app(CompletedExtraVisitService::class)->approve($request, auth()->user());
        $this->reloadPlan();
        session()->flash('status', match ($result->status) {
            CompletedExtraVisitRequest::STATUS_APPLIED => 'Extra visit approved, recorded, and payment processed.',
            CompletedExtraVisitRequest::STATUS_PAYMENT_ACTION_REQUIRED => 'The visit is approved. Confirm your payment method to finish processing.',
            default => 'Extra visit approved. Payment is processing safely.',
        });
    }

    public function requestCompletedExtraVisitChanges(int $requestId): void
    {
        $note = (string) ($this->completedExtraVisitResponseNotes[$requestId] ?? '');
        app(CompletedExtraVisitService::class)->requestChanges(
            $this->ownedCompletedExtraVisit($requestId),
            auth()->user(),
            $note,
        );
        unset($this->completedExtraVisitResponseNotes[$requestId]);
        $this->reloadPlan();
        session()->flash('status', 'Your change request was sent. No payment was made.');
    }

    public function disputeCompletedExtraVisit(int $requestId): void
    {
        $note = (string) ($this->completedExtraVisitResponseNotes[$requestId] ?? '');
        app(CompletedExtraVisitService::class)->dispute(
            $this->ownedCompletedExtraVisit($requestId),
            auth()->user(),
            $note,
        );
        unset($this->completedExtraVisitResponseNotes[$requestId]);
        $this->reloadPlan();
        session()->flash('status', 'The visit was not approved. LoLo Care support can review the preserved report.');
    }

    public function retryCompletedExtraVisitPayment(int $requestId): void
    {
        $result = app(CompletedExtraVisitService::class)->resumePayment(
            $this->ownedCompletedExtraVisit($requestId),
            auth()->user(),
        );
        $this->reloadPlan();
        session()->flash('status', $result->status === CompletedExtraVisitRequest::STATUS_APPLIED
            ? 'Payment confirmed. The extra visit is now in care history.'
            : 'Payment still needs attention. Open Billing & Payments to confirm your card.');
    }

    public function escalateCompletedExtraVisit(int $requestId): void
    {
        $note = (string) ($this->completedExtraVisitResponseNotes[$requestId] ?? '');
        app(CompletedExtraVisitService::class)->escalate(
            $this->ownedCompletedExtraVisit($requestId),
            auth()->user(),
            $note,
        );
        unset($this->completedExtraVisitResponseNotes[$requestId]);
        $this->reloadPlan();
        session()->flash('status', 'LoLo Care support will review this extra visit. No payment was made.');
    }

    public function render(CarePlanService $plans, CompletedExtraVisitService $completedExtraVisits)
    {
        $upcomingVisits = $plans->upcomingVisits($this->plan, 4);
        $laterVisits = array_values(array_filter(
            $upcomingVisits,
            fn (array $visit): bool => (int) ($visit['booking']?->id ?? 0) !== (int) $this->plan->next_booking_id
        ));

        return view('livewire.family.regular-care-show', [
            'completedExtraVisits' => CompletedExtraVisitRequest::query()
                ->with(['caregiver:id,name', 'booking:id,care_request_id,status', 'booking.payment:id,care_booking_id,status,amount_captured_cents,caregiver_amount_cents,currency'])
                ->where('care_plan_id', $this->plan->id)
                ->forFamilyAccount(app(FamilyAccountContext::class)->account(auth()->user()))
                ->where('status', '!=', CompletedExtraVisitRequest::STATUS_SUPERSEDED)
                ->latest('version')
                ->limit(12)
                ->get(),
            'completedExtraVisitService' => $completedExtraVisits,
            'pendingTimeCorrections' => CareBookingTimeCorrection::query()
                ->with(['booking:id,care_request_id,care_plan_id,scheduled_start_at', 'requester:id,name'])
                ->forFamilyAccount(app(FamilyAccountContext::class)->account(auth()->user()))
                ->whereHas('booking', fn ($query) => $query->where('care_plan_id', $this->plan->id))
                ->whereIn('status', [
                    CareBookingTimeCorrection::STATUS_PENDING_FAMILY,
                    CareBookingTimeCorrection::STATUS_PAYMENT_ACTION_REQUIRED,
                ])
                ->orderBy('submitted_at')
                ->get(),
            'laterVisits' => array_slice($laterVisits, 0, 3),
            'counterVisits' => $this->plan->status === CarePlan::STATUS_COUNTERED
                ? $plans->upcomingVisits($this->plan, 3, true)
                : [],
            'scheduleLabel' => $plans->scheduleLabel($this->plan),
            'counterScheduleLabel' => $this->plan->status === CarePlan::STATUS_COUNTERED
                ? $plans->scheduleLabel($this->plan, true)
                : null,
            'careProfileSnapshot' => app(CareRecipientProfilePresenter::class)
                ->forCarePlan(auth()->user(), $this->plan),
        ]);
    }

    private function loadPlan(int $id): CarePlan
    {
        $plan = CarePlan::query()
            ->with([
                'family:id,name,email',
                'caregiver:id,name,email,phone,city,state',
                'caregiver.caregiverProfile:id,user_id,profile_photo_path,average_rating,reviews_count,platform_hourly_rate',
                'sourceCareRequest:id,title',
                'sourceCareBooking:id,status,scheduled_start_at,scheduled_end_at',
                'nextBooking:id,care_request_id,status,scheduled_start_at,scheduled_end_at',
                'nextBooking.payment:id,care_booking_id,status,amount_authorized_cents,authorization_expires_at,last_error',
                'pendingScheduleChanges',
                'generatedBookings' => fn ($query) => $query
                    ->with(['careRequest:id,title,address_line1,address_line2,city,state,zip', 'payment:id,care_booking_id,status,amount_authorized_cents,last_error'])
                    ->where('scheduled_start_at', '>=', now()->subDay())
                    ->orderBy('scheduled_start_at')
                    ->limit(12),
            ])
            ->findOrFail($id);

        abort_unless(app(FamilyAccountContext::class)->canAccessRecord(auth()->user(), $plan), 403);

        return $plan;
    }

    private function reloadPlan(): void
    {
        $this->plan = $this->loadPlan($this->plan->id);
    }

    private function fillManagementDefaults(): void
    {
        $slots = $this->plan->weeklyScheduleSlots();
        $first = WeeklySchedule::first($slots);
        $this->scheduleDays = array_map('strval', WeeklySchedule::days($slots));
        $this->scheduleStartTime = (string) ($first['start_time'] ?? '');
        $this->scheduleEndTime = (string) ($first['end_time'] ?? '');
        $this->scheduleSlots = collect($slots)->mapWithKeys(fn (array $slot): array => [
            (string) $slot['day'] => [
                'start_time' => $slot['start_time'],
                'end_time' => $slot['end_time'],
            ],
        ])->all();
        $this->scheduleEffectiveOn = now()->addDays(2)->toDateString();
        $this->extraVisitDate = now()->addDays(2)->toDateString();
        $this->extraVisitTime = $this->scheduleStartTime;
        $this->pauseFrom = now()->addDay()->toDateString();
    }

    public function updatedScheduleDays(): void
    {
        $this->syncScheduleSlots();
    }

    public function updatedScheduleStartTime(string $value): void
    {
        foreach (array_keys($this->scheduleSlots) as $day) {
            $this->scheduleSlots[$day]['start_time'] = $value;
        }
    }

    public function updatedScheduleEndTime(string $value): void
    {
        foreach (array_keys($this->scheduleSlots) as $day) {
            $this->scheduleSlots[$day]['end_time'] = $value;
        }
    }

    private function syncScheduleSlots(): void
    {
        $existing = $this->scheduleSlots;
        $template = collect($existing)->first(fn (mixed $slot): bool => is_array($slot) && filled($slot['start_time'] ?? null)) ?? [
            'start_time' => $this->scheduleStartTime,
            'end_time' => $this->scheduleEndTime,
        ];

        $this->scheduleSlots = collect($this->normalizedScheduleDays())->mapWithKeys(function (int $day) use ($existing, $template): array {
            $slot = $existing[(string) $day] ?? $existing[$day] ?? $template;

            return [(string) $day => [
                'start_time' => substr((string) ($slot['start_time'] ?? $template['start_time'] ?? ''), 0, 5),
                'end_time' => substr((string) ($slot['end_time'] ?? $template['end_time'] ?? ''), 0, 5),
            ]];
        })->all();

        $first = WeeklySchedule::first($this->normalizedScheduleSlots());
        $this->scheduleStartTime = (string) ($first['start_time'] ?? '');
        $this->scheduleEndTime = (string) ($first['end_time'] ?? '');
    }

    private function normalizedScheduleDays(): array
    {
        return collect($this->scheduleDays)
            ->map(fn ($day): int => (int) $day)
            ->filter(fn (int $day): bool => $day >= 0 && $day <= 6)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function normalizedScheduleSlots(): array
    {
        return WeeklySchedule::normalize(
            $this->scheduleSlots,
            $this->normalizedScheduleDays(),
            $this->scheduleStartTime,
            $this->scheduleEndTime,
        );
    }

    private function ownedCompletedExtraVisit(int $requestId): CompletedExtraVisitRequest
    {
        return CompletedExtraVisitRequest::query()
            ->where('care_plan_id', $this->plan->id)
            ->forFamilyAccount(app(FamilyAccountContext::class)->account(auth()->user()))
            ->findOrFail($requestId);
    }
}
