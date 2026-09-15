<?php

namespace App\Services\Booking;

use App\Models\CareBooking;
use App\Models\CareBookingChangeRequest;
use App\Models\CareBookingPayment;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Services\Payments\BookingPaymentService;
use App\Support\MarketplaceEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CaregiverCancellationService
{
    public function __construct(
        private readonly BookingPaymentService $payments,
        private readonly BookingTrustService $trust,
    ) {}

    public function cancel(CareBooking $visit, User $actor, string $reason, ?int $changeRequestId = null): CareBooking
    {
        $reason = trim($reason);
        Validator::make(['cancellationReason' => $reason], ['cancellationReason' => ['required', 'string', 'min:8', 'max:2000']])->validate();

        return DB::transaction(function () use ($visit, $actor, $reason, $changeRequestId): CareBooking {
            // The hiring flow takes the same request lock before selecting an applicant.
            $request = CareRequest::query()->lockForUpdate()->findOrFail($visit->care_request_id);
            $booking = CareBooking::query()->lockForUpdate()->findOrFail($visit->id);
            $this->ensure((int) $booking->family_user_id === (int) $request->family_user_id
                && (int) $booking->family_account_id === (int) $request->family_account_id,
                'The visit ownership needs support review before cancellation.');
            $change = $changeRequestId ? $booking->changeRequests()->lockForUpdate()->findOrFail($changeRequestId) : null;
            $isCaregiver = $actor->role === 'caregiver' && (int) $actor->id === (int) $booking->caregiver_user_id;
            $isFamilyAccepting = $actor->role === 'family'
                && app(FamilyAccountContext::class)->canAccessRecord($actor, $request)
                && $change && $change->type === CareBookingChangeRequest::TYPE_CANCEL
                && (int) $change->requester_user_id === (int) $booking->caregiver_user_id;
            abort_unless($isCaregiver || $isFamilyAccepting, 403);

            if ($booking->replacement_released_at) {
                return $booking; // A retry must never reopen a request that has since been hired again.
            }
            $this->ensure(! $change || ($change->status === CareBookingChangeRequest::STATUS_PENDING
                && $change->type === CareBookingChangeRequest::TYPE_CANCEL
                && (int) $change->requester_user_id === (int) $booking->caregiver_user_id),
                'This caregiver cancellation request is no longer pending.');
            $this->ensure($request->request_type === CareRequest::TYPE_ONE_TIME && ! $request->care_plan_id && ! $booking->care_plan_id,
                'For regular care, use the visit change request or contact LoLo Care.');
            $this->ensure($request->status === CareRequest::STATUS_FILLED && (int) $request->booking?->id === (int) $booking->id,
                'This request has changed. Refresh the visit before cancelling.');
            $this->ensure($booking->status === CareBooking::STATUS_SCHEDULED
                && ! $booking->started_at && ! $booking->completed_at && ! $booking->paused_at
                && ! $booking->timesheet_submitted_at && ! $booking->family_confirmed_at
                && ! $booking->worked_minutes && ! $booking->total_paused_seconds && ! $booking->dispute_opened_at,
                'This visit has recorded care or is no longer scheduled. Contact LoLo Care to correct it.');
            $this->ensure($request->isAcceptingApplications(), 'This visit time has passed. Contact LoLo Care for help with the visit.');
            $this->ensure(! $booking->timeCorrections()->active()->exists(), 'This visit has a time correction in progress. Contact LoLo Care.');

            $application = CareRequestApplication::query()->lockForUpdate()->findOrFail($booking->care_request_application_id);
            $this->ensure((int) $application->care_request_id === (int) $request->id
                && (int) $application->caregiver_user_id === (int) $booking->caregiver_user_id
                && $application->status === CareRequestApplication::STATUS_HIRED, 'The hired caregiver has changed. Refresh this visit.');

            $payment = $booking->payment()->lockForUpdate()->first();
            if ($payment) {
                $this->ensure((int) $payment->family_user_id === (int) $booking->family_user_id
                    && (int) $payment->family_account_id === (int) $booking->family_account_id
                    && (int) $payment->caregiver_user_id === (int) $booking->caregiver_user_id
                    && ! $payment->amount_captured_cents && ! $payment->amount_refunded_cents
                    && ! $payment->captured_at && ! $payment->transferred_at && ! $payment->stripe_transfer_id
                    && ! $payment->stripe_overage_payment_intent_id
                    && in_array($payment->status, [CareBookingPayment::STATUS_DRAFT, CareBookingPayment::STATUS_AUTHORIZED,
                        CareBookingPayment::STATUS_AUTHORIZATION_REQUIRED, CareBookingPayment::STATUS_REAUTH_REQUIRED,
                        CareBookingPayment::STATUS_FAILED, CareBookingPayment::STATUS_CANCELLED], true),
                    'This visit has a payment that needs support review before cancellation.');
                if ($payment->status === CareBookingPayment::STATUS_DRAFT && ! $payment->stripe_payment_intent_id) {
                    $payment->update(['status' => CareBookingPayment::STATUS_CANCELLED]);
                } else {
                    $this->payments->cancelForBooking($booking);
                }
                // cancelForBooking records processor errors instead of throwing them.
                $this->ensure($payment->fresh()->status === CareBookingPayment::STATUS_CANCELLED,
                    'The card authorization could not be released. Your visit has not been cancelled. Please retry or contact LoLo Care.');
            }

            $booking->update([
                'status' => CareBooking::STATUS_CANCELLED,
                'cancelled_at' => now(), 'cancelled_by_user_id' => $booking->caregiver_user_id,
                'cancellation_reason' => $reason, 'late_cancel_flag' => $this->trust->markLateCancelFlag($booking),
                'replacement_released_at' => now(),
            ]);
            $application->update(['status' => CareRequestApplication::STATUS_WITHDRAWN]);
            $restored = $request->applications()->where('status', CareRequestApplication::STATUS_NOT_SELECTED)
                ->lockForUpdate()->pluck('id')->all();
            $request->applications()->whereIn('id', $restored)->update(['status' => CareRequestApplication::STATUS_APPLIED]);
            // Reopening an existing request must not emit a new-request welcome campaign.
            $request->forceFill(['status' => CareRequest::STATUS_OPEN])->saveQuietly();
            if ($change) {
                $change->update(['status' => CareBookingChangeRequest::STATUS_ACCEPTED,
                    'resolved_at' => now(), 'resolved_by_user_id' => $actor->id]);
            }
            $booking->changeRequests()->where('status', CareBookingChangeRequest::STATUS_PENDING)->update([
                'status' => CareBookingChangeRequest::STATUS_WITHDRAWN, 'resolved_at' => now(),
                'resolved_by_user_id' => $actor->id, 'resolution_note' => 'The caregiver cancelled this visit; the care request reopened.',
            ]);
            $this->trust->recordEvent($booking, $actor->id, $actor->role, 'caregiver_cancelled_request_reopened', [
                'care_request_id' => $request->id, 'application_id' => $application->id,
                'restored_application_ids' => $restored, 'reason' => $reason,
                'change_request_id' => $change?->id, 'payment_id' => $payment?->id,
            ]);
            $this->trust->recomputeReliabilityForBooking($booking);
            DB::afterCommit(function () use ($booking, $request): void {
                if ($booking->family) {
                    app(MarketplaceNotificationService::class)->notify(
                        recipients: $booking->family, eventKey: MarketplaceEvent::SHIFT_CANCELLED,
                        title: 'Choose a replacement caregiver',
                        body: $booking->caregiver->name.' cannot make this visit. Your request is open again so you can hire someone else.',
                        url: route('family.requests.show', ['careRequest' => $request->id, 'tab' => 'applicants']),
                        payload: ['care_booking_id' => $booking->id, 'care_request_id' => $request->id],
                        subject: $booking, dedupeKey: 'caregiver-cancelled-reopened:booking-'.$booking->id,
                    );
                }
            });

            return $booking;
        });
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['cancellationReason' => $message]);
        }
    }
}
