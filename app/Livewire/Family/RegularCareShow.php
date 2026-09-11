<?php

namespace App\Livewire\Family;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareBooking;
use App\Models\CareBookingChangeRequest;
use App\Models\CareBookingPayment;
use App\Models\CareBookingTimeCorrection;
use App\Models\CarePlan;
use App\Models\CareRequestConversation;
use App\Models\CompletedExtraVisitRequest;
use App\Services\CareRecipientProfiles\CareRecipientProfilePresenter;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\RegularCare\CarePlanService;
use App\Services\RegularCare\CompletedExtraVisitService;
use App\Support\WeeklySchedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class RegularCareShow extends Component
{
    use WithPagination;

    public CarePlan $plan;

    #[Url(as: 'tab', history: true)]
    public string $activeTab = 'overview';

    #[Url(as: 'visit_filter', history: true)]
    public string $visitFilter = 'upcoming';

    public bool $showHelp = false;

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
        $this->normalizeNavigation();
        $this->fillManagementDefaults();
    }

    public function setActiveTab(string $tab): void
    {
        if (in_array($tab, ['overview', 'caregivers', 'visits', 'details'], true)) {
            $this->activeTab = $tab;
        }
    }

    public function setVisitFilter(string $filter): void
    {
        if (in_array($filter, ['upcoming', 'attention', 'past', 'cancelled', 'all'], true)) {
            $this->visitFilter = $filter;
            $this->activeTab = 'visits';
            $this->resetPage('visitsPage');
        }
    }

    private function normalizeNavigation(): void
    {
        if (! in_array($this->activeTab, ['overview', 'caregivers', 'visits', 'details'], true)) {
            $this->activeTab = 'overview';
        }
        if (! in_array($this->visitFilter, ['upcoming', 'attention', 'past', 'cancelled', 'all'], true)) {
            $this->visitFilter = 'upcoming';
        }
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
        if (! in_array($panel, ['', 'extra', 'schedule', 'pause', 'end'], true) || ! $this->plan->isLive()) {
            return;
        }
        $this->activeTab = 'visits';
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
        session()->flash('status', $this->resumeOn !== '' ? 'Recurring care paused until '.$this->plan->resumes_on?->format('F j').'.' : 'Recurring care paused.');
    }

    public function resumePlan(): void
    {
        app(CarePlanService::class)->resumePlan($this->plan, auth()->user());
        $this->reloadPlan();
        session()->flash('status', 'Recurring care resumed. Your upcoming visits are ready.');
    }

    public function endPlan(): void
    {
        app(CarePlanService::class)->endPlan($this->plan, auth()->user(), $this->cancelNextWhenEnding);
        $this->reloadPlan();
        $this->managePanel = '';
        session()->flash('status', $this->cancelNextWhenEnding
            ? 'Recurring care ended and the next visit was cancelled.'
            : 'Recurring care ended. The next confirmed visit remains scheduled.');
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
        $this->normalizeNavigation();
        $upcomingVisits = $this->upcomingQuery($this->visitQuery())->orderBy('scheduled_start_at')->get();
        $nextVisit = $upcomingVisits->first(fn (CareBooking $booking): bool => in_array($booking->status, [CareBooking::STATUS_IN_PROGRESS, CareBooking::STATUS_PAUSED], true))
            ?? $upcomingVisits->first();
        $attentionVisits = $this->attentionQuery($this->visitQuery())->orderBy('scheduled_start_at')->get()
            ->sortBy(fn (CareBooking $booking): int => collect($this->visitAttention($booking))
                ->every(fn (array $item): bool => $item['optional'] ?? false) ? 1 : 0)
            ->values();
        $visitsQuery = $this->visitQuery();
        if ($this->visitFilter === 'upcoming') {
            $this->upcomingQuery($visitsQuery);
        } elseif ($this->visitFilter === 'attention') {
            $this->attentionQuery($visitsQuery);
        } elseif ($this->visitFilter === 'past') {
            $visitsQuery->whereNotIn('id', $upcomingVisits->pluck('id'))->where('status', '!=', CareBooking::STATUS_CANCELLED);
        } elseif ($this->visitFilter === 'cancelled') {
            $visitsQuery->where('status', CareBooking::STATUS_CANCELLED);
        }
        $visitsQuery->orderBy('scheduled_start_at', in_array($this->visitFilter, ['past', 'cancelled', 'all'], true) ? 'desc' : 'asc');
        $conversation = $this->plan->source_care_request_id
            ? CareRequestConversation::query()->forUser(auth()->user())
                ->where('care_request_id', $this->plan->source_care_request_id)
                ->where('caregiver_user_id', $this->plan->caregiver_user_id)->first()
            : null;

        return view('livewire.family.regular-care-show', [
            'upcomingVisits' => $upcomingVisits,
            'nextVisit' => $nextVisit,
            'attentionVisits' => $attentionVisits,
            'visits' => $visitsQuery->paginate(12, pageName: 'visitsPage'),
            'messageUrl' => $conversation ? route('messages.show', $conversation->id) : null,
            'planStateLabel' => $this->planStateLabel(),
            'completedExtraVisits' => CompletedExtraVisitRequest::query()
                ->with(['caregiver:id,name', 'booking:id,care_request_id,status', 'booking.payment:id,care_booking_id,status,amount_captured_cents,caregiver_amount_cents,currency'])
                ->where('care_plan_id', $this->plan->id)
                ->forFamilyAccount(app(FamilyAccountContext::class)->account(auth()->user()))
                ->where('status', '!=', CompletedExtraVisitRequest::STATUS_SUPERSEDED)
                ->orderByRaw('CASE WHEN status IN ('.implode(',', array_fill(0, count(CompletedExtraVisitRequest::unresolvedStatuses()), '?')).') THEN 0 ELSE 1 END', CompletedExtraVisitRequest::unresolvedStatuses())
                ->latest('version')
                ->get(),
            'completedExtraVisitService' => $completedExtraVisits,
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

    private function visitQuery(): Builder
    {
        return CareBooking::query()->where('care_plan_id', $this->plan->id)
            ->forFamilyAccount(app(FamilyAccountContext::class)->account(auth()->user()))
            ->with([
                'careRequest:id,title,address_line1,address_line2,city,state,zip',
                'caregiver:id,name', 'payment', 'latestTimeCorrection',
                'changeRequests' => fn ($query) => $query->where('status', CareBookingChangeRequest::STATUS_PENDING),
                'reviews' => fn ($query) => $query->where('reviewer_user_id', auth()->id()),
            ]);
    }

    private function upcomingQuery(Builder $query): Builder
    {
        return $query->where(fn (Builder $bookings) => $bookings
            ->whereIn('status', [CareBooking::STATUS_IN_PROGRESS, CareBooking::STATUS_PAUSED])
            ->orWhere(fn (Builder $scheduled) => $scheduled->where('status', CareBooking::STATUS_SCHEDULED)->whereScheduledCheckInNotExpired()));
    }

    private function attentionQuery(Builder $query): Builder
    {
        return $query->where(function (Builder $bookings): void {
            $bookings->where('status', CareBooking::STATUS_DISPUTED)
                ->orWhere(fn (Builder $completed) => $completed
                    ->whereIn('status', [CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED])
                    ->where(fn (Builder $review) => $review->whereNull('family_confirmed_at')
                        ->orWhereDoesntHave('reviews', fn (Builder $reviews) => $reviews->where('reviewer_user_id', auth()->id()))))
                ->orWhereHas('payment', fn (Builder $payment) => $payment->whereIn('status', CareBookingPayment::FAMILY_ACTION_REQUIRED_STATUSES))
                ->orWhereHas('latestTimeCorrection', fn (Builder $correction) => $correction->whereIn('status', CareBookingTimeCorrection::activeStatuses()))
                ->orWhereHas('changeRequests', fn (Builder $change) => $change->where('status', CareBookingChangeRequest::STATUS_PENDING))
                ->orWhere(fn (Builder $missed) => $missed->where('status', CareBooking::STATUS_SCHEDULED)
                    ->where('scheduled_start_at', '<', now()->subMinutes(CareBooking::regularCareCheckInGraceMinutes()))
                    ->where(fn (Builder $override) => $override->whereNull('check_in_override_at')->orWhereNull('check_in_override_by_user_id')));
        });
    }

    public function visitUrl(CareBooking $booking, string $tab = 'shift'): string
    {
        return route('family.requests.show', [
            'careRequest' => $booking->care_request_id, 'tab' => $tab,
            'return_tab' => $this->activeTab, 'visit_filter' => $this->visitFilter,
            'return_page' => $this->getPage('visitsPage'),
        ]);
    }

    public function visitAttention(CareBooking $booking): array
    {
        $items = [];
        $correction = $booking->latestTimeCorrection;
        if ($correction && in_array($correction->status, CareBookingTimeCorrection::activeStatuses(), true)) {
            $items[] = ['label' => $correction->statusLabel(), 'action' => match ($correction->status) {
                CareBookingTimeCorrection::STATUS_PENDING_FAMILY => 'Review corrected hours',
                CareBookingTimeCorrection::STATUS_PAYMENT_ACTION_REQUIRED => 'Confirm payment',
                default => 'View time correction',
            }, 'tab' => 'shift'];
        } elseif (in_array($booking->status, [CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED], true) && ! $booking->family_confirmed_at) {
            $items[] = ['label' => 'Hours awaiting approval', 'action' => 'Review hours', 'tab' => 'shift'];
        }
        if ($booking->payment?->requiresFamilyAction()) {
            $items[] = ['label' => 'Payment needs attention', 'action' => 'Fix payment', 'tab' => 'shift'];
        }
        if ($booking->status === CareBooking::STATUS_DISPUTED) {
            $items[] = ['label' => 'Support is reviewing this visit', 'action' => 'View support review', 'tab' => 'support'];
        }
        if ($booking->status === CareBooking::STATUS_SCHEDULED && $booking->checkInWindowHasClosed()) {
            $items[] = ['label' => 'Check-in missing', 'action' => 'Review visit', 'tab' => 'shift'];
        }
        if ($booking->changeRequests->isNotEmpty()) {
            $needsReply = $booking->changeRequests->contains(fn ($change) => (int) $change->requester_user_id === (int) $booking->caregiver_user_id);
            $items[] = ['label' => $needsReply ? 'Visit change needs your reply' : 'Waiting for a visit-change response', 'action' => 'View visit change', 'tab' => 'shift'];
        }
        if ($items === [] && in_array($booking->status, [CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED], true) && $booking->family_confirmed_at && $booking->reviews->isEmpty()) {
            $items[] = ['label' => 'Caregiver review available', 'action' => 'Leave a review', 'tab' => 'shift', 'optional' => true];
        }

        return $items;
    }

    public function visitStatusLabel(CareBooking $booking): string
    {
        if ($booking->status === CareBooking::STATUS_SCHEDULED && $booking->checkInWindowHasClosed()) {
            return 'Check-in missing';
        }

        return match ($booking->status) {
            CareBooking::STATUS_IN_PROGRESS => 'Happening now',
            CareBooking::STATUS_PAUSED => 'Visit paused',
            CareBooking::STATUS_CANCELLED => $booking->no_show_flag ? 'Caregiver no-show' : 'Cancelled',
            CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED => $booking->family_confirmed_at ? 'Completed' : 'Hours awaiting approval',
            default => ucfirst(str_replace('_', ' ', $booking->status)),
        };
    }

    public function paymentLabel(CareBooking $booking): string
    {
        if ($booking->payment?->requiresFamilyAction()) {
            return 'Payment needs attention';
        }

        return match ($booking->payment?->status) {
            CareBookingPayment::STATUS_AUTHORIZED => 'Card authorized',
            CareBookingPayment::STATUS_CAPTURED, CareBookingPayment::STATUS_TRANSFERRED => 'Paid',
            CareBookingPayment::STATUS_PARTIALLY_REFUNDED => 'Partially refunded',
            CareBookingPayment::STATUS_REFUNDED => 'Refunded',
            CareBookingPayment::STATUS_CANCELLED => 'Payment cancelled',
            CareBookingPayment::STATUS_TRANSFER_FAILED => 'Paid · transfer needs attention',
            default => 'Payment not confirmed',
        };
    }

    private function planStateLabel(): string
    {
        return match ($this->plan->status) {
            CarePlan::STATUS_PENDING_CAREGIVER => 'Waiting for '.$this->plan->caregiver?->name,
            CarePlan::STATUS_COUNTERED => 'New schedule proposed',
            CarePlan::STATUS_ACTIVE => 'Recurring care is active',
            CarePlan::STATUS_PAYMENT_ATTENTION => 'Payment needs attention',
            CarePlan::STATUS_PAUSED => 'Care is paused',
            CarePlan::STATUS_ENDED => 'Recurring care ended',
            CarePlan::STATUS_CANCELLED => 'Recurring care cancelled',
            CarePlan::STATUS_DECLINED => 'Offer declined',
            CarePlan::STATUS_EXPIRED => 'Offer expired',
            default => 'Draft recurring care',
        };
    }

    private function loadPlan(int $id): CarePlan
    {
        $plan = CarePlan::query()
            ->with([
                'family:id,name,email',
                'caregiver:id,name,email,phone,city,state',
                'caregiver.caregiverProfile.skills',
                'caregiver.caregiverProfile.languages',
                'sourceCareRequest.recipient',
                'sourceCareRequest.thirdPartyContact',
                'sourceCareRequest.applications.caregiver:id,name',
                'sourceCareRequest.invitations.caregiver:id,name',
                'sourceCareBooking:id,status,scheduled_start_at,scheduled_end_at',
                'nextBooking:id,care_request_id,status,scheduled_start_at,scheduled_end_at',
                'nextBooking.payment:id,care_booking_id,status,amount_authorized_cents,authorization_expires_at,last_error',
                'pendingScheduleChanges',
                'scheduleChanges' => fn ($query) => $query->latest(),
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
