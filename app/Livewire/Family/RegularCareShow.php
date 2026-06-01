<?php

namespace App\Livewire\Family;

use App\Exceptions\Payments\PaymentException;
use App\Models\CarePlan;
use App\Services\RegularCare\CarePlanService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class RegularCareShow extends Component
{
    public CarePlan $plan;

    public function mount(int $carePlan): void
    {
        $this->plan = $this->loadPlan($carePlan);
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

    public function endPlan(): void
    {
        app(CarePlanService::class)->endPlan($this->plan, auth()->user());
        $this->plan = $this->loadPlan($this->plan->id);
        session()->flash('status', 'Regular care plan ended.');
    }

    public function render(CarePlanService $plans)
    {
        return view('livewire.family.regular-care-show', [
            'upcomingVisits' => $plans->upcomingVisits($this->plan, 5),
            'counterVisits' => $this->plan->status === CarePlan::STATUS_COUNTERED
                ? $plans->upcomingVisits($this->plan, 3, true)
                : [],
            'scheduleLabel' => $plans->scheduleLabel($this->plan),
            'counterScheduleLabel' => $this->plan->status === CarePlan::STATUS_COUNTERED
                ? $plans->scheduleLabel($this->plan, true)
                : null,
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
                'nextBooking.payment:id,care_booking_id,status,amount_authorized_cents,authorization_expires_at',
                'generatedBookings' => fn ($query) => $query
                    ->with('careRequest:id,title')
                    ->latest()
                    ->limit(5),
            ])
            ->findOrFail($id);

        abort_unless((int) $plan->family_user_id === (int) auth()->id(), 403);

        return $plan;
    }
}
