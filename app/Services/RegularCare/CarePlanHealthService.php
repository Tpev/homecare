<?php

namespace App\Services\RegularCare;

use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CarePlan;

class CarePlanHealthService
{
    public function reconcileForBooking(CareBooking $booking): ?CarePlan
    {
        if (! $booking->care_plan_id) {
            return null;
        }

        $plan = CarePlan::query()->find($booking->care_plan_id);

        return $plan ? $this->reconcile($plan) : null;
    }

    public function reconcile(CarePlan $plan): CarePlan
    {
        $nextBooking = $plan->generatedBookings()
            ->whereIn('status', [
                CareBooking::STATUS_SCHEDULED,
                CareBooking::STATUS_IN_PROGRESS,
                CareBooking::STATUS_PAUSED,
            ])
            ->orderByRaw("CASE WHEN status IN ('in_progress', 'paused') THEN 0 ELSE 1 END")
            ->orderBy('scheduled_start_at')
            ->first();

        $payment = $nextBooking?->payment;
        $paymentStatus = CarePlan::PAYMENT_UNCHECKED;
        $lastError = null;

        if ($payment) {
            if (in_array($payment->status, [
                CareBookingPayment::STATUS_AUTHORIZED,
                CareBookingPayment::STATUS_CAPTURED,
                CareBookingPayment::STATUS_TRANSFERRED,
                CareBookingPayment::STATUS_TRANSFER_FAILED,
                CareBookingPayment::STATUS_PARTIALLY_REFUNDED,
                CareBookingPayment::STATUS_REFUNDED,
            ], true)) {
                $paymentStatus = CarePlan::PAYMENT_AUTHORIZED;
            } elseif (in_array($payment->status, [
                CareBookingPayment::STATUS_AUTHORIZATION_REQUIRED,
                CareBookingPayment::STATUS_REAUTH_REQUIRED,
                CareBookingPayment::STATUS_FAILED,
            ], true)) {
                $paymentStatus = CarePlan::PAYMENT_ACTION_REQUIRED;
                $lastError = $payment->last_error;
            }
        }

        $status = $plan->status;
        if (in_array($status, [CarePlan::STATUS_ACTIVE, CarePlan::STATUS_PAYMENT_ATTENTION], true)) {
            $status = $paymentStatus === CarePlan::PAYMENT_ACTION_REQUIRED
                ? CarePlan::STATUS_PAYMENT_ATTENTION
                : CarePlan::STATUS_ACTIVE;
        }

        $plan->forceFill([
            'next_booking_id' => $nextBooking?->id,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'last_error' => $lastError,
        ])->save();

        return $plan->fresh(['nextBooking.payment']);
    }
}
