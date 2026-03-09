<?php

namespace App\Livewire\Family;

use App\Models\CareBooking;
use App\Models\CareBookingChangeRequest;
use App\Models\CareBookingIncident;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\CareRequestInvitation;
use App\Models\CareReview;
use App\Models\SupportTicket;
use App\Notifications\MarketplaceAlert;
use App\Services\Booking\BookingTrustService;
use App\Support\FunnelTracker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ManageCareRequest extends Component
{
    public CareRequest $requestItem;
    public string $activeTab = 'overview';
    public string $applicationStatus = 'all';
    public string $applicationSort = 'latest';

    public ?int $reviewRating = null;
    public string $reviewComment = '';

    public string $changeType = CareBookingChangeRequest::TYPE_CANCEL;
    public string $changeReason = '';
    public string $proposedStartAt = '';
    public string $proposedEndAt = '';

    public string $supportSubject = '';
    public string $supportDescription = '';
    public string $supportCategory = 'general';

    public string $confirmationNote = '';
    public string $disputeReason = '';
    public string $incidentTitle = '';
    public string $incidentDescription = '';
    public string $incidentSeverity = 'medium';

    public array $applicationStatusOptions = [
        ['label' => 'All applicants', 'value' => 'all'],
        ['label' => 'Applied', 'value' => CareRequestApplication::STATUS_APPLIED],
        ['label' => 'Shortlisted', 'value' => CareRequestApplication::STATUS_SHORTLISTED],
        ['label' => 'Hired', 'value' => CareRequestApplication::STATUS_HIRED],
        ['label' => 'Rejected', 'value' => CareRequestApplication::STATUS_REJECTED],
        ['label' => 'Not selected', 'value' => CareRequestApplication::STATUS_NOT_SELECTED],
        ['label' => 'Withdrawn', 'value' => CareRequestApplication::STATUS_WITHDRAWN],
    ];

    public function mount(int $careRequest): void
    {
        $this->requestItem = CareRequest::query()
            ->with([
                'family:id,name,email,phone',
                'recipient',
                'thirdPartyContact',
                'tasks',
                'booking',
                'booking.taskChecks',
                'booking.events.actor:id,name',
                'booking.incidents.reporter:id,name',
                'booking.changeRequests.requester:id,name',
                'booking.reviews.reviewer:id,name',
                'invitations' => fn ($query) => $query->with(['caregiver:id,name']),
                'applications' => fn ($query) => $query->with([
                    'caregiver:id,name,email,phone,city,state',
                    'caregiver.caregiverProfile:id,user_id,status,average_rating,reviews_count,hourly_rate',
                    'conversation:id,care_request_application_id,care_request_id,caregiver_user_id',
                    'booking:id,care_request_id,care_request_application_id,status,scheduled_start_at,scheduled_end_at',
                ]),
            ])
            ->findOrFail($careRequest);

        abort_unless(auth()->user()->can('manageApplicants', $this->requestItem), 403);

        $this->activeTab = $this->requestItem->booking
            ? 'shift'
            : ($this->requestItem->status === CareRequest::STATUS_OPEN ? 'applicants' : 'overview');
    }

    public function setActiveTab(string $tab): void
    {
        if (! in_array($tab, ['overview', 'applicants', 'shift', 'support'], true)) {
            return;
        }

        if ($tab === 'shift' && ! $this->requestItem->booking) {
            $this->activeTab = 'overview';
            return;
        }

        $this->activeTab = $tab;
    }

    public function shortlist(int $applicationId): void
    {
        $application = $this->findOwnedApplication($applicationId);

        if (! in_array($application->status, [CareRequestApplication::STATUS_APPLIED, CareRequestApplication::STATUS_SHORTLISTED], true)) {
            return;
        }

        $application->update(['status' => CareRequestApplication::STATUS_SHORTLISTED]);
        $this->refreshRequestItem();
        session()->flash('status', 'Applicant shortlisted for follow-up.');
    }

    public function reject(int $applicationId): void
    {
        $application = $this->findOwnedApplication($applicationId);

        if (in_array($application->status, [CareRequestApplication::STATUS_HIRED, CareRequestApplication::STATUS_WITHDRAWN], true)) {
            return;
        }

        $application->update(['status' => CareRequestApplication::STATUS_REJECTED]);
        $this->refreshRequestItem();
        session()->flash('status', 'Applicant rejected.');
    }

    public function hire(int $applicationId): void
    {
        if ($this->requestItem->status !== CareRequest::STATUS_OPEN) {
            return;
        }

        $application = $this->findOwnedApplication($applicationId);

        DB::transaction(function () use ($application) {
            $application->update(['status' => CareRequestApplication::STATUS_HIRED]);

            CareRequestConversation::findOrCreateForApplication($application->loadMissing('careRequest'), auth()->id());

            $this->requestItem->applications()
                ->where('id', '!=', $application->id)
                ->whereIn('status', [
                    CareRequestApplication::STATUS_APPLIED,
                    CareRequestApplication::STATUS_SHORTLISTED,
                ])
                ->update(['status' => CareRequestApplication::STATUS_NOT_SELECTED]);

            $this->requestItem->update(['status' => CareRequest::STATUS_FILLED]);

            $startAt = $this->deriveScheduledStartAt();
            $endAt = $this->deriveScheduledEndAt();
            $expectedMinutes = ($startAt && $endAt)
                ? (int) max(0, $startAt->diffInMinutes($endAt, false))
                : null;

            $booking = CareBooking::query()->updateOrCreate(
                ['care_request_id' => $this->requestItem->id],
                [
                    'care_request_application_id' => $application->id,
                    'family_user_id' => (int) auth()->id(),
                    'caregiver_user_id' => (int) $application->caregiver_user_id,
                    'agreement_snapshot' => app(BookingTrustService::class)->buildAgreementSnapshot(
                        $this->requestItem->fresh(['recipient', 'tasks']),
                        $application
                    ),
                    'family_terms_accepted_at' => now(),
                    'status' => CareBooking::STATUS_SCHEDULED,
                    'scheduled_start_at' => $startAt,
                    'scheduled_end_at' => $endAt,
                    'expected_minutes' => $expectedMinutes,
                ]
            );

            app(BookingTrustService::class)->seedTaskChecks($booking, $this->requestItem->fresh(['tasks']));
            app(BookingTrustService::class)->recordEvent(
                $booking,
                auth()->id(),
                'family',
                'booking_hired',
                ['application_id' => $application->id]
            );
        });

        $application->caregiver?->notify(new MarketplaceAlert(
            'You were hired',
            'A family selected you for this care request.',
            route('care-requests.apply', $this->requestItem->id),
            'caregiver_hired'
        ));

        FunnelTracker::track('caregiver_hired', auth()->user(), $application, [
            'care_request_id' => $this->requestItem->id,
        ]);

        $this->refreshRequestItem();
        session()->flash('status', 'Care request filled and caregiver hired. Booking created.');
    }

    public function completeBooking(): void
    {
        $booking = $this->requestItem->booking;
        if (! $booking) {
            return;
        }

        if ($booking->status === CareBooking::STATUS_IN_PROGRESS) {
            $booking->update([
                'status' => CareBooking::STATUS_COMPLETED,
                'completed_at' => now(),
                'timesheet_submitted_at' => $booking->timesheet_submitted_at ?: now(),
            ]);

            app(BookingTrustService::class)->recordEvent(
                $booking,
                auth()->id(),
                'family',
                'booking_completed_by_family'
            );
        } elseif ($booking->status === CareBooking::STATUS_COMPLETED && ! $booking->family_confirmed_at) {
            $booking->update([
                'family_confirmed_at' => now(),
            ]);

            app(BookingTrustService::class)->recordEvent(
                $booking,
                auth()->id(),
                'family',
                'timesheet_confirmed_by_family',
                ['note' => trim($this->confirmationNote) ?: null]
            );

            app(BookingTrustService::class)->recomputeReliabilityForBooking($booking);
            $this->confirmationNote = '';
            $this->refreshRequestItem();
            session()->flash('status', 'Timesheet confirmed.');
            return;
        } else {
            session()->flash('status', 'This action is only available after caregiver check-in or timesheet submission.');
            return;
        }

        $booking->caregiver?->notify(new MarketplaceAlert(
            'Care shift completed',
            auth()->user()->name.' marked this shift as completed.',
            route('care-requests.apply', $this->requestItem->id),
            'booking_completed'
        ));

        FunnelTracker::track('care_booking_completed', auth()->user(), $booking);
        app(BookingTrustService::class)->recomputeReliabilityForBooking($booking);
        $this->refreshRequestItem();
        session()->flash('status', 'Shift marked completed.');
    }

    public function submitReview(): void
    {
        $booking = $this->requestItem->booking;
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
                'reviewee_user_id' => $booking->caregiver_user_id,
                'rating' => $this->reviewRating,
                'comment' => trim($this->reviewComment) ?: null,
            ]
        );

        $caregiverProfile = $booking->caregiver?->caregiverProfile;
        if ($caregiverProfile) {
            $avg = CareReview::query()
                ->where('reviewee_user_id', $booking->caregiver_user_id)
                ->avg('rating');
            $count = CareReview::query()
                ->where('reviewee_user_id', $booking->caregiver_user_id)
                ->count();

            $caregiverProfile->update([
                'average_rating' => round((float) $avg, 2),
                'reviews_count' => $count,
            ]);
        }

        $booking->update([
            'status' => CareBooking::STATUS_REVIEWED,
            'reviewed_at' => now(),
            'family_confirmed_at' => $booking->family_confirmed_at ?: now(),
        ]);

        app(BookingTrustService::class)->recordEvent(
            $booking,
            auth()->id(),
            'family',
            'review_submitted_by_family',
            ['rating' => $this->reviewRating]
        );

        $booking->caregiver?->notify(new MarketplaceAlert(
            'New family review',
            auth()->user()->name.' left a review after the shift.',
            route('care-requests.apply', $this->requestItem->id),
            'review_submitted'
        ));

        FunnelTracker::track('care_review_submitted', auth()->user(), $booking, [
            'rating' => $this->reviewRating,
        ]);
        app(BookingTrustService::class)->recomputeReliabilityForBooking($booking);

        $this->reset(['reviewRating', 'reviewComment']);
        $this->refreshRequestItem();
        session()->flash('status', 'Review submitted.');
    }

    public function submitChangeRequest(): void
    {
        $booking = $this->requestItem->booking;
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
            'family',
            'booking_change_requested',
            [
                'type' => $this->changeType,
                'reason' => trim($this->changeReason),
            ]
        );

        $booking->caregiver?->notify(new MarketplaceAlert(
            'Booking change request',
            auth()->user()->name.' requested to '.$this->changeType.' this booking.',
            route('care-requests.apply', $this->requestItem->id),
            'booking_change_request'
        ));

        FunnelTracker::track('booking_change_requested', auth()->user(), $changeRequest, [
            'type' => $this->changeType,
        ]);

        $this->reset(['changeReason', 'proposedStartAt', 'proposedEndAt']);
        $this->refreshRequestItem();
        session()->flash('status', 'Change request sent to caregiver.');
    }

    public function resolveChangeRequest(int $changeRequestId, string $decision): void
    {
        $booking = $this->requestItem->booking;
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
                'family',
                'booking_change_rejected',
                ['change_request_id' => $changeRequest->id]
            );
            $changeRequest->requester?->notify(new MarketplaceAlert(
                'Change request rejected',
                'Your booking change request was rejected.',
                route('care-requests.apply', $this->requestItem->id),
                'booking_change_rejected'
            ));

            $this->refreshRequestItem();
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
            'family',
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

        $this->refreshRequestItem();
        session()->flash('status', 'Change request accepted and booking updated.');
    }

    public function createSupportTicket(): void
    {
        $this->validate([
            'supportSubject' => ['required', 'string', 'min:8', 'max:160'],
            'supportDescription' => ['required', 'string', 'min:12', 'max:4000'],
            'supportCategory' => ['required', Rule::in(['general', 'dispute', 'incident', 'cancellation', 'billing'])],
        ]);

        SupportTicket::query()->create([
            'opener_user_id' => auth()->id(),
            'counterparty_user_id' => $this->requestItem->booking?->caregiver_user_id,
            'care_request_id' => $this->requestItem->id,
            'care_booking_id' => $this->requestItem->booking?->id,
            'category' => $this->supportCategory,
            'subject' => trim($this->supportSubject),
            'description' => trim($this->supportDescription),
        ]);

        if ($this->requestItem->booking) {
            app(BookingTrustService::class)->recordEvent(
                $this->requestItem->booking,
                auth()->id(),
                'family',
                'support_ticket_opened',
                ['category' => $this->supportCategory]
            );
        }

        $this->reset(['supportSubject', 'supportDescription', 'supportCategory']);
        session()->flash('status', 'Support ticket created.');
    }

    public function openDispute(): void
    {
        $booking = $this->requestItem->booking;
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
            'counterparty_user_id' => $booking->caregiver_user_id,
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
            'family',
            'dispute_opened_by_family',
            ['reason' => trim($this->disputeReason)]
        );
        app(BookingTrustService::class)->recomputeReliabilityForBooking($booking);

        $this->disputeReason = '';
        $this->refreshRequestItem();
        session()->flash('status', 'Dispute opened and support ticket created.');
    }

    public function markNoShow(): void
    {
        $booking = $this->requestItem->booking;
        if (! $booking || $booking->status !== CareBooking::STATUS_SCHEDULED) {
            return;
        }

        if (! $booking->scheduled_start_at || now()->lt($booking->scheduled_start_at->copy()->addMinutes(30))) {
            session()->flash('status', 'No-show can be marked 30 minutes after scheduled start.');
            return;
        }

        $booking->update([
            'status' => CareBooking::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by_user_id' => auth()->id(),
            'cancellation_reason' => 'Marked as caregiver no-show by family.',
            'no_show_flag' => true,
        ]);

        app(BookingTrustService::class)->recordEvent(
            $booking,
            auth()->id(),
            'family',
            'caregiver_no_show_marked'
        );
        app(BookingTrustService::class)->recomputeReliabilityForBooking($booking);

        $this->refreshRequestItem();
        session()->flash('status', 'Booking marked as no-show.');
    }

    public function reportIncident(): void
    {
        $booking = $this->requestItem->booking;
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
            'counterparty_user_id' => $booking->caregiver_user_id,
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
            'family',
            'incident_reported_by_family',
            ['severity' => $this->incidentSeverity, 'title' => trim($this->incidentTitle)]
        );

        $this->reset(['incidentTitle', 'incidentDescription', 'incidentSeverity']);
        $this->refreshRequestItem();
        session()->flash('status', 'Incident reported. Support was notified.');
    }

    public function rebookHiredCaregiver(): void
    {
        $hiredApplication = $this->requestItem->applications
            ->firstWhere('status', CareRequestApplication::STATUS_HIRED);

        if (! $hiredApplication) {
            return;
        }

        $newRequest = DB::transaction(function () use ($hiredApplication) {
            $attributes = $this->requestItem->only([
                'title',
                'additional_info',
                'scope_of_work',
                'time_expectations',
                'home_access_notes',
                'preferred_response_hours',
                'request_type',
                'budget_min',
                'budget_max',
                'requested_start_at',
                'requested_end_at',
                'recurring_days',
                'recurring_start_time',
                'recurring_end_time',
                'recurring_starts_on',
                'recurring_ends_on',
                'address_line1',
                'address_line2',
                'city',
                'state',
                'zip',
                'lat',
                'lng',
            ]);

            $attributes['title'] = 'Rebook: '.$this->requestItem->title;
            $attributes['status'] = CareRequest::STATUS_OPEN;
            $attributes['family_user_id'] = auth()->id();

            if ($this->requestItem->request_type === CareRequest::TYPE_ONE_TIME) {
                $attributes['requested_start_at'] = optional($this->requestItem->requested_start_at)->copy()?->addWeek();
                $attributes['requested_end_at'] = optional($this->requestItem->requested_end_at)->copy()?->addWeek();
            } else {
                $attributes['recurring_starts_on'] = optional($this->requestItem->recurring_starts_on)?->copy()?->addWeek()?->toDateString();
            }

            $newRequest = CareRequest::query()->create($attributes);

            if ($this->requestItem->recipient) {
                $newRequest->recipient()->create($this->requestItem->recipient->only([
                    'full_name',
                    'date_of_birth',
                    'gender',
                    'mobility_level',
                    'relationship_to_family',
                    'care_notes',
                ]));
            }

            if ($this->requestItem->thirdPartyContact) {
                $newRequest->thirdPartyContact()->create($this->requestItem->thirdPartyContact->only([
                    'full_name',
                    'relationship_to_recipient',
                    'phone',
                    'email',
                ]));
            }

            $taskPayload = $this->requestItem->tasks
                ->mapWithKeys(fn ($task) => [$task->id => ['task_note' => $task->pivot?->task_note]])
                ->all();
            $newRequest->tasks()->sync($taskPayload);

            CareRequestInvitation::query()->create([
                'care_request_id' => $newRequest->id,
                'family_user_id' => auth()->id(),
                'caregiver_user_id' => $hiredApplication->caregiver_user_id,
                'status' => CareRequestInvitation::STATUS_PENDING,
                'message' => 'Rebooking request based on previous completed care.',
                'expires_at' => now()->addHours(72),
            ]);

            return $newRequest;
        });

        FunnelTracker::track('care_request_rebooked', auth()->user(), $newRequest, [
            'source_request_id' => $this->requestItem->id,
        ]);

        session()->flash('status', 'Rebook request created and caregiver invited.');
        $this->redirect(route('family.requests.show', $newRequest->id, false), navigate: true);
    }

    public function startConversation(int $applicationId): void
    {
        $application = $this->findOwnedApplication($applicationId);
        $application->loadMissing('careRequest');

        if ((int) $application->careRequest->family_user_id !== (int) auth()->id()
            || ! in_array($application->status, [
                CareRequestApplication::STATUS_SHORTLISTED,
                CareRequestApplication::STATUS_HIRED,
            ], true)) {
            session()->flash('status', 'You can chat after shortlisting this applicant.');
            return;
        }

        $conversation = CareRequestConversation::findOrCreateForApplication($application, auth()->id());
        $this->redirect(route('messages.show', $conversation->id, false), navigate: true);
    }

    private function deriveScheduledStartAt(): ?Carbon
    {
        if ($this->requestItem->request_type === CareRequest::TYPE_ONE_TIME) {
            return $this->requestItem->requested_start_at;
        }

        $startDate = $this->requestItem->recurring_starts_on ?: now();
        $startTime = $this->requestItem->recurring_start_time ?: '09:00:00';

        return $this->combineDateAndTime($startDate, $startTime);
    }

    private function deriveScheduledEndAt(): ?Carbon
    {
        if ($this->requestItem->request_type === CareRequest::TYPE_ONE_TIME) {
            return $this->requestItem->requested_end_at;
        }

        $startDate = $this->requestItem->recurring_starts_on ?: now();
        $endTime = $this->requestItem->recurring_end_time ?: '13:00:00';

        return $this->combineDateAndTime($startDate, $endTime);
    }

    private function combineDateAndTime(mixed $dateValue, mixed $timeValue): Carbon
    {
        $date = $dateValue instanceof Carbon
            ? $dateValue->copy()
            : Carbon::parse((string) $dateValue);

        return $date->setTimeFromTimeString($this->normalizeTimeString($timeValue));
    }

    private function normalizeTimeString(mixed $timeValue): string
    {
        if ($timeValue instanceof Carbon) {
            return $timeValue->format('H:i:s');
        }

        $time = trim((string) $timeValue);

        if ($time === '') {
            return '00:00:00';
        }

        if (preg_match('/^\d{2}:\d{2}$/', $time) === 1) {
            return $time.':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time) === 1) {
            return $time;
        }

        return Carbon::parse($time)->format('H:i:s');
    }

    private function findOwnedApplication(int $applicationId): CareRequestApplication
    {
        return CareRequestApplication::query()
            ->where('care_request_id', $this->requestItem->id)
            ->whereKey($applicationId)
            ->firstOrFail();
    }

    private function refreshRequestItem(): void
    {
        $this->requestItem = $this->requestItem->fresh([
            'family:id,name,email,phone',
            'recipient',
            'thirdPartyContact',
            'tasks',
            'booking',
            'booking.taskChecks',
            'booking.events.actor:id,name',
            'booking.incidents.reporter:id,name',
            'booking.changeRequests.requester:id,name',
            'booking.reviews.reviewer:id,name',
            'invitations' => fn ($query) => $query->with(['caregiver:id,name']),
            'applications' => fn ($query) => $query->with([
                'caregiver:id,name,email,phone,city,state',
                'caregiver.caregiverProfile:id,user_id,status,average_rating,reviews_count,hourly_rate',
                'conversation:id,care_request_application_id,care_request_id,caregiver_user_id',
                'booking:id,care_request_id,care_request_application_id,status,scheduled_start_at,scheduled_end_at',
            ]),
        ]);
    }

    public function getVisibleApplicationsProperty()
    {
        $applications = $this->requestItem->applications;

        if ($this->applicationStatus !== 'all') {
            $applications = $applications->where('status', $this->applicationStatus);
        }

        return match ($this->applicationSort) {
            'oldest' => $applications->sortBy('created_at')->values(),
            'rate_high' => $applications->sortByDesc('proposed_rate')->values(),
            'rate_low' => $applications->sortBy('proposed_rate')->values(),
            default => $applications->sortByDesc('created_at')->values(),
        };
    }

    public function render()
    {
        return view('livewire.family.manage-care-request');
    }
}
