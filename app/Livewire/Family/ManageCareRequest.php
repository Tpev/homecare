<?php

namespace App\Livewire\Family;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareBooking;
use App\Models\CareBookingChangeRequest;
use App\Models\CareBookingIncident;
use App\Models\CareBookingPayment;
use App\Models\CareBookingTimeCorrection;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\CareRequestInvitation;
use App\Models\CareReview;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\AiSupport\AiSupportPreparationService;
use App\Services\Booking\BookingTrustService;
use App\Services\Booking\CareBookingTimeCorrectionService;
use App\Services\CareRecipientProfiles\CareRecipientProfilePresenter;
use App\Services\CareRequests\CareRequestLifecycleService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\Marketplace\CaregiverInvitationDiscoveryService;
use App\Services\Marketplace\CareRequestInvitationService;
use App\Services\Matching\CaregiverSuggestionService;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Services\Payments\BookingPaymentService;
use App\Support\CaregiverCertificationCriteria;
use App\Support\CaregiverPrelaunch;
use App\Support\CareRequestProgress;
use App\Support\FunnelTracker;
use App\Support\MarketplaceEvent;
use App\Support\WeeklySchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class ManageCareRequest extends Component
{
    public CareRequest $requestItem;

    #[Url(as: 'tab')]
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

    public bool $showCaregiverInvitePanel = false;

    public string $caregiverSearch = '';

    public array $certificationTypes = [];

    public string $certificationVerification = CaregiverCertificationCriteria::VERIFICATION_ANY_CURRENT;

    public array $applicationCertificationTypes = [];

    public string $applicationCertificationVerification = CaregiverCertificationCriteria::VERIFICATION_ANY_CURRENT;

    public ?int $confirmingCaregiverId = null;

    public bool $confirmingReinvite = false;

    public string $caregiverInviteMessage = '';

    public ?array $caregiverInviteFeedback = null;

    public string $directCancelReason = '';

    public string $disputeReason = '';

    public string $incidentTitle = '';

    public string $incidentDescription = '';

    public string $incidentSeverity = 'medium';

    public string $timeCorrectionResponseNote = '';

    public ?int $confirmingTimeCorrectionId = null;

    public bool $aiPrepared = false;

    public array $applicationStatusOptions = [
        ['label' => 'All caregivers', 'value' => 'all'],
        ['label' => 'Interested', 'value' => CareRequestApplication::STATUS_APPLIED],
        ['label' => 'Saved', 'value' => CareRequestApplication::STATUS_SHORTLISTED],
        ['label' => 'Hired', 'value' => CareRequestApplication::STATUS_HIRED],
        ['label' => 'Declined', 'value' => CareRequestApplication::STATUS_REJECTED],
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
                'booking.payment',
                'booking.carePlan:id,timezone',
                'booking.timeCorrections' => fn ($query) => $query
                    ->with(['requester:id,name', 'approvedBy:id,name', 'supportTicket:id,status'])
                    ->orderByDesc('version'),
                'booking.taskChecks',
                'booking.events.actor:id,name',
                'booking.incidents.reporter:id,name',
                'booking.changeRequests.requester:id,name,role',
                'booking.reviews.reviewer:id,name',
                'invitations' => fn ($query) => $query->with(['caregiver:id,name']),
                'applications' => fn ($query) => $query->with([
                    'caregiver:id,name,email,phone,city,state',
                    'caregiver.caregiverProfile:id,user_id,slug,profile_photo_path,bio,years_experience,status,average_rating,reviews_count,platform_hourly_rate,identity_verified_at,identity_verification_status,background_check_verified_at,top_caregiver,invite_response_rate,reliability_score,completed_bookings_count,is_accepting_new_clients',
                    'caregiver.caregiverProfile.skills:id,name',
                    'caregiver.caregiverProfile.languages:id,name',
                    'caregiver.caregiverProfile.publicSearchCertifications',
                    'conversation:id,care_request_application_id,care_request_id,caregiver_user_id',
                    'booking:id,care_request_id,care_request_application_id,status,scheduled_start_at,scheduled_end_at',
                ]),
            ])
            ->findOrFail($careRequest);

        abort_unless(auth()->user()->can('manageApplicants', $this->requestItem), 403);

        if ($this->reconcileFilledStateFromBooking()) {
            $this->refreshRequestItem(preferLifecyclePrimary: true);
        }

        $lifecycle = CareRequestProgress::familyLifecycleStage($this->requestItem);
        $visibleTabs = collect($lifecycle['tabs'])->pluck('key')->all();
        $requestedTab = request()->query->has('tab')
            ? trim((string) request()->query('tab'))
            : null;
        $this->activeTab = $requestedTab !== null && in_array($requestedTab, $visibleTabs, true)
            ? $requestedTab
            : $lifecycle['primary_tab'];

        $prepared = app(AiSupportPreparationService::class)->consume(
            auth()->user(),
            'submitted_hours_correction_v1',
            'care_request',
            $this->requestItem->id,
        );
        if ($prepared !== []) {
            $reason = (string) ($prepared['reason'] ?? '');
            $issueType = (string) ($prepared['issue_type'] ?? 'correction');
            if (in_array($issueType, ['correction', 'change_request'], true)) {
                $this->timeCorrectionResponseNote = $reason;
            } elseif ($issueType === 'dispute') {
                $this->disputeReason = $reason;
            } else {
                $this->supportCategory = 'time_correction';
                $this->supportSubject = 'Help with submitted hours';
                $this->supportDescription = $reason;
            }
            if (in_array('shift', $visibleTabs, true)) {
                $this->activeTab = 'shift';
            }
            $this->aiPrepared = true;
        }
    }

    public function setActiveTab(string $tab): void
    {
        $visibleTabs = collect(CareRequestProgress::familyLifecycleStage($this->requestItem)['tabs'])
            ->pluck('key')
            ->all();

        if (! in_array($tab, $visibleTabs, true)) {
            return;
        }

        if ($tab === 'shift' && ! $this->requestItem->booking) {
            $this->activeTab = CareRequestProgress::familyLifecycleStage($this->requestItem)['primary_tab'];

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
        if (! $this->requestItem->first_shortlist_at) {
            $this->requestItem->update(['first_shortlist_at' => now()]);
        }
        $this->refreshRequestItem();
        session()->flash('status', 'Caregiver saved for follow-up.');
    }

    public function reject(int $applicationId): void
    {
        $application = $this->findOwnedApplication($applicationId);

        if (in_array($application->status, [CareRequestApplication::STATUS_HIRED, CareRequestApplication::STATUS_WITHDRAWN], true)) {
            return;
        }

        $application->update(['status' => CareRequestApplication::STATUS_REJECTED]);
        $this->refreshRequestItem();
        session()->flash('status', 'Caregiver declined.');
    }

    public function withdrawRequest(): void
    {
        try {
            app(CareRequestLifecycleService::class)->withdraw(auth()->user(), $this->requestItem);
            $this->refreshRequestItem(preferLifecyclePrimary: true);
            session()->flash('status', 'Request withdrawn. Caregivers can no longer apply.');
        } catch (ValidationException $exception) {
            session()->flash('status', (string) collect($exception->errors())->flatten()->first());
        }
    }

    public function hire(int $applicationId): void
    {
        if ($this->requestItem->status !== CareRequest::STATUS_OPEN) {
            session()->flash('status', 'This request already has a caregiver or is no longer open. You can still open caregiver profiles or chat from this page.');

            return;
        }

        $application = $this->findOwnedApplication($applicationId)->loadMissing('caregiver:id,email,name');
        if (! CaregiverPrelaunch::familyCanProceedWithCaregiver(
            $application->caregiver?->email,
            $this->requestItem,
            (int) $application->caregiver_user_id,
        )) {
            session()->flash('status', CaregiverPrelaunch::familyHireMessage());

            return;
        }

        if ($this->requestItem->request_type === CareRequest::TYPE_RECURRING) {
            try {
                $plan = app(\App\Services\RegularCare\CarePlanService::class)
                    ->activateFromRecurringRequest($this->requestItem, $application, auth()->user());
            } catch (PaymentException $e) {
                $this->refreshRequestItem(preferLifecyclePrimary: true);
                session()->flash('warning', $e->userMessage);

                return;
            } catch (\Illuminate\Validation\ValidationException $e) {
                throw $e;
            }

            $this->refreshRequestItem(preferLifecyclePrimary: true);
            $payment = $this->requestItem->booking?->payment;
            if ($payment?->status === CareBookingPayment::STATUS_AUTHORIZATION_REQUIRED) {
                $this->dispatchPaymentConfirmation($payment);
                session()->flash('status', 'Regular care is set up. Confirm your card for the first visit.');
            } else {
                session()->flash('status', 'Regular care is confirmed. Your upcoming visits are ready.');
            }

            FunnelTracker::track('regular_care_marketplace_hired', auth()->user(), $plan, [
                'care_request_id' => $this->requestItem->id,
                'caregiver_user_id' => $application->caregiver_user_id,
            ]);

            return;
        }

        $paymentWarning = null;
        $paymentConfirmationId = null;

        try {
            DB::transaction(function () use ($application, &$paymentWarning, &$paymentConfirmationId) {
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
                if (! $this->requestItem->first_hire_at) {
                    $this->requestItem->update(['first_hire_at' => now()]);
                }

                $startAt = $this->deriveScheduledStartAt();
                $endAt = $this->deriveScheduledEndAt();
                $expectedMinutes = ($startAt && $endAt)
                    ? (int) max(0, $startAt->diffInMinutes($endAt, false))
                    : null;

                $booking = CareBooking::query()->updateOrCreate(
                    ['care_request_id' => $this->requestItem->id],
                    [
                        ...app(FamilyAccountContext::class)->ownershipAttributes(auth()->user()),
                        'care_request_application_id' => $application->id,
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

                $payment = app(BookingPaymentService::class)->prepareOnSessionAuthorization($booking);
                if ($payment->status === CareBookingPayment::STATUS_AUTHORIZATION_REQUIRED) {
                    $paymentConfirmationId = $payment->id;
                    $paymentWarning = 'Confirm the card authorization to protect this visit.';
                }
            });
        } catch (PaymentException $e) {
            $this->refreshRequestItem(preferLifecyclePrimary: true);
            session()->flash('warning', $e->userMessage);

            return;
        }

        if ($application->caregiver) {
            app(MarketplaceNotificationService::class)->notify(
                recipients: $application->caregiver,
                eventKey: MarketplaceEvent::CAREGIVER_HIRED,
                title: 'You were hired',
                body: 'A family selected you for this care request.',
                url: route('care-requests.apply', $this->requestItem->id),
                payload: ['care_request_id' => $this->requestItem->id],
                subject: $application
            );
        }

        app(MarketplaceNotificationService::class)->notify(
            recipients: auth()->user(),
            eventKey: MarketplaceEvent::HIRE_CONFIRMED,
            title: 'Hire confirmed',
            body: 'Caregiver hired and visit created for this request.',
            url: route('family.requests.show', $this->requestItem->id),
            payload: ['care_request_id' => $this->requestItem->id],
            subject: $application,
            dedupeKey: 'hire-confirmed:request-'.$this->requestItem->id.'-user-'.auth()->id()
        );

        FunnelTracker::track('caregiver_hired', auth()->user(), $application, [
            'care_request_id' => $this->requestItem->id,
        ]);

        $this->refreshRequestItem(preferLifecyclePrimary: true);
        if ($paymentConfirmationId) {
            $payment = CareBookingPayment::query()->find($paymentConfirmationId);
            if ($payment) {
                $this->dispatchPaymentConfirmation($payment);
            }
        }

        if ($paymentWarning) {
            session()->flash('status', 'Caregiver hired and visit created. Payment still needs attention: '.$paymentWarning);
        } else {
            session()->flash('status', 'Caregiver hired and visit created.');
        }
    }

    public function startPaymentAuthorization(): void
    {
        $booking = $this->requestItem->booking;
        if (! $booking) {
            session()->flash('warning', 'No visit is ready for payment authorization yet.');

            return;
        }

        $correction = app(CareBookingTimeCorrectionService::class)->activeForBooking($booking);
        if ($correction?->status === CareBookingTimeCorrection::STATUS_PAYMENT_ACTION_REQUIRED) {
            $this->continueTimeCorrectionPayment($correction->id);

            return;
        }
        if ($correction?->status === CareBookingTimeCorrection::STATUS_APPROVED_PROCESSING) {
            $correction = app(CareBookingTimeCorrectionService::class)->retryApprovedProcessing($correction);
            $this->refreshRequestItem();
            if ($correction->status === CareBookingTimeCorrection::STATUS_PAYMENT_ACTION_REQUIRED) {
                $payment = $this->requestItem->booking?->payment;
                if ($payment) {
                    $this->dispatchPaymentConfirmation($payment);
                }
                session()->flash('warning', 'Confirm billing in the secure Stripe window.');
            } elseif ($correction->status === CareBookingTimeCorrection::STATUS_APPLIED) {
                session()->flash('status', 'Visit time updated and payment completed.');
            } else {
                session()->flash('warning', 'The approved hours are still processing. LoLo Care will keep the payment record safe.');
            }

            return;
        }

        try {
            $payment = app(BookingPaymentService::class)->prepareOnSessionAuthorization($booking);
        } catch (PaymentException $e) {
            session()->flash('warning', $e->userMessage);

            return;
        }

        $this->refreshRequestItem();

        if ($payment->status === CareBookingPayment::STATUS_AUTHORIZED) {
            session()->flash('status', 'Card pre-authorization is already complete.');

            return;
        }

        $this->dispatchPaymentConfirmation($payment);
        session()->flash('status', 'Confirm the card authorization in the secure Stripe window.');
    }

    public function finalizeStripeAuthorization(string $paymentIntentId): void
    {
        $booking = $this->requestItem->booking;
        if (! $booking) {
            session()->flash('warning', 'No visit is ready for payment authorization yet.');

            return;
        }

        try {
            $payment = app(BookingPaymentService::class)->syncPreparedAuthorization($booking, $paymentIntentId);
        } catch (PaymentException $e) {
            session()->flash('warning', $e->userMessage);

            return;
        }

        $this->refreshRequestItem();

        if ($payment->status === CareBookingPayment::STATUS_AUTHORIZED) {
            $correction = app(CareBookingTimeCorrectionService::class)->activeForBooking($booking);
            if ($correction?->status === CareBookingTimeCorrection::STATUS_PAYMENT_ACTION_REQUIRED) {
                $correction = app(CareBookingTimeCorrectionService::class)->resumeApproved($correction, auth()->user());
                $this->refreshRequestItem();
                if ($correction->status === CareBookingTimeCorrection::STATUS_APPLIED) {
                    session()->flash('status', 'Visit time updated and payment completed.');
                } else {
                    session()->flash('warning', 'Hours remain approved. Payment still needs attention.');
                }

                return;
            }

            session()->flash('status', 'Card pre-authorization complete. This visit is now payment protected.');

            return;
        }

        session()->flash('warning', $payment->last_error ?: 'Payment authorization still needs attention.');
    }

    public function failStripeAuthorization(?string $paymentIntentId = null, string $message = ''): void
    {
        $booking = $this->requestItem->booking;
        if (! $booking) {
            session()->flash('warning', $message !== '' ? $message : 'Card authorization failed.');

            return;
        }

        $payment = app(BookingPaymentService::class)->recordClientAuthorizationFailure(
            $booking,
            $paymentIntentId,
            $message
        );

        $this->refreshRequestItem();
        session()->flash('warning', $payment?->last_error ?: ($message !== '' ? $message : 'Card authorization failed.'));
    }

    public function beginTimeCorrectionApproval(int $correctionId): void
    {
        $correction = $this->ownedTimeCorrection($correctionId);
        if ($correction->status !== CareBookingTimeCorrection::STATUS_PENDING_FAMILY) {
            session()->flash('status', 'A newer time correction is available.');

            return;
        }

        $this->confirmingTimeCorrectionId = $correction->id;
        $this->resetValidation();
        $this->dispatch('time-correction-approval-opened');
    }

    public function cancelTimeCorrectionApproval(): void
    {
        $this->confirmingTimeCorrectionId = null;
        $this->dispatch('time-correction-approval-closed');
    }

    public function approveTimeCorrection(int $correctionId): void
    {
        abort_unless((int) $this->confirmingTimeCorrectionId === $correctionId, 403);
        $correction = app(CareBookingTimeCorrectionService::class)->approve(
            $this->ownedTimeCorrection($correctionId),
            auth()->user(),
        );
        $this->confirmingTimeCorrectionId = null;
        $this->refreshRequestItem();

        if ($correction->status === CareBookingTimeCorrection::STATUS_PAYMENT_ACTION_REQUIRED) {
            $payment = $this->requestItem->booking?->payment;
            if ($payment) {
                $this->dispatchPaymentConfirmation($payment);
            }
            session()->flash('warning', 'Hours approved — payment confirmation needed.');

            return;
        }

        session()->flash('status', $correction->status === CareBookingTimeCorrection::STATUS_APPROVED_ADMIN_REQUIRED
            ? 'Hours approved. LoLo Care will review the existing payment before updating the visit.'
            : 'Visit time approved and updated.');
    }

    public function requestTimeCorrectionChanges(int $correctionId): void
    {
        app(CareBookingTimeCorrectionService::class)->requestChanges(
            $this->ownedTimeCorrection($correctionId),
            auth()->user(),
            $this->timeCorrectionResponseNote,
        );
        $this->timeCorrectionResponseNote = '';
        $this->confirmingTimeCorrectionId = null;
        $this->refreshRequestItem();
        session()->flash('status', 'Your note was sent to the caregiver.');
    }

    public function continueTimeCorrectionPayment(int $correctionId): void
    {
        $correction = app(CareBookingTimeCorrectionService::class)->resumeApproved(
            $this->ownedTimeCorrection($correctionId),
            auth()->user(),
        );
        $this->refreshRequestItem();

        if ($correction->status === CareBookingTimeCorrection::STATUS_PAYMENT_ACTION_REQUIRED) {
            $payment = $this->requestItem->booking?->payment;
            if ($payment) {
                $this->dispatchPaymentConfirmation($payment);
            }
            session()->flash('warning', 'Confirm billing in the secure Stripe window.');

            return;
        }

        session()->flash('status', 'Visit time updated and payment completed.');
    }

    public function escalateTimeCorrection(int $correctionId): void
    {
        app(CareBookingTimeCorrectionService::class)->escalate(
            $this->ownedTimeCorrection($correctionId),
            auth()->user(),
            'The family asked LoLo Care to help resolve the visit time.',
        );
        $this->refreshRequestItem();
        session()->flash('status', 'LoLo Care support will review this visit.');
    }

    public function completeBooking(): void
    {
        $booking = $this->requestItem->booking;
        if (! $booking) {
            return;
        }

        if (in_array($booking->status, [CareBooking::STATUS_IN_PROGRESS, CareBooking::STATUS_PAUSED], true)) {
            $booking->update([
                'status' => CareBooking::STATUS_COMPLETED,
                'completed_at' => now(),
                'timesheet_submitted_at' => $booking->timesheet_submitted_at ?: now(),
                'paused_at' => null,
            ]);

            app(BookingTrustService::class)->recordEvent(
                $booking,
                auth()->id(),
                'family',
                'booking_completed_by_family'
            );
        } elseif (in_array($booking->status, [CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED], true) && ! $booking->family_confirmed_at) {
            try {
                app(BookingPaymentService::class)->captureForBooking($booking);
            } catch (PaymentException $e) {
                session()->flash('status', $e->userMessage);

                return;
            }

            $booking->update([
                'family_confirmed_at' => now(),
                'family_confirmed_by_user_id' => auth()->id(),
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
            $this->refreshRequestItem(preferLifecyclePrimary: true);
            session()->flash('status', 'Timesheet confirmed.');

            return;
        } else {
            session()->flash('status', 'This action is only available after caregiver check-in or timesheet submission.');

            return;
        }

        if ($booking->caregiver) {
            app(MarketplaceNotificationService::class)->notify(
                recipients: $booking->caregiver,
                eventKey: MarketplaceEvent::SHIFT_COMPLETED,
                title: 'Care visit completed',
                body: auth()->user()->name.' marked this visit as completed.',
                url: route('care-requests.apply', $this->requestItem->id),
                payload: ['care_booking_id' => $booking->id],
                subject: $booking,
                dedupeKey: 'shift-completed:booking-'.$booking->id.'-user-'.$booking->caregiver->id
            );
        }

        FunnelTracker::track('care_booking_completed', auth()->user(), $booking);
        app(BookingTrustService::class)->recomputeReliabilityForBooking($booking);
        $this->refreshRequestItem(preferLifecyclePrimary: true);
        session()->flash('status', 'Visit marked completed.');
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

        if (! $booking->family_confirmed_at) {
            try {
                app(BookingPaymentService::class)->captureForBooking($booking);
            } catch (PaymentException $e) {
                session()->flash('status', $e->userMessage);

                return;
            }
        }

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
            'family_confirmed_by_user_id' => $booking->family_confirmed_by_user_id ?: auth()->id(),
        ]);

        app(BookingTrustService::class)->recordEvent(
            $booking,
            auth()->id(),
            'family',
            'review_submitted_by_family',
            ['rating' => $this->reviewRating]
        );

        if ($booking->caregiver) {
            app(MarketplaceNotificationService::class)->notify(
                recipients: $booking->caregiver,
                eventKey: MarketplaceEvent::REVIEW_RECEIVED,
                title: 'You received a new review',
                body: auth()->user()->name.' left feedback after visit #'.$booking->id.'.',
                url: route('care-requests.apply', $this->requestItem->id),
                payload: ['care_booking_id' => $booking->id, 'rating' => $this->reviewRating],
                subject: $booking
            );
        }

        FunnelTracker::track('care_review_submitted', auth()->user(), $booking, [
            'rating' => $this->reviewRating,
        ]);
        app(BookingTrustService::class)->recomputeReliabilityForBooking($booking);

        $this->reset(['reviewRating', 'reviewComment']);
        $this->refreshRequestItem(preferLifecyclePrimary: true);
        session()->flash('status', 'Review submitted.');
    }

    public function submitChangeRequest(): void
    {
        $booking = $this->requestItem->booking;
        if (! $booking || $booking->status !== CareBooking::STATUS_SCHEDULED) {
            session()->flash('status', 'Visit changes are only available before caregiver check-in. Use support for active or completed visits.');

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

        if ($booking->caregiver) {
            app(MarketplaceNotificationService::class)->notify(
                recipients: $booking->caregiver,
                eventKey: MarketplaceEvent::BOOKING_CHANGE_REQUESTED,
                title: 'Visit change request',
                body: auth()->user()->name.' requested to '.$this->changeType.' this visit.',
                url: route('care-requests.apply', $this->requestItem->id),
                payload: ['care_booking_id' => $booking->id, 'change_type' => $this->changeType],
                subject: $changeRequest
            );
        }

        FunnelTracker::track('booking_change_requested', auth()->user(), $changeRequest, [
            'type' => $this->changeType,
        ]);

        $this->reset(['changeReason', 'proposedStartAt', 'proposedEndAt']);
        $this->refreshRequestItem(preferLifecyclePrimary: true);
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
            if ($changeRequest->requester) {
                app(MarketplaceNotificationService::class)->notify(
                    recipients: $changeRequest->requester,
                    eventKey: MarketplaceEvent::BOOKING_CHANGE_DECLINED,
                    title: 'Visit change declined',
                    body: 'The other person could not accept your requested visit change. Open the visit to review the current schedule.',
                    url: route('care-requests.apply', $this->requestItem->id),
                    payload: ['care_booking_id' => $booking->id, 'change_request_id' => $changeRequest->id],
                    subject: $changeRequest
                );
            }

            $this->refreshRequestItem(preferLifecyclePrimary: true);
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
            app(BookingPaymentService::class)->cancelForBooking($booking);
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

        if ($changeRequest->requester) {
            app(MarketplaceNotificationService::class)->notify(
                recipients: $changeRequest->requester,
                eventKey: MarketplaceEvent::BOOKING_CHANGE_ACCEPTED,
                title: 'Visit change accepted',
                body: 'The requested visit change was accepted. Open the visit to review the updated details.',
                url: route('care-requests.apply', $this->requestItem->id),
                payload: ['care_booking_id' => $booking->id, 'change_request_id' => $changeRequest->id],
                subject: $changeRequest
            );
        }

        $this->refreshRequestItem(preferLifecyclePrimary: true);
        session()->flash('status', 'Change request accepted and visit updated.');
    }

    public function createSupportTicket(): void
    {
        $this->validate([
            'supportSubject' => ['required', 'string', 'min:8', 'max:160'],
            'supportDescription' => ['required', 'string', 'min:12', 'max:4000'],
            'supportCategory' => ['required', Rule::in(['general', 'dispute', 'incident', 'cancellation', 'billing', 'time_correction'])],
        ]);

        SupportTicket::query()->create([
            'family_account_id' => $this->requestItem->family_account_id,
            'family_visibility' => $this->supportCategory === 'billing' ? 'owner_only' : 'shared_care',
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
            'family_account_id' => $this->requestItem->family_account_id,
            'family_visibility' => 'shared_care',
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
        $this->refreshRequestItem(preferLifecyclePrimary: true);
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
        app(BookingPaymentService::class)->cancelForBooking($booking);

        app(BookingTrustService::class)->recordEvent(
            $booking,
            auth()->id(),
            'family',
            'caregiver_no_show_marked'
        );
        app(BookingTrustService::class)->recomputeReliabilityForBooking($booking);

        if ($booking->caregiver) {
            app(MarketplaceNotificationService::class)->notify(
                recipients: $booking->caregiver,
                eventKey: MarketplaceEvent::SHIFT_CANCELLED,
                title: 'Visit marked no-show',
                body: auth()->user()->name.' marked the visit as caregiver no-show.',
                url: route('care-requests.apply', $this->requestItem->id),
                payload: ['care_booking_id' => $booking->id, 'care_request_id' => $this->requestItem->id],
                subject: $booking,
                dedupeKey: 'shift-no-show:booking-'.$booking->id.'-user-'.$booking->caregiver_user_id
            );
        }

        $this->refreshRequestItem(preferLifecyclePrimary: true);
        session()->flash('status', 'Visit marked as no-show.');
    }

    public function cancelScheduledBooking(): void
    {
        $booking = $this->requestItem->booking;
        if (! $booking || $booking->status !== CareBooking::STATUS_SCHEDULED) {
            session()->flash('status', 'Only scheduled visits can be cancelled here.');

            return;
        }

        $this->validate([
            'directCancelReason' => ['required', 'string', 'min:8', 'max:2000'],
        ]);

        $lateCancel = app(BookingTrustService::class)->markLateCancelFlag($booking);
        $reason = trim($this->directCancelReason);

        $booking->update([
            'status' => CareBooking::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by_user_id' => auth()->id(),
            'cancellation_reason' => $reason,
            'late_cancel_flag' => $lateCancel,
        ]);

        app(BookingPaymentService::class)->cancelForBooking($booking);
        app(BookingTrustService::class)->recordEvent(
            $booking,
            auth()->id(),
            'family',
            'booking_cancelled_by_family',
            [
                'reason' => $reason,
                'late_cancel' => $lateCancel,
            ]
        );
        app(BookingTrustService::class)->recomputeReliabilityForBooking($booking);

        if ($booking->caregiver) {
            app(MarketplaceNotificationService::class)->notify(
                recipients: $booking->caregiver,
                eventKey: MarketplaceEvent::SHIFT_CANCELLED,
                title: 'Visit cancelled',
                body: auth()->user()->name.' cancelled a scheduled visit.',
                url: route('care-requests.apply', $this->requestItem->id),
                payload: [
                    'care_booking_id' => $booking->id,
                    'care_request_id' => $this->requestItem->id,
                    'late_cancel' => $lateCancel,
                ],
                subject: $booking,
                dedupeKey: 'shift-cancelled:booking-'.$booking->id.'-user-'.$booking->caregiver_user_id
            );
        }

        FunnelTracker::track('care_booking_cancelled', auth()->user(), $booking, [
            'source' => 'family_shift_command',
            'late_cancel' => $lateCancel,
        ]);

        $this->directCancelReason = '';
        $this->refreshRequestItem(preferLifecyclePrimary: true);
        session()->flash('status', $lateCancel
            ? 'Visit cancelled. This was inside the late-cancellation window.'
            : 'Visit cancelled and payment authorization released.'
        );
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
            'family_account_id' => $this->requestItem->family_account_id,
            'family_visibility' => 'shared_care',
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

        $hiredApplication->loadMissing('caregiver:id,email');
        if (! CaregiverPrelaunch::familyCanProceedWithCaregiver(
            $hiredApplication->caregiver?->email,
            $this->requestItem,
            (int) $hiredApplication->caregiver_user_id,
        )) {
            session()->flash('status', CaregiverPrelaunch::familyHireMessage());

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
                'recurring_schedule',
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
            $attributes = array_merge(
                $attributes,
                app(FamilyAccountContext::class)->ownershipAttributes(auth()->user()),
                ['created_by_user_id' => auth()->id()],
            );

            if ($this->requestItem->request_type === CareRequest::TYPE_ONE_TIME) {
                $attributes['requested_start_at'] = optional($this->requestItem->requested_start_at)->copy()?->addWeek();
                $attributes['requested_end_at'] = optional($this->requestItem->requested_end_at)->copy()?->addWeek();
            } else {
                $attributes['recurring_starts_on'] = optional($this->requestItem->recurring_starts_on)?->copy()?->addWeek()?->toDateString();
            }

            $newRequest = CareRequest::query()->create($attributes);

            if ($this->requestItem->recipient) {
                $newRequest->recipient()->create($this->requestItem->recipient->only([
                    'recipient_is_requester',
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
                ...app(FamilyAccountContext::class)->ownershipAttributes(auth()->user()),
                'invited_by_user_id' => auth()->id(),
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

        if (! app(FamilyAccountContext::class)->canAccessRecord(auth()->user(), $application->careRequest)
            || ! in_array($application->status, [
                CareRequestApplication::STATUS_APPLIED,
                CareRequestApplication::STATUS_SHORTLISTED,
                CareRequestApplication::STATUS_HIRED,
            ], true)) {
            session()->flash('status', 'You can chat with active caregivers in this request.');

            return;
        }

        if ($application->status === CareRequestApplication::STATUS_APPLIED) {
            $application->update(['status' => CareRequestApplication::STATUS_SHORTLISTED]);
            if (! $this->requestItem->first_shortlist_at) {
                $this->requestItem->update(['first_shortlist_at' => now()]);
            }
        }

        $conversation = CareRequestConversation::findOrCreateForApplication($application, auth()->id());
        $this->redirect(route('messages.show', $conversation->id, false), navigate: true);
    }

    public function inviteSuggestedCaregiver(int $caregiverUserId): void
    {
        $caregiver = User::query()->with('caregiverProfile')->find($caregiverUserId);
        if (! $caregiver) {
            session()->flash('status', 'Caregiver could not be found.');

            return;
        }

        $result = app(CareRequestInvitationService::class)->send(
            family: auth()->user(),
            careRequest: $this->requestItem,
            caregiver: $caregiver,
            message: 'We think your profile could be a strong fit for this request.',
            source: 'request_suggestion',
        );

        $this->refreshRequestItem();
        session()->flash('status', $result->message);
    }

    public function openCaregiverInvitePanel(): void
    {
        if ($this->requestItem->status !== CareRequest::STATUS_OPEN) {
            session()->flash('status', 'Invitations are available only for open requests.');

            return;
        }

        $this->activeTab = 'applicants';
        $this->showCaregiverInvitePanel = true;
        $this->confirmingCaregiverId = null;
        $this->confirmingReinvite = false;
        $this->caregiverInviteFeedback = null;
        $this->resetValidation('caregiverInviteMessage');
        $this->dispatch('caregiver-invite-panel-opened');
    }

    public function closeCaregiverInvitePanel(): void
    {
        $this->showCaregiverInvitePanel = false;
        $this->confirmingCaregiverId = null;
        $this->confirmingReinvite = false;
        $this->caregiverInviteMessage = '';
        $this->caregiverInviteFeedback = null;
        $this->certificationTypes = [];
        $this->certificationVerification = CaregiverCertificationCriteria::VERIFICATION_ANY_CURRENT;
        $this->resetValidation('caregiverInviteMessage');
        $this->dispatch('caregiver-invite-panel-closed');
    }

    public function clearCaregiverSearch(): void
    {
        $this->caregiverSearch = '';
        $this->caregiverInviteFeedback = null;
    }

    public function updatedCertificationTypes(): void
    {
        $this->normalizeCertificationFilters();
        $this->clearConfirmingCaregiverIfNoLongerMatches();
    }

    public function updatedCertificationVerification(): void
    {
        $this->normalizeCertificationFilters();
        $this->clearConfirmingCaregiverIfNoLongerMatches();
    }

    public function clearCertificationFilters(): void
    {
        $this->certificationTypes = [];
        $this->certificationVerification = CaregiverCertificationCriteria::VERIFICATION_ANY_CURRENT;
        $this->clearConfirmingCaregiverIfNoLongerMatches();
    }

    public function removeCertificationFilter(string $slug): void
    {
        $this->certificationTypes = collect($this->certificationTypes)
            ->reject(fn ($selected): bool => (string) $selected === $slug)
            ->values()
            ->all();
        $this->normalizeCertificationFilters();
        $this->clearConfirmingCaregiverIfNoLongerMatches();
    }

    public function includeReportedCertifications(): void
    {
        $this->certificationVerification = CaregiverCertificationCriteria::VERIFICATION_ANY_CURRENT;
        $this->clearConfirmingCaregiverIfNoLongerMatches();
    }

    public function updatedApplicationCertificationTypes(): void
    {
        $this->normalizeApplicationCertificationFilters();
    }

    public function updatedApplicationCertificationVerification(): void
    {
        $this->normalizeApplicationCertificationFilters();
    }

    public function clearApplicationCertificationFilters(): void
    {
        $this->applicationCertificationTypes = [];
        $this->applicationCertificationVerification = CaregiverCertificationCriteria::VERIFICATION_ANY_CURRENT;
    }

    public function removeApplicationCertificationFilter(string $slug): void
    {
        $this->applicationCertificationTypes = collect($this->applicationCertificationTypes)
            ->reject(fn ($selected): bool => (string) $selected === $slug)
            ->values()
            ->all();
        $this->normalizeApplicationCertificationFilters();
    }

    public function includeReportedApplicationCertifications(): void
    {
        $this->applicationCertificationVerification = CaregiverCertificationCriteria::VERIFICATION_ANY_CURRENT;
    }

    public function beginCaregiverInvitation(int $caregiverUserId, bool $reinvite = false): void
    {
        $family = auth()->user();
        abort_unless($family, 403);

        $card = app(CaregiverInvitationDiscoveryService::class)
            ->caregiver($this->requestItem, $family, $caregiverUserId, $this->certificationCriteria());

        if (! $card) {
            $this->caregiverInviteFeedback = [
                'type' => 'error',
                'message' => 'This caregiver is not currently available to invite.',
            ];

            return;
        }

        $allowed = $reinvite ? $card['can_reinvite'] : $card['can_invite'];
        if (! $allowed) {
            $this->caregiverInviteFeedback = [
                'type' => 'info',
                'message' => $card['status_detail'],
            ];

            return;
        }

        $this->confirmingCaregiverId = $caregiverUserId;
        $this->confirmingReinvite = $reinvite;
        $this->caregiverInviteMessage = 'Hi '.$card['first_name'].', we would like to invite you to review “'.$this->requestItem->title.'”.';
        $this->caregiverInviteFeedback = null;
        $this->resetValidation('caregiverInviteMessage');
        $this->dispatch('caregiver-invite-content-top');
    }

    public function cancelCaregiverInvitation(): void
    {
        $this->confirmingCaregiverId = null;
        $this->confirmingReinvite = false;
        $this->caregiverInviteMessage = '';
        $this->resetValidation('caregiverInviteMessage');
        $this->dispatch('caregiver-invite-content-top');
    }

    public function sendCaregiverInvitation(): void
    {
        $this->validate([
            'caregiverInviteMessage' => ['nullable', 'string', 'max:1200'],
            'confirmingCaregiverId' => ['required', 'integer'],
        ], [
            'confirmingCaregiverId.required' => 'Choose a caregiver before sending an invitation.',
        ]);

        $family = auth()->user();
        abort_unless($family, 403);

        $card = app(CaregiverInvitationDiscoveryService::class)->caregiver(
            $this->requestItem,
            $family,
            (int) $this->confirmingCaregiverId,
            $this->certificationCriteria(),
        );
        $allowed = $card && ($this->confirmingReinvite ? $card['can_reinvite'] : $card['can_invite']);
        if (! $allowed) {
            $this->confirmingCaregiverId = null;
            $this->confirmingReinvite = false;
            $this->caregiverInviteFeedback = [
                'type' => 'error',
                'message' => 'This caregiver no longer matches your filters or is no longer available. Return to the results and choose again.',
            ];

            return;
        }

        $caregiver = User::query()
            ->with('caregiverProfile')
            ->find($this->confirmingCaregiverId);

        if (! $caregiver) {
            $this->caregiverInviteFeedback = [
                'type' => 'error',
                'message' => 'This caregiver could not be found. Try searching again.',
            ];

            return;
        }

        $result = app(CareRequestInvitationService::class)->send(
            family: $family,
            careRequest: $this->requestItem,
            caregiver: $caregiver,
            message: $this->caregiverInviteMessage,
            reinvite: $this->confirmingReinvite,
            source: 'request_search',
        );

        $this->caregiverInviteFeedback = [
            'type' => $result->sentNow ? 'success' : 'info',
            'message' => $result->message,
        ];
        $this->confirmingCaregiverId = null;
        $this->confirmingReinvite = false;
        $this->caregiverInviteMessage = '';
        $this->refreshRequestItem();
        $this->dispatch('caregiver-invite-content-top');
    }

    private function deriveScheduledStartAt(): ?Carbon
    {
        if ($this->requestItem->request_type === CareRequest::TYPE_ONE_TIME) {
            return $this->requestItem->requested_start_at;
        }

        return $this->deriveRecurringScheduledRange()[0] ?? null;
    }

    private function deriveScheduledEndAt(): ?Carbon
    {
        if ($this->requestItem->request_type === CareRequest::TYPE_ONE_TIME) {
            return $this->requestItem->requested_end_at;
        }

        return $this->deriveRecurringScheduledRange()[1] ?? null;
    }

    /** @return array{0:Carbon,1:Carbon}|null */
    private function deriveRecurringScheduledRange(): ?array
    {
        $slots = $this->requestItem->recurringScheduleSlots();
        if ($slots === []) {
            return null;
        }

        $date = ($this->requestItem->recurring_starts_on ?: now())->copy()->startOfDay();
        for ($offset = 0; $offset < 7; $offset++) {
            $candidate = $date->copy()->addDays($offset);
            $slot = WeeklySchedule::forDay($slots, (int) $candidate->dayOfWeek);
            if (! $slot) {
                continue;
            }

            return [
                $this->combineDateAndTime($candidate, $slot['start_time']),
                $this->combineDateAndTime($candidate, $slot['end_time']),
            ];
        }

        return null;
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

    private function refreshRequestItem(bool $preferLifecyclePrimary = false): void
    {
        $this->requestItem = $this->requestItem->fresh([
            'family:id,name,email,phone',
            'recipient',
            'thirdPartyContact',
            'tasks',
            'booking',
            'booking.payment',
            'booking.carePlan:id,timezone',
            'booking.timeCorrections' => fn ($query) => $query
                ->with(['requester:id,name', 'approvedBy:id,name', 'supportTicket:id,status'])
                ->orderByDesc('version'),
            'booking.taskChecks',
            'booking.events.actor:id,name',
            'booking.incidents.reporter:id,name',
            'booking.changeRequests.requester:id,name,role',
            'booking.reviews.reviewer:id,name',
            'invitations' => fn ($query) => $query->with(['caregiver:id,name']),
            'applications' => fn ($query) => $query->with([
                'caregiver:id,name,email,phone,city,state',
                'caregiver.caregiverProfile:id,user_id,slug,profile_photo_path,bio,years_experience,status,average_rating,reviews_count,platform_hourly_rate,identity_verified_at,identity_verification_status,background_check_verified_at,top_caregiver,invite_response_rate,reliability_score,completed_bookings_count,is_accepting_new_clients',
                'caregiver.caregiverProfile.skills:id,name',
                'caregiver.caregiverProfile.languages:id,name',
                'caregiver.caregiverProfile.publicSearchCertifications',
                'conversation:id,care_request_application_id,care_request_id,caregiver_user_id',
                'booking:id,care_request_id,care_request_application_id,status,scheduled_start_at,scheduled_end_at',
            ]),
        ]);

        if ($this->reconcileFilledStateFromBooking()) {
            $this->requestItem = $this->requestItem->fresh([
                'family:id,name,email,phone',
                'recipient',
                'thirdPartyContact',
                'tasks',
                'booking',
                'booking.payment',
                'booking.carePlan:id,timezone',
                'booking.timeCorrections' => fn ($query) => $query
                    ->with(['requester:id,name', 'approvedBy:id,name', 'supportTicket:id,status'])
                    ->orderByDesc('version'),
                'booking.taskChecks',
                'booking.events.actor:id,name',
                'booking.incidents.reporter:id,name',
                'booking.changeRequests.requester:id,name,role',
                'booking.reviews.reviewer:id,name',
                'invitations' => fn ($query) => $query->with(['caregiver:id,name']),
                'applications' => fn ($query) => $query->with([
                    'caregiver:id,name,email,phone,city,state',
                    'caregiver.caregiverProfile:id,user_id,slug,profile_photo_path,bio,years_experience,status,average_rating,reviews_count,platform_hourly_rate,identity_verified_at,identity_verification_status,background_check_verified_at,top_caregiver,invite_response_rate,reliability_score,completed_bookings_count,is_accepting_new_clients',
                    'caregiver.caregiverProfile.skills:id,name',
                    'caregiver.caregiverProfile.languages:id,name',
                    'caregiver.caregiverProfile.publicSearchCertifications',
                    'conversation:id,care_request_application_id,care_request_id,caregiver_user_id',
                    'booking:id,care_request_id,care_request_application_id,status,scheduled_start_at,scheduled_end_at',
                ]),
            ]);
        }

        $visibleTabs = collect(CareRequestProgress::familyLifecycleStage($this->requestItem)['tabs'])
            ->pluck('key')
            ->all();

        if ($preferLifecyclePrimary || ! in_array($this->activeTab, $visibleTabs, true)) {
            $this->activeTab = CareRequestProgress::familyLifecycleStage($this->requestItem)['primary_tab'];
        }
    }

    private function reconcileFilledStateFromBooking(): bool
    {
        $booking = $this->requestItem->relationLoaded('booking')
            ? $this->requestItem->booking
            : $this->requestItem->booking()->first();

        if (! $booking) {
            return false;
        }

        $changed = false;
        $requestUpdates = [];

        if (! in_array($this->requestItem->status, [CareRequest::STATUS_FILLED, CareRequest::STATUS_CANCELLED], true)) {
            $requestUpdates['status'] = CareRequest::STATUS_FILLED;
        }

        if (! $this->requestItem->first_hire_at) {
            $requestUpdates['first_hire_at'] = $booking->created_at ?: now();
        }

        if ($requestUpdates !== []) {
            $this->requestItem->forceFill($requestUpdates)->save();
            $changed = true;
        }

        $hiredApplicationId = (int) ($booking->care_request_application_id ?? 0);
        if ($hiredApplicationId > 0) {
            $application = CareRequestApplication::query()
                ->where('care_request_id', $this->requestItem->id)
                ->whereKey($hiredApplicationId)
                ->first();

            if ($application && $application->status !== CareRequestApplication::STATUS_HIRED) {
                $application->update(['status' => CareRequestApplication::STATUS_HIRED]);
                $changed = true;
            }

            $updatedOthers = CareRequestApplication::query()
                ->where('care_request_id', $this->requestItem->id)
                ->whereKeyNot($hiredApplicationId)
                ->whereIn('status', [
                    CareRequestApplication::STATUS_APPLIED,
                    CareRequestApplication::STATUS_SHORTLISTED,
                ])
                ->update(['status' => CareRequestApplication::STATUS_NOT_SELECTED]);

            $changed = $changed || $updatedOthers > 0;
        }

        return $changed;
    }

    private function dispatchPaymentConfirmation(CareBookingPayment $payment): void
    {
        $publishableKey = (string) config('services.stripe.publishable_key', '');
        $clientSecret = (string) $payment->stripe_payment_intent_client_secret;

        if ($publishableKey === '' || $clientSecret === '') {
            session()->flash('status', 'Payment authorization is waiting, but Stripe confirmation is not available. Update billing and retry.');

            return;
        }

        $this->dispatch(
            'confirm-stripe-booking-payment',
            publishableKey: $publishableKey,
            clientSecret: $clientSecret,
            paymentMethodId: (string) $payment->stripe_payment_method_id,
            paymentIntentId: (string) $payment->stripe_payment_intent_id,
            bookingId: (int) $payment->care_booking_id,
        );
    }

    private function ownedTimeCorrection(int $correctionId): CareBookingTimeCorrection
    {
        return CareBookingTimeCorrection::query()
            ->where('care_booking_id', $this->requestItem->booking?->id)
            ->findOrFail($correctionId);
    }

    public function getVisibleApplicationsProperty()
    {
        $applications = $this->requestItem->applications;

        if ($this->applicationStatus !== 'all') {
            $applications = $applications->where('status', $this->applicationStatus);
        }

        $applicationCertificationCriteria = $this->applicationCertificationCriteria();
        if ($applicationCertificationCriteria->hasSelections()) {
            $certificationFilter = app(\App\Services\Marketplace\CaregiverCertificationFilter::class);
            $applications = $applications->filter(function (CareRequestApplication $application) use ($applicationCertificationCriteria, $certificationFilter): bool {
                $profile = $application->caregiver?->caregiverProfile;

                return $profile && $certificationFilter->matches($profile, $applicationCertificationCriteria);
            });
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
        $certificationCriteria = $this->certificationCriteria();
        $applicationCertificationCriteria = $this->applicationCertificationCriteria();
        $suggestedCaregivers = collect();
        if ($this->requestItem->status === CareRequest::STATUS_OPEN) {
            $suggestedCaregivers = app(CaregiverSuggestionService::class)
                ->topMatchesForRequest($this->requestItem, 3, $certificationCriteria);
        }

        $lifecycleStage = CareRequestProgress::familyLifecycleStage($this->requestItem);

        if (! collect($lifecycleStage['tabs'])->pluck('key')->contains($this->activeTab)) {
            $this->activeTab = $lifecycleStage['primary_tab'];
        }

        $caregiverSearchResults = collect();
        $caregiverInitialSections = [];
        $confirmingCaregiver = null;
        if ($this->showCaregiverInvitePanel) {
            $family = auth()->user();
            $discovery = app(CaregiverInvitationDiscoveryService::class);
            $search = trim($this->caregiverSearch);

            if ($search === '') {
                $caregiverInitialSections = $discovery->initialSections($this->requestItem, $family, $certificationCriteria);
            } elseif (mb_strlen($search) >= 2) {
                $caregiverSearchResults = $discovery->search($this->requestItem, $family, $search, $certificationCriteria);
            }

            if ($this->confirmingCaregiverId) {
                $confirmingCaregiver = $discovery->caregiver(
                    $this->requestItem,
                    $family,
                    $this->confirmingCaregiverId,
                    $certificationCriteria,
                );
            }
        }

        return view('livewire.family.manage-care-request', [
            'suggestedCaregivers' => $suggestedCaregivers,
            'lifecycleStage' => $lifecycleStage,
            'caregiverSearchResults' => $caregiverSearchResults,
            'caregiverInitialSections' => $caregiverInitialSections,
            'confirmingCaregiver' => $confirmingCaregiver,
            'certificationOptions' => CaregiverCertificationCriteria::activeOptions(),
            'certificationCriteria' => $certificationCriteria,
            'applicationCertificationCriteria' => $applicationCertificationCriteria,
            'careProfileSnapshot' => app(CareRecipientProfilePresenter::class)
                ->forCareRequest(auth()->user(), $this->requestItem),
        ]);
    }

    private function certificationCriteria(): CaregiverCertificationCriteria
    {
        return CaregiverCertificationCriteria::fromInput(
            $this->certificationTypes,
            $this->certificationVerification,
        );
    }

    private function applicationCertificationCriteria(): CaregiverCertificationCriteria
    {
        return CaregiverCertificationCriteria::fromInput(
            $this->applicationCertificationTypes,
            $this->applicationCertificationVerification,
        );
    }

    private function normalizeCertificationFilters(): void
    {
        $criteria = $this->certificationCriteria();
        $this->certificationTypes = $criteria->typeSlugs();
        $this->certificationVerification = $criteria->verification();
    }

    private function normalizeApplicationCertificationFilters(): void
    {
        $criteria = $this->applicationCertificationCriteria();
        $this->applicationCertificationTypes = $criteria->typeSlugs();
        $this->applicationCertificationVerification = $criteria->verification();
    }

    private function clearConfirmingCaregiverIfNoLongerMatches(): void
    {
        if (! $this->showCaregiverInvitePanel || ! $this->confirmingCaregiverId) {
            return;
        }

        $family = auth()->user();
        if (! $family) {
            return;
        }

        $card = app(CaregiverInvitationDiscoveryService::class)->caregiver(
            $this->requestItem,
            $family,
            $this->confirmingCaregiverId,
            $this->certificationCriteria(),
        );

        if ($card) {
            return;
        }

        $this->confirmingCaregiverId = null;
        $this->confirmingReinvite = false;
        $this->caregiverInviteMessage = '';
        $this->caregiverInviteFeedback = [
            'type' => 'info',
            'message' => 'Your selected caregiver no longer matches the certification filters. Choose another caregiver.',
        ];
    }
}
