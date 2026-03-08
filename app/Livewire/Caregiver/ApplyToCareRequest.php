<?php

namespace App\Livewire\Caregiver;

use App\Models\CareBooking;
use App\Models\CareBookingChangeRequest;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\CareReview;
use App\Models\SupportTicket;
use App\Notifications\MarketplaceAlert;
use App\Support\FunnelTracker;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ApplyToCareRequest extends Component
{
    public CareRequest $requestItem;
    public ?float $proposed_rate = null;
    public string $cover_note = '';
    public ?CareRequestApplication $existingApplication = null;

    public ?int $reviewRating = null;
    public string $reviewComment = '';

    public string $changeType = CareBookingChangeRequest::TYPE_CANCEL;
    public string $changeReason = '';
    public string $proposedStartAt = '';
    public string $proposedEndAt = '';

    public string $supportSubject = '';
    public string $supportDescription = '';
    public string $supportCategory = 'general';

    public function mount(int $careRequest): void
    {
        $this->requestItem = CareRequest::query()
            ->with(['recipient', 'tasks', 'thirdPartyContact'])
            ->findOrFail($careRequest);

        $this->refreshExistingApplication();

        if ($this->requestItem->status === CareRequest::STATUS_OPEN) {
            abort_unless(auth()->user()->can('apply', $this->requestItem), 403);
        } else {
            abort_unless($this->existingApplication !== null, 403);
        }

        if ($this->existingApplication) {
            $this->proposed_rate = $this->existingApplication->proposed_rate ? (float) $this->existingApplication->proposed_rate : null;
            $this->cover_note = (string) $this->existingApplication->cover_note;
        }
    }

    public function submit(): void
    {
        if (! auth()->user()->caregiverProfile?->isMarketplaceReady()) {
            session()->flash('status', 'Complete your profile before applying to requests.');
            return;
        }

        $this->validate([
            'proposed_rate' => ['required', 'numeric', 'min:15', 'max:200'],
            'cover_note' => ['required', 'string', 'min:40', 'max:2500'],
        ]);

        $existing = CareRequestApplication::query()
            ->where('care_request_id', $this->requestItem->id)
            ->where('caregiver_user_id', auth()->id())
            ->first();

        $status = $existing && in_array($existing->status, [
            CareRequestApplication::STATUS_SHORTLISTED,
            CareRequestApplication::STATUS_HIRED,
        ], true)
            ? $existing->status
            : CareRequestApplication::STATUS_APPLIED;

        $this->existingApplication = CareRequestApplication::query()->updateOrCreate(
            [
                'care_request_id' => $this->requestItem->id,
                'caregiver_user_id' => auth()->id(),
            ],
            [
                'status' => $status,
                'proposed_rate' => $this->proposed_rate,
                'cover_note' => trim($this->cover_note),
            ],
        );

        $this->requestItem->family?->notify(new MarketplaceAlert(
            'New caregiver application',
            auth()->user()->name.' applied to your request.',
            route('family.requests.show', $this->requestItem->id),
            'application_submitted'
        ));

        FunnelTracker::track('care_request_application_submitted', auth()->user(), $this->existingApplication, [
            'care_request_id' => $this->requestItem->id,
        ]);

        session()->flash('status', 'Application sent to family.');
        $this->redirect(route('care-requests.index', absolute: false), navigate: true);
    }

    public function startBooking(): void
    {
        $booking = $this->getManagedBooking();
        if (! $booking || $booking->status !== CareBooking::STATUS_SCHEDULED) {
            return;
        }

        $booking->update([
            'status' => CareBooking::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        $booking->family?->notify(new MarketplaceAlert(
            'Care shift started',
            auth()->user()->name.' marked this shift as in progress.',
            route('family.requests.show', $this->requestItem->id),
            'booking_in_progress'
        ));

        FunnelTracker::track('care_booking_started', auth()->user(), $booking);
        $this->refreshExistingApplication();
        session()->flash('status', 'Shift marked in progress.');
    }

    public function completeBooking(): void
    {
        $booking = $this->getManagedBooking();
        if (! $booking || ! in_array($booking->status, [CareBooking::STATUS_SCHEDULED, CareBooking::STATUS_IN_PROGRESS], true)) {
            return;
        }

        $booking->update([
            'status' => CareBooking::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $booking->family?->notify(new MarketplaceAlert(
            'Care shift completed',
            auth()->user()->name.' marked this shift as completed.',
            route('family.requests.show', $this->requestItem->id),
            'booking_completed'
        ));

        FunnelTracker::track('care_booking_completed', auth()->user(), $booking);
        $this->refreshExistingApplication();
        session()->flash('status', 'Shift marked completed.');
    }

    public function submitReview(): void
    {
        $booking = $this->getManagedBooking();
        if (! $booking || ! in_array($booking->status, [CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED], true)) {
            return;
        }

        $this->validate([
            'reviewRating' => ['required', 'integer', 'min:1', 'max:5'],
            'reviewComment' => ['nullable', 'string', 'max:1500'],
        ]);

        CareReview::query()->updateOrCreate(
            [
                'care_booking_id' => $booking->id,
                'reviewer_user_id' => auth()->id(),
            ],
            [
                'care_request_id' => $this->requestItem->id,
                'reviewee_user_id' => $booking->family_user_id,
                'rating' => $this->reviewRating,
                'comment' => trim($this->reviewComment) ?: null,
            ]
        );

        $booking->update([
            'status' => CareBooking::STATUS_REVIEWED,
            'reviewed_at' => now(),
        ]);

        $booking->family?->notify(new MarketplaceAlert(
            'New review submitted',
            auth()->user()->name.' left a review after the shift.',
            route('family.requests.show', $this->requestItem->id),
            'review_submitted'
        ));

        FunnelTracker::track('care_review_submitted', auth()->user(), $booking, [
            'rating' => $this->reviewRating,
        ]);

        $this->reset(['reviewRating', 'reviewComment']);
        $this->refreshExistingApplication();
        session()->flash('status', 'Review submitted.');
    }

    public function submitChangeRequest(): void
    {
        $booking = $this->getManagedBooking();
        if (! $booking || in_array($booking->status, [CareBooking::STATUS_CANCELLED, CareBooking::STATUS_REVIEWED], true)) {
            return;
        }

        $rules = [
            'changeType' => ['required', Rule::in([CareBookingChangeRequest::TYPE_CANCEL, CareBookingChangeRequest::TYPE_RESCHEDULE])],
            'changeReason' => ['required', 'string', 'min:8', 'max:2000'],
        ];

        if ($this->changeType === CareBookingChangeRequest::TYPE_RESCHEDULE) {
            $rules['proposedStartAt'] = ['required', 'date', 'after:now'];
            $rules['proposedEndAt'] = ['required', 'date', 'after:proposedStartAt'];
        }

        $this->validate($rules);

        $changeRequest = CareBookingChangeRequest::query()->create([
            'care_booking_id' => $booking->id,
            'requester_user_id' => auth()->id(),
            'type' => $this->changeType,
            'status' => CareBookingChangeRequest::STATUS_PENDING,
            'reason' => trim($this->changeReason),
            'proposed_start_at' => $this->changeType === CareBookingChangeRequest::TYPE_RESCHEDULE ? $this->proposedStartAt : null,
            'proposed_end_at' => $this->changeType === CareBookingChangeRequest::TYPE_RESCHEDULE ? $this->proposedEndAt : null,
        ]);

        $booking->family?->notify(new MarketplaceAlert(
            'Booking change request',
            auth()->user()->name.' requested to '.$this->changeType.' this booking.',
            route('family.requests.show', $this->requestItem->id),
            'booking_change_request'
        ));

        FunnelTracker::track('booking_change_requested', auth()->user(), $changeRequest, [
            'type' => $this->changeType,
        ]);

        $this->reset(['changeReason', 'proposedStartAt', 'proposedEndAt']);
        $this->refreshExistingApplication();
        session()->flash('status', 'Change request sent to family.');
    }

    public function resolveChangeRequest(int $changeRequestId, string $decision): void
    {
        $booking = $this->getManagedBooking();
        if (! $booking) {
            return;
        }

        $changeRequest = $booking->changeRequests()
            ->whereKey($changeRequestId)
            ->where('status', CareBookingChangeRequest::STATUS_PENDING)
            ->firstOrFail();

        if ((int) $changeRequest->requester_user_id === (int) auth()->id()) {
            return;
        }

        if ($decision === 'reject') {
            $changeRequest->update([
                'status' => CareBookingChangeRequest::STATUS_REJECTED,
                'resolved_at' => now(),
                'resolved_by_user_id' => auth()->id(),
            ]);

            $changeRequest->requester?->notify(new MarketplaceAlert(
                'Change request rejected',
                'Your booking change request was rejected.',
                route('care-requests.apply', $this->requestItem->id),
                'booking_change_rejected'
            ));

            $this->refreshExistingApplication();
            session()->flash('status', 'Change request rejected.');
            return;
        }

        $changeRequest->update([
            'status' => CareBookingChangeRequest::STATUS_ACCEPTED,
            'resolved_at' => now(),
            'resolved_by_user_id' => auth()->id(),
        ]);

        if ($changeRequest->type === CareBookingChangeRequest::TYPE_CANCEL) {
            $booking->update([
                'status' => CareBooking::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $changeRequest->requester_user_id,
                'cancellation_reason' => $changeRequest->reason,
            ]);
            FunnelTracker::track('care_booking_cancelled', auth()->user(), $booking);
        } else {
            $booking->update([
                'status' => CareBooking::STATUS_SCHEDULED,
                'scheduled_start_at' => $changeRequest->proposed_start_at,
                'scheduled_end_at' => $changeRequest->proposed_end_at,
                'last_rescheduled_at' => now(),
                'last_reschedule_reason' => $changeRequest->reason,
            ]);
            FunnelTracker::track('care_booking_rescheduled', auth()->user(), $booking);
        }

        $changeRequest->requester?->notify(new MarketplaceAlert(
            'Change request accepted',
            'Your booking change request was accepted.',
            route('care-requests.apply', $this->requestItem->id),
            'booking_change_accepted'
        ));

        $this->refreshExistingApplication();
        session()->flash('status', 'Change request accepted and booking updated.');
    }

    public function createSupportTicket(): void
    {
        $booking = $this->getManagedBooking();

        $this->validate([
            'supportSubject' => ['required', 'string', 'min:8', 'max:160'],
            'supportDescription' => ['required', 'string', 'min:12', 'max:4000'],
            'supportCategory' => ['required', Rule::in(['general', 'dispute', 'incident', 'cancellation', 'billing'])],
        ]);

        SupportTicket::query()->create([
            'opener_user_id' => auth()->id(),
            'counterparty_user_id' => $this->requestItem->family_user_id,
            'care_request_id' => $this->requestItem->id,
            'care_booking_id' => $booking?->id,
            'category' => $this->supportCategory,
            'subject' => trim($this->supportSubject),
            'description' => trim($this->supportDescription),
        ]);

        $this->reset(['supportSubject', 'supportDescription', 'supportCategory']);
        session()->flash('status', 'Support ticket created.');
    }

    public function openChat(): void
    {
        $application = CareRequestApplication::query()
            ->where('care_request_id', $this->requestItem->id)
            ->where('caregiver_user_id', auth()->id())
            ->with(['careRequest', 'conversation'])
            ->firstOrFail();

        if (! in_array($application->status, [
            CareRequestApplication::STATUS_SHORTLISTED,
            CareRequestApplication::STATUS_HIRED,
        ], true)) {
            session()->flash('status', 'Chat is available once you are shortlisted or hired.');
            return;
        }

        $conversation = CareRequestConversation::findOrCreateForApplication($application, auth()->id());
        $this->redirect(route('messages.show', $conversation->id, false), navigate: true);
    }

    private function getManagedBooking(): ?CareBooking
    {
        return $this->existingApplication?->booking;
    }

    private function refreshExistingApplication(): void
    {
        $this->existingApplication = CareRequestApplication::query()
            ->where('care_request_id', $this->requestItem->id)
            ->where('caregiver_user_id', auth()->id())
            ->with([
                'conversation:id,care_request_application_id,care_request_id,caregiver_user_id',
                'booking:id,care_request_id,care_request_application_id,family_user_id,caregiver_user_id,status,scheduled_start_at,scheduled_end_at,started_at,completed_at,reviewed_at,cancelled_at',
                'booking.changeRequests',
                'booking.reviews',
            ])
            ->first();
    }

    public function render()
    {
        return view('livewire.caregiver.apply-to-care-request');
    }
}
