<?php

namespace App\Livewire\Caregiver;

use App\Models\CarePlan;
use App\Services\RegularCare\CarePlanService;
use App\Support\CaregiverPrelaunch;
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

    public function render(CarePlanService $plans)
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
                'nextBooking:id,care_request_id,status,scheduled_start_at,scheduled_end_at',
                'nextBooking.payment:id,care_booking_id,status',
            ])
            ->where('caregiver_user_id', $userId)
            ->whereIn('status', [
                CarePlan::STATUS_ACTIVE,
                CarePlan::STATUS_PAYMENT_ATTENTION,
                CarePlan::STATUS_PAUSED,
            ])
            ->latest()
            ->get();

        return view('livewire.caregiver.regular-clients', [
            'offers' => $offers,
            'activePlans' => $activePlans,
            'scheduleService' => $plans,
            'prelaunchMode' => CaregiverPrelaunch::enabled(),
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
}
