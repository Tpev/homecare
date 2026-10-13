<?php

namespace App\Livewire\Family;

use App\Models\CareArrangement;
use App\Models\CareBooking;
use App\Services\Family\SharedCarePresentationService;
use App\Services\RegularCare\SharedCareService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class SharedCareShow extends Component
{
    #[Locked]
    public int $arrangementId;

    #[Url(except: '')]
    public string $week = '';

    #[Locked]
    public bool $embedded = false;

    public string $scheduleView = 'week';

    #[Url(as: 'visit', except: null)]
    public ?int $selectedNeedId = null;

    public string $tab = 'schedule';

    public string $panel = '';

    public int $planId = 0;

    public ?int $needId = null;

    public string $endFrom = '';

    public bool $stillNeeded = true;

    public bool $findReplacement = true;

    public string $visitDate = '';

    public string $visitStart = '08:00';

    public string $visitEnd = '14:00';

    public string $visitNote = '';

    public function mount(int $arrangement, bool $embedded = false): void
    {
        $this->arrangementId = $arrangement;
        $this->embedded = $embedded;
        $care = $this->care();
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->week) || ! strtotime($this->week)) {
            $this->week = now($care->timezone)->max(Carbon::parse($care->starts_on->toDateString(), $care->timezone))->startOfWeek()->toDateString();
        }
        $this->endFrom = now($care->timezone)->addDay()->toDateString();
        $this->visitDate = $this->endFrom;
    }

    private function care(): CareArrangement
    {
        $care = CareArrangement::with(['sourceRequest.recipient', 'plans.caregiver.caregiverProfile', 'plans.generatedBookings.payment'])->findOrFail($this->arrangementId);
        app(SharedCareService::class)->assertFamily($care, auth()->user());

        return $care;
    }

    public function moveWeek(int $direction): void
    {
        $this->week = Carbon::parse($this->week)->addWeeks($direction < 0 ? -1 : 1)->toDateString();
        $this->selectedNeedId = null;
        $this->panel = '';
    }

    public function currentWeek(): void
    {
        $care = $this->care();
        $this->week = now($care->timezone)->startOfWeek()->toDateString();
        $this->selectedNeedId = null;
        $this->panel = '';
    }

    public function updatedVisitDate(): void
    {
        $this->planId = 0;
    }

    public function selectNeed(int $needId): void
    {
        $this->care()->needs()->findOrFail($needId);
        $this->selectedNeedId = $needId;
        $this->panel = '';
    }

    public function openCancel(int $needId, bool $stillNeeded = true): void
    {
        $this->care()->needs()->findOrFail($needId);
        $this->needId = $needId;
        $this->stillNeeded = $stillNeeded;
        $this->panel = 'cancel';
        $this->resetValidation();
    }

    public function confirmCancel(): void
    {
        if ($this->needId) {
            $this->cancelVisit($this->needId, $this->stillNeeded);
            $this->selectedNeedId = $this->needId;
            $this->panel = $this->stillNeeded ? 'extra' : '';
            if ($this->stillNeeded) {
                $this->openExtra($this->needId);
            }
        }
    }

    public function openDate(string $date): void
    {
        $this->openExtra();
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date)) {
            $this->visitDate = $date;
        }
    }

    public function openEnd(int $planId): void
    {
        $this->care()->plans()->findOrFail($planId);
        $this->planId = $planId;
        $this->panel = 'end';
        $this->stillNeeded = true;
        $this->findReplacement = true;
        $this->resetValidation();
    }

    public function endCare(): void
    {
        $this->validate(['endFrom' => ['required', 'date', 'after_or_equal:today']]);
        $care = $this->care();
        app(SharedCareService::class)->endAssignment($care->plans()->findOrFail($this->planId), auth()->user(), $this->endFrom, $this->stillNeeded, $this->findReplacement);
        $this->panel = '';
        session()->flash('status', $this->stillNeeded ? 'Caregiver assignment updated. The days you still need remain on your schedule.' : 'Caregiver assignment updated. Those recurring days are marked as no longer needed.');
    }

    public function openExtra(?int $needId = null): void
    {
        $care = $this->care();
        $this->needId = $needId;
        $this->planId = 0;
        $this->visitNote = '';
        $this->panel = 'extra';
        $this->resetValidation();
        if ($needId) {
            $need = $care->needs()->findOrFail($needId);
            $this->visitDate = $need->starts_at->copy()->setTimezone($care->timezone)->toDateString();
            $this->visitStart = $need->starts_at->copy()->setTimezone($care->timezone)->format('H:i');
            $this->visitEnd = $need->ends_at->copy()->setTimezone($care->timezone)->format('H:i');
        } else {
            $this->visitDate = now($care->timezone)->addDay()->toDateString();
            $this->visitStart = '08:00';
            $this->visitEnd = '14:00';
        }
    }

    public function sendExtra(): void
    {
        $this->validate(['planId' => ['required', 'integer', 'min:1'], 'visitDate' => ['required', 'date', 'after_or_equal:today'],
            'visitStart' => ['required', 'date_format:H:i'], 'visitEnd' => ['required', 'date_format:H:i', 'after:visitStart'], 'visitNote' => ['nullable', 'string', 'max:1000']]);
        $care = $this->care();
        app(SharedCareService::class)->requestVisit($care, auth()->user(), $this->planId,
            Carbon::parse($this->visitDate.' '.$this->visitStart, $care->timezone), Carbon::parse($this->visitDate.' '.$this->visitEnd, $care->timezone), $this->needId, $this->visitNote);
        $this->week = Carbon::parse($this->visitDate, $care->timezone)->startOfWeek()->toDateString();
        $this->selectedNeedId = $care->needs()->where('starts_at', Carbon::parse($this->visitDate.' '.$this->visitStart, $care->timezone))->value('id');
        $this->panel = '';
        session()->flash('status', 'Visit requested. It is awaiting the caregiver’s acceptance and is not yet booked.');
    }

    public function cancelVisit(int $needId, bool $stillNeeded): void
    {
        $need = $this->care()->needs()->findOrFail($needId);
        app(SharedCareService::class)->cancelNeed($need, auth()->user(), $stillNeeded);
        session()->flash('status', $stillNeeded ? 'Visit cancelled. Care is still needed for these hours.' : 'This date is marked as care not needed.');
    }

    public function withdrawOffer(int $needId): void
    {
        app(SharedCareService::class)->withdrawOffer($this->care()->needs()->findOrFail($needId), auth()->user());
        session()->flash('status', 'Offer withdrawn. You can ask another caregiver.');
    }

    public function restoreNeed(int $needId): void
    {
        $need = $this->care()->needs()->findOrFail($needId);
        app(SharedCareService::class)->restoreNeed($need, auth()->user());
        session()->flash('status', 'Care is needed again for this date. Ask a caregiver to cover it.');
    }

    public function setRecruitment(bool $open): void
    {
        $care = $this->care();
        if ($open && app(SharedCareService::class)->availableSlots($care->sourceRequest) === []) {
            $this->addError('sharedCare', 'All recurring days are assigned or no longer needed. For a single missing date, ask a caregiver to cover that visit.');

            return;
        }
        $care->sourceRequest->update(['status' => $open ? 'open' : 'filled']);
        session()->flash('status', $open ? 'Recruitment is open for remaining days.' : 'Recruitment stopped. Your booked care continues and missing care stays visible.');
    }

    public function render()
    {
        $care = $this->care();
        $this->validate(['week' => ['required', 'date']]);
        $from = Carbon::parse($this->week, $care->timezone)->startOfWeek();
        $presentation = app(SharedCarePresentationService::class);
        $rows = $presentation->rows($care, $from, $from->copy()->addDays(6));
        $selected = $rows->firstWhere('need.id', $this->selectedNeedId)
            ?: $rows->first(fn ($row) => in_array($row['tone'], ['urgent', 'gap']))
            ?: $rows->first();
        $days = collect(range(0, 6))->map(function ($offset) use ($from, $rows, $care) {
            $date = $from->copy()->addDays($offset);

            return ['date' => $date, 'rows' => $rows->filter(fn ($row) => $row['need']->starts_at->copy()->setTimezone($care->timezone)->isSameDay($date))];
        });
        $nextWeekGaps = $presentation->rows($care, $from->copy()->addWeek(), $from->copy()->addDays(13))
            ->filter(fn ($row) => in_array($row['state'], ['Needs caregiver', 'Waiting for reply']) && $row['need']->starts_at->isFuture());
        $applications = $care->sourceRequest->applications()->whereIn('status', ['applied', 'shortlisted'])->with('caregiver.caregiverProfile')->get();
        $required = $rows->where('need.status', 'needed');
        $coveredCount = $required->whereIn('state', ['Booked', 'In progress', 'Completed'])->count();
        $missingCount = $required->where('state', 'Needs caregiver')->count();
        $pendingCount = $required->where('state', 'Waiting for reply')->count();
        $attention = $rows->first(fn ($row) => $row['tone'] === 'urgent' || $row['paymentLabel'] === 'Payment action needed');
        $cancelNeed = $this->panel === 'cancel' ? $care->needs()->find($this->needId) : null;
        $allBookings = CareBooking::whereHas('carePlan', fn ($q) => $q->where('care_arrangement_id', $care->id))->with(['caregiver', 'payment'])->orderByDesc('scheduled_start_at')->limit(120)->get();
        $endPlan = $care->plans->firstWhere('id', $this->planId);
        $endVisits = $this->panel === 'end' && $endPlan && strtotime($this->endFrom)
            ? $endPlan->generatedBookings->where('status', 'scheduled')->filter(fn ($booking) => $booking->scheduled_start_at->gte(Carbon::parse($this->endFrom, $care->timezone))) : collect();
        $extraPrice = null;
        $eligiblePlans = $care->plans->filter(fn ($plan) => $plan->isLive()
            && (! $plan->ends_on || $this->visitDate <= $plan->ends_on->toDateString()));
        if ($this->panel === 'extra' && $endPlan && preg_match('/^\d{2}:\d{2}$/', $this->visitStart) && preg_match('/^\d{2}:\d{2}$/', $this->visitEnd)) {
            $minutes = \App\Support\WeeklySchedule::timeToMinutes($this->visitEnd) - \App\Support\WeeklySchedule::timeToMinutes($this->visitStart);
            if ($minutes > 0) {
                $pricing = app(\App\Support\MarketplacePricing::class);
                $extraPrice = $pricing->currentPricingEnabled() ? $pricing->quoteForPair($care->family_account_id, $endPlan->caregiver_user_id, $minutes)['total_charge_cents'] / 100 : $minutes / 60 * (float) $endPlan->hourly_rate;
            }
        }

        return view('livewire.family.shared-care-show', compact('care', 'from', 'rows', 'days', 'selected', 'nextWeekGaps', 'applications', 'required', 'coveredCount', 'missingCount', 'pendingCount', 'attention', 'cancelNeed', 'allBookings', 'endPlan', 'endVisits', 'extraPrice', 'eligiblePlans'));
    }
}
