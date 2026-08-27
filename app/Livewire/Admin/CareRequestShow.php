<?php

namespace App\Livewire\Admin;

use App\Models\CareBooking;
use App\Models\CareRequest;
use App\Services\Booking\BookingTrustService;
use App\Services\CareRecipientProfiles\CareRecipientProfilePresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class CareRequestShow extends Component
{
    public CareRequest $careRequest;

    public string $checkInOverrideReason = '';

    public function mount(CareRequest $careRequest): void
    {
        $this->careRequest = $this->loadRequest($careRequest->id);
    }

    public function forceRequestStatus(string $status): void
    {
        if (! in_array($status, [
            CareRequest::STATUS_DRAFT,
            CareRequest::STATUS_OPEN,
            CareRequest::STATUS_FILLED,
            CareRequest::STATUS_CANCELLED,
            CareRequest::STATUS_EXPIRED,
        ], true)) {
            return;
        }

        $this->careRequest->update(['status' => $status]);
        $this->refreshRequest();
        session()->flash('status', 'Request status updated to '.strtoupper($status).'.');
    }

    public function forceBookingStatus(string $status): void
    {
        if (! in_array($status, [
            CareBooking::STATUS_SCHEDULED,
            CareBooking::STATUS_IN_PROGRESS,
            CareBooking::STATUS_PAUSED,
            CareBooking::STATUS_COMPLETED,
            CareBooking::STATUS_DISPUTED,
            CareBooking::STATUS_REVIEWED,
            CareBooking::STATUS_CANCELLED,
        ], true)) {
            return;
        }

        $booking = $this->careRequest->booking;
        if (! $booking) {
            $this->addError('booking', 'No booking exists for this request.');

            return;
        }

        $payload = ['status' => $status];
        if ($status === CareBooking::STATUS_COMPLETED && ! $booking->completed_at) {
            $payload['completed_at'] = now();
        }
        if ($status === CareBooking::STATUS_CANCELLED && ! $booking->cancelled_at) {
            $payload['cancelled_at'] = now();
            $payload['cancelled_by_user_id'] = auth()->id();
            $payload['cancellation_reason'] = 'Admin override from request detail panel.';
        }

        $booking->update($payload);
        $this->refreshRequest();
        session()->flash('status', 'Booking status updated to '.strtoupper($status).'.');
    }

    public function deleteRequest(): void
    {
        $id = $this->careRequest->id;

        try {
            $this->careRequest->delete();
        } catch (QueryException) {
            $this->addError('delete', 'Could not delete this request because related records are protected.');

            return;
        }

        session()->flash('status', 'Request #'.$id.' deleted.');
        $this->redirect(route('admin.requests.index', absolute: false), navigate: true);
    }

    public function allowRegularCareCheckIn(BookingTrustService $trust): void
    {
        $this->validate(['checkInOverrideReason' => ['required', 'string', 'min:8', 'max:1000']]);
        $booking = $this->careRequest->booking;
        if (! $booking?->care_plan_id) {
            $this->addError('checkInOverrideReason', 'This is not a recurring care visit.');

            return;
        }

        $booking->forceFill([
            'check_in_override_at' => now(),
            'check_in_override_by_user_id' => auth()->id(),
            'check_in_override_reason' => trim($this->checkInOverrideReason),
        ])->save();
        $trust->recordEvent($booking, auth()->id(), 'admin', 'regular_care_check_in_override', [
            'reason' => trim($this->checkInOverrideReason),
        ]);
        $this->checkInOverrideReason = '';
        $this->refreshRequest();
        session()->flash('status', 'Check-in override recorded for this visit.');
    }

    public function render(CareRecipientProfilePresenter $profiles): View
    {
        return view('livewire.admin.care-request-show', [
            'careProfileSnapshot' => $profiles->forCareRequest(auth()->user(), $this->careRequest),
        ]);
    }

    private function refreshRequest(): void
    {
        $this->careRequest = $this->loadRequest($this->careRequest->id);
    }

    private function loadRequest(int $requestId): CareRequest
    {
        return CareRequest::query()
            ->with([
                'family:id,name,email,phone,city,state',
                'recipient',
                'thirdPartyContact',
                'tasks',
                'applications' => fn ($query) => $query->with([
                    'caregiver:id,name,email,phone,city,state',
                    'caregiver.caregiverProfile:id,user_id,status,average_rating,reviews_count,platform_hourly_rate',
                    'conversation:id,care_request_application_id,care_request_id,caregiver_user_id,last_message_at',
                    'booking:id,care_request_id,care_request_application_id,status,scheduled_start_at,scheduled_end_at,completed_at',
                ])->latest(),
                'invitations' => fn ($query) => $query->with(['caregiver:id,name,email'])->latest(),
                'conversations:id,care_request_id,family_user_id,caregiver_user_id,last_message_at',
                'booking',
                'booking.caregiver:id,name,email',
                'booking.payment',
                'booking.reviews.reviewer:id,name',
                'reviews.reviewer:id,name',
            ])
            ->withCount(['applications', 'invitations', 'conversations', 'tasks'])
            ->findOrFail($requestId);
    }
}
