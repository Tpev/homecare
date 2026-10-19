<?php

namespace App\Services\Payments;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CareBookingPaymentOperation;
use Illuminate\Support\Str;

/** Financial boundaries for the audited October 17 visit reassignment. */
class Oct17RecoveryPaymentGuard
{
    public const PAYMENT_INTENT_ID = 'pi_3URf2A6ewFB76RHF0YL59lIw';

    public const TARGET_CHARGE_CENTS = 36012;

    public static function assertNormalFlowAllowed(CareBooking $booking): void
    {
        // Callers, including an already-running scheduler, can hold stale relations.
        $payment = CareBookingPayment::query()->where('care_booking_id', $booking->id)->first();
        if (! $payment) {
            return;
        }
        self::assertNormalPaymentFlowAllowed($payment);
    }

    public static function assertNormalPaymentFlowAllowed(CareBookingPayment $payment): void
    {
        self::assertSourceNotReassigned($payment);
        if (self::isActiveTarget($payment)) {
            throw new PaymentException('This visit is being reconciled through its reviewed October 17 recovery. Resume that recovery instead.');
        }
    }

    public static function isActiveTarget(CareBookingPayment $payment): bool
    {
        return data_get($payment->metadata, 'oct17_payment_recovery') !== null
            && data_get($payment->metadata, 'oct17_payment_recovery.state') !== 'complete';
    }

    public static function assertSourceNotReassigned(CareBookingPayment $payment): void
    {
        if (data_get($payment->metadata, 'oct17_visit_reassignment') !== null) {
            throw new PaymentException('This canceled visit is not billable. Its recorded work was reassigned through the audited October 17 recovery.');
        }
    }

    /** Called with fresh booking/payment rows under the V2 payment lock. */
    public static function assertV2PaymentAllowed(CareBooking $booking, CareBookingPayment $payment): void
    {
        self::assertSourceNotReassigned($payment);
        if (! self::isActiveTarget($payment)) {
            return;
        }
        $receipt = (array) data_get($payment->metadata, 'oct17_payment_recovery', []);
        if (! in_array($receipt['state'] ?? null, ['reconciled', 'capture_unknown'], true)
            || ! Str::isUuid((string) ($receipt['request_id'] ?? ''))
            || ($receipt['assignment_confirmed'] ?? null) !== true
            || ($receipt['approval_basis'] ?? null) !== 'auto-approval'
            || (int) ($receipt['target_charge_cents'] ?? 0) !== self::TARGET_CHARGE_CENTS
            || (int) ($receipt['worked_minutes'] ?? 0) !== 697
            || ($receipt['payment_intent_id'] ?? null) !== self::PAYMENT_INTENT_ID
            || empty($receipt['reconciled_at']) || empty($receipt['provider_verified_at'])) {
            throw new PaymentException('The October 17 recovery has not finished its reviewed reassignment and provider checks.');
        }
        if ((int) $booking->id !== 311 || (int) $booking->care_request_id !== 317
            || (int) $booking->family_account_id !== 3 || (int) $booking->family_user_id !== 335
            || (int) $booking->caregiver_user_id !== 349 || $booking->care_plan_id
            || (int) $payment->id !== 175 || (int) $payment->care_booking_id !== 311
            || (int) $payment->family_account_id !== 3 || (int) $payment->family_user_id !== 335
            || (int) $payment->caregiver_user_id !== 349
            || $payment->stripe_payment_intent_id !== self::PAYMENT_INTENT_ID
            || $payment->currency !== 'usd' || (int) $payment->amount_authorized_cents !== 42780
            || ! in_array((int) $payment->amount_captured_cents, [0, self::TARGET_CHARGE_CENTS], true)
            || (int) $payment->worked_minutes !== 697
            || (int) $payment->family_care_amount_cents !== 34850
            || (int) $payment->family_processing_fee_cents !== 1162
            || (int) $payment->caregiver_gross_amount_cents !== 31365
            || $booking->status !== CareBooking::STATUS_COMPLETED || (int) $booking->worked_minutes !== 697
            || (int) $booking->total_paused_seconds !== 0 || $booking->paused_at
            || $booking->cancelled_at || $booking->dispute_opened_at || $booking->dispute_status !== 'none'
            || $booking->no_show_flag || $booking->late_cancel_flag
            || $booking->pricing_version !== '2026-08-v2' || $booking->pricing_agreement_id
            || $payment->pricing_version !== $booking->pricing_version
            || (int) $booking->family_care_rate_cents !== 3000 || (int) $payment->family_care_rate_cents !== 3000
            || (int) $booking->family_processing_fee_rate_cents !== 100 || (int) $payment->family_processing_fee_rate_cents !== 100
            || (int) $booking->caregiver_gross_rate_cents !== 2700 || (int) $payment->caregiver_gross_rate_cents !== 2700
            || $booking->caregiver_fee_policy !== 'successful_charge_balance_transaction'
            || $payment->caregiver_fee_policy !== $booking->caregiver_fee_policy
            || $booking->timeCorrections()->exists() || $booking->corrections()->exists() || $booking->changeRequests()->exists()
            || $payment->hasPrepaidVisitHold() || (int) $payment->amount_refunded_cents !== 0
            || (int) $payment->amount_overage_cents !== 0 || (int) $payment->overage_pending_cents !== 0
            || $payment->stripe_overage_payment_intent_id || $payment->stripe_overage_charge_id
            || $payment->stripe_last_refund_id || $payment->stripe_last_transfer_reversal_id
            || $payment->operations()->whereIn('type', [CareBookingPaymentOperation::TYPE_REFUND,
                CareBookingPaymentOperation::TYPE_TRANSFER_REVERSAL, CareBookingPaymentOperation::TYPE_DISPUTE])->exists()) {
            throw new PaymentException('The reviewed October 17 visit or payment changed. Stop and reconcile the recovery.');
        }
        foreach (['started_at' => '2026-10-17 19:30:56', 'completed_at' => '2026-10-18 07:08:49',
            'timesheet_submitted_at' => '2026-10-18 07:08:49', 'scheduled_start_at' => '2026-10-17 19:30:00',
            'scheduled_end_at' => '2026-10-18 07:00:00'] as $field => $expected) {
            if ($booking->$field?->copy()->setTimezone('America/New_York')->format('Y-m-d H:i:s') !== $expected) {
                throw new PaymentException('The reviewed October 17 times changed. Stop and reconcile the recovery.');
            }
        }
        if (! $booking->timesheet_submitted_at->lte(now()->subHours(24))) {
            throw new PaymentException('The October 17 timesheet approval window has not elapsed.');
        }

        // Do not lock the earlier source booking after holding target 311: the recovery
        // locks both bookings in ascending order. Its durable marker blocks source money.
        $source = CareBooking::query()->find(244);
        $sourcePayment = CareBookingPayment::query()->find(126);
        $assignment = (array) data_get($sourcePayment?->metadata, 'oct17_visit_reassignment', []);
        if (! $source || ! $sourcePayment || $source->status !== CareBooking::STATUS_CANCELLED
            || (int) $source->care_request_id !== 238 || (int) $source->family_account_id !== 3
            || (int) $source->family_user_id !== 335 || (int) $source->caregiver_user_id !== 349
            || (int) $sourcePayment->care_booking_id !== 244 || (int) $sourcePayment->amount_captured_cents !== 0
            || $sourcePayment->operations()->whereIn('type', [CareBookingPaymentOperation::TYPE_CHARGE,
                CareBookingPaymentOperation::TYPE_TRANSFER, CareBookingPaymentOperation::TYPE_REFUND,
                CareBookingPaymentOperation::TYPE_TRANSFER_REVERSAL, CareBookingPaymentOperation::TYPE_DISPUTE])->exists()
            || ($assignment['request_id'] ?? null) !== $receipt['request_id']
            || (int) ($assignment['source_booking_id'] ?? 0) !== 244
            || (int) ($assignment['target_booking_id'] ?? 0) !== 311) {
            throw new PaymentException('The canceled source visit no longer matches the October 17 reassignment receipt.');
        }
        self::assertTargetLedger($payment);
    }

