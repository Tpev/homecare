<?php

namespace App\Livewire\Caregiver;

use App\Models\CareBooking;
use App\Models\CareBookingChangeRequest;
use App\Models\CareBookingIncident;
use App\Models\CareBookingTaskCheck;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\CareReview;
use App\Models\SupportTicket;
use App\Notifications\MarketplaceAlert;
use App\Services\Booking\BookingTrustService;
use App\Support\FunnelTracker;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ApplyToCareRequest extends Component
{
    public CareRequest $requestItem;
    public string $activeTab = 'overview';
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

    public string $checkInNote = '';
    public string $checkOutNote = '';
    public ?float $checkInLat = null;
    public ?float $checkInLng = null;
    public ?float $checkOutLat = null;
    public ?float $checkOutLng = null;
    public string $heartbeatNote = '';

    public string $incidentTitle = '';
    public string $incidentDescription = '';
    public string $incidentSeverity = 'medium';
    public string $disputeReason = '';

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

        $this->activeTab = $this->existingApplication?->booking
            ? 'shift'
            : ($this->existingApplication ? 'application' : 'overview');
    }

    public function setActiveTab(string $tab): void
    {
        if (! in_array($tab, ['overview', 'application', 'shift', 'support'], true)) {
            return;
        }

        if (in_array($tab, ['shift', 'support'], true) && ! $this->existingApplication?->booking) {
            $this->activeTab = 'application';
            return;
        }

        $this->activeTab = $tab;
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

        if (! $booking->caregiver_terms_accepted_at) {
            session()->flash('status', 'Accept the booking agreement before check-in.');
            return;
        }

        $this->validate([
            'checkInLat' => ['nullable', 'numeric', 'between:-90,90'],
            'checkInLng' => ['nullable', 'numeric', 'between:-180,180'],
            'checkInNote' => ['nullable', 'string', 'max:1000'],
        ]);

        $booking->update([
            'status' => CareBooking::STATUS_IN_PROGRESS,
            'started_at' => now(),
            'check_in_lat' => $this->checkInLat,
            'check_in_lng' => $this->checkInLng,
            'check_in_note' => trim($this->checkInNote) ?: null,
            'heartbeat_pinged_at' => now(),
        ]);

        app(BookingTrustService::class)->recordEvent(
            $booking,
            auth()->id(),
            'caregiver',
            'checked_in_by_caregiver',
            [
                'lat' => $this->checkInLat,
                'lng' => $this->checkInLng,
                'note' => trim($this->checkInNote) ?: null,
            ]
        );

        $booking->family?->notify(new MarketplaceAlert(
            'Care shift started',
            auth()->user()->name.' marked this shift as in progress.',
            route('family.requests.show', $this->requestItem->id),
            'booking_in_progress'
        ));

        FunnelTracker::track('care_booking_started', auth()->user(), $booking);
        app(BookingTrustService::class)->recomputeReliabilityForBooking($booking);
        $this->reset(['checkInNote', 'checkInLat', 'checkInLng']);
        $this->refreshExistingApplication();
        session()->flash('status', 'Shift marked in progress.');
    }

    public function completeBooking(): void
    {
        $booking = $this->getManagedBooking();
        if (! $booking) {
            return;
        }

        if ($booking->status !== CareBooking::STATUS_IN_PROGRESS) {
            session()->flash('status', 'Check in first before checking out.');
            return;
        }

        $this->validate([
            'checkOutLat' => ['nullable', 'numeric', 'between:-90,90'],
            'checkOutLng' => ['nullable', 'numeric', 'between:-180,180'],
            'checkOutNote' => ['nullable', 'string', 'max:1500'],
        ]);

        $workedMinutes = null;
        if ($booking->started_at) {
            $workedMinutes = $booking->started_at->diffInMinutes(now());
        } elseif ($booking->scheduled_start_at) {
            $workedMinutes = $booking->scheduled_start_at->diffInMinutes(now());
        }

        $booking->update([
            'status' => CareBooking::STATUS_COMPLETED,
            'completed_at' => now(),
            'timesheet_submitted_at' => now(),
            'check_out_lat' => $this->checkOutLat,
            'check_out_lng' => $this->checkOutLng,
            'check_out_note' => trim($this->checkOutNote) ?: null,
            'worked_minutes' => $workedMinutes,
        ]);

        app(BookingTrustService::class)->recordEvent(
            $booking,
            auth()->id(),
            'caregiver',
            'checked_out_by_caregiver',
            [
                'lat' => $this->checkOutLat,
                'lng' => $this->checkOutLng,
                'note' => trim($this->checkOutNote) ?: null,
                'worked_minutes' => $workedMinutes,
            ]
        );

        $booking->family?->notify(new MarketplaceAlert(
            'Care shift completed',
            auth()->user()->name.' marked this shift as completed.',
            route('family.requests.show', $this->requestItem->id),
            'booking_completed'
        ));

        FunnelTracker::track('care_booking_completed', auth()->user(), $booking);
        app(BookingTrustService::class)->recomputeReliabilityForBooking($booking);
        $this->reset(['checkOutNote', 'checkOutLat', 'checkOutLng']);
        $this->refreshExistingApplication();
        session()->flash('status', 'Shift marked completed and timesheet submitted.');
    }

    public function acceptBookingAgreement(): void
    {
        $booking = $this->getManagedBooking();
        if (! $booking || $booking->caregiver_terms_accepted_at) {
            return;
        }

        $booking->update([
            'caregiver_terms_accepted_at' => now(),
        ]);

        app(BookingTrustService::class)->recordEvent(
            $booking,
            auth()->id(),
            'caregiver',
            'agreement_accepted_by_caregiver'
        );

        $this->refreshExistingApplication();
        session()->flash('status', 'Booking agreement accepted.');
    }

    public function sendHeartbeat(): void
    {
        $booking = $this->getManagedBooking();
        if (! $booking || $booking->status !== CareBooking::STATUS_IN_PROGRESS) {
            return;
        }

        $this->validate([
            'heartbeatNote' => ['nullable', 'string', 'max:500'],
        ]);

        $booking->update([
            'heartbeat_pinged_at' => now(),
        ]);

        app(BookingTrustService::class)->recordEvent(
            $booking,
            auth()->id(),
            'caregiver',
            'heartbeat_sent',
            ['note' => trim($this->heartbeatNote) ?: null]
        );

        $this->heartbeatNote = '';
        $this->refreshExistingApplication();
        session()->flash('status', 'Heartbeat sent.');
    }

    public function toggleTaskCheck(int $taskCheckId): void
    {
        $booking = $this->getManagedBooking();
        if (! $booking) {
            return;
        }

        $taskCheck = CareBookingTaskCheck::query()
            ->where('care_booking_id', $booking->id)
            ->whereKey($taskCheckId)
            ->firstOrFail();

        $next = ! $taskCheck->is_completed;
        $taskCheck->update([
            'is_completed' => $next,
            'completed_at' => $next ? now() : null,
            'completed_by_user_id' => $next ? auth()->id() : null,
        ]);

        app(BookingTrustService::class)->recordEvent(
            $booking,
            auth()->id(),
            'caregiver',
            $next ? 'task_marked_completed' : 'task_marked_uncompleted',
            ['task_check_id' => $taskCheck->id, 'label' => $taskCheck->label]
        );

        $this->refreshExistingApplication();
    }

    public function openDispute(): void
    {
        $booking = $this->getManagedBooking();
        if (! $booking || ! in_array($booking->status, [CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED], true)) {
            return;
        }

        $this->validate([
            'disputeReason' => ['required', 'string', 'min:12', 'max:3000'],
        ]);

        $booking->update([
            'status' => CareBooking::STATUS_DISPUTED,
            'dispute_opened_at' => now(),
            'dispute_opened_by_user_id' => auth()->id(),
            'dispute_reason' => trim($this->disputeReason),
            'dispute_status' => 'open',
        ]);

        SupportTicket::query()->create([
            'opener_user_id' => auth()->id(),
            'counterparty_user_id' => $booking->family_user_id,
            'care_request_id' => $this->requestItem->id,
            'care_booking_id' => $booking->id,
            'category' => 'dispute',
            'priority' => 'high',
            'subject' => 'Booking dispute for request #'.$this->requestItem->id,
            'description' => trim($this->disputeReason),
        ]);

        app(BookingTrustService::class)->recordEvent(
            $booking,
            auth()->id(),
            'caregiver',
            'dispute_opened_by_caregiver',
            ['reason' => trim($this->disputeReason)]
        );
        app(BookingTrustService::class)->recomputeReliabilityForBooking($booking);

        $this->disputeReason = '';
        $this->refreshExistingApplication();
        session()->flash('status', 'Dispute opened and support ticket created.');
    }

    public function reportIncident(): void
    {
        $booking = $this->getManagedBooking();
        if (! $booking) {
            return;
        }

        $this->validate([
            'incidentTitle' => ['required', 'string', 'min:6', 'max:160'],
            'incidentDescription' => ['required', 'string', 'min:12', 'max:4000'],
            'incidentSeverity' => ['required', Rule::in(['low', 'medium', 'high'])],
        ]);

        CareBookingIncident::query()->create([
            'care_booking_id' => $booking->id,
            'reporter_user_id' => auth()->id(),
            'severity' => $this->incidentSeverity,
            'title' => trim($this->incidentTitle),
            'description' => trim($this->incidentDescription),
            'reported_at' => now(),
        ]);

        SupportTicket::query()->create([
            'opener_user_id' => auth()->id(),
            'counterparty_user_id' => $booking->family_user_id,
            'care_request_id' => $this->requestItem->id,
            'care_booking_id' => $booking->id,
            'category' => 'incident',
            'priority' => $this->incidentSeverity === 'high' ? 'high' : 'normal',
            'subject' => trim($this->incidentTitle),
            'description' => trim($this->incidentDescription),
        ]);

        app(BookingTrustService::class)->recordEvent(
            $booking,
            auth()->id(),
            'caregiver',
            'incident_reported_by_caregiver',
            ['severity' => $this->incidentSeverity, 'title' => trim($this->incidentTitle)]
        );

        $this->reset(['incidentTitle', 'incidentDescription', 'incidentSeverity']);
        $this->refreshExistingApplication();
        session()->flash('status', 'Incident reported. Support was notified.');
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

        app(BookingTrustService::class)->recordEvent(
            $booking,
            auth()->id(),
            'caregiver',
            'review_submitted_by_caregiver',
            ['rating' => $this->reviewRating]
        );

        $booking->family?->notify(new MarketplaceAlert(
            'New review submitted',
            auth()->user()->name.' left a review after the shift.',
            route('family.requests.show', $this->requestItem->id),
            'review_submitted'
        ));

        FunnelTracker::track('care_review_submitted', auth()->user(), $booking, [
            'rating' => $this->reviewRating,
        ]);
        app(BookingTrustService::class)->recomputeReliabilityForBooking($booking);

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

        app(BookingTrustService::class)->recordEvent(
            $booking,
            auth()->id(),
            'caregiver',
            'booking_change_requested',
            [
                'type' => $this->changeType,
                'reason' => trim($this->changeReason),
            ]
        );

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

            app(BookingTrustService::class)->recordEvent(
                $booking,
                auth()->id(),
                'caregiver',
                'booking_change_rejected',
                ['change_request_id' => $changeRequest->id]
            );

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
            $lateCancel = app(BookingTrustService::class)->markLateCancelFlag($booking);
            $booking->update([
                'status' => CareBooking::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $changeRequest->requester_user_id,
                'cancellation_reason' => $changeRequest->reason,
                'late_cancel_flag' => $lateCancel,
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

        app(BookingTrustService::class)->recordEvent(
            $booking,
            auth()->id(),
            'caregiver',
            $changeRequest->type === CareBookingChangeRequest::TYPE_CANCEL
                ? 'booking_cancelled_by_change_request'
                : 'booking_rescheduled_by_change_request',
            [
                'change_request_id' => $changeRequest->id,
                'requester_user_id' => $changeRequest->requester_user_id,
            ]
        );
        app(BookingTrustService::class)->recomputeReliabilityForBooking($booking);

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

        if ($booking) {
            app(BookingTrustService::class)->recordEvent(
                $booking,
                auth()->id(),
                'caregiver',
                'support_ticket_opened',
                ['category' => $this->supportCategory]
            );
        }

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
                'booking:id,care_request_id,care_request_application_id,family_user_id,caregiver_user_id,status,scheduled_start_at,scheduled_end_at,agreement_snapshot,family_terms_accepted_at,caregiver_terms_accepted_at,started_at,check_in_lat,check_in_lng,check_in_note,heartbeat_pinged_at,completed_at,check_out_lat,check_out_lng,check_out_note,timesheet_submitted_at,expected_minutes,worked_minutes,family_confirmed_at,dispute_opened_at,dispute_opened_by_user_id,dispute_reason,dispute_status,no_show_flag,late_cancel_flag,reviewed_at,cancelled_at,cancelled_by_user_id,cancellation_reason',
                'booking.changeRequests',
                'booking.reviews',
                'booking.taskChecks',
                'booking.events.actor:id,name',
                'booking.incidents.reporter:id,name',
            ])
            ->first();
    }

    public function render()
    {
        return view('livewire.caregiver.apply-to-care-request');
    }
}
