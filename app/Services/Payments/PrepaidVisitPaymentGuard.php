<?php

namespace App\Services\Payments;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use Illuminate\Support\Carbon;

/** A held payment may record family approval, but cannot move money. */
class PrepaidVisitPaymentGuard
{
    public static function assertActualVisitCompleted(CareBooking $booking, CareBookingPayment $payment): void
    {
        $reopenedAt = Carbon::parse((string) data_get($payment->metadata, 'prepaid_visit_recovery.reopened_at'));
        $elapsedSeconds = $booking->started_at && $booking->completed_at
            ? (int) $booking->started_at->diffInSeconds($booking->completed_at, false) : 0;
        $pausedSeconds = (int) $booking->total_paused_seconds;
        if (! in_array($booking->status, [CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED], true)
            || ! $booking->started_at || $booking->started_at->lt($reopenedAt)
            || ! $booking->completed_at || $booking->completed_at->lte($booking->started_at)
            || $booking->completed_at->isFuture()
            || ! $booking->timesheet_submitted_at || $booking->timesheet_submitted_at->lt($booking->completed_at)
            || $booking->timesheet_submitted_at->isFuture() || $booking->paused_at
            || $pausedSeconds < 0 || $pausedSeconds >= $elapsedSeconds
            || (int) $booking->worked_minutes <= 0
            || (int) $booking->worked_minutes !== (int) floor(($elapsedSeconds - $pausedSeconds) / 60)) {
            throw new PaymentException('This prepaid visit must be completed again before its hours can be approved. No payment was taken.');
        }
    }

    public static function assertNotHeld(CareBookingPayment $payment): void
    {
        if ($payment->hasPrepaidVisitHold()) {
            throw new PaymentException('This prepaid visit is held for LoLo review. No charge, refund, or transfer was made.');
        }
    }
}
