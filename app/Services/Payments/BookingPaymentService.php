<?php

namespace App\Services\Payments;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CareBookingPaymentAttempt;
use App\Models\CareBookingPaymentOperation;
use App\Models\CareBookingTimeCorrection;
use App\Models\CaregiverPayout;
use App\Models\CaregiverPayoutItem;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Services\RegularCare\CarePlanHealthService;
use App\Support\MarketplaceEvent;
use App\Support\MarketplacePricing;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class BookingPaymentService
{
    public function __construct(
        private readonly StripeClient $stripe,
        private readonly MarketplaceNotificationService $notifications,
        private readonly MarketplacePricing $pricing,
        private readonly CarePlanHealthService $planHealth,
        private readonly BookingPaymentV2Service $paymentsV2,
    ) {}

    /**
     * @return array{worked_minutes:int,hourly_rate:float,subtotal_cents:int,platform_fee_percent:float,platform_fee_cents:int,total_charge_cents:int,caregiver_amount_cents:int}
     */
    public function quoteForWorkedMinutes(CareBooking $booking, int $workedMinutes): array
    {
        $booking->loadMissing(['application', 'family', 'caregiver.caregiverProfile']);

        $workedMinutes = max(1, $workedMinutes);
        if ($this->pricing->usesCurrentPricing($booking)) {
            $booking->loadMissing('payment');

            return $this->pricing->quoteForCurrentBooking(
                $booking,
                $workedMinutes,
                max(0, (int) ($booking->payment?->stripe_processing_fee_cents ?? 0)),
            );
        }

        $hourlyRate = $this->effectiveHourlyRate($booking);
        $subtotalCents = (int) round($this->bookingSubtotal($booking, $workedMinutes) * 100);
        $platformFeePercent = $this->platformFeePercent($booking);
        $totalChargeCents = max(100, (int) round($subtotalCents * (1 + ($platformFeePercent / 100))));
        $platformFeeCents = (int) round($totalChargeCents * ($platformFeePercent / 100));

        return [
            'worked_minutes' => $workedMinutes,
            'hourly_rate' => $hourlyRate,
            'subtotal_cents' => $subtotalCents,
            'platform_fee_percent' => $platformFeePercent,
            'platform_fee_cents' => $platformFeeCents,
            'total_charge_cents' => $totalChargeCents,
            'caregiver_amount_cents' => max(0, $totalChargeCents - $platformFeeCents),
        ];
    }

    public function authorizeForBooking(CareBooking $booking, bool $forceReauthorize = false, bool $notify = true): CareBookingPayment
    {
        $booking->loadMissing([
            'application',
            'family',
            'caregiver',
            'caregiver.caregiverProfile',
            'payment',
        ]);

        $booking = $this->ensureCurrentPricingForNewPayment($booking);

        $existing = $booking->payment?->fresh();
        if ($existing && in_array($existing->status, [
            CareBookingPayment::STATUS_CAPTURED,
            CareBookingPayment::STATUS_TRANSFERRED,
            CareBookingPayment::STATUS_TRANSFER_FAILED,
            CareBookingPayment::STATUS_PARTIALLY_REFUNDED,
            CareBookingPayment::STATUS_REFUNDED,
        ], true)) {
            return $existing;
        }
        if ($existing?->status === CareBookingPayment::STATUS_AUTHORIZED) {
            if (! $this->isAuthorizationExpired($existing)) {
                return $existing;
            }
            $forceReauthorize = true;
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
        [$attempt, $previousIntentId, $previousStatus] = $this->reserveAuthorizationAttempt(
            $booking,
            $customerId,
            (string) $defaultPaymentMethod['id'],
            $currency,
            $amountCents
        );

        if ($forceReauthorize
            && $previousIntentId
            && in_array($previousStatus, [
                CareBookingPayment::STATUS_AUTHORIZED,
                CareBookingPayment::STATUS_AUTHORIZATION_REQUIRED,
                CareBookingPayment::STATUS_REAUTH_REQUIRED,
            ], true)) {
            try {
                $this->stripe->cancelPaymentIntent($previousIntentId);
            } catch (PaymentException $exception) {
                $this->releaseAuthorizationAttempt($booking->id, $exception->getMessage());
                throw $exception;
            }
        }

        try {
            $authorization = $this->stripe->createManualAuthorization(
                $booking,
                $customerId,
                (string) $defaultPaymentMethod['id'],
                $amountCents,
                $currency,
                $this->idempotencyKey(
                    $booking->id,
                    'authorize-attempt-'.$attempt.'-pm-'.substr(hash('sha256', (string) $defaultPaymentMethod['id']), 0, 12),
                    $amountCents
                )
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
                message: $e->getMessage(),
                attempt: $attempt
            );

            if ($notify) {
                $this->notifyAuthorizationState($booking, $payment, $status);
            }

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
                clientSecret: (string) ($authorization['client_secret'] ?? null),
                attempt: $attempt
            );

            if ($notify) {
                $this->notifyAuthorizationState($booking, $payment, $status);
            }

            throw new PaymentException($this->authorizationFailureMessage($authorizationStatus), $message);
        }

        $payment = CareBookingPayment::query()->updateOrCreate(
            ['care_booking_id' => $booking->id],
            array_merge($this->newPaymentSnapshotAttributes($booking), [
                'family_account_id' => (int) $booking->family_account_id,
                'family_user_id' => (int) $booking->family_user_id,
                'initiated_by_user_id' => auth()->id() ?: $booking->family_user_id,
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
                'metadata' => $this->completedAuthorizationMetadata(
                    $booking,
                    (int) $authorization['amount'],
                    $attempt,
                    $previousIntentId,
                    $previousStatus
                ),
            ])
        );
        $this->paymentsV2->recordAuthorization($payment);

        if ($notify) {
            $this->notifyAuthorizationState($booking, $payment, CareBookingPayment::STATUS_AUTHORIZED);
        }

        return $payment;
    }

    public function canRetryAuthorization(CareBooking $booking): bool
    {
        $payment = $booking->relationLoaded('payment') ? $booking->payment : $booking->payment()->first();
        if (! $payment) {
            return false;
        }

        if ($payment->status === CareBookingPayment::STATUS_AUTHORIZED) {
            return $this->isAuthorizationExpired($payment);
        }

        return in_array($payment->status, [
            CareBookingPayment::STATUS_FAILED,
            CareBookingPayment::STATUS_CANCELLED,
            CareBookingPayment::STATUS_REAUTH_REQUIRED,
            CareBookingPayment::STATUS_AUTHORIZATION_REQUIRED,
        ], true);
    }

    public function retryAuthorizationForBooking(CareBooking $booking): CareBookingPayment
    {
        $booking->loadMissing('payment');
        if (! $this->canRetryAuthorization($booking)) {
            throw new PaymentException('This visit does not currently need a payment authorization retry.');
        }

        return $this->authorizeForBooking($booking, true);
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
        $booking = $this->ensureCurrentPricingForNewPayment($booking);

        $amountCents = $this->authorizationAmountCents($booking);
        $revisionKey = 'booking:v1:'.hash('sha256', implode('|', [
            $booking->scheduled_start_at?->toIso8601String(),
            $booking->scheduled_end_at?->toIso8601String(),
            $this->estimatedMinutes($booking),
            $this->effectiveHourlyRate($booking),
            $this->platformFeePercent($booking),
            $this->authorizationBufferPercent(),
            $amountCents,
        ]));

        if ($forceNewIntent) {
            $revisionKey .= ':forced:'.bin2hex(random_bytes(8));
        }

        return $this->prepareAuthorizationIntentForAmount(
            $booking,
            $amountCents,
            $revisionKey,
            'booking_authorization',
            null,
            $forceNewIntent,
        );
    }

    public function prepareOnSessionAuthorizationForAmount(
        CareBooking $booking,
        int $amountCents,
        string $revisionKey,
        ?CareBookingTimeCorrection $correction = null,
    ): CareBookingPayment {
        if ($amountCents < 100) {
            throw new PaymentException('The approved payment amount is invalid. Please contact LoLo Care support.');
        }

        return $this->prepareAuthorizationIntentForAmount(
            $booking,
            $amountCents,
            $revisionKey,
            $correction ? 'time_correction' : 'booking_authorization',
            $correction?->id,
        );
    }

    private function prepareAuthorizationIntentForAmount(
        CareBooking $booking,
        int $amountCents,
        string $revisionKey,
        string $purpose,
        ?int $correctionId,
        bool $forceNewIntent = false,
    ): CareBookingPayment {
        $booking->loadMissing([
            'application',
            'family',
            'caregiver',
            'caregiver.caregiverProfile',
            'payment',
        ]);
        $booking = $this->ensureCurrentPricingForNewPayment($booking);

        $existing = $booking->payment;
        if ($existing && in_array($existing->status, [
            CareBookingPayment::STATUS_CAPTURED,
            CareBookingPayment::STATUS_TRANSFERRED,
            CareBookingPayment::STATUS_TRANSFER_FAILED,
            CareBookingPayment::STATUS_PARTIALLY_REFUNDED,
            CareBookingPayment::STATUS_REFUNDED,
        ], true)) {
            return $existing;
        }
        if (! $forceNewIntent
            && $existing?->status === CareBookingPayment::STATUS_AUTHORIZED
            && ! $this->isAuthorizationExpired($existing)
            && (int) $existing->amount_authorized_cents >= $amountCents
            && (string) data_get($existing->metadata, 'authorization_revision_key') === $revisionKey) {
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

        $currency = $this->stripe->currency();
        $paymentMethodId = (string) $defaultPaymentMethod['id'];
        $authorizationKey = hash('sha256', implode('|', [
            $booking->id,
            $revisionKey,
            $amountCents,
            strtolower($currency),
            $paymentMethodId,
        ]));

        if (! $forceNewIntent && $existing && $this->canReuseAuthorizationIntent(
            $existing,
            $amountCents,
            $currency,
            $paymentMethodId,
            $revisionKey,
        )) {
            $existing->forceFill([
                'stripe_customer_id' => $customerId,
                'metadata' => array_merge(
                    $existing->metadata ?? [],
                    $this->authorizationMetadata($booking, $amountCents, $revisionKey, $purpose, $correctionId),
                ),
            ])->save();

            Log::info('payment.authorization_intent_reused', [
                'care_booking_id' => $booking->id,
                'care_booking_payment_id' => $existing->id,
                'stripe_payment_intent_id' => $existing->stripe_payment_intent_id,
                'revision_key' => $revisionKey,
                'amount_cents' => $amountCents,
            ]);

            return $existing->fresh();
        }

        [$attempt, $previousIntentId, $previousStatus] = $this->reserveAuthorizationAttempt(
            $booking,
            $customerId,
            $paymentMethodId,
            $currency,
            $amountCents,
            $revisionKey,
            $purpose,
            $correctionId,
        );

        try {
            $intent = $this->stripe->createManualAuthorizationIntent(
                $booking,
                $customerId,
                $paymentMethodId,
                $amountCents,
                $currency,
                'booking-auth-'.$authorizationKey,
            );
        } catch (PaymentException $exception) {
            $payment = $this->persistAuthorizationFailure(
                booking: $booking,
                customerId: $customerId,
                paymentMethodId: $paymentMethodId,
                status: CareBookingPayment::STATUS_FAILED,
                message: $exception->getMessage(),
                attempt: $attempt,
            );
            $this->notifyAuthorizationState($booking, $payment, CareBookingPayment::STATUS_FAILED);

            throw $exception;
        }

        $status = (string) ($intent['status'] ?? '');
        $paymentStatus = $status === 'requires_capture'
            ? CareBookingPayment::STATUS_AUTHORIZED
            : CareBookingPayment::STATUS_AUTHORIZATION_REQUIRED;

        $payment = CareBookingPayment::query()->updateOrCreate(
            ['care_booking_id' => $booking->id],
            array_merge($this->newPaymentSnapshotAttributes($booking), [
                'family_account_id' => (int) $booking->family_account_id,
                'family_user_id' => (int) $booking->family_user_id,
                'initiated_by_user_id' => auth()->id() ?: $booking->family_user_id,
                'caregiver_user_id' => (int) $booking->caregiver_user_id,
                'status' => $paymentStatus,
                'currency' => $currency,
                'stripe_customer_id' => $customerId,
                'stripe_payment_method_id' => $paymentMethodId,
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
                'metadata' => $this->completedAuthorizationMetadata(
                    $booking,
                    (int) $intent['amount'],
                    $attempt,
                    $previousIntentId,
                    $previousStatus,
                    $revisionKey,
                    $purpose,
                    $correctionId,
                ),
            ])
        );
        $this->paymentsV2->recordAuthorization($payment);

        $this->recordPreparedAuthorizationAttempt(
            $payment,
            $intent,
            $authorizationKey,
            $revisionKey,
            $purpose,
            $correctionId,
            $paymentMethodId,
            $paymentStatus,
        );

        Log::info('payment.authorization_intent_prepared', [
            'care_booking_id' => $booking->id,
            'care_booking_payment_id' => $payment->id,
            'stripe_payment_intent_id' => (string) $intent['payment_intent_id'],
            'revision_key' => $revisionKey,
            'purpose' => $purpose,
            'amount_cents' => (int) $intent['amount'],
            'status' => $paymentStatus,
        ]);

        if ($previousIntentId
            && $previousIntentId !== (string) $intent['payment_intent_id']
            && in_array($previousStatus, [
                CareBookingPayment::STATUS_AUTHORIZED,
                CareBookingPayment::STATUS_AUTHORIZATION_REQUIRED,
                CareBookingPayment::STATUS_REAUTH_REQUIRED,
            ], true)) {
            try {
                $this->stripe->cancelPaymentIntent($previousIntentId);
                CareBookingPaymentAttempt::query()
                    ->where('stripe_payment_intent_id', $previousIntentId)
                    ->update([
                        'status' => CareBookingPayment::STATUS_CANCELLED,
                        'is_active' => false,
                        'canceled_at' => now(),
                        'superseded_at' => now(),
                    ]);
            } catch (PaymentException $exception) {
                CareBookingPaymentAttempt::query()
                    ->where('stripe_payment_intent_id', $previousIntentId)
                    ->update([
                        'is_active' => false,
                        'last_error' => 'Superseded intent could not be canceled: '.$exception->getMessage(),
                        'superseded_at' => now(),
                    ]);
            }
        }

        $this->notifyAuthorizationState($booking, $payment, $paymentStatus);

        return $payment;
    }

    public function syncPreparedAuthorization(CareBooking $booking, string $paymentIntentId): CareBookingPayment
    {
        $booking->loadMissing(['payment', 'family', 'caregiver']);

        $payment = $booking->payment;
        if (! $payment) {
            throw new PaymentException('Payment authorization was not found for this booking.');
        }

        $attempt = CareBookingPaymentAttempt::query()
            ->where('care_booking_id', $booking->id)
            ->where('stripe_payment_intent_id', $paymentIntentId)
            ->first();

        if (! $attempt && (string) $payment->stripe_payment_intent_id !== $paymentIntentId) {
            throw new PaymentException('Payment authorization was not found for this booking.');
        }

        $intent = $this->stripe->retrievePaymentIntent($paymentIntentId);
        if ($attempt) {
            $isCurrentAttempt = $attempt->is_active
                && (string) $payment->stripe_payment_intent_id === $paymentIntentId;
            $this->applyPaymentIntentStateToAttempt($attempt, $intent);

            if (! $isCurrentAttempt) {
                Log::notice('payment.stale_client_confirmation_ignored', [
                    'care_booking_id' => $booking->id,
                    'stripe_payment_intent_id' => $paymentIntentId,
                    'current_payment_intent_id' => $payment->stripe_payment_intent_id,
                ]);

                return $payment->fresh();
            }
        }

        return $this->applyPaymentIntentState($payment, $intent);
    }

    public function recordClientAuthorizationFailure(CareBooking $booking, ?string $paymentIntentId, string $message): ?CareBookingPayment
    {
        $booking->loadMissing(['payment']);

        $payment = $booking->payment;
        if (! $payment) {
            return null;
        }

        if ($paymentIntentId) {
            $attempt = CareBookingPaymentAttempt::query()
                ->where('care_booking_id', $booking->id)
                ->where('stripe_payment_intent_id', $paymentIntentId)
                ->first();
            if ($attempt) {
                $attempt->forceFill([
                    'status' => CareBookingPayment::STATUS_FAILED,
                    'last_error' => $message !== '' ? $message : 'Card authorization failed.',
                ])->save();
            }
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

        return $this->reconcilePaymentPlan($payment);
    }

    public function captureForBooking(CareBooking $booking, bool $notify = true): CareBookingPayment
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
                $payment = $this->authorizeForBooking($booking, forceReauthorize: true, notify: $notify);
            } catch (PaymentException $e) {
                $payment->forceFill([
                    'status' => CareBookingPayment::STATUS_REAUTH_REQUIRED,
                    'failed_at' => now(),
                    'last_error' => $e->getMessage(),
                ])->save();

                if ($notify) {
                    $this->notifyAuthorizationState($booking, $payment, CareBookingPayment::STATUS_REAUTH_REQUIRED);
                }

                throw new PaymentException(
                    'Previous card authorization expired. Re-open Billing & Payments and retry confirmation.',
                    $e->getMessage()
                );
            }
        }

        if ($this->paymentsV2->usesV2($payment)) {
            $payment = $this->paymentsV2->capture($booking, $payment);
            $this->notifyV2CaptureState($booking, $payment, $notify);

            return $this->reconcilePaymentPlan($payment);
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
        CareBookingPaymentAttempt::query()
            ->where('stripe_payment_intent_id', $paymentIntentId)
            ->update([
                'status' => CareBookingPayment::STATUS_CAPTURED,
                'captured_at' => now(),
                'last_error' => null,
            ]);
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

                if ($notify) {
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

            }
        }

        $payment->save();
        $this->syncPayoutRecords($booking, $payment, $transferCompleted);

        if ($notify) {
            $this->notify(
                recipients: $booking->family,
                eventKey: MarketplaceEvent::PAYMENT_CAPTURED,
                title: 'Shift payment captured',
                body: 'Payment for the completed visit was processed successfully. No action is needed.',
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
        }

        if ($notify && $transferCompleted && $booking->caregiver) {
            $this->notify(
                recipients: $booking->caregiver,
                eventKey: MarketplaceEvent::PAYOUT_TRANSFERRED,
                title: 'Payout sent',
                body: 'Your earnings for this visit were sent to your connected payout account.',
                url: route('caregiver.earnings.index'),
                payload: [
                    'care_booking_id' => $booking->id,
                    'care_booking_payment_id' => $payment->id,
                    'caregiver_amount_cents' => $caregiverAmountCents,
                ],
                subject: $booking,
                dedupeKey: 'payout-transferred:booking-'.$booking->id
            );
        }

        return $this->reconcilePaymentPlan($payment);
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
            $this->planHealth->reconcileForBooking($booking);

            return;
        }

        $payment->forceFill([
            'status' => CareBookingPayment::STATUS_CANCELLED,
            'failed_at' => now(),
            'last_error' => 'Authorization cancelled before capture.',
        ])->save();
        $this->planHealth->reconcileForBooking($booking);
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

        if ($this->paymentsV2->usesV2($payment)) {
            $beforeRefunded = (int) $payment->amount_refunded_cents;
            $payment = $this->paymentsV2->refund($booking, $payment, $amountCents, $reason);
            $this->notifyV2RefundState($booking, $payment, $beforeRefunded);

            return $payment;
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
                url: route('caregiver.earnings.index'),
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

        if ($this->paymentsV2->usesV2($payment)) {
            $payment = $this->paymentsV2->finalizeFeesAndTransfers($payment);
            if ($payment->status === CareBookingPayment::STATUS_TRANSFERRED && $booking->caregiver) {
                $this->notify(
                    recipients: $booking->caregiver,
                    eventKey: MarketplaceEvent::PAYOUT_TRANSFERRED,
                    title: 'Earnings available in Stripe',
                    body: 'Your earnings were transferred to your Stripe balance. Stripe controls the timing of the bank payout.',
                    url: route('caregiver.earnings.index'),
                    payload: ['care_booking_id' => $booking->id, 'care_booking_payment_id' => $payment->id],
                    subject: $booking,
                    dedupeKey: 'earnings-transferred-v2:booking-'.$booking->id,
                );
            }

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
                body: 'Your delayed payout was successfully sent to your connected payout account.',
                url: route('caregiver.earnings.index'),
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

        $attempt = CareBookingPaymentAttempt::query()
            ->where('stripe_payment_intent_id', $paymentIntentId)
            ->first();
        if ($attempt) {
            $payment = $attempt->payment;
            $isCurrentAttempt = $payment
                && $attempt->is_active
                && (string) $payment->stripe_payment_intent_id === $paymentIntentId;
            $this->applyPaymentIntentStateToAttempt($attempt, $object);
            if (! $isCurrentAttempt) {
                Log::notice('payment.stale_webhook_ignored', [
                    'care_booking_id' => $attempt->care_booking_id,
                    'stripe_payment_intent_id' => $paymentIntentId,
                    'stripe_status' => (string) ($object['status'] ?? ''),
                ]);

                return;
            }

            $this->applyPaymentIntentState($payment, $object);

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
    private function applyPaymentIntentStateToAttempt(CareBookingPaymentAttempt $attempt, array $object): void
    {
        $stripeStatus = (string) ($object['status'] ?? '');
        $status = match ($stripeStatus) {
            'requires_capture' => CareBookingPayment::STATUS_AUTHORIZED,
            'requires_action', 'requires_confirmation' => CareBookingPayment::STATUS_AUTHORIZATION_REQUIRED,
            'succeeded' => CareBookingPayment::STATUS_CAPTURED,
            'canceled' => CareBookingPayment::STATUS_CANCELLED,
            'requires_payment_method' => CareBookingPayment::STATUS_FAILED,
            default => $attempt->status,
        };
        $clientSecret = (string) ($object['client_secret'] ?? '');
        $error = (string) data_get($object, 'last_payment_error.message', '');

        $payload = [
            'status' => $status,
            'last_error' => in_array($status, [
                CareBookingPayment::STATUS_AUTHORIZED,
                CareBookingPayment::STATUS_CAPTURED,
            ], true) ? null : ($error !== '' ? $error : $attempt->last_error),
        ];

        if ($clientSecret !== '') {
            $payload['client_secret'] = $clientSecret;
        }
        if ((int) ($object['amount'] ?? 0) > 0) {
            $payload['amount_cents'] = (int) $object['amount'];
        }
        if ($status === CareBookingPayment::STATUS_AUTHORIZED) {
            $payload['authorized_at'] = $attempt->authorized_at ?: now();
        }
        if ($status === CareBookingPayment::STATUS_CAPTURED) {
            $payload['captured_at'] = $attempt->captured_at ?: now();
        }
        if ($status === CareBookingPayment::STATUS_CANCELLED) {
            $payload['is_active'] = false;
            $payload['canceled_at'] = $attempt->canceled_at ?: now();
        }

        $attempt->forceFill($payload)->save();
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
            $this->paymentsV2->recordAuthorization($payment->fresh());

            return $this->reconcilePaymentPlan($payment);
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

            return $this->reconcilePaymentPlan($payment);
        }

        if ($status === 'succeeded') {
            if ($this->paymentsV2->usesV2($payment)) {
                return $this->reconcilePaymentPlan(
                    $this->paymentsV2->recordSucceededPaymentIntent($payment, $object)
                );
            }

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

            return $this->reconcilePaymentPlan($payment);
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

        return $this->reconcilePaymentPlan($payment);
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

        $operation = CareBookingPaymentOperation::query()
            ->where('type', CareBookingPaymentOperation::TYPE_TRANSFER)
            ->where('stripe_object_id', $transferId)
            ->first();
        if ($operation) {
            $operation->forceFill([
                'status' => CareBookingPaymentOperation::STATUS_SUCCEEDED,
                'processed_at' => $operation->processed_at ?: now(),
                'failed_at' => null,
                'last_error' => null,
            ])->save();
            $this->paymentsV2->finalizeFeesAndTransfers($operation->payment);

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
        $this->reconcilePaymentPlan($payment);
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

        $operation = CareBookingPaymentOperation::query()
            ->where('type', CareBookingPaymentOperation::TYPE_TRANSFER)
            ->where('stripe_object_id', $transferId)
            ->first();
        if ($operation) {
            CareBookingPaymentOperation::query()->updateOrCreate(
                [
                    'type' => CareBookingPaymentOperation::TYPE_TRANSFER_REVERSAL,
                    'stripe_object_id' => (string) ($object['id'] ?? ''),
                ],
                [
                    'care_booking_payment_id' => $operation->care_booking_payment_id,
                    'care_booking_id' => $operation->care_booking_id,
                    'family_account_id' => $operation->family_account_id,
                    'parent_operation_id' => $operation->id,
                    'financial_reference' => $operation->financial_reference,
                    'status' => CareBookingPaymentOperation::STATUS_SUCCEEDED,
                    'amount_cents' => max(0, (int) ($object['amount'] ?? 0)),
                    'currency' => $operation->currency,
                    'stripe_parent_object_id' => $transferId,
                    'occurred_at' => now(),
                    'processed_at' => now(),
                ],
            );
            $this->paymentsV2->finalizeFeesAndTransfers($operation->payment);

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
        $this->reconcilePaymentPlan($payment);
    }

    /**
     * @param  array<string, mixed>  $object
     */
    public function handleChargeRefundWebhook(array $object): void
    {
        if ($this->paymentsV2->handleChargeRefund($object)) {
            return;
        }

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

    /** @param array<string, mixed> $object */
    public function handleRefundWebhook(array $object): void
    {
        $this->paymentsV2->handleRefund($object);
    }

    /** @param array<string, mixed> $object */
    public function handleDisputeWebhook(array $object): void
    {
        $this->paymentsV2->handleDispute($object);
    }

    private function authorizationAmountCents(CareBooking $booking): int
    {
        if ($this->pricing->usesCurrentPricing($booking)) {
            $quote = $this->pricing->quoteForCurrentBooking($booking, $this->estimatedMinutes($booking));

            return max(100, (int) round(
                (int) $quote['total_charge_cents'] * (1 + ($this->authorizationBufferPercent() / 100))
            ));
        }

        $subtotal = $this->bookingSubtotal($booking, $this->estimatedMinutes($booking));
        $withPlatformFee = $subtotal * (1 + ($this->platformFeePercent($booking) / 100));
        $withBuffer = $withPlatformFee * (1 + ($this->authorizationBufferPercent() / 100));

        return max(100, (int) round($withBuffer * 100));
    }

    /**
     * @return array<string, mixed>
     */
    private function authorizationMetadata(
        CareBooking $booking,
        int $amountCents,
        ?string $revisionKey = null,
        ?string $purpose = null,
        ?int $correctionId = null,
    ): array {
        $isV2 = $this->pricing->usesCurrentPricing($booking);
        $v2Quote = $isV2
            ? $this->pricing->quoteForCurrentBooking($booking, $this->estimatedMinutes($booking))
            : null;

        return array_filter([
            'family_account_id' => (int) $booking->family_account_id,
            'acting_user_id' => auth()->id() ?: $booking->family_user_id,
            'financial_reference' => $isV2 ? $booking->financial_reference : null,
            'pricing_version' => $isV2 ? $booking->pricing_version : null,
            'hourly_rate' => $isV2
                ? (float) data_get($v2Quote, 'hourly_rate')
                : $this->effectiveHourlyRate($booking),
            'family_processing_fee_rate' => $isV2
                ? ((int) data_get($v2Quote, 'family_processing_fee_rate_cents') / 100)
                : null,
            'caregiver_gross_rate' => $isV2
                ? ((int) data_get($v2Quote, 'caregiver_gross_rate_cents') / 100)
                : null,
            'estimated_minutes' => $this->estimatedMinutes($booking),
            'platform_fee_percent' => $isV2 ? 0.0 : $this->platformFeePercent($booking),
            'buffer_percent' => $this->authorizationBufferPercent(),
            'requested_authorization_cents' => $amountCents,
            'authorization_revision_key' => $revisionKey,
            'authorization_purpose' => $purpose,
            'care_booking_time_correction_id' => $correctionId,
        ], static fn ($value): bool => $value !== null);
    }

    /**
     * @return array{0:int,1:string|null,2:string|null}
     */
    private function reserveAuthorizationAttempt(
        CareBooking $booking,
        string $customerId,
        string $paymentMethodId,
        string $currency,
        int $amountCents,
        ?string $revisionKey = null,
        ?string $purpose = null,
        ?int $correctionId = null,
    ): array {
        try {
            return DB::transaction(function () use ($booking, $customerId, $paymentMethodId, $currency, $amountCents, $revisionKey, $purpose, $correctionId): array {
                $payment = CareBookingPayment::query()
                    ->where('care_booking_id', $booking->id)
                    ->lockForUpdate()
                    ->first();
                $metadata = (array) ($payment?->metadata ?? []);
                $inProgressAt = data_get($metadata, 'authorization_in_progress_at');
                if ($inProgressAt && Carbon::parse((string) $inProgressAt)->gt(now()->subMinutes(5))) {
                    throw new PaymentException('A payment authorization attempt is already in progress for this visit.');
                }

                $currentAttempt = max(0, (int) data_get($metadata, 'authorization_attempt_count', 0));
                $reuseAmbiguousAttempt = $payment?->status === CareBookingPayment::STATUS_FAILED
                    && ! $payment->stripe_payment_intent_id
                    && (string) $payment->stripe_payment_method_id === $paymentMethodId
                    && $currentAttempt > 0;
                $attempt = $reuseAmbiguousAttempt ? $currentAttempt : $currentAttempt + 1;
                $previousIntentId = $payment?->stripe_payment_intent_id
                    ? (string) $payment->stripe_payment_intent_id
                    : null;
                $previousStatus = $payment?->status ? (string) $payment->status : null;
                $history = array_values((array) data_get($metadata, 'authorization_history', []));
                if ($previousIntentId && ! collect($history)->contains(
                    fn ($item): bool => (string) data_get($item, 'payment_intent_id') === $previousIntentId
                )) {
                    $history[] = [
                        'payment_intent_id' => $previousIntentId,
                        'status' => $previousStatus,
                        'replaced_at' => now()->toIso8601String(),
                    ];
                }
                $history = array_slice($history, -20);

                $authorizationMetadata = $this->authorizationMetadata(
                    $booking,
                    $amountCents,
                    $revisionKey,
                    $purpose,
                    $correctionId,
                );
                if ($revisionKey === null) {
                    $metadata = array_merge($metadata, $authorizationMetadata);
                } else {
                    $metadata['pending_authorization'] = $authorizationMetadata;
                }
                $metadata = array_merge($metadata, [
                    'authorization_attempt_count' => $attempt,
                    'authorization_attempt_payment_method_id' => $paymentMethodId,
                    'authorization_in_progress_at' => now()->toIso8601String(),
                    'authorization_history' => $history,
                ]);

                if ($payment) {
                    $payment->forceFill([
                        'stripe_customer_id' => $customerId,
                        'stripe_payment_method_id' => $paymentMethodId,
                        'currency' => $currency,
                        'metadata' => $metadata,
                    ])->save();
                } else {
                    CareBookingPayment::query()->create(array_merge($this->newPaymentSnapshotAttributes($booking), [
                        'care_booking_id' => $booking->id,
                        'family_account_id' => (int) $booking->family_account_id,
                        'family_user_id' => (int) $booking->family_user_id,
                        'initiated_by_user_id' => auth()->id() ?: $booking->family_user_id,
                        'caregiver_user_id' => (int) $booking->caregiver_user_id,
                        'status' => CareBookingPayment::STATUS_DRAFT,
                        'currency' => $currency,
                        'stripe_customer_id' => $customerId,
                        'stripe_payment_method_id' => $paymentMethodId,
                        'metadata' => $metadata,
                    ]));
                }

                return [$attempt, $previousIntentId, $previousStatus];
            });
        } catch (QueryException $exception) {
            $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
            if (in_array($sqlState, ['23000', '23505'], true)
                || str_contains(strtolower($exception->getMessage()), 'unique')) {
                throw new PaymentException('A payment authorization attempt is already in progress for this visit.', previous: $exception);
            }

            throw $exception;
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function completedAuthorizationMetadata(
        CareBooking $booking,
        int $amountCents,
        int $attempt,
        ?string $previousIntentId,
        ?string $previousStatus,
        ?string $revisionKey = null,
        ?string $purpose = null,
        ?int $correctionId = null,
    ): array {
        $reservedPayment = CareBookingPayment::query()->where('care_booking_id', $booking->id)->first();
        $metadata = (array) ($reservedPayment?->metadata ?? []);
        unset($metadata['authorization_in_progress_at']);
        unset($metadata['pending_authorization']);

        return array_merge($metadata, $this->authorizationMetadata(
            $booking,
            $amountCents,
            $revisionKey,
            $purpose,
            $correctionId,
        ), [
            'authorization_attempt_count' => $attempt,
            'previous_payment_intent_id' => $previousIntentId,
            'previous_payment_status' => $previousStatus,
            'authorization_completed_at' => now()->toIso8601String(),
        ]);
    }

    private function releaseAuthorizationAttempt(int $bookingId, string $message): void
    {
        $payment = CareBookingPayment::query()->where('care_booking_id', $bookingId)->first();
        if (! $payment) {
            return;
        }

        $metadata = (array) $payment->metadata;
        unset($metadata['authorization_in_progress_at']);
        $payment->forceFill([
            'metadata' => $metadata,
            'last_error' => $message,
        ])->save();
    }

    private function canReuseAuthorizationIntent(
        CareBookingPayment $payment,
        int $amountCents,
        string $currency,
        string $paymentMethodId,
        ?string $revisionKey = null,
    ): bool {
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

        $metadataRevision = data_get($payment->metadata, 'authorization_revision_key');

        return $metadataAmount === $amountCents
            && strtolower((string) $payment->currency) === strtolower($currency)
            && (string) $payment->stripe_payment_method_id === $paymentMethodId
            && ($revisionKey === null
                || $metadataRevision === null
                || (string) $metadataRevision === $revisionKey);
    }

    /**
     * @param  array<string, mixed>  $intent
     */
    private function recordPreparedAuthorizationAttempt(
        CareBookingPayment $payment,
        array $intent,
        string $authorizationKey,
        string $revisionKey,
        string $purpose,
        ?int $correctionId,
        string $paymentMethodId,
        string $paymentStatus,
    ): void {
        $intentId = (string) ($intent['payment_intent_id'] ?? '');
        if ($intentId === '') {
            return;
        }

        DB::transaction(function () use (
            $payment,
            $intent,
            $intentId,
            $authorizationKey,
            $revisionKey,
            $purpose,
            $correctionId,
            $paymentMethodId,
            $paymentStatus,
        ): void {
            CareBookingPaymentAttempt::query()
                ->where('care_booking_payment_id', $payment->id)
                ->where('stripe_payment_intent_id', '!=', $intentId)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'superseded_at' => now(),
                ]);

            CareBookingPaymentAttempt::query()->updateOrCreate(
                [
                    'care_booking_payment_id' => $payment->id,
                    'authorization_key' => $authorizationKey,
                ],
                [
                    'care_booking_id' => $payment->care_booking_id,
                    'family_account_id' => $payment->family_account_id,
                    'care_booking_time_correction_id' => $correctionId,
                    'purpose' => $purpose,
                    'revision_key' => $revisionKey,
                    'stripe_payment_intent_id' => $intentId,
                    'stripe_payment_method_id' => $paymentMethodId,
                    'client_secret' => (string) ($intent['client_secret'] ?? ''),
                    'amount_cents' => (int) ($intent['amount'] ?? 0),
                    'currency' => (string) $payment->currency,
                    'status' => $paymentStatus,
                    'is_active' => true,
                    'last_error' => $paymentStatus === CareBookingPayment::STATUS_AUTHORIZED
                        ? null
                        : 'Card authorization needs confirmation.',
                    'authorized_at' => $paymentStatus === CareBookingPayment::STATUS_AUTHORIZED ? now() : null,
                    'captured_at' => null,
                    'canceled_at' => null,
                    'superseded_at' => null,
                    'metadata' => [
                        'stripe_status' => (string) ($intent['status'] ?? ''),
                    ],
                ],
            );
        });
    }

    private function captureAmountCents(CareBooking $booking, CareBookingPayment $payment): int
    {
        $workedMinutes = (int) ($booking->worked_minutes ?? 0);
        if ($workedMinutes <= 0) {
            $workedMinutes = $this->estimatedMinutes($booking);
        }

        if ($this->paymentsV2->usesV2($payment)) {
            return (int) $this->pricing->quoteForCurrentBooking($booking, $workedMinutes)['total_charge_cents'];
        }

        $subtotal = $this->bookingSubtotal($booking, $workedMinutes);
        $withPlatformFee = $subtotal * (1 + ($this->platformFeePercent($booking) / 100));

        if ($withPlatformFee <= 0) {
            return (int) ($payment->amount_authorized_cents ?? 0);
        }

        return max(100, (int) round($withPlatformFee * 100));
    }

    private function ensureCurrentPricingForNewPayment(CareBooking $booking): CareBooking
    {
        $booking->loadMissing('payment');
        if ($booking->payment || ! $this->pricing->currentPricingEnabled()) {
            return $booking;
        }

        $booking = $this->pricing->ensureCurrentSnapshot($booking);
        $booking->loadMissing(['application', 'family', 'caregiver', 'caregiver.caregiverProfile', 'payment']);

        return $booking;
    }

    /** @return array<string, mixed> */
    private function newPaymentSnapshotAttributes(CareBooking $booking): array
    {
        if (! $this->pricing->usesCurrentPricing($booking)) {
            return [];
        }

        return $this->paymentsV2->paymentSnapshotAttributes($booking);
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
            return 'Card verification is required. Confirm or replace your card for this visit, then retry authorization.';
        }

        if ($status === 'requires_payment_method') {
            return 'Your card was declined. Confirm or replace your card for this visit, then retry authorization.';
        }

        return 'Card authorization failed. Confirm or replace your card for this visit, then retry authorization.';
    }

    private function persistAuthorizationFailure(
        CareBooking $booking,
        string $customerId,
        string $paymentMethodId,
        string $status,
        string $message,
        ?string $paymentIntentId = null,
        ?string $clientSecret = null,
        int $attempt = 1,
    ): CareBookingPayment {
        $reservedPayment = CareBookingPayment::query()->where('care_booking_id', $booking->id)->first();
        $metadata = (array) ($reservedPayment?->metadata ?? []);
        unset($metadata['authorization_in_progress_at']);
        $metadata['authorization_attempt_count'] = $attempt;
        $metadata['authorization_failed_at'] = now()->toIso8601String();

        $attributes = array_merge($this->newPaymentSnapshotAttributes($booking), [
            'family_account_id' => (int) $booking->family_account_id,
            'family_user_id' => (int) $booking->family_user_id,
            'initiated_by_user_id' => auth()->id() ?: $booking->family_user_id,
            'caregiver_user_id' => (int) $booking->caregiver_user_id,
            'status' => $status,
            'currency' => $this->stripe->currency(),
            'stripe_customer_id' => $customerId,
            'stripe_payment_method_id' => $paymentMethodId,
            'failed_at' => now(),
            'last_error' => $message,
            'metadata' => $metadata,
        ]);
        if ($paymentIntentId !== null) {
            $attributes['stripe_payment_intent_id'] = $paymentIntentId;
            $attributes['stripe_payment_intent_client_secret'] = $clientSecret;
        }

        return CareBookingPayment::query()->updateOrCreate(
            ['care_booking_id' => $booking->id],
            $attributes,
        );
    }

    private function notifyAuthorizationState(
        CareBooking $booking,
        CareBookingPayment $payment,
        string $status,
    ): void {
        $this->planHealth->reconcileForBooking($booking);
        $attempt = max(1, (int) data_get($payment->metadata, 'authorization_attempt_count', 1));

        if (! $booking->family) {
            return;
        }

        if ($status === CareBookingPayment::STATUS_AUTHORIZED) {
            $isRegularCare = (bool) $booking->care_plan_id;
            $this->notify(
                recipients: $booking->family,
                eventKey: MarketplaceEvent::PAYMENT_AUTHORIZED,
                title: $isRegularCare ? 'Payment confirmed for your visit' : 'Card pre-authorization complete',
                body: $isRegularCare ? 'Your upcoming visit is payment protected. No action is needed.' : 'Your card was pre-authorized for this booking.',
                url: route('family.requests.show', $booking->care_request_id),
                payload: [
                    'care_booking_id' => $booking->id,
                    'care_booking_payment_id' => $payment->id,
                    'amount_authorized_cents' => $payment->amount_authorized_cents,
                ],
                subject: $booking,
                dedupeKey: 'payment-authorized:booking-'.$booking->id.'-attempt-'.$attempt
            );

            return;
        }

        if (in_array($status, [
            CareBookingPayment::STATUS_AUTHORIZATION_REQUIRED,
            CareBookingPayment::STATUS_REAUTH_REQUIRED,
        ], true)) {
            $isRegularCare = (bool) $booking->care_plan_id;
            $this->notify(
                recipients: $booking->family,
                eventKey: $isRegularCare
                    ? MarketplaceEvent::REGULAR_CARE_PAYMENT_ATTENTION
                    : MarketplaceEvent::PAYMENT_ACTION_REQUIRED,
                title: 'Payment confirmation needed for your visit',
                body: 'Open the visit to confirm or replace your card and retry authorization.',
                url: route('family.requests.show', $booking->care_request_id),
                payload: [
                    'care_booking_id' => $booking->id,
                    'care_booking_payment_id' => $payment->id,
                    'payment_status' => $status,
                ],
                subject: $booking,
                dedupeKey: ($isRegularCare ? 'regular-care-payment-attention:' : 'payment-action-required:')
                    .'booking-'.$booking->id.'-status-'.$status.'-attempt-'.$attempt
            );

            return;
        }

        $isRegularCare = (bool) $booking->care_plan_id;
        $this->notify(
            recipients: $booking->family,
            eventKey: $isRegularCare
                ? MarketplaceEvent::REGULAR_CARE_PAYMENT_ATTENTION
                : MarketplaceEvent::PAYMENT_AUTHORIZATION_FAILED,
            title: 'Card authorization failed',
            body: 'Open the visit to confirm or replace your card and retry authorization.',
            url: route('family.requests.show', $booking->care_request_id),
            payload: [
                'care_booking_id' => $booking->id,
                'care_booking_payment_id' => $payment->id,
                'payment_status' => $status,
            ],
            subject: $booking,
            dedupeKey: ($isRegularCare ? 'regular-care-payment-attention:' : 'payment-authorization-failed:')
                .'booking-'.$booking->id.'-status-'.$status.'-attempt-'.$attempt
        );
    }

    /**
     * @param  \App\Models\User|\Illuminate\Support\Collection<int,\App\Models\User>|array<int,\App\Models\User>|null  $recipients
     * @param  array<string, mixed>  $payload
     */
    private function notifyV2CaptureState(CareBooking $booking, CareBookingPayment $payment, bool $notify): void
    {
        if (! $notify) {
            return;
        }

        $this->notify(
            recipients: $booking->family,
            eventKey: (int) $payment->overage_pending_cents > 0
                ? MarketplaceEvent::PAYMENT_ACTION_REQUIRED
                : MarketplaceEvent::PAYMENT_CAPTURED,
            title: (int) $payment->overage_pending_cents > 0
                ? 'Additional payment action needed'
                : 'Shift payment captured',
            body: (int) $payment->overage_pending_cents > 0
                ? 'The final shift total exceeded the authorization. Open Billing & Payments to confirm the remaining amount.'
                : 'The completed shift charge includes care and the hourly processing fee. No action is needed.',
            url: (int) $payment->overage_pending_cents > 0
                ? route('family.billing.show')
                : route('family.requests.show', $booking->care_request_id),
            payload: [
                'care_booking_id' => $booking->id,
                'care_booking_payment_id' => $payment->id,
                'financial_reference' => $payment->financial_reference,
                'amount_captured_cents' => $payment->amount_captured_cents,
                'overage_pending_cents' => $payment->overage_pending_cents,
            ],
            subject: $booking,
            dedupeKey: ((int) $payment->overage_pending_cents > 0 ? 'payment-overage-v2:' : 'payment-captured-v2:')
                .'booking-'.$booking->id,
        );

        if ($payment->status === CareBookingPayment::STATUS_TRANSFERRED && $booking->caregiver) {
            $this->notify(
                recipients: $booking->caregiver,
                eventKey: MarketplaceEvent::PAYOUT_TRANSFERRED,
                title: 'Earnings available in Stripe',
                body: 'Your gross earnings, less Stripe processing fees, were transferred to your Stripe balance. Stripe controls bank payout timing.',
                url: route('caregiver.earnings.index'),
                payload: [
                    'care_booking_id' => $booking->id,
                    'care_booking_payment_id' => $payment->id,
                    'financial_reference' => $payment->financial_reference,
                    'gross_cents' => $payment->caregiver_gross_amount_cents,
                    'processing_fee_cents' => $payment->stripe_processing_fee_cents,
                    'net_cents' => $payment->caregiver_amount_cents,
                ],
                subject: $booking,
                dedupeKey: 'earnings-transferred-v2:booking-'.$booking->id,
            );
        }
    }

    private function notifyV2RefundState(
        CareBooking $booking,
        CareBookingPayment $payment,
        int $beforeRefundedCents,
    ): void {
        $delta = max(0, (int) $payment->amount_refunded_cents - $beforeRefundedCents);
        if ($delta <= 0) {
            return;
        }

        $fullyRefunded = $payment->status === CareBookingPayment::STATUS_REFUNDED;
        $this->notify(
            recipients: $booking->family,
            eventKey: MarketplaceEvent::PAYMENT_REFUNDED,
            title: $fullyRefunded ? 'Payment refunded' : 'Partial refund issued',
            body: $fullyRefunded
                ? 'The family charge for this shift was fully refunded.'
                : 'A partial refund was issued for this shift.',
            url: route('family.requests.show', $booking->care_request_id),
            payload: [
                'care_booking_id' => $booking->id,
                'care_booking_payment_id' => $payment->id,
                'financial_reference' => $payment->financial_reference,
                'amount_refunded_cents' => $delta,
            ],
            subject: $booking,
            dedupeKey: 'payment-refund-v2:booking-'.$booking->id.'-total-'.$payment->amount_refunded_cents,
        );

        if ($booking->caregiver) {
            $this->notify(
                recipients: $booking->caregiver,
                eventKey: MarketplaceEvent::PAYMENT_REFUNDED,
                title: 'Shift earnings adjusted',
                body: 'A family refund adjusted the earnings transfer for this shift. Refund costs and dispute fees are not deducted from you.',
                url: route('caregiver.earnings.index'),
                payload: [
                    'care_booking_id' => $booking->id,
                    'care_booking_payment_id' => $payment->id,
                    'financial_reference' => $payment->financial_reference,
                ],
                subject: $booking,
                dedupeKey: 'caregiver-refund-v2:booking-'.$booking->id.'-total-'.$payment->amount_refunded_cents,
            );
        }
    }

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

    private function reconcilePaymentPlan(CareBookingPayment $payment): CareBookingPayment
    {
        $booking = $payment->booking()->first();
        if ($booking) {
            $this->planHealth->reconcileForBooking($booking);
        }

        return $payment->fresh();
    }
}
