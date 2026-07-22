<?php

namespace App\Services\RegularCare;

use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use Illuminate\Support\Carbon;

class CareBookingCheckInPolicy
{
    /**
     * @return array{allowed:bool,reason:?string,code:string}
     */
    public function evaluate(?CareBooking $booking, ?Carbon $at = null): array
    {
        if (! $booking || $booking->status !== CareBooking::STATUS_SCHEDULED) {
            return ['allowed' => false, 'reason' => 'This visit is not ready to start.', 'code' => 'not_scheduled'];
        }

        if (! $booking->caregiver_terms_accepted_at) {
            return ['allowed' => false, 'reason' => 'Accept the visit agreement before check-in.', 'code' => 'agreement'];
        }

        if (! $booking->care_plan_id || $booking->hasCheckInOverride()) {
            return ['allowed' => true, 'reason' => null, 'code' => $booking->hasCheckInOverride() ? 'admin_override' : 'one_time'];
        }

        $at ??= now();
        if ($booking->checkInWindowOpensAt() && $at->lt($booking->checkInWindowOpensAt())) {
            return [
                'allowed' => false,
                'reason' => 'Check-in opens at '.$booking->checkInWindowOpensAt()->format('g:i A').' on the visit day.',
                'code' => 'too_early',
            ];
        }

        if ($booking->checkInWindowClosesAt() && $at->gt($booking->checkInWindowClosesAt())) {
            return [
                'allowed' => false,
                'reason' => 'The check-in window has closed. Contact LoLo support for help.',
                'code' => 'too_late',
            ];
        }

        $payment = $booking->payment;
        $protected = $payment && in_array($payment->status, [
            CareBookingPayment::STATUS_AUTHORIZED,
            CareBookingPayment::STATUS_CAPTURED,
            CareBookingPayment::STATUS_TRANSFERRED,
            CareBookingPayment::STATUS_TRANSFER_FAILED,
            CareBookingPayment::STATUS_PARTIALLY_REFUNDED,
        ], true) && ! ($payment->status === CareBookingPayment::STATUS_AUTHORIZED
            && $payment->authorization_expires_at
            && $payment->authorization_expires_at->isPast());

        if (! $protected) {
            return [
                'allowed' => false,
                'reason' => 'This visit is waiting for family payment confirmation. Do not start care yet.',
                'code' => 'payment_not_protected',
            ];
        }

        return ['allowed' => true, 'reason' => null, 'code' => 'ready'];
    }
}