    public static function assertTargetLedger(CareBookingPayment $payment): void
    {
        $charges = $payment->operations()->where('type', CareBookingPaymentOperation::TYPE_CHARGE)->get();
        if ($charges->isNotEmpty() && ($charges->count() !== 1
            || $charges->first()->status !== CareBookingPaymentOperation::STATUS_SUCCEEDED
            || (int) $charges->first()->amount_cents !== self::TARGET_CHARGE_CENTS
            || $charges->first()->stripe_parent_object_id !== self::PAYMENT_INTENT_ID
            || data_get($charges->first()->metadata, 'kind') !== 'primary')) {
            throw new PaymentException('The October 17 charge ledger does not match the single reviewed payment.');
        }
    }

    public static function assertTargetChargeResult(CareBookingPayment $payment, array $result): void
    {
        if (($result['id'] ?? null) !== self::PAYMENT_INTENT_ID
            || ($result['status'] ?? null) !== 'succeeded'
            || (int) ($result['amount_received'] ?? 0) !== self::TARGET_CHARGE_CENTS
            || empty($result['latest_charge_id'])) {
            throw new PaymentException('Stripe has not confirmed the reviewed October 17 charge of USD 360.12.');
        }
        $charge = CareBookingPaymentOperation::query()->where('type', CareBookingPaymentOperation::TYPE_CHARGE)
            ->where('stripe_object_id', $result['latest_charge_id'])->first();
        if ($charge && ((int) $charge->care_booking_payment_id !== $payment->id
            || $charge->status !== CareBookingPaymentOperation::STATUS_SUCCEEDED
            || (int) $charge->amount_cents !== self::TARGET_CHARGE_CENTS
            || $charge->stripe_parent_object_id !== self::PAYMENT_INTENT_ID
            || data_get($charge->metadata, 'kind') !== 'primary')) {
            throw new PaymentException('The October 17 Stripe charge conflicts with an existing ledger receipt.');
        }
        if (! empty($result['balance_transaction_id'])) {
            $fee = CareBookingPaymentOperation::query()->where('type', CareBookingPaymentOperation::TYPE_PROCESSING_FEE)
                ->where('stripe_object_id', $result['balance_transaction_id'])->first();
            if ($fee && ((int) $fee->care_booking_payment_id !== $payment->id
                || $fee->stripe_parent_object_id !== $result['latest_charge_id'])) {
                throw new PaymentException('The October 17 processing fee conflicts with an existing charge.');
            }
        }
    }
}
