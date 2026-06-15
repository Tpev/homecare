<?php

namespace App\Services\Payments;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CaregiverPayout;
use App\Models\CaregiverPayoutItem;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Support\MarketplaceEvent;
use App\Support\MarketplacePricing;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class BookingPaymentService
{
    public function __construct(
        private readonly StripeClient $stripe,
        private readonly MarketplaceNotificationService $notifications,
        private readonly MarketplacePricing $pricing,
    ) {
    }

    public function authorizeForBooking(CareBooking $booking, bool $forceReauthorize = false): CareBookingPayment
    {
        $booking->loadMissing([
            'application',
            'family',
            'caregiver',
            'caregiver.caregiverProfile',
            'payment',
        ]);

        $existing = $booking->payment;
        if (! $forceReauthorize && $existing && in_array($existing->status, [
            CareBookingPayment::STATUS_AUTHORIZED,
            CareBookingPayment::STATUS_CAPTURED,
            CareBookingPayment::STATUS_TRANSFERRED,
            CareBookingPayment::STATUS_PARTIALLY_REFUNDED,
            CareBookingPayment::STATUS_REFUNDED,
        ], true)) {
            return $existing;
        }

        $family = $booking->family;
        if (! $family) {
            throw new PaymentException('Family billing profile is missing.');
        }

        $customerId = $this->stripe->ensureFamilyCustomer($family);
        $defaultPaymentMethod = $this->stripe->defaultPaymentMethodForCustomer($customerId);

        if (! $defaultPaymentMethod) {
            throw new PaymentException(
                'Add a payment method before hiring. Open Billing & Payments from your account menu.'
            );
        }

        $amountCents = $this->authorizationAmountCents($booking);
        $currency = $this->stripe->currency();

        try {
            $authorization = $this->stripe->createManualAuthorization(
                $booking,
                $customerId,
                (string) $defaultPaymentMethod['id'],
                $amountCents,
                $currency,
                $this->idempotencyKey($booking->id, 'authorize', $amountCents)
            );
        } catch (PaymentException $e) {
            $status = $this->looksLikeActionRequired($e)
                ? CareBookingPayment::STATUS_AUTHORIZATION_REQUIRED
                : CareBookingPayment::STATUS_FAILED;

            $payment = $this->persistAuthorizationFailure(
                booking: $booking,
                customerId: $customerId,
                paymentMethodId: (string) $defaultPaymentMethod['id'],
                status: $status,
                message: $e->getMessage()
            );

            $this->notifyAuthorizationState($booking, $payment, $status);

            if ($status === CareBookingPayment::STATUS_AUTHORIZATION_REQUIRED) {
                throw new PaymentException(
                    'Card verification is required. Open Billing & Payments, confirm your card, then retry.',
                    $e->getMessage()
                );
            }

            throw $e;
        }

        $authorizationStatus = (string) ($authorization['status'] ?? '');

        if ($authorizationStatus !== 'requires_capture') {
            $status = $this->authorizationStatusRequiresAction($authorizationStatus)
                ? CareBookingPayment::STATUS_AUTHORIZATION_REQUIRED
                : CareBookingPayment::STATUS_FAILED;
            $message = (string) ($authorization['failure_message'] ?? '');
            if ($message === '') {
                $message = $this->authorizationFailureMessage($authorizationStatus);
            }

            $payment = $this->persistAuthorizationFailure(
                booking: $booking,
                customerId: $customerId,
                paymentMethodId: (string) $defaultPaymentMethod['id'],
                status: $status,
                message: $message,
                paymentIntentId: (string) ($authorization['payment_intent_id'] ?? null),
                clientSecret: (string) ($authorization['client_secret'] ?? null)
            );

            $this->notifyAuthorizationState($booking, $payment, $status);

            throw new PaymentException($this->authorizationFailureMessage($authorizationStatus), $message);
        }

        $payment = CareBookingPayment::query()->updateOrCreate(
            ['care_booking_id' => $booking->id],
            [
                'family_user_id' => (int) $booking->family_user_id,
                'caregiver_user_id' => (int) $booking->caregiver_user_id,
                'status' => CareBookingPayment::STATUS_AUTHORIZED,
                'currency' => $currency,
                'stripe_customer_id' => $customerId,
                'stripe_payment_method_id' => (string) $defaultPaymentMethod['id'],
                'stripe_payment_intent_id' => (string) $authorization['payment_intent_id'],
                'stripe_payment_intent_client_secret' => (string) ($authorization['client_secret'] ?? null),
                'amount_authorized_cents' => (int) $authorization['amount'],
                'authorization_expires_at' => $authorization['authorization_expires_at'],
                'authorized_at' => now(),
                'reauthorized_at' => $forceReauthorize ? now() : null,
                'failed_at' => null,
                'last_error' => null,
                'overage_pending_cents' => 0,
                'metadata' => $this->authorizationMetadata($booking, (int) $authorization['amount']),
            ]
        );

        $this->notifyAuthorizationState($booking, $payment, CareBookingPayment::STATUS_AUTHORIZED);

        return $payment;
    }

    public function prepareOnSessionAuthorization(CareBooking $booking, bool $forceNewIntent = false): CareBookingPayment
    {
        $booking->loadMissing([
            'application',
            'family',
            'caregiver',
            'caregiver.caregiverProfile',
            'payment',
        ]);

        $existing = $booking->payment;
        if (! $forceNewIntent && $existing && in_array($existing->status, [
            CareBookingPayment::STATUS_AUTHORIZED,
            CareBookingPayment::STATUS_CAPTURED,
            CareBookingPayment::STATUS_TRANSFERRED,
            CareBookingPayment::STATUS_PARTIALLY_REFUNDED,
            CareBookingPayment::STATUS_REFUNDED,
        ], true)) {
            return $existing;
        }

        $family = $booking->family;
        if (! $family) {
            throw new PaymentException('Family billing profile is missing.');
        }

        $customerId = $this->stripe->ensureFamilyCustomer($family);
        $defaultPaymentMethod = $this->stripe->defaultPaymentMethodForCustomer($customerId);

        if (! $defaultPaymentMethod) {
            throw new PaymentException(
                'Add a payment method before hiring. Open Billing & Payments from your account menu.'
            );
        }

        $amountCents = $this->authorizationAmountCents($booking);
        $currency = $this->stripe->currency();
        $metadata = $this->authorizationMetadata($booking, $amountCents);

        if (! $forceNewIntent && $existing && $this->canReuseAuthorizationIntent($existing, $amountCents, $currency)) {
            $existing->forceFill([
                'stripe_customer_id' => $customerId,
                'stripe_payment_method_id' => (string) $defaultPaymentMethod['id'],
                'metadata' => array_merge($existing->metadata ?? [], $metadata),
            ])->save();

            return $existing->fresh();
        }

        $intent = $this->stripe->createManualAuthorizationIntent(
            $booking,
            $customerId,
            (string) $defaultPaymentMethod['id'],
            $amountCents,
            $currency,
            $this->idempotencyKey($booking->id, 'authorize-client-'.now()->format('YmdHisv'), $amountCents)
        );

        $status = (string) ($intent['status'] ?? '');
        $paymentStatus = $status === 'requires_capture'
            ? CareBookingPayment::STATUS_AUTHORIZED
            : CareBookingPayment::STATUS_AUTHORIZATION_REQUIRED;

        $payment = CareBookingPayment::query()->updateOrCreate(
            ['care_booking_id' => $booking->id],
            [
                'family_user_id' => (int) $booking->family_user_id,
                'caregiver_user_id' => (int) $booking->caregiver_user_id,
                'status' => $paymentStatus,
                'currency' => $currency,
                'stripe_customer_id' => $customerId,
                'stripe_payment_method_id' => (string) $defaultPaymentMethod['id'],
                'stripe_payment_intent_id' => (string) $intent['payment_intent_id'],
                'stripe_payment_intent_client_secret' => (string) ($intent['client_secret'] ?? null),
                'amount_authorized_cents' => $paymentStatus === CareBookingPayment::STATUS_AUTHORIZED
                    ? (int) $intent['amount']
                    : null,
                'authorization_expires_at' => $intent['authorization_expires_at'],
                'authorized_at' => $paymentStatus === CareBookingPayment::STATUS_AUTHORIZED ? now() : null,
                'failed_at' => $paymentStatus === CareBookingPayment::STATUS_AUTHORIZED ? null : now(),
                'last_error' => $paymentStatus === CareBookingPayment::STATUS_AUTHORIZED
                    ? null
                    : 'Card authorization needs confirmation.',
                'metadata' => $metadata,
            ]
        );

        $this->notifyAuthorizationState($booking, $payment, $paymentStatus);

        return $payment;
    }

    public function syncPreparedAuthorization(CareBooking $booking, string $paymentIntentId): CareBookingPayment
    {
        $booking->loadMissing(['payment', 'family', 'caregiver']);

        $payment = $booking->payment;
        if (! $payment || (string) $payment->stripe_payment_intent_id !== $paymentIntentId) {
            throw new PaymentException('Payment authorization was not found for this booking.');
        }

        $intent = $this->stripe->retrievePaymentIntent($paymentIntentId);

        return $this->applyPaymentIntentState($payment, $intent);
    }

    public function recordClientAuthorizationFailure(CareBooking $booking, ?string $paymentIntentId, string $message): ?CareBookingPayment
    {
        $booking->loadMissing(['payment']);

        $payment = $booking->payment;
        if (! $payment) {
            return null;
        }

        if ($paymentIntentId && (string) $payment->stripe_payment_intent_id !== $paymentIntentId) {
            return $payment;
        }

        $payment->forceFill([
            'status' => CareBookingPayment::STATUS_FAILED,
            'failed_at' => now(),
            'last_error' => $message !== '' ? $message : 'Card authorization failed.',
        ])->save();

        $this->notifyAuthorizationState($booking, $payment, CareBookingPayment::STATUS_FAILED);

        return $payment->fresh();
    }

    public function captureForBooking(CareBooking $booking): CareBookingPayment
    {
        $booking->loadMissing([
            'application',
            'family',
            'caregiver',
            'caregiver.caregiverProfile',
            'payment',
            'payment.booking',
        ]);

        $payment = $booking->payment;
        if (! $payment) {
            throw new PaymentException('Payment authorization is missing for this booking.');
        }

        if (in_array($payment->status, [
            CareBookingPayment::STATUS_AUTHORIZATION_REQUIRED,
            CareBookingPayment::STATUS_REAUTH_REQUIRED,
            CareBookingPayment::STATUS_FAILED,
        ], true)) {
            throw new PaymentException(
                'Payment authorization is not ready. Confirm the card authorization before approving the timesheet.'
            );
        }

        if (in_array($payment->status, [CareBookingPayment::STATUS_CAPTURED, CareBookingPayment::STATUS_TRANSFERRED], true)
            && (int) $payment->overage_pending_cents <= 0) {
            return $payment;
        }

        if (in_array($payment->status, [CareBookingPayment::STATUS_REFUNDED], true)) {
            return $payment;
        }

        if ($this->isAuthorizationExpired($payment)) {
            try {
                $payment = $this->authorizeForBooking($booking, forceReauthorize: true);
            } catch (PaymentException $e) {
                $payment->forceFill([
                    'status' => CareBookingPayment::STATUS_REAUTH_REQUIRED,
                    'failed_at' => now(),
                    'last_error' => $e->getMessage(),
                ])->save();

                $this->notifyAuthorizationState($booking, $payment, CareBookingPayment::STATUS_REAUTH_REQUIRED);

                throw new PaymentException(
                    'Previous card authorization expired. Re-open Billing & Payments and retry confirmation.',
                    $e->getMessage()
                );
            }
        }

        $paymentIntentId = (string) $payment->stripe_payment_intent_id;
        if ($paymentIntentId === '') {
            throw new PaymentException('Payment authorization reference is missing.');
        }

        $targetCaptureCents = $this->captureAmountCents($booking, $payment);
        $authorizedCents = (int) ($payment->amount_authorized_cents ?? 0);
        if ($authorizedCents <= 0) {
            throw new PaymentException('Authorized amount is missing for this booking.');
        }

        $primaryCaptureCents = min($targetCaptureCents, $authorizedCents);
        if ($primaryCaptureCents <= 0) {
            throw new PaymentException('Capture amount is invalid for this booking.');
        }

        $capture = $this->stripe->capturePaymentIntent(
            $paymentIntentId,
            $primaryCaptureCents,
            $this->idempotencyKey($booking->id, 'capture-primary', $primaryCaptureCents)
        );

        $capturedCents = (int) ($capture['amount_received'] ?? $primaryCaptureCents);
        $overageCents = max(0, $targetCaptureCents - $primaryCaptureCents);
        $overageChargedCents = 0;
        $overagePendingCents = 0;
        $overageIntentId = null;

        if ($overageCents > 0) {
            try {
                $overage = $this->stripe->createAndConfirmCharge(
                    $booking,
                    (string) $payment->stripe_customer_id,
                    (string) $payment->stripe_payment_method_id,
                    $overageCents,
                    (string) $payment->currency,
                    [
                        'care_booking_payment_id' => (string) $payment->id,
                    ],
                    $this->idempotencyKey($booking->id, 'capture-overage', $overageCents)
                );

                $overageChargedCents = (int) ($overage['amount_received'] ?? $overageCents);
                $overageIntentId = (string) ($overage['id'] ?? null);
                $capturedCents += $overageChargedCents;
            } catch (PaymentException $e) {
                $overagePendingCents = $overageCents;
                $payment->forceFill([
                    'last_error' => $e->getMessage(),
                ])->save();

                $this->notify(
                    recipients: $booking->family,
                    eventKey: MarketplaceEvent::PAYMENT_ACTION_REQUIRED,
                    title: 'Additional payment action needed',
                    body: 'Final shift amount exceeded the original authorization. Open billing to complete it.',
                    url: route('family.billing.show'),
                    payload: [
                        'care_booking_id' => $booking->id,
                        'care_booking_payment_id' => $payment->id,
                        'overage_pending_cents' => $overagePendingCents,
                    ],
                    subject: $booking,
                    dedupeKey: 'payment-overage-action:booking-'.$booking->id
                );
            }
        }

        $platformFeeCents = (int) round($capturedCents * ($this->platformFeePercent($booking) / 100));
        $caregiverAmountCents = max(0, $capturedCents - $platformFeeCents);

        $payment->forceFill([
            'status' => CareBookingPayment::STATUS_CAPTURED,
            'amount_captured_cents' => $capturedCents,
            'amount_overage_cents' => $overageChargedCents,
            'overage_pending_cents' => $overagePendingCents,
            'stripe_overage_payment_intent_id' => $overageIntentId,
            'platform_fee_cents' => $platformFeeCents,
            'caregiver_amount_cents' => $caregiverAmountCents,
            'captured_at' => now(),
            'failed_at' => null,
        ]);

        $transferCompleted = false;
        $profile = $booking->caregiver?->caregiverProfile;
        if ($profile?->stripeConnectIsReady() && $profile->stripe_connect_account_id && $caregiverAmountCents > 0) {
            try {
                $transfer = $this->stripe->createTransfer(
                    (string) $profile->stripe_connect_account_id,
                    $caregiverAmountCents,
                    (string) $payment->currency,
                    [
                        'care_booking_id' => (string) $booking->id,
                        'care_request_id' => (string) $booking->care_request_id,
                        'caregiver_user_id' => (string) $booking->caregiver_user_id,
                    ],
                    $this->idempotencyKey($booking->id, 'transfer', $caregiverAmountCents)
                );

                $transferCompleted = true;
                $payment->forceFill([
                    'status' => CareBookingPayment::STATUS_TRANSFERRED,
                    'stripe_transfer_id' => (string) $transfer['id'],
                    'transferred_at' => now(),
                    'last_error' => null,
                ]);
            } catch (PaymentException $e) {
                $payment->forceFill([
                    'status' => CareBookingPayment::STATUS_TRANSFER_FAILED,
                    'last_error' => $e->getMessage(),
                ]);

                $this->notify(
                    recipients: $booking->family,
                    eventKey: MarketplaceEvent::PAYOUT_TRANSFER_FAILED,
                    title: 'Payout transfer delayed',
                    body: 'The shift payment was captured, but caregiver payout transfer is delayed. Admin will retry automatically.',
                    url: route('family.requests.show', $booking->care_request_id),
                    payload: [
                        'care_booking_id' => $booking->id,
                        'care_booking_payment_id' => $payment->id,
                    ],
                    subject: $booking,
                    dedupeKey: 'payout-transfer-failed:booking-'.$booking->id
                );
            }
        }

        $payment->save();
        $this->syncPayoutRecords($booking, $payment, $transferCompleted);

        $this->notify(
            recipients: $booking->family,
            eventKey: MarketplaceEvent::PAYMENT_CAPTURED,
            title: 'Shift payment captured',
            body: 'Final payment was captured successfully for this completed shift.',
            url: route('family.requests.show', $booking->care_request_id),
            payload: [
                'care_booking_id' => $booking->id,
                'care_booking_payment_id' => $payment->id,
                'amount_captured_cents' => $capturedCents,
                'overage_pending_cents' => $overagePendingCents,
            ],
            subject: $booking,
            dedupeKey: 'payment-captured:booking-'.$booking->id
        );

        if ($transferCompleted && $booking->caregiver) {
            $this->notify(
                recipients: $booking->caregiver,
                eventKey: MarketplaceEvent::PAYOUT_TRANSFERRED,
                title: 'Payout sent',
                body: 'Your payout for this shift has been transferred.',
                url: route('care-requests.apply', $booking->care_request_id),
                payload: [
                    'care_booking_id' => $booking->id,
                    'care_booking_payment_id' => $payment->id,
                    'caregiver_amount_cents' => $caregiverAmountCents,
                ],
                subject: $booking,
                dedupeKey: 'payout-transferred:booking-'.$booking->id
            );
        }

        return $payment->fresh();
    }

    public function cancelForBooking(CareBooking $booking): void
    {
        $payment = CareBookingPayment::query()
            ->where('care_booking_id', $booking->id)
            ->first();

        if (! $payment || ! in_array($payment->status, [
            CareBookingPayment::STATUS_AUTHORIZED,
            CareBookingPayment::STATUS_AUTHORIZATION_REQUIRED,
            CareBookingPayment::STATUS_REAUTH_REQUIRED,
            CareBookingPayment::STATUS_FAILED,
        ], true)) {
            return;
        }

        try {
            if ($payment->stripe_payment_intent_id) {
                $this->stripe->cancelPaymentIntent((string) $payment->stripe_payment_intent_id);
            }
        } catch (PaymentException $e) {
            $payment->forceFill([
                'last_error' => $e->getMessage(),
            ])->save();

            return;
        }

        $payment->forceFill([
            'status' => CareBookingPayment::STATUS_CANCELLED,
            'failed_at' => now(),
            'last_error' => 'Authorization cancelled before capture.',
        ])->save();
    }

    public function refundForBooking(
        CareBooking $booking,
        ?int $amountCents = null,
        string $reason = 'requested_by_customer',
    ): CareBookingPayment {
        $booking->loadMissing(['payment', 'family', 'caregiver']);
        $payment = $booking->payment;

        if (! $payment || ! in_array($payment->status, [
            CareBookingPayment::STATUS_CAPTURED,
            CareBookingPayment::STATUS_TRANSFERRED,
            CareBookingPayment::STATUS_TRANSFER_FAILED,
            CareBookingPayment::STATUS_PARTIALLY_REFUNDED,
        ], true)) {
            throw new PaymentException('This booking is not in a refundable state.');
        }

        $captured = (int) ($payment->amount_captured_cents ?? 0);
        $alreadyRefunded = (int) ($payment->amount_refunded_cents ?? 0);
        $remaining = max(0, $captured - $alreadyRefunded);
        if ($remaining <= 0) {
            return $payment;
        }

        $refundCents = $amountCents ? min($amountCents, $remaining) : $remaining;
        if ($refundCents <= 0) {
            return $payment;
        }

        $paymentIntentId = (string) $payment->stripe_payment_intent_id;
        if ($paymentIntentId === '') {
            throw new PaymentException('Missing payment intent reference for refund.');
        }

        $refund = $this->stripe->createRefund(
            $paymentIntentId,
            $refundCents,
            $reason,
            [
                'care_booking_id' => (string) $booking->id,
                'care_booking_payment_id' => (string) $payment->id,
            ],
            $this->idempotencyKey($booking->id, 'refund', $refundCents)
        );

        $caregiverRefundCents = $this->caregiverRefundShareCents($payment, $refundCents);
        $reversalId = null;
        if ($payment->stripe_transfer_id && $caregiverRefundCents > 0) {
            $reversal = $this->stripe->createTransferReversal(
                (string) $payment->stripe_transfer_id,
                $caregiverRefundCents,
                [
                    'care_booking_id' => (string) $booking->id,
                    'care_booking_payment_id' => (string) $payment->id,
                ],
                $this->idempotencyKey($booking->id, 'transfer-reversal', $caregiverRefundCents)
            );
            $reversalId = (string) ($reversal['id'] ?? null);
        }

        $newRefundedTotal = $alreadyRefunded + (int) ($refund['amount'] ?? $refundCents);
        $fullyRefunded = $newRefundedTotal >= $captured;
        $newStatus = $fullyRefunded
            ? CareBookingPayment::STATUS_REFUNDED
            : CareBookingPayment::STATUS_PARTIALLY_REFUNDED;

        $payment->forceFill([
            'status' => $newStatus,
            'amount_refunded_cents' => $newRefundedTotal,
            'stripe_last_refund_id' => (string) ($refund['id'] ?? null),
            'stripe_last_transfer_reversal_id' => $reversalId,
            'last_error' => null,
            'failed_at' => null,
            'metadata' => array_merge((array) $payment->metadata, [
                'last_refund_reason' => $reason,
                'last_refund_at' => now()->toISOString(),
            ]),
        ])->save();

        $this->syncPayoutReversalRecords($booking, $caregiverRefundCents, $fullyRefunded);

        $this->notify(
            recipients: $booking->family,
            eventKey: MarketplaceEvent::PAYMENT_REFUNDED,
            title: $fullyRefunded ? 'Payment refunded' : 'Partial refund issued',
            body: $fullyRefunded
                ? 'Your payment was fully refunded for this booking.'
                : 'A partial refund was issued for this booking.',
            url: route('family.requests.show', $booking->care_request_id),
            payload: [
                'care_booking_id' => $booking->id,
                'care_booking_payment_id' => $payment->id,
                'amount_refunded_cents' => $refundCents,
            ],
            subject: $booking,
            dedupeKey: 'payment-refund:booking-'.$booking->id.'-total-'.$newRefundedTotal
        );

        if ($booking->caregiver) {
            $this->notify(
                recipients: $booking->caregiver,
                eventKey: MarketplaceEvent::PAYMENT_REFUNDED,
                title: $fullyRefunded ? 'Shift payout adjusted' : 'Partial payout adjustment',
                body: $fullyRefunded
                    ? 'This shift was refunded and payout was adjusted.'
                    : 'A partial refund was issued and payout was adjusted.',
                url: route('care-requests.apply', $booking->care_request_id),
                payload: [
                    'care_booking_id' => $booking->id,
                    'care_booking_payment_id' => $payment->id,
                    'caregiver_refund_cents' => $caregiverRefundCents,
                ],
                subject: $booking,
                dedupeKey: 'caregiver-refund:booking-'.$booking->id.'-total-'.$newRefundedTotal
            );
        }

        return $payment->fresh();
    }

    public function retryTransfer(CareBookingPayment $payment): CareBookingPayment
    {
        $payment->loadMissing(['booking.caregiver.caregiverProfile', 'booking.caregiver']);
        $booking = $payment->booking;
        if (! $booking) {
            throw new PaymentException('Booking not found for this payment.');
        }

        if (! in_array($payment->status, [
            CareBookingPayment::STATUS_CAPTURED,
            CareBookingPayment::STATUS_TRANSFER_FAILED,
        ], true)) {
            return $payment;
        }

        if ($payment->stripe_transfer_id) {
            return $payment->fresh();
        }

        $caregiverAmountCents = (int) ($payment->caregiver_amount_cents ?? 0);
        if ($caregiverAmountCents <= 0) {
            return $payment->fresh();
        }

        $profile = $booking->caregiver?->caregiverProfile;
        if (! $profile?->stripeConnectIsReady() || ! $profile->stripe_connect_account_id) {
            throw new PaymentException('Caregiver payout account is not ready.');
        }

        try {
            $transfer = $this->stripe->createTransfer(
                (string) $profile->stripe_connect_account_id,
                $caregiverAmountCents,
                (string) $payment->currency,
                [
                    'care_booking_id' => (string) $booking->id,
                    'care_request_id' => (string) $booking->care_request_id,
                    'caregiver_user_id' => (string) $booking->caregiver_user_id,
                ],
                $this->idempotencyKey($booking->id, 'transfer-retry', $caregiverAmountCents)
            );
        } catch (PaymentException $e) {
            $payment->forceFill([
                'status' => CareBookingPayment::STATUS_TRANSFER_FAILED,
                'last_error' => $e->userMessage,
            ])->save();

            throw $e;
        }

        $payment->forceFill([
            'status' => CareBookingPayment::STATUS_TRANSFERRED,
            'stripe_transfer_id' => (string) $transfer['id'],
            'transferred_at' => now(),
            'last_error' => null,
        ])->save();

        $this->syncPayoutRecords($booking, $payment->fresh(), transferCompleted: true);

        if ($booking->caregiver) {
            $this->notify(
                recipients: $booking->caregiver,
                eventKey: MarketplaceEvent::PAYOUT_TRANSFERRED,
                title: 'Payout sent',
                body: 'Your payout transfer was completed.',
                url: route('care-requests.apply', $booking->care_request_id),
                payload: [
                    'care_booking_id' => $booking->id,
                    'care_booking_payment_id' => $payment->id,
                ],
                subject: $booking,
                dedupeKey: 'payout-transferred-retry:booking-'.$booking->id
            );
        }

        return $payment->fresh();
    }

    /**
     * @param  array<string, mixed>  $object
     */
    public function handlePaymentIntentWebhook(array $object): void
    {
        $paymentIntentId = (string) ($object['id'] ?? '');
        if ($paymentIntentId === '') {
            return;
        }

        $payment = CareBookingPayment::query()
            ->where('stripe_payment_intent_id', $paymentIntentId)
            ->orWhere('stripe_overage_payment_intent_id', $paymentIntentId)
            ->first();

        if (! $payment) {
            return;
        }

        $this->applyPaymentIntentState($payment, $object);
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function applyPaymentIntentState(CareBookingPayment $payment, array $object): CareBookingPayment
    {
        $paymentIntentId = (string) ($object['id'] ?? '');
        $status = (string) ($object['status'] ?? '');
        $amount = (int) ($object['amount'] ?? 0);
        $amountReceived = (int) ($object['amount_received'] ?? 0);
        $clientSecret = (string) ($object['client_secret'] ?? '');
        $authorizationExpiresAt = $object['authorization_expires_at'] ?? null;

        if ($status === 'requires_capture') {
            $payload = [
                'status' => CareBookingPayment::STATUS_AUTHORIZED,
                'amount_authorized_cents' => $amount > 0 ? $amount : $payment->amount_authorized_cents,
                'authorized_at' => $payment->authorized_at ?: now(),
                'failed_at' => null,
                'last_error' => null,
            ];

            if ($clientSecret !== '') {
                $payload['stripe_payment_intent_client_secret'] = $clientSecret;
            }

            if ($authorizationExpiresAt) {
                $payload['authorization_expires_at'] = $authorizationExpiresAt;
            }

            $payment->forceFill($payload)->save();

            return $payment->fresh();
        }

        if (in_array($status, ['requires_action', 'requires_confirmation'], true)) {
            $payload = [
                'status' => CareBookingPayment::STATUS_AUTHORIZATION_REQUIRED,
                'failed_at' => now(),
                'last_error' => $status === 'requires_confirmation'
                    ? 'Card authorization needs confirmation.'
                    : 'Stripe requires additional action before capture.',
            ];

            if ($clientSecret !== '') {
                $payload['stripe_payment_intent_client_secret'] = $clientSecret;
            }

            $payment->forceFill($payload)->save();

            return $payment->fresh();
        }

        if ($status === 'succeeded') {
            $isOverageIntent = $paymentIntentId !== ''
                && $payment->stripe_overage_payment_intent_id
                && $paymentIntentId === (string) $payment->stripe_overage_payment_intent_id;
            $capturedAmountCents = $amountReceived > 0
                ? $amountReceived
                : (int) ($payment->amount_captured_cents ?? 0);

            if ($isOverageIntent) {
                $capturedAmountCents = max(
                    (int) ($payment->amount_captured_cents ?? 0),
                    (int) ($payment->amount_authorized_cents ?? 0) + $amountReceived
                );
            }

            $platformFeeCents = $payment->platform_fee_cents;
            $caregiverAmountCents = $payment->caregiver_amount_cents;
            if (! is_int($platformFeeCents) || ! is_int($caregiverAmountCents)) {
                $booking = $payment->booking;
                $booking?->loadMissing('family');
                $platformFeeCents = (int) round($capturedAmountCents * ($this->platformFeePercent($booking) / 100));
                $caregiverAmountCents = max(0, $capturedAmountCents - $platformFeeCents);
            }

            $currentStatus = (string) $payment->status;
            $preservedStatus = in_array($currentStatus, [
                CareBookingPayment::STATUS_TRANSFER_FAILED,
                CareBookingPayment::STATUS_PARTIALLY_REFUNDED,
                CareBookingPayment::STATUS_REFUNDED,
            ], true)
                ? $currentStatus
                : null;

            $payment->forceFill([
                'status' => $preservedStatus
                    ?: ($payment->stripe_transfer_id ? CareBookingPayment::STATUS_TRANSFERRED : CareBookingPayment::STATUS_CAPTURED),
                'amount_captured_cents' => $capturedAmountCents > 0 ? $capturedAmountCents : $payment->amount_captured_cents,
                'amount_overage_cents' => $isOverageIntent
                    ? max((int) ($payment->amount_overage_cents ?? 0), $amountReceived)
                    : $payment->amount_overage_cents,
                'overage_pending_cents' => $isOverageIntent ? 0 : $payment->overage_pending_cents,
                'platform_fee_cents' => $platformFeeCents,
                'caregiver_amount_cents' => $caregiverAmountCents,
                'captured_at' => $payment->captured_at ?: now(),
                'failed_at' => null,
                'last_error' => $currentStatus === CareBookingPayment::STATUS_TRANSFER_FAILED
                    ? $payment->last_error
                    : null,
            ])->save();

            return $payment->fresh();
        }

        if (in_array($status, ['canceled', 'requires_payment_method'], true)) {
            $payment->forceFill([
                'status' => $status === 'canceled'
                    ? CareBookingPayment::STATUS_CANCELLED
                    : CareBookingPayment::STATUS_FAILED,
                'failed_at' => now(),
                'last_error' => (string) data_get($object, 'last_payment_error.message', 'Stripe payment failed.'),
            ])->save();
        }

        return $payment->fresh();
    }

    /**
     * @param  array<string, mixed>  $object
     */
    public function handleTransferWebhook(array $object): void
    {
        $transferId = (string) ($object['id'] ?? '');
        if ($transferId === '') {
            return;
        }

        $payment = CareBookingPayment::query()
            ->where('stripe_transfer_id', $transferId)
            ->first();

        if (! $payment) {
            return;
        }

        $payment->forceFill([
            'status' => CareBookingPayment::STATUS_TRANSFERRED,
            'transferred_at' => $payment->transferred_at ?: now(),
            'last_error' => null,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $object
     */
    public function handleTransferReversalWebhook(array $object): void
    {
        $transferId = (string) ($object['transfer'] ?? '');
        if ($transferId === '') {
            return;
        }

        $payment = CareBookingPayment::query()
            ->where('stripe_transfer_id', $transferId)
            ->first();

        if (! $payment) {
            return;
        }

        $payment->forceFill([
            'stripe_last_transfer_reversal_id' => (string) ($object['id'] ?? null),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $object
     */
    public function handleChargeRefundWebhook(array $object): void
    {
        $paymentIntentId = (string) ($object['payment_intent'] ?? '');
        if ($paymentIntentId === '') {
            return;
        }

        $payment = CareBookingPayment::query()
            ->where('stripe_payment_intent_id', $paymentIntentId)
            ->first();

        if (! $payment) {
            return;
        }

        $refunded = (int) ($object['amount_refunded'] ?? 0);
        $captured = (int) ($payment->amount_captured_cents ?? 0);
        $status = $refunded >= $captured
            ? CareBookingPayment::STATUS_REFUNDED
            : CareBookingPayment::STATUS_PARTIALLY_REFUNDED;

        $payment->forceFill([
            'amount_refunded_cents' => max((int) $payment->amount_refunded_cents, $refunded),
            'status' => $captured > 0 ? $status : $payment->status,
        ])->save();
    }

    private function authorizationAmountCents(CareBooking $booking): int
    {
        $subtotal = $this->bookingSubtotal($booking, $this->estimatedMinutes($booking));
        $withPlatformFee = $subtotal * (1 + ($this->platformFeePercent($booking) / 100));
        $withBuffer = $withPlatformFee * (1 + ($this->authorizationBufferPercent() / 100));

        return max(100, (int) round($withBuffer * 100));
    }

    /**
     * @return array<string, mixed>
     */
    private function authorizationMetadata(CareBooking $booking, int $amountCents): array
    {
        return [
            'hourly_rate' => $this->effectiveHourlyRate($booking),
            'estimated_minutes' => $this->estimatedMinutes($booking),
            'platform_fee_percent' => $this->platformFeePercent($booking),
            'buffer_percent' => $this->authorizationBufferPercent(),
            'requested_authorization_cents' => $amountCents,
        ];
    }

    private function canReuseAuthorizationIntent(CareBookingPayment $payment, int $amountCents, string $currency): bool
    {
        if (! $payment->stripe_payment_intent_id || ! $payment->stripe_payment_intent_client_secret) {
            return false;
        }

        if (! in_array($payment->status, [
            CareBookingPayment::STATUS_AUTHORIZATION_REQUIRED,
            CareBookingPayment::STATUS_REAUTH_REQUIRED,
            CareBookingPayment::STATUS_FAILED,
        ], true)) {
            return false;
        }

        $metadataAmount = (int) data_get($payment->metadata, 'requested_authorization_cents', 0);

        return $metadataAmount === $amountCents
            && strtolower((string) $payment->currency) === strtolower($currency);
    }

    private function captureAmountCents(CareBooking $booking, CareBookingPayment $payment): int
    {
        $workedMinutes = (int) ($booking->worked_minutes ?? 0);
        if ($workedMinutes <= 0) {
            $workedMinutes = $this->estimatedMinutes($booking);
        }

        $subtotal = $this->bookingSubtotal($booking, $workedMinutes);
        $withPlatformFee = $subtotal * (1 + ($this->platformFeePercent($booking) / 100));

        if ($withPlatformFee <= 0) {
            return (int) ($payment->amount_authorized_cents ?? 0);
        }

        return max(100, (int) round($withPlatformFee * 100));
    }

    private function bookingSubtotal(CareBooking $booking, int $minutes): float
    {
        $minutes = max(1, $minutes);
        $hourlyRate = $this->effectiveHourlyRate($booking);

        return round(($minutes / 60) * $hourlyRate, 2);
    }

    private function effectiveHourlyRate(CareBooking $booking): float
    {
        $applicationRate = (float) ($booking->application?->proposed_rate ?? 0);
        if ($applicationRate > 0) {
            return $this->pricing->hourlyRateForBooking($booking, $applicationRate);
        }

        $profileRate = (float) ($booking->caregiver?->caregiverProfile?->resolvePlatformHourlyRate() ?? 0);
        if ($profileRate > 0) {
            return $this->pricing->hourlyRateForBooking($booking, $profileRate);
        }

        return $this->pricing->hourlyRateForBooking(
            $booking,
            (float) config('marketplace.family_estimate_hourly_rate', 30.0)
        );
    }

    private function estimatedMinutes(CareBooking $booking): int
    {
        if (! is_null($booking->expected_minutes) && (int) $booking->expected_minutes > 0) {
            return (int) $booking->expected_minutes;
        }

        if ($booking->scheduled_start_at && $booking->scheduled_end_at) {
            return max(1, (int) $booking->scheduled_start_at->diffInMinutes($booking->scheduled_end_at, false));
        }

        return 60;
    }

    private function platformFeePercent(?CareBooking $booking = null): float
    {
        $fallback = max(0, (float) config('marketplace.payments.platform_fee_percent', 10));

        if (! $booking) {
            return $fallback;
        }

        return $this->pricing->platformFeePercentForBooking($booking, $fallback);
    }

    private function authorizationBufferPercent(): float
    {
        return max(0, (float) config('marketplace.payments.authorization_buffer_percent', 20));
    }

    private function syncPayoutRecords(CareBooking $booking, CareBookingPayment $payment, bool $transferCompleted): void
    {
        $caregiverAmount = (float) ((int) ($payment->caregiver_amount_cents ?? 0) / 100);
        if ($caregiverAmount <= 0) {
            return;
        }

        DB::transaction(function () use ($booking, $payment, $transferCompleted, $caregiverAmount): void {
            $existingItem = CaregiverPayoutItem::query()
                ->where('care_booking_id', $booking->id)
                ->first();

            $payout = $existingItem?->payout;
            if (! $payout) {
                $payout = CaregiverPayout::query()->create([
                    'caregiver_user_id' => $booking->caregiver_user_id,
                    'period_start_on' => ($booking->completed_at ?: now())->toDateString(),
                    'period_end_on' => ($booking->completed_at ?: now())->toDateString(),
                    'scheduled_for' => $transferCompleted ? now() : $this->nextEstimatedPayoutDate(now()),
                    'paid_at' => $transferCompleted ? now() : null,
                    'status' => $transferCompleted ? CaregiverPayout::STATUS_PAID : CaregiverPayout::STATUS_SCHEDULED,
                    'currency' => strtoupper((string) $payment->currency),
                    'gross_amount' => 0,
                    'adjustments_amount' => 0,
                    'net_amount' => 0,
                    'provider_reference' => $transferCompleted ? $payment->stripe_transfer_id : null,
                    'notes' => 'Auto-generated from booking payment #'.$payment->id,
                ]);
            } else {
                $payout->forceFill([
                    'status' => $transferCompleted ? CaregiverPayout::STATUS_PAID : $payout->status,
                    'paid_at' => $transferCompleted ? now() : $payout->paid_at,
                    'provider_reference' => $transferCompleted
                        ? ($payment->stripe_transfer_id ?: $payout->provider_reference)
                        : $payout->provider_reference,
                ])->save();
            }

            CaregiverPayoutItem::query()->updateOrCreate(
                ['care_booking_id' => $booking->id],
                [
                    'caregiver_payout_id' => $payout->id,
                    'caregiver_user_id' => $booking->caregiver_user_id,
                    'status' => $transferCompleted ? CaregiverPayoutItem::STATUS_PAID : CaregiverPayoutItem::STATUS_SCHEDULED,
                    'currency' => strtoupper((string) $payment->currency),
                    'amount' => $caregiverAmount,
                    'included_at' => now(),
                    'paid_at' => $transferCompleted ? now() : null,
                    'notes' => $transferCompleted
                        ? 'Stripe transfer '.$payment->stripe_transfer_id
                        : 'Awaiting payout transfer setup',
                ]
            );

            $gross = (float) $payout->items()->sum('amount');
            $payout->forceFill([
                'gross_amount' => $gross,
                'net_amount' => $gross + (float) $payout->adjustments_amount,
            ])->save();
        });
    }

    private function syncPayoutReversalRecords(CareBooking $booking, int $caregiverRefundCents, bool $fullyRefunded): void
    {
        if ($caregiverRefundCents <= 0) {
            return;
        }

        DB::transaction(function () use ($booking, $caregiverRefundCents, $fullyRefunded): void {
            $item = CaregiverPayoutItem::query()
                ->where('care_booking_id', $booking->id)
                ->first();

            if (! $item) {
                return;
            }

            $payout = $item->payout;
            if (! $payout) {
                return;
            }

            $refundAmount = round($caregiverRefundCents / 100, 2);

            $item->forceFill([
                'status' => $fullyRefunded ? CaregiverPayoutItem::STATUS_REVERSED : $item->status,
                'notes' => trim(($item->notes ? $item->notes.' | ' : '').'Refund adjustment -$'.number_format($refundAmount, 2)),
            ])->save();

            $adjustments = (float) $payout->adjustments_amount - $refundAmount;
            $gross = (float) $payout->items()->sum('amount');

            $payout->forceFill([
                'adjustments_amount' => $adjustments,
                'net_amount' => $gross + $adjustments,
            ])->save();
        });
    }

    private function nextEstimatedPayoutDate(Carbon $now): Carbon
    {
        $daysUntilFriday = (Carbon::FRIDAY - $now->dayOfWeek + 7) % 7;
        if ($daysUntilFriday === 0) {
            $daysUntilFriday = 7;
        }

        return $now->copy()->startOfDay()->addDays($daysUntilFriday)->setTime(10, 0);
    }

    private function isAuthorizationExpired(CareBookingPayment $payment): bool
    {
        if (! $payment->authorization_expires_at) {
            return false;
        }

        return now()->greaterThanOrEqualTo($payment->authorization_expires_at->copy()->subMinutes(5));
    }

    private function caregiverRefundShareCents(CareBookingPayment $payment, int $refundCents): int
    {
        $captured = (int) ($payment->amount_captured_cents ?? 0);
        $caregiverAmount = (int) ($payment->caregiver_amount_cents ?? 0);
        if ($captured <= 0 || $caregiverAmount <= 0) {
            return 0;
        }

        $ratio = $caregiverAmount / $captured;

        return (int) min($caregiverAmount, round($refundCents * $ratio));
    }

    private function idempotencyKey(int $bookingId, string $action, int $amountCents = 0): string
    {
        return 'booking-'.$bookingId.':'.$action.':'.$amountCents;
    }

    private function looksLikeActionRequired(PaymentException $e): bool
    {
        $haystack = strtolower($e->userMessage.' '.$e->getMessage());

        return str_contains($haystack, 'requires action')
            || str_contains($haystack, 'requires_action')
            || str_contains($haystack, 'authentication')
            || str_contains($haystack, '3d secure')
            || str_contains($haystack, '3ds');
    }

    private function authorizationStatusRequiresAction(string $status): bool
    {
        return in_array($status, [
            'requires_action',
            'requires_confirmation',
        ], true);
    }

    private function authorizationFailureMessage(string $status): string
    {
        if ($this->authorizationStatusRequiresAction($status)) {
            return 'Card verification is required. Open Billing & Payments, confirm your card, then retry.';
        }

        if ($status === 'requires_payment_method') {
            return 'Card was declined. Update your payment method in Billing & Payments, then try hiring again.';
        }

        return 'Card authorization failed. Update your payment method in Billing & Payments, then try hiring again.';
    }

    private function persistAuthorizationFailure(
        CareBooking $booking,
        string $customerId,
        string $paymentMethodId,
        string $status,
        string $message,
        ?string $paymentIntentId = null,
        ?string $clientSecret = null,
    ): CareBookingPayment {
        return CareBookingPayment::query()->updateOrCreate(
            ['care_booking_id' => $booking->id],
            [
                'family_user_id' => (int) $booking->family_user_id,
                'caregiver_user_id' => (int) $booking->caregiver_user_id,
                'status' => $status,
                'currency' => $this->stripe->currency(),
                'stripe_customer_id' => $customerId,
                'stripe_payment_method_id' => $paymentMethodId,
                'stripe_payment_intent_id' => $paymentIntentId,
                'stripe_payment_intent_client_secret' => $clientSecret,
                'failed_at' => now(),
                'last_error' => $message,
            ]
        );
    }

    private function notifyAuthorizationState(
        CareBooking $booking,
        CareBookingPayment $payment,
        string $status,
    ): void {
        if (! $booking->family) {
            return;
        }

        if ($status === CareBookingPayment::STATUS_AUTHORIZED) {
            $this->notify(
                recipients: $booking->family,
                eventKey: MarketplaceEvent::PAYMENT_AUTHORIZED,
                title: 'Card pre-authorization complete',
                body: 'Your card was pre-authorized for this booking.',
                url: route('family.requests.show', $booking->care_request_id),
                payload: [
                    'care_booking_id' => $booking->id,
                    'care_booking_payment_id' => $payment->id,
                    'amount_authorized_cents' => $payment->amount_authorized_cents,
                ],
                subject: $booking,
                dedupeKey: 'payment-authorized:booking-'.$booking->id
            );

            return;
        }

        if (in_array($status, [
            CareBookingPayment::STATUS_AUTHORIZATION_REQUIRED,
            CareBookingPayment::STATUS_REAUTH_REQUIRED,
        ], true)) {
            $this->notify(
                recipients: $booking->family,
                eventKey: MarketplaceEvent::PAYMENT_ACTION_REQUIRED,
                title: 'Action needed on your payment method',
                body: 'Please open Billing & Payments to re-authorize your card.',
                url: route('family.billing.show'),
                payload: [
                    'care_booking_id' => $booking->id,
                    'care_booking_payment_id' => $payment->id,
                    'payment_status' => $status,
                ],
                subject: $booking,
                dedupeKey: 'payment-action-required:booking-'.$booking->id.'-status-'.$status
            );

            return;
        }

        $this->notify(
            recipients: $booking->family,
            eventKey: MarketplaceEvent::PAYMENT_AUTHORIZATION_FAILED,
            title: 'Card authorization failed',
            body: 'We could not authorize your card for this booking. Update billing and retry.',
            url: route('family.billing.show'),
            payload: [
                'care_booking_id' => $booking->id,
                'care_booking_payment_id' => $payment->id,
                'payment_status' => $status,
            ],
            subject: $booking,
            dedupeKey: 'payment-authorization-failed:booking-'.$booking->id
        );
    }

    /**
     * @param  \App\Models\User|\Illuminate\Support\Collection<int,\App\Models\User>|array<int,\App\Models\User>|null  $recipients
     * @param  array<string, mixed>  $payload
     */
    private function notify(
        mixed $recipients,
        string $eventKey,
        string $title,
        string $body,
        ?string $url = null,
        array $payload = [],
        mixed $subject = null,
        ?string $dedupeKey = null,
    ): void {
        if (! $recipients) {
            return;
        }

        try {
            $this->notifications->notify(
                recipients: $recipients,
                eventKey: $eventKey,
                title: $title,
                body: $body,
                url: $url,
                payload: $payload,
                subject: $subject,
                dedupeKey: $dedupeKey
            );
        } catch (Throwable) {
            // Payment flow should not fail if notifications fail.
        }
    }
}
