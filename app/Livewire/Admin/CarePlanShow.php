<?php

namespace App\Livewire\Admin;

use App\Exceptions\Payments\PaymentException;
use App\Models\CarePlan;
use App\Models\MarketplaceNotificationDelivery;
use App\Services\Payments\BookingPaymentService;
use App\Services\RegularCare\CarePlanOccurrenceService;
use App\Services\RegularCare\CarePlanOperationsService;
use App\Services\RegularCare\CarePlanPaymentWindowService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class CarePlanShow extends Component
{
    use WithPagination;

    public CarePlan $plan;

    public string $operationsReason = '';

    public function mount(CarePlan $carePlan): void
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);
        $this->plan = $this->loadPlan($carePlan->id);
    }

    public function regenerate(CarePlanOccurrenceService $occurrences): void
    {
        $this->assertAdministrator();
        $result = $occurrences->materialize($this->plan);
        $this->reload();
        session()->flash('status', $result['created']->count().' missing visit(s) generated. Existing visits were preserved.');
    }

    public function preparePayments(CarePlanPaymentWindowService $window): void
    {
        $this->assertAdministrator();
        $result = $window->preparePlan($this->plan);
        $this->reload();
        session()->flash('status', $result['ready']->count().' visit(s) protected; '.$result['needs_action']->count().' need family action.');
    }

    public function retryPayment(int $bookingId, BookingPaymentService $payments): void
    {
        $this->assertAdministrator();
        $booking = $this->plan->generatedBookings()->with(['family', 'caregiver.caregiverProfile', 'application', 'payment'])->whereKey($bookingId)->firstOrFail();
        try {
            $payments->retryAuthorizationForBooking($booking);
            session()->flash('status', 'Payment authorization retried for booking #'.$booking->id.'.');
        } catch (PaymentException $exception) {
            session()->flash('status', 'Payment still needs attention: '.$exception->userMessage);
        }
        $this->reload();
    }

    public function changeState(string $state, CarePlanOperationsService $operations): void
    {
        $this->assertAdministrator();
        if (! in_array($state, ['pause', 'resume', 'end'], true)) {
            abort(422, 'Unsupported recurring care state transition.');
        }
        $this->validate(['operationsReason' => ['required', 'string', 'min:8', 'max:1000']]);
        match ($state) {
            'pause' => $operations->pause($this->plan, auth()->user(), $this->operationsReason),
            'resume' => $operations->resume($this->plan, auth()->user(), $this->operationsReason),
            'end' => $operations->end($this->plan, auth()->user(), $this->operationsReason),
        };
        $this->operationsReason = '';
        $this->reload();
        session()->flash('status', 'Recurring care updated by operations.');
    }

    public function render(BookingPaymentService $payments)
    {
        $this->assertAdministrator();
        $bookings = $this->plan->generatedBookings()
            ->with(['careRequest:id,title,address_line1,city,state,zip', 'payment', 'corrections:id,care_booking_id,status,action'])
            ->orderByDesc('scheduled_start_at')
            ->paginate(25, ['*'], 'visitsPage');
        $bookings->getCollection()->each(function ($booking) use ($payments): void {
            $booking->payment_retryable = $booking->status === \App\Models\CareBooking::STATUS_SCHEDULED
                && $payments->canRetryAuthorization($booking);
        });

        $notifications = MarketplaceNotificationDelivery::query()
            ->with('user:id,name')
            ->whereIn('user_id', [$this->plan->family_user_id, $this->plan->caregiver_user_id])
            ->where(function ($query): void {
                $query->where(function ($subject): void {
                    $subject->where('notifiable_type', CarePlan::class)
                        ->where('notifiable_id', $this->plan->id);
                })->orWhere('payload->care_plan_id', $this->plan->id);
            })
            ->latest()
            ->limit(50)
            ->get();

        return view('livewire.admin.care-plan-show', compact('notifications', 'bookings'));
    }

    private function reload(): void
    {
        $this->plan = $this->loadPlan($this->plan->id);
    }

    private function loadPlan(int $id): CarePlan
    {
        return CarePlan::query()->with([
            'family:id,name,email,phone', 'caregiver:id,name,email,phone', 'sourceCareRequest:id,title',
            'scheduleChanges.requestedBy:id,name', 'scheduleChanges.respondedBy:id,name',
            'events.actor:id,name',
            'completedExtraVisitRequests' => fn ($query) => $query->with([
                'caregiver:id,name,email', 'family:id,name,email', 'approvedBy:id,name',
                'booking:id,care_request_id,status', 'booking.payment:id,care_booking_id,status,amount_captured_cents,caregiver_amount_cents,currency,last_error',
                'supportTicket:id,status,subject',
                'notificationDeliveries:id,notifiable_type,notifiable_id,user_id,event_key,channel,status,created_at,sent_at',
            ])->latest('version'),
        ])->findOrFail($id);
    }

    private function assertAdministrator(): void
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);
    }
}
