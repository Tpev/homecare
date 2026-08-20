<?php

namespace App\Services\Marketplace;

use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\User;
use App\Services\Booking\BookingTrustService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Services\Payments\BookingPaymentService;
use App\Services\RegularCare\CarePlanService;
use App\Support\CaregiverPrelaunch;
use App\Support\FunnelTracker;
use App\Support\MarketplaceEvent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CareRequestHiringService
{
    public function __construct(
        private readonly FamilyAccountContext $familyAccounts,
        private readonly BookingTrustService $trust,
        private readonly BookingPaymentService $payments,
        private readonly CarePlanService $plans,
        private readonly MarketplaceNotificationService $notifications,
    ) {}

    /** @return array{kind:string,request:CareRequest,application:CareRequestApplication,booking:?CareBooking,payment:?CareBookingPayment,care_plan_id:?int} */
    public function hire(User $family, CareRequestApplication $application): array
    {
        $application->loadMissing(['careRequest.recipient', 'careRequest.tasks', 'caregiver:id,email,name']);
        $request = $application->careRequest;
        if ($family->role !== 'family' || ! $this->familyAccounts->canAccessRecord($family, $request)) {
            throw new AuthorizationException('You cannot hire for this care request.');
        }
        if ($request->status !== CareRequest::STATUS_OPEN
            || ! in_array($application->status, [CareRequestApplication::STATUS_APPLIED, CareRequestApplication::STATUS_SHORTLISTED], true)) {
            throw ValidationException::withMessages(['hire' => 'This request or applicant is no longer eligible for hiring.']);
        }
        if (! CaregiverPrelaunch::familyCanProceedWithCaregiver($application->caregiver?->email, $request, (int) $application->caregiver_user_id)) {
            throw ValidationException::withMessages(['hire' => CaregiverPrelaunch::familyHireMessage()]);
        }

        if ($request->request_type === CareRequest::TYPE_RECURRING) {
            $plan = $this->plans->activateFromRecurringRequest($request, $application, $family);
            $request = $request->fresh(['booking.payment']);
            FunnelTracker::track('regular_care_marketplace_hired', $family, $plan, [
                'care_request_id' => $request->id,
                'caregiver_user_id' => $application->caregiver_user_id,
                'source' => 'ai_support_confirmed',
            ]);

            return [
                'kind' => 'regular_care', 'request' => $request, 'application' => $application->fresh(),
                'booking' => $request->booking, 'payment' => $request->booking?->payment, 'care_plan_id' => (int) $plan->id,
            ];
        }

        $booking = DB::transaction(function () use ($family, $request, $application): CareBooking {
            $lockedRequest = CareRequest::query()->lockForUpdate()->with(['recipient', 'tasks'])->findOrFail($request->id);
            $lockedApplication = CareRequestApplication::query()->lockForUpdate()->findOrFail($application->id);
            if (! $this->familyAccounts->canAccessRecord($family, $lockedRequest)
                || $lockedRequest->status !== CareRequest::STATUS_OPEN
                || ! in_array($lockedApplication->status, [CareRequestApplication::STATUS_APPLIED, CareRequestApplication::STATUS_SHORTLISTED], true)) {
                throw ValidationException::withMessages(['hire' => 'This request or applicant changed. Review the current applicants and confirm again.']);
            }

            $lockedApplication->update(['status' => CareRequestApplication::STATUS_HIRED]);
            CareRequestConversation::findOrCreateForApplication($lockedApplication->loadMissing('careRequest'), $family->id);
            $lockedRequest->applications()->where('id', '!=', $lockedApplication->id)
                ->whereIn('status', [CareRequestApplication::STATUS_APPLIED, CareRequestApplication::STATUS_SHORTLISTED])
                ->update(['status' => CareRequestApplication::STATUS_NOT_SELECTED]);
            $lockedRequest->update(['status' => CareRequest::STATUS_FILLED]);
            if (! $lockedRequest->first_hire_at) {
                $lockedRequest->update(['first_hire_at' => now()]);
            }
            $start = $lockedRequest->requested_start_at;
            $end = $lockedRequest->requested_end_at;
            $booking = CareBooking::query()->updateOrCreate(
                ['care_request_id' => $lockedRequest->id],
                [
                    ...$this->familyAccounts->ownershipAttributes($family),
                    'care_request_application_id' => $lockedApplication->id,
                    'caregiver_user_id' => (int) $lockedApplication->caregiver_user_id,
                    'agreement_snapshot' => $this->trust->buildAgreementSnapshot($lockedRequest, $lockedApplication),
                    'family_terms_accepted_at' => now(),
                    'status' => CareBooking::STATUS_SCHEDULED,
                    'scheduled_start_at' => $start,
                    'scheduled_end_at' => $end,
                    'expected_minutes' => ($start && $end) ? max(0, (int) $start->diffInMinutes($end, false)) : null,
                ],
            );
            $this->trust->seedTaskChecks($booking, $lockedRequest);
            $this->trust->recordEvent($booking, $family->id, 'family', 'booking_hired', [
                'application_id' => $lockedApplication->id,
                'source' => 'ai_support_confirmed',
            ]);
            $this->payments->prepareOnSessionAuthorization($booking);

            return $booking->fresh(['payment', 'caregiver']);
        }, 3);

        if ($booking->caregiver) {
            $this->notifications->notify(
                recipients: $booking->caregiver,
                eventKey: MarketplaceEvent::CAREGIVER_HIRED,
                title: 'You were hired',
                body: 'A family selected you for this care request.',
                url: route('care-requests.apply', $request->id),
                payload: ['care_request_id' => $request->id],
                subject: $application,
            );
        }
        $this->notifications->notify(
            recipients: $family,
            eventKey: MarketplaceEvent::HIRE_CONFIRMED,
            title: 'Hire confirmed',
            body: 'Caregiver hired and visit created for this request.',
            url: route('family.requests.show', $request->id),
            payload: ['care_request_id' => $request->id],
            subject: $application,
            dedupeKey: 'hire-confirmed:request-'.$request->id.'-user-'.$family->id,
        );
        FunnelTracker::track('caregiver_hired', $family, $application, [
            'care_request_id' => $request->id,
            'source' => 'ai_support_confirmed',
        ]);

        return [
            'kind' => 'one_time', 'request' => $request->fresh(), 'application' => $application->fresh(),
            'booking' => $booking, 'payment' => $booking->payment, 'care_plan_id' => null,
        ];
    }
}
