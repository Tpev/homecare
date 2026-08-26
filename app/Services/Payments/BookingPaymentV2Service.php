<?php

namespace App\Services\Payments;

use App\Exceptions\Payments\PaymentActionRequiredException;
use App\Exceptions\Payments\PaymentException;
use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CareBookingPaymentAttempt;
use App\Models\CareBookingPaymentOperation;
use App\Models\CaregiverPayout;
use App\Models\CaregiverPayoutItem;
use App\Support\MarketplacePricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class BookingPaymentV2Service
{
    public function __construct(
        private readonly StripeClient $stripe,
        private readonly MarketplacePricing $pricing,
    ) {}

    public function capture(CareBooking $booking, CareBookingPayment $payment): CareBookingPayment
    {
        $booking->loadMissing(['caregiver.caregiverProfile']);
        $workedMinutes = (int) ($booking->worked_minutes ?: $this->estimatedMinutes($booking));
        $quote = $this->pricing->quoteForCurrentBooking($booking, $workedMinutes);
        $targetCents = (int) $quote['total_charge_cents'];
        $authorizedCents = (int) ($payment->amount_authorized_cents ?? 0);

        if ($targetCents <= 0 || $authorizedCents <= 0) {
            throw new PaymentException('The approved payment amount is invalid for this shift.');
        }

        $primaryCents = min($targetCents, $authorizedCents);
        $primaryCharge = $this->chargeOperation($payment, 'primary');

        if (! $primaryCharge) {
            $capture = $this->stripe->capturePaymentIntent(
                (string) $payment->stripe_payment_intent_id,
                $primaryCents,
                $this->key($payment, 'capture-primary', $primaryCents),
            );
            $primaryCharge = $this->recordChargeResult($payment, $capture, 'primary', $primaryCents);
            CareBookingPaymentAttempt::query()
                ->where('stripe_payment_intent_id', $payment->stripe_payment_intent_id)
                ->update([
                    'status' => CareBookingPayment::STATUS_CAPTURED,
                    'captured_at' => now(),
                    'last_error' => null,
                ]);
        }

        $primaryCaptured = (int) $primaryCharge->amount_cents;
        $overageCents = max(0, $targetCents - $primaryCaptured);
        $overageCharge = $this->chargeOperation($payment, 'overage');

        if ($overageCents > 0 && ! $overageCharge && $payment->stripe_overage_payment_intent_id) {
            try {
                $financials = $this->stripe->retrievePaymentIntentFinancials(
                    (string) $payment->stripe_overage_payment_intent_id,
                    $overageCents,
                );
                if ((string) ($financials['status'] ?? '') === 'succeeded') {
                    $overageCharge = $this->recordChargeResult($payment, $financials, 'overage', $overageCents);
                }
            } catch (PaymentException) {
                // The same stable idempotency key below safely resumes the charge.
            }
        }

        if ($overageCents > 0 && ! $overageCharge) {
            try {
                $overage = $this->stripe->createAndConfirmCharge(
                    $booking,
                    (string) $payment->stripe_customer_id,
                    (string) $payment->stripe_payment_method_id,
                    $overageCents,
                    (string) $payment->currency,
                    $this->stripeMetadata($payment, 'overage'),
                    $this->key($payment, 'capture-overage', $overageCents),
                );
                $overageCharge = $this->recordChargeResult($payment, $overage, 'overage', $overageCents);
            } catch (PaymentActionRequiredException $exception) {
                $payment->forceFill(array_merge($this->snapshotAttributes($booking, $quote), [
                    'status' => CareBookingPayment::STATUS_CAPTURED,
                    'amount_captured_cents' => $primaryCaptured,
                    'stripe_overage_payment_intent_id' => $exception->paymentIntentId,
                    'stripe_payment_intent_client_secret' => $exception->clientSecret,
                    'overage_pending_cents' => $overageCents,
                    'captured_at' => $payment->captured_at ?: now(),
                    'last_error' => $exception->userMessage,
                ]))->save();

                $this->finalizeFeesAndTransfers($payment->fresh());

                return $payment->fresh();
            } catch (PaymentException $exception) {
                $payment->forceFill(array_merge($this->snapshotAttributes($booking, $quote), [
                    'status' => CareBookingPayment::STATUS_CAPTURED,
                    'amount_captured_cents' => $primaryCaptured,
                    'overage_pending_cents' => $overageCents,
                    'captured_at' => $payment->captured_at ?: now(),
                    'last_error' => $exception->userMessage,
                ]))->save();

                $this->finalizeFeesAndTransfers($payment->fresh());

                return $payment->fresh();
            }
        }

        $capturedCents = (int) $payment->operations()
            ->where('type', CareBookingPaymentOperation::TYPE_CHARGE)
            ->where('status', CareBookingPaymentOperation::STATUS_SUCCEEDED)
            ->sum('amount_cents');
        $overageCaptured = $overageCharge ? (int) $overageCharge->amount_cents : 0;

        $payment->forceFill(array_merge($this->snapshotAttributes($booking, $quote), [
            'status' => CareBookingPayment::STATUS_CAPTURED,
            'amount_captured_cents' => $capturedCents,
            'amount_overage_cents' => $overageCaptured,
            'overage_pending_cents' => max(0, $targetCents - $capturedCents),
            'stripe_overage_payment_intent_id' => $overageCharge
                ? (string) data_get($overageCharge->metadata, 'payment_intent_id')
                : $payment->stripe_overage_payment_intent_id,
            'captured_at' => $payment->captured_at ?: now(),
            'failed_at' => null,
            'last_error' => null,
        ]))->save();

        return $this->finalizeFeesAndTransfers($payment->fresh());
    }

    public function finalizeFeesAndTransfers(CareBookingPayment $payment): CareBookingPayment
    {
        $payment->loadMissing(['booking.caregiver.caregiverProfile']);
        $booking = $payment->booking;
        if (! $booking || ! $this->usesV2($payment)) {
            return $payment;
        }

        $this->refreshMissingFees($payment);
        $charges = $payment->operations()
            ->where('type', CareBookingPaymentOperation::TYPE_CHARGE)
            ->where('status', CareBookingPaymentOperation::STATUS_SUCCEEDED)
            ->orderBy('id')
            ->get();
        $capturedCents = (int) $charges->sum('amount_cents');
        $feeCents = (int) $payment->operations()
            ->where('type', CareBookingPaymentOperation::TYPE_PROCESSING_FEE)
            ->where('status', CareBookingPaymentOperation::STATUS_SUCCEEDED)
            ->sum('amount_cents');
        $feesFinalized = $charges->isNotEmpty()
            && $charges->every(fn (CareBookingPaymentOperation $charge): bool => $payment->operations()
                ->where('type', CareBookingPaymentOperation::TYPE_PROCESSING_FEE)
                ->where('parent_operation_id', $charge->id)
                ->where('status', CareBookingPaymentOperation::STATUS_SUCCEEDED)
                ->exists());
        $quote = $this->pricing->quoteForCurrentBooking(
            $booking,
            (int) ($payment->worked_minutes ?: $this->estimatedMinutes($booking)),
            $feeCents,
        );

        $payment->forceFill([
            'amount_captured_cents' => $capturedCents,
            'stripe_processing_fee_cents' => $feeCents,
            'caregiver_gross_amount_cents' => (int) $quote['caregiver_gross_amount_cents'],
            'caregiver_amount_cents' => (int) $quote['caregiver_amount_cents'],
            'platform_fee_cents' => (int) $quote['platform_fee_cents'],
            'fee_finalization_status' => $feesFinalized ? 'finalized' : 'pending',
            'fee_finalized_at' => $feesFinalized ? ($payment->fee_finalized_at ?: now()) : null,
        ])->save();

        if ($feesFinalized) {
            $grossCents = (int) $quote['caregiver_gross_amount_cents'];
            $earningKey = $this->key(
                $payment,
                'earning-'.$grossCents.'-fee-'.$feeCents,
                (int) $quote['caregiver_amount_cents'],
            );
            CareBookingPaymentOperation::query()->firstOrCreate(
                ['idempotency_key' => $earningKey],
                array_merge($this->baseOperationAttributes($payment), [
                    'type' => CareBookingPaymentOperation::TYPE_EARNING,
                    'status' => CareBookingPaymentOperation::STATUS_SUCCEEDED,
                    'amount_cents' => $grossCents,
                    'metadata' => [
                        'processing_fee_cents' => $feeCents,
                        'net_earnings_cents' => (int) $quote['caregiver_amount_cents'],
                        'policy' => 'caregiver_gross_less_successful_charge_processing_fees',
                    ],
                    'occurred_at' => now(),
                    'processed_at' => now(),
                ]),
            );
        }

        if (! $feesFinalized || (int) $payment->overage_pending_cents > 0) {
            $this->syncPayoutRecord($payment, false);

            return $payment->fresh();
        }

        return $this->transferEarnings($payment->fresh(), $charges);
    }

    public function refund(
        CareBooking $booking,
        CareBookingPayment $payment,
        ?int $amountCents,
        string $reason,
        ?int $caregiverReversalCents = null,
        ?string $operationReference = null,
    ): CareBookingPayment {
        $remaining = max(0, (int) $payment->amount_captured_cents - (int) $payment->amount_refunded_cents);
        $requestedCents = $amountCents === null ? $remaining : min(max(0, $amountCents), $remaining);
        if ($requestedCents <= 0) {
            return $payment;
        }

        $targetRefundTotal = (int) $payment->amount_refunded_cents + $requestedCents;
        $left = $requestedCents;
        $caregiverReversalLeft = $caregiverReversalCents === null
            ? null
            : max(0, $caregiverReversalCents);
        $charges = $payment->operations()
            ->where('type', CareBookingPaymentOperation::TYPE_CHARGE)
            ->where('status', CareBookingPaymentOperation::STATUS_SUCCEEDED)
            ->orderBy('id')
            ->get();

        foreach ($charges as $charge) {
            if ($left <= 0) {
                break;
            }

            $alreadyRefunded = (int) $payment->operations()
                ->where('type', CareBookingPaymentOperation::TYPE_REFUND)
                ->where('parent_operation_id', $charge->id)
                ->where('status', CareBookingPaymentOperation::STATUS_SUCCEEDED)
                ->sum('amount_cents');
            $chargeRemaining = max(0, (int) $charge->amount_cents - $alreadyRefunded);
            $part = min($left, $chargeRemaining);
            if ($part <= 0) {
                continue;
            }

            $reasonKey = $operationReference ?: 'refund-'.$targetRefundTotal;
            $key = $this->key($payment, $reasonKey.'-'.$charge->id, $part);
            $operation = $this->pendingOperation(
                $payment,
                CareBookingPaymentOperation::TYPE_REFUND,
                $part,
                $key,
                $charge,
                ['reason' => $reason, 'adjustment_reference' => $operationReference],
            );
            if ($operation->status !== CareBookingPaymentOperation::STATUS_SUCCEEDED) {
                $refund = $this->stripe->createRefundForCharge(
                    (string) $charge->stripe_object_id,
                    $part,
                    $reason,
                    $this->stripeMetadata($payment, 'refund'),
                    $key,
                );
                $operation->forceFill([
                    'status' => (string) ($refund['status'] ?? '') === 'succeeded'
                        ? CareBookingPaymentOperation::STATUS_SUCCEEDED
                        : CareBookingPaymentOperation::STATUS_PENDING,
                    'stripe_object_id' => (string) ($refund['id'] ?? ''),
                    'processed_at' => (string) ($refund['status'] ?? '') === 'succeeded' ? now() : null,
                    'last_error' => null,
                ])->save();
            }

            if ($operation->fresh()->status === CareBookingPaymentOperation::STATUS_SUCCEEDED) {
                // The family refund is already final at this point. Persist that fact before
                // attempting the independently retryable caregiver transfer reversal.
                $this->refreshRefundTotals($payment->fresh());
                $exactReversal = null;
                if ($caregiverReversalLeft !== null) {
                    $exactReversal = $part === $left
                        ? $caregiverReversalLeft
                        : min(
                            $caregiverReversalLeft,
                            (int) round($caregiverReversalCents * ($part / max(1, $requestedCents))),
                        );
                }
                $actualReversal = $this->reverseTransferForCharge(
                    $payment,
                    $charge,
                    $part,
                    $reasonKey,
                    $exactReversal,
                );
                if ($caregiverReversalLeft !== null) {
                    $caregiverReversalLeft = max(0, $caregiverReversalLeft - $actualReversal);
                }
                $left -= $part;
            }
        }

        $feeCents = (int) $payment->operations()
            ->where('type', CareBookingPaymentOperation::TYPE_PROCESSING_FEE)
            ->where('status', CareBookingPaymentOperation::STATUS_SUCCEEDED)
            ->sum('amount_cents');
        $quote = $this->pricing->quoteForCurrentBooking(
            $booking,
            (int) ($booking->worked_minutes ?: $payment->worked_minutes ?: $this->estimatedMinutes($booking)),
            $feeCents,
        );
        $payment->forceFill(array_merge($this->snapshotAttributes($booking, $quote), [
            'stripe_processing_fee_cents' => $feeCents,
            'caregiver_amount_cents' => (int) $quote['caregiver_amount_cents'],
        ]))->save();
        $this->refreshRefundTotals($payment->fresh());
        $this->syncPayoutRecord($payment->fresh(), $payment->fresh()->status === CareBookingPayment::STATUS_TRANSFERRED);

        return $payment->fresh();
    }

    public function chargeAdjustment(
        CareBooking $booking,
        CareBookingPayment $payment,
        int $amountCents,
        string $adjustmentReference,
    ): CareBookingPayment {
        if ($amountCents <= 0) {
            return $payment;
        }

        $key = $this->key($payment, 'charge-adjustment-'.$adjustmentReference, $amountCents);
        try {
            $result = $this->stripe->createAndConfirmCharge(
                $booking,
                (string) $payment->stripe_customer_id,
                (string) $payment->stripe_payment_method_id,
                $amountCents,
                (string) $payment->currency,
                array_merge($this->stripeMetadata($payment, 'charge_adjustment'), [
                    'adjustment_reference' => $adjustmentReference,
                ]),
                $key,
            );
        } catch (PaymentActionRequiredException $exception) {
            $payment->forceFill([
                'status' => CareBookingPayment::STATUS_CAPTURED,
                'stripe_overage_payment_intent_id' => $exception->paymentIntentId,
                'stripe_payment_intent_client_secret' => $exception->clientSecret,
                'overage_pending_cents' => $amountCents,
                'last_error' => $exception->userMessage,
            ])->save();

            throw $exception;
        }
        $charge = $this->recordChargeResult(
            $payment,
            $result,
            'adjustment-'.$adjustmentReference,
            $amountCents,
        );
        $workedMinutes = (int) ($booking->worked_minutes ?: $payment->worked_minutes ?: $this->estimatedMinutes($booking));
        $quote = $this->pricing->quoteForCurrentBooking($booking, $workedMinutes);
        $payment->forceFill(array_merge($this->snapshotAttributes($booking, $quote), [
            'status' => CareBookingPayment::STATUS_CAPTURED,
            'stripe_overage_payment_intent_id' => (string) data_get($charge->metadata, 'payment_intent_id'),
            'stripe_overage_charge_id' => (string) $charge->stripe_object_id,
            'amount_overage_cents' => (int) $payment->amount_overage_cents + (int) $charge->amount_cents,
            'overage_pending_cents' => 0,
            'last_error' => null,
        ]))->save();

        return $this->finalizeFeesAndTransfers($payment->fresh());
    }

    public function recordSucceededPaymentIntent(CareBookingPayment $payment, array $object): CareBookingPayment
    {
        if (! $this->usesV2($payment) || (string) ($object['status'] ?? '') !== 'succeeded') {
            return $payment;
        }

        $kind = (string) ($object['id'] ?? '') === (string) $payment->stripe_overage_payment_intent_id
            ? 'overage'
            : 'primary';
        $fallback = $kind === 'overage'
            ? (int) $payment->overage_pending_cents
            : min((int) $payment->amount_authorized_cents, (int) ($object['amount_received'] ?? 0));
        $financials = $object;
        if (! isset($financials['latest_charge_id'])) {
            $financials = $this->stripe->retrievePaymentIntentFinancials(
                (string) $object['id'],
                max(0, $fallback),
            );
        }
        $this->recordChargeResult($payment, $financials, $kind, max(0, $fallback));
        if ($kind === 'overage') {
            $payment->forceFill(['overage_pending_cents' => 0, 'last_error' => null])->save();
        }

        return $this->finalizeFeesAndTransfers($payment->fresh());
    }

    public function handleDispute(array $object): void
    {
        $chargeId = is_array($object['charge'] ?? null)
            ? (string) data_get($object, 'charge.id', '')
            : (string) ($object['charge'] ?? '');
        $disputeId = (string) ($object['id'] ?? '');
        if ($chargeId === '' || $disputeId === '') {
            return;
        }

        $charge = CareBookingPaymentOperation::query()
            ->where('type', CareBookingPaymentOperation::TYPE_CHARGE)
            ->where('stripe_object_id', $chargeId)
            ->first();
        if (! $charge) {
            return;
        }

        $payment = $charge->payment;
        $status = (string) ($object['status'] ?? 'needs_response');
        $operation = CareBookingPaymentOperation::query()->updateOrCreate(
            [
                'type' => CareBookingPaymentOperation::TYPE_DISPUTE,
                'stripe_object_id' => $disputeId,
            ],
            array_merge($this->baseOperationAttributes($payment), [
                'parent_operation_id' => $charge->id,
                'status' => $status === 'won'
                    ? CareBookingPaymentOperation::STATUS_REVERSED
                    : ($status === 'lost' ? CareBookingPaymentOperation::STATUS_SUCCEEDED : CareBookingPaymentOperation::STATUS_PENDING),
                'amount_cents' => max(0, (int) ($object['amount'] ?? 0)),
                'stripe_parent_object_id' => $chargeId,
                'metadata' => ['stripe_status' => $status, 'reason' => $object['reason'] ?? null],
                'occurred_at' => now(),
                'processed_at' => in_array($status, ['won', 'lost'], true) ? now() : null,
            ]),
        );

        // Keep the caregiver's completed-shift earnings intact while a dispute is
        // only pending. Reverse the source-linked transfer only if Stripe makes
        // the dispute loss final; a later "won" event then needs no compensating
        // transfer back to the caregiver.
        if ($status === 'lost') {
            try {
                $this->reverseTransferForCharge(
                    $payment,
                    $charge,
                    min((int) $charge->amount_cents, (int) $operation->amount_cents),
                    'dispute-'.$disputeId,
                );
            } catch (Throwable $exception) {
                Log::error('payment.dispute_transfer_reversal_failed', [
                    'care_booking_payment_id' => $payment->id,
                    'stripe_dispute_id' => $disputeId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $payment->forceFill([
            'metadata' => array_merge((array) $payment->metadata, [
                'latest_dispute_id' => $disputeId,
                'latest_dispute_status' => $status,
            ]),
        ])->save();
        $this->syncPayoutRecord($payment->fresh(), false);
    }

    public function handleChargeRefund(array $object): bool
    {
        $chargeId = (string) ($object['id'] ?? '');
        $charge = CareBookingPaymentOperation::query()
            ->where('type', CareBookingPaymentOperation::TYPE_CHARGE)
            ->where('stripe_object_id', $chargeId)
            ->first();
        if (! $charge) {
            return false;
        }

        $refunds = [];
        foreach ((array) data_get($object, 'refunds.data', []) as $refund) {
            if (is_array($refund)) {
                $this->recordRefundWebhookObject($charge, $refund);
                $refunds[] = $refund;
            }
        }
        $this->refreshRefundTotals($charge->payment);
        foreach ($refunds as $refund) {
            $this->ensureRefundTransferReversal($charge, $refund);
        }
        $this->syncPayoutRecord($charge->payment->fresh(), false);

        return true;
    }

    public function handleRefund(array $object): bool
    {
        $chargeId = is_array($object['charge'] ?? null)
            ? (string) data_get($object, 'charge.id', '')
            : (string) ($object['charge'] ?? '');
        $charge = CareBookingPaymentOperation::query()
            ->where('type', CareBookingPaymentOperation::TYPE_CHARGE)
            ->where('stripe_object_id', $chargeId)
            ->first();
        if (! $charge) {
            return false;
        }

        $this->recordRefundWebhookObject($charge, $object);
        $this->refreshRefundTotals($charge->payment);
        $this->ensureRefundTransferReversal($charge, $object);
        $this->syncPayoutRecord($charge->payment->fresh(), false);

        return true;
    }

    public function retryPendingTransferReversals(CareBookingPayment $payment): CareBookingPayment
    {
        if (! $this->usesV2($payment)) {
            return $payment;
        }

        $operations = $payment->operations()
            ->with('parent')
            ->where('type', CareBookingPaymentOperation::TYPE_TRANSFER_REVERSAL)
            ->whereIn('status', [
                CareBookingPaymentOperation::STATUS_PENDING,
                CareBookingPaymentOperation::STATUS_FAILED,
            ])
            ->orderBy('id')
            ->get();

        foreach ($operations as $operation) {
            $transfer = $operation->parent;
            if (! $transfer?->stripe_object_id) {
                $operation->forceFill([
                    'status' => CareBookingPaymentOperation::STATUS_FAILED,
                    'failed_at' => now(),
                    'last_error' => 'The source Stripe transfer reference is missing.',
                ])->save();

                continue;
            }

            try {
                $reversal = $this->stripe->createTransferReversal(
                    (string) $transfer->stripe_object_id,
                    (int) $operation->amount_cents,
                    $this->stripeMetadata($payment, 'earnings_adjustment_retry'),
                    (string) $operation->idempotency_key,
                );
                $succeeded = (string) ($reversal['status'] ?? '') === 'succeeded';
                $operation->forceFill([
                    'status' => $succeeded
                        ? CareBookingPaymentOperation::STATUS_SUCCEEDED
                        : CareBookingPaymentOperation::STATUS_PENDING,
                    'stripe_object_id' => (string) ($reversal['id'] ?? $operation->stripe_object_id),
                    'stripe_parent_object_id' => (string) $transfer->stripe_object_id,
                    'processed_at' => $succeeded ? now() : null,
                    'failed_at' => null,
                    'last_error' => null,
                ])->save();
                if ((string) ($reversal['id'] ?? '') !== '') {
                    $payment->forceFill([
                        'stripe_last_transfer_reversal_id' => (string) $reversal['id'],
                    ])->save();
                }
            } catch (PaymentException $exception) {
                $operation->forceFill([
                    'status' => CareBookingPaymentOperation::STATUS_FAILED,
                    'failed_at' => now(),
                    'last_error' => $exception->userMessage,
                ])->save();
            }
        }

        $remaining = $payment->operations()
            ->where('type', CareBookingPaymentOperation::TYPE_TRANSFER_REVERSAL)
            ->whereIn('status', [
                CareBookingPaymentOperation::STATUS_PENDING,
                CareBookingPaymentOperation::STATUS_FAILED,
            ])
            ->exists();
        $this->syncPayoutRecord($payment->fresh(), ! $remaining);
        if ($remaining) {
            throw new PaymentException('The caregiver Stripe transfer reversal is still pending. Retry shortly.');
        }

        return $payment->fresh();
    }

    public function usesV2(CareBookingPayment $payment): bool
    {
        return (string) $payment->pricing_version === $this->pricing->currentVersion();
    }

    public function recordAuthorization(CareBookingPayment $payment): void
    {
        if (! $this->usesV2($payment) || ! $payment->stripe_payment_intent_id) {
            return;
        }

        CareBookingPaymentOperation::query()->firstOrCreate(
            [
                'type' => CareBookingPaymentOperation::TYPE_AUTHORIZATION,
                'stripe_object_id' => (string) $payment->stripe_payment_intent_id,
            ],
            array_merge($this->baseOperationAttributes($payment), [
                'status' => $payment->status === CareBookingPayment::STATUS_AUTHORIZED
                    ? CareBookingPaymentOperation::STATUS_SUCCEEDED
                    : CareBookingPaymentOperation::STATUS_PENDING,
                'amount_cents' => max(0, (int) $payment->amount_authorized_cents),
                'idempotency_key' => $this->key(
                    $payment,
                    'ledger-authorization-'.substr(hash('sha256', (string) $payment->stripe_payment_intent_id), 0, 12),
                    (int) $payment->amount_authorized_cents,
                ),
                'metadata' => ['capture_method' => 'manual'],
                'occurred_at' => $payment->authorized_at ?: now(),
                'processed_at' => $payment->status === CareBookingPayment::STATUS_AUTHORIZED ? now() : null,
            ]),
        );
    }

    /** @return array<string, mixed> */
    public function paymentSnapshotAttributes(CareBooking $booking): array
    {
        return [
            'financial_reference' => (string) $booking->financial_reference,
            'pricing_version' => (string) $booking->pricing_version,
            'family_care_rate_cents' => (int) $booking->family_care_rate_cents,
            'family_processing_fee_rate_cents' => (int) $booking->family_processing_fee_rate_cents,
            'caregiver_gross_rate_cents' => (int) $booking->caregiver_gross_rate_cents,
            'stripe_transfer_group' => 'LOLO_'.(string) $booking->financial_reference,
        ];
    }

    private function transferEarnings(CareBookingPayment $payment, $charges): CareBookingPayment
    {
        $booking = $payment->booking;
        $profile = $booking?->caregiver?->caregiverProfile;
        $netCents = max(0, (int) $payment->caregiver_amount_cents);
        if ($netCents <= 0) {
            $this->syncPayoutRecord($payment, true);

            return $payment;
        }
        if (! $profile?->stripeConnectIsReady() || ! $profile->stripe_connect_account_id) {
            $payment->forceFill([
                'status' => CareBookingPayment::STATUS_TRANSFER_FAILED,
                'last_error' => 'Caregiver Stripe balance is not ready to receive earnings.',
            ])->save();
            $this->syncPayoutRecord($payment, false);

            return $payment->fresh();
        }

        $alreadyTransferred = (int) $payment->operations()
            ->where('type', CareBookingPaymentOperation::TYPE_TRANSFER)
            ->where('status', CareBookingPaymentOperation::STATUS_SUCCEEDED)
            ->sum('amount_cents');
        $remaining = max(0, $netCents - $alreadyTransferred);
        $transferRemainingTarget = $remaining;
        $untransferredCharges = $charges->filter(fn (CareBookingPaymentOperation $charge): bool => ! $payment->operations()
            ->where('type', CareBookingPaymentOperation::TYPE_TRANSFER)
            ->where('parent_operation_id', $charge->id)
            ->where('status', CareBookingPaymentOperation::STATUS_SUCCEEDED)
            ->exists())->values();
        $allocationBase = max(1, (int) $untransferredCharges->sum('amount_cents'));
        $transferIds = $payment->operations()
            ->where('type', CareBookingPaymentOperation::TYPE_TRANSFER)
            ->where('status', CareBookingPaymentOperation::STATUS_SUCCEEDED)
            ->pluck('stripe_object_id')->filter()->values()->all();
        foreach ($untransferredCharges as $index => $charge) {
            $allocation = $index === $untransferredCharges->count() - 1
                ? $remaining
                : min($remaining, (int) floor($transferRemainingTarget * ((int) $charge->amount_cents / $allocationBase)));
            $remaining -= $allocation;
            if ($allocation <= 0) {
                continue;
            }

            $key = $this->key($payment, 'transfer-'.$charge->id, $allocation);
            $operation = $this->pendingOperation(
                $payment,
                CareBookingPaymentOperation::TYPE_TRANSFER,
                $allocation,
                $key,
                $charge,
                ['source_charge_id' => $charge->stripe_object_id],
            );
            if ($operation->status !== CareBookingPaymentOperation::STATUS_SUCCEEDED) {
                try {
                    $transfer = $this->stripe->createTransferForCharge(
                        (string) $profile->stripe_connect_account_id,
                        (string) $charge->stripe_object_id,
                        (string) $payment->stripe_transfer_group,
                        $allocation,
                        (string) $payment->currency,
                        $this->stripeMetadata($payment, 'caregiver_earnings'),
                        $key,
                    );
                    $operation->forceFill([
                        'status' => CareBookingPaymentOperation::STATUS_SUCCEEDED,
                        'stripe_object_id' => (string) $transfer['id'],
                        'stripe_parent_object_id' => (string) $charge->stripe_object_id,
                        'processed_at' => now(),
                        'failed_at' => null,
                        'last_error' => null,
                    ])->save();
                } catch (PaymentException $exception) {
                    $operation->forceFill([
                        'status' => CareBookingPaymentOperation::STATUS_FAILED,
                        'failed_at' => now(),
                        'last_error' => $exception->userMessage,
                    ])->save();
                    $payment->forceFill([
                        'status' => CareBookingPayment::STATUS_TRANSFER_FAILED,
                        'last_error' => $exception->userMessage,
                    ])->save();
                    $this->syncPayoutRecord($payment, false);

                    return $payment->fresh();
                }
            }
            $transferIds[] = (string) $operation->fresh()->stripe_object_id;
        }

        $allTransferred = (int) $payment->operations()
            ->where('type', CareBookingPaymentOperation::TYPE_TRANSFER)
            ->where('status', CareBookingPaymentOperation::STATUS_SUCCEEDED)
            ->sum('amount_cents') >= $netCents;
        $payment->forceFill([
            'status' => $allTransferred ? CareBookingPayment::STATUS_TRANSFERRED : CareBookingPayment::STATUS_TRANSFER_FAILED,
            'stripe_transfer_id' => $transferIds[0] ?? $payment->stripe_transfer_id,
            'transferred_at' => $allTransferred ? ($payment->transferred_at ?: now()) : null,
            'last_error' => $allTransferred ? null : 'Caregiver earnings transfer is incomplete.',
        ])->save();
        $this->syncPayoutRecord($payment->fresh(), $allTransferred);

        return $payment->fresh();
    }

    private function refreshMissingFees(CareBookingPayment $payment): void
    {
        $charges = $payment->operations()
            ->where('type', CareBookingPaymentOperation::TYPE_CHARGE)
            ->where('status', CareBookingPaymentOperation::STATUS_SUCCEEDED)
            ->get();
        foreach ($charges as $charge) {
            if ($payment->operations()
                ->where('type', CareBookingPaymentOperation::TYPE_PROCESSING_FEE)
                ->where('parent_operation_id', $charge->id)
                ->where('status', CareBookingPaymentOperation::STATUS_SUCCEEDED)
                ->exists()) {
                continue;
            }

            $intentId = (string) data_get($charge->metadata, 'payment_intent_id', '');
            if ($intentId === '') {
                continue;
            }
            try {
                $financials = $this->stripe->retrievePaymentIntentFinancials($intentId, (int) $charge->amount_cents);
                $this->recordProcessingFee($payment, $charge, $financials);
            } catch (PaymentException $exception) {
                Log::warning('payment.processing_fee_reconciliation_pending', [
                    'care_booking_payment_id' => $payment->id,
                    'stripe_payment_intent_id' => $intentId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function recordChargeResult(
        CareBookingPayment $payment,
        array $result,
        string $kind,
        int $fallbackAmountCents,
    ): CareBookingPaymentOperation {
        $intentId = (string) ($result['id'] ?? $result['payment_intent_id'] ?? '');
        $chargeId = (string) ($result['latest_charge_id'] ?? '');
        if ($intentId === '' || $chargeId === '') {
            throw new PaymentException(
                'The family charge succeeded, but its Stripe charge reference is still being finalized. Please retry shortly.'
            );
        }
        $amount = max(0, (int) ($result['amount_received'] ?? $fallbackAmountCents));
        $operation = CareBookingPaymentOperation::query()->firstOrCreate(
            ['type' => CareBookingPaymentOperation::TYPE_CHARGE, 'stripe_object_id' => $chargeId],
            array_merge($this->baseOperationAttributes($payment), [
                'status' => CareBookingPaymentOperation::STATUS_SUCCEEDED,
                'amount_cents' => $amount,
                'stripe_parent_object_id' => $intentId,
                'idempotency_key' => $this->key($payment, 'ledger-charge-'.$kind, $amount),
                'metadata' => [
                    'kind' => $kind,
                    'payment_intent_id' => $intentId,
                    'balance_transaction_id' => $result['balance_transaction_id'] ?? null,
                ],
                'occurred_at' => now(),
                'processed_at' => now(),
            ]),
        );
        $this->recordProcessingFee($payment, $operation, $result);

        if ($kind === 'primary' || $kind === 'overage') {
            $payment->forceFill([
                $kind === 'overage' ? 'stripe_overage_charge_id' : 'stripe_primary_charge_id' => $chargeId,
            ])->save();
        }

        return $operation;
    }

    private function recordProcessingFee(
        CareBookingPayment $payment,
        CareBookingPaymentOperation $charge,
        array $result,
    ): void {
        if (! ($result['fee_finalized'] ?? false) || ! is_numeric($result['processing_fee_cents'] ?? null)) {
            return;
        }

        $balanceTransactionId = (string) ($result['balance_transaction_id'] ?? data_get($charge->metadata, 'balance_transaction_id', ''));
        if ($balanceTransactionId === '') {
            return;
        }

        CareBookingPaymentOperation::query()->firstOrCreate(
            ['type' => CareBookingPaymentOperation::TYPE_PROCESSING_FEE, 'stripe_object_id' => $balanceTransactionId],
            array_merge($this->baseOperationAttributes($payment), [
                'parent_operation_id' => $charge->id,
                'status' => CareBookingPaymentOperation::STATUS_SUCCEEDED,
                'amount_cents' => max(0, (int) $result['processing_fee_cents']),
                'stripe_parent_object_id' => (string) $charge->stripe_object_id,
                'idempotency_key' => $this->key($payment, 'ledger-fee-'.$charge->id, (int) $result['processing_fee_cents']),
                'metadata' => ['policy' => 'successful_charge_balance_transaction'],
                'occurred_at' => now(),
                'processed_at' => now(),
            ]),
        );
    }

    private function reverseTransferForCharge(
        CareBookingPayment $payment,
        CareBookingPaymentOperation $charge,
        int $familyAdjustmentCents,
        string $reasonKey,
        ?int $exactReversalCents = null,
    ): int {
        $transfer = $payment->operations()
            ->where('type', CareBookingPaymentOperation::TYPE_TRANSFER)
            ->where('parent_operation_id', $charge->id)
            ->where('status', CareBookingPaymentOperation::STATUS_SUCCEEDED)
            ->first();
        if (! $transfer || $familyAdjustmentCents <= 0 || (int) $charge->amount_cents <= 0) {
            return 0;
        }

        $alreadyReversed = (int) $payment->operations()
            ->where('type', CareBookingPaymentOperation::TYPE_TRANSFER_REVERSAL)
            ->where('parent_operation_id', $transfer->id)
            ->where('status', CareBookingPaymentOperation::STATUS_SUCCEEDED)
            ->sum('amount_cents');
        $target = $exactReversalCents === null
            ? (int) round((int) $transfer->amount_cents * ($familyAdjustmentCents / (int) $charge->amount_cents))
            : max(0, $exactReversalCents);
        $amount = min(max(0, (int) $transfer->amount_cents - $alreadyReversed), max(0, $target));
        if ($amount <= 0) {
            return 0;
        }

        $key = $this->key($payment, 'reversal-'.$reasonKey.'-'.$transfer->id, $amount);
        $operation = $this->pendingOperation(
            $payment,
            CareBookingPaymentOperation::TYPE_TRANSFER_REVERSAL,
            $amount,
            $key,
            $transfer,
            ['reason' => $reasonKey],
        );
        if ($operation->status === CareBookingPaymentOperation::STATUS_SUCCEEDED) {
            return (int) $operation->amount_cents;
        }

        try {
            $reversal = $this->stripe->createTransferReversal(
                (string) $transfer->stripe_object_id,
                $amount,
                $this->stripeMetadata($payment, 'earnings_adjustment'),
                $key,
            );
        } catch (PaymentException $exception) {
            $operation->forceFill([
                'status' => CareBookingPaymentOperation::STATUS_FAILED,
                'failed_at' => now(),
                'last_error' => $exception->userMessage,
            ])->save();

            throw $exception;
        }
        $operation->forceFill([
            'status' => (string) ($reversal['status'] ?? '') === 'succeeded'
                ? CareBookingPaymentOperation::STATUS_SUCCEEDED
                : CareBookingPaymentOperation::STATUS_PENDING,
            'stripe_object_id' => (string) ($reversal['id'] ?? ''),
            'stripe_parent_object_id' => (string) $transfer->stripe_object_id,
            'processed_at' => (string) ($reversal['status'] ?? '') === 'succeeded' ? now() : null,
        ])->save();
        if ((string) ($reversal['id'] ?? '') !== '') {
            $payment->forceFill([
                'stripe_last_transfer_reversal_id' => (string) $reversal['id'],
            ])->save();
        }

        return $operation->fresh()->status === CareBookingPaymentOperation::STATUS_SUCCEEDED
            ? (int) $operation->amount_cents
            : 0;
    }

    private function refreshRefundTotals(CareBookingPayment $payment): void
    {
        $refunded = (int) $payment->operations()
            ->where('type', CareBookingPaymentOperation::TYPE_REFUND)
            ->where('status', CareBookingPaymentOperation::STATUS_SUCCEEDED)
            ->sum('amount_cents');
        $captured = (int) $payment->amount_captured_cents;
        $payment->forceFill([
            'amount_refunded_cents' => $refunded,
            'status' => $refunded >= $captured
                ? CareBookingPayment::STATUS_REFUNDED
                : ($refunded > 0 ? CareBookingPayment::STATUS_PARTIALLY_REFUNDED : $payment->status),
            'stripe_last_refund_id' => (string) $payment->operations()
                ->where('type', CareBookingPaymentOperation::TYPE_REFUND)
                ->latest('id')
                ->value('stripe_object_id'),
        ])->save();
    }

    private function recordRefundWebhookObject(CareBookingPaymentOperation $charge, array $refund): void
    {
        $refundId = (string) ($refund['id'] ?? '');
        if ($refundId === '') {
            return;
        }

        $stripeStatus = (string) ($refund['status'] ?? 'pending');
        $status = match ($stripeStatus) {
            'succeeded' => CareBookingPaymentOperation::STATUS_SUCCEEDED,
            'failed', 'canceled' => CareBookingPaymentOperation::STATUS_FAILED,
            default => CareBookingPaymentOperation::STATUS_PENDING,
        };
        CareBookingPaymentOperation::query()->updateOrCreate(
            ['type' => CareBookingPaymentOperation::TYPE_REFUND, 'stripe_object_id' => $refundId],
            array_merge($this->baseOperationAttributes($charge->payment), [
                'parent_operation_id' => $charge->id,
                'status' => $status,
                'amount_cents' => max(0, (int) ($refund['amount'] ?? 0)),
                'stripe_parent_object_id' => (string) $charge->stripe_object_id,
                'metadata' => ['stripe_status' => $stripeStatus],
                'occurred_at' => now(),
                'processed_at' => $status === CareBookingPaymentOperation::STATUS_SUCCEEDED ? now() : null,
                'failed_at' => $status === CareBookingPaymentOperation::STATUS_FAILED ? now() : null,
                'last_error' => $status === CareBookingPaymentOperation::STATUS_FAILED
                    ? (string) ($refund['failure_reason'] ?? 'Stripe refund failed.')
                    : null,
            ]),
        );
    }

    private function ensureRefundTransferReversal(
        CareBookingPaymentOperation $charge,
        array $refund,
    ): void {
        if ((string) ($refund['status'] ?? '') !== 'succeeded') {
            return;
        }

        $refundId = (string) ($refund['id'] ?? '');
        $payment = $charge->payment;
        $transfer = $payment->operations()
            ->where('type', CareBookingPaymentOperation::TYPE_TRANSFER)
            ->where('parent_operation_id', $charge->id)
            ->where('status', CareBookingPaymentOperation::STATUS_SUCCEEDED)
            ->first();
        if ($refundId === '' || ! $transfer || (int) $charge->amount_cents <= 0) {
            return;
        }

        $refundedCents = (int) $payment->operations()
            ->where('type', CareBookingPaymentOperation::TYPE_REFUND)
            ->where('parent_operation_id', $charge->id)
            ->where('status', CareBookingPaymentOperation::STATUS_SUCCEEDED)
            ->sum('amount_cents');
        $targetReversalCents = (int) round(
            (int) $transfer->amount_cents
            * (min($refundedCents, (int) $charge->amount_cents) / (int) $charge->amount_cents)
        );
        $reversalQuery = $payment->operations()
            ->where('type', CareBookingPaymentOperation::TYPE_TRANSFER_REVERSAL)
            ->where('parent_operation_id', $transfer->id);
        $alreadyReversedCents = (int) (clone $reversalQuery)
            ->where('status', CareBookingPaymentOperation::STATUS_SUCCEEDED)
            ->sum('amount_cents');
        $reservedReversalCents = (int) (clone $reversalQuery)
            ->whereIn('status', [
                CareBookingPaymentOperation::STATUS_PENDING,
                CareBookingPaymentOperation::STATUS_FAILED,
            ])
            ->sum('amount_cents');
        $reversalCents = max(
            0,
            $targetReversalCents - $alreadyReversedCents - $reservedReversalCents,
        );
        if ($reversalCents <= 0) {
            return;
        }

        $this->reverseTransferForCharge(
            $payment,
            $charge,
            max(1, $refundedCents),
            'refund-'.$refundId,
            $reversalCents,
        );
    }

    private function syncPayoutRecord(CareBookingPayment $payment, bool $transferred): void
    {
        $booking = $payment->booking;
        if (! $booking || (int) $payment->caregiver_gross_amount_cents <= 0) {
            return;
        }

        $transferredCents = (int) $payment->operations()
            ->where('type', CareBookingPaymentOperation::TYPE_TRANSFER)
            ->where('status', CareBookingPaymentOperation::STATUS_SUCCEEDED)
            ->sum('amount_cents');
        $reversedCents = (int) $payment->operations()
            ->where('type', CareBookingPaymentOperation::TYPE_TRANSFER_REVERSAL)
            ->where('status', CareBookingPaymentOperation::STATUS_SUCCEEDED)
            ->sum('amount_cents');
        $netCents = $transferredCents > 0
            ? max(0, $transferredCents - $reversedCents)
            : max(0, (int) $payment->caregiver_amount_cents);
        $fullyReversed = $transferredCents > 0 && $netCents === 0;
        $hasUnresolvedTransfer = $payment->operations()
            ->where('type', CareBookingPaymentOperation::TYPE_TRANSFER)
            ->whereIn('status', [
                CareBookingPaymentOperation::STATUS_PENDING,
                CareBookingPaymentOperation::STATUS_FAILED,
            ])
            ->exists();
        $settled = $fullyReversed || ($transferredCents > 0 && ! $hasUnresolvedTransfer) || $transferred;
        $transferIds = $payment->operations()
            ->where('type', CareBookingPaymentOperation::TYPE_TRANSFER)
            ->where('status', CareBookingPaymentOperation::STATUS_SUCCEEDED)
            ->pluck('stripe_object_id')
            ->filter()
            ->values()
            ->all();

        DB::transaction(function () use ($booking, $payment, $settled, $fullyReversed, $netCents, $transferIds): void {
            $item = CaregiverPayoutItem::query()->where('care_booking_id', $booking->id)->first();
            $payout = $item?->payout ?: CaregiverPayout::query()->create([
                'caregiver_user_id' => $booking->caregiver_user_id,
                'period_start_on' => ($booking->completed_at ?: now())->toDateString(),
                'period_end_on' => ($booking->completed_at ?: now())->toDateString(),
                'scheduled_for' => now(),
                'paid_at' => $settled ? now() : null,
                'status' => $settled ? CaregiverPayout::STATUS_PAID : CaregiverPayout::STATUS_PROCESSING,
                'currency' => strtoupper((string) $payment->currency),
                'gross_amount' => 0,
                'adjustments_amount' => 0,
                'net_amount' => 0,
                'provider_reference' => null,
                'notes' => 'Stripe balance transfers for '.$payment->financial_reference,
            ]);

            CaregiverPayoutItem::query()->updateOrCreate(
                ['care_booking_id' => $booking->id],
                [
                    'caregiver_payout_id' => $payout->id,
                    'caregiver_user_id' => $booking->caregiver_user_id,
                    'care_booking_payment_id' => $payment->id,
                    'financial_reference' => $payment->financial_reference,
                    'status' => $fullyReversed
                        ? CaregiverPayoutItem::STATUS_REVERSED
                        : ($settled ? CaregiverPayoutItem::STATUS_PAID : CaregiverPayoutItem::STATUS_SCHEDULED),
                    'currency' => strtoupper((string) $payment->currency),
                    'gross_amount' => round((int) $payment->caregiver_gross_amount_cents / 100, 2),
                    'processing_fee_amount' => round((int) $payment->stripe_processing_fee_cents / 100, 2),
                    'amount' => round($netCents / 100, 2),
                    'stripe_transfer_ids' => $transferIds,
                    'included_at' => now(),
                    'paid_at' => $settled ? now() : null,
                    'notes' => $fullyReversed
                        ? 'Stripe balance transfer fully reversed'
                        : ($settled
                            ? 'Earnings transferred to Stripe balance'
                            : 'Awaiting processing-fee finalization or Stripe balance transfer'),
                ],
            );

            $items = $payout->items()->with('payment')->get();
            $gross = (float) $items->sum(function (CaregiverPayoutItem $payoutItem): float {
                $firstRecordedGrossCents = $payoutItem->payment?->operations()
                    ->where('type', CareBookingPaymentOperation::TYPE_EARNING)
                    ->where('status', CareBookingPaymentOperation::STATUS_SUCCEEDED)
                    ->oldest('id')
                    ->value('amount_cents');

                return is_numeric($firstRecordedGrossCents)
                    ? ((int) $firstRecordedGrossCents / 100)
                    : (float) $payoutItem->gross_amount;
            });
            $net = (float) $items->sum('amount');
            $payout->forceFill([
                'status' => $settled ? CaregiverPayout::STATUS_PAID : CaregiverPayout::STATUS_PROCESSING,
                'paid_at' => $settled ? ($payout->paid_at ?: now()) : null,
                'gross_amount' => $gross,
                'adjustments_amount' => $net - $gross,
                'net_amount' => $net,
                'provider_reference' => $transferIds === [] ? null : implode(',', $transferIds),
            ])->save();
        });
    }

    private function chargeOperation(CareBookingPayment $payment, string $kind): ?CareBookingPaymentOperation
    {
        return $payment->operations()
            ->where('type', CareBookingPaymentOperation::TYPE_CHARGE)
            ->where('status', CareBookingPaymentOperation::STATUS_SUCCEEDED)
            ->where('metadata->kind', $kind)
            ->first();
    }

    private function pendingOperation(
        CareBookingPayment $payment,
        string $type,
        int $amountCents,
        string $idempotencyKey,
        ?CareBookingPaymentOperation $parent = null,
        array $metadata = [],
    ): CareBookingPaymentOperation {
        return CareBookingPaymentOperation::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            array_merge($this->baseOperationAttributes($payment), [
                'parent_operation_id' => $parent?->id,
                'type' => $type,
                'status' => CareBookingPaymentOperation::STATUS_PENDING,
                'amount_cents' => max(0, $amountCents),
                'stripe_parent_object_id' => $parent?->stripe_object_id,
                'metadata' => $metadata,
                'occurred_at' => now(),
            ]),
        );
    }

    /** @return array<string, mixed> */
    private function baseOperationAttributes(CareBookingPayment $payment): array
    {
        return [
            'care_booking_payment_id' => $payment->id,
            'care_booking_id' => $payment->care_booking_id,
            'family_account_id' => $payment->family_account_id,
            'financial_reference' => (string) $payment->financial_reference,
            'currency' => strtolower((string) $payment->currency),
        ];
    }

    /** @return array<string, mixed> */
    private function snapshotAttributes(CareBooking $booking, array $quote): array
    {
        return array_merge($this->paymentSnapshotAttributes($booking), [
            'worked_minutes' => (int) $quote['worked_minutes'],
            'family_care_amount_cents' => (int) $quote['family_care_amount_cents'],
            'family_processing_fee_cents' => (int) $quote['family_processing_fee_cents'],
            'caregiver_gross_amount_cents' => (int) $quote['caregiver_gross_amount_cents'],
            'platform_fee_cents' => (int) $quote['platform_fee_cents'],
        ]);
    }

    /** @return array<string, string> */
    private function stripeMetadata(CareBookingPayment $payment, string $operation): array
    {
        return [
            'care_booking_id' => (string) $payment->care_booking_id,
            'care_booking_payment_id' => (string) $payment->id,
            'financial_reference' => (string) $payment->financial_reference,
            'pricing_version' => (string) $payment->pricing_version,
            'operation' => $operation,
        ];
    }

    private function estimatedMinutes(CareBooking $booking): int
    {
        if ((int) $booking->expected_minutes > 0) {
            return (int) $booking->expected_minutes;
        }

        if ($booking->scheduled_start_at && $booking->scheduled_end_at) {
            return max(1, (int) $booking->scheduled_start_at->diffInMinutes($booking->scheduled_end_at, false));
        }

        return 60;
    }

    private function key(CareBookingPayment $payment, string $action, int $amountCents = 0): string
    {
        return 'payment-v2:'.$payment->id.':'.$action.':'.$amountCents;
    }
}
