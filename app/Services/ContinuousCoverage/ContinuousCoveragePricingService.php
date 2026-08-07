<?php

namespace App\Services\ContinuousCoverage;

use App\Models\CareBooking;
use App\Models\CareRequestApplication;
use App\Models\ContinuousCoveragePlan;
use App\Models\ContinuousCoverageShift;
use App\Models\User;
use App\Services\Payments\BookingPaymentService;
use Illuminate\Validation\ValidationException;

class ContinuousCoveragePricingService
{
    public function __construct(
        private readonly BookingPaymentService $payments,
    ) {}

    /**
     * Build a read-only estimate through the existing canonical booking quote.
     * No booking, payment record, or external payment request is created.
     *
     * @return array{worked_minutes:int,hourly_rate:float,subtotal_cents:int,platform_fee_percent:float,platform_fee_cents:int,total_charge_cents:int,caregiver_amount_cents:int}
     */
    public function quoteForPlan(
        ContinuousCoveragePlan $plan,
        User $caregiver,
        int $workedMinutes,
    ): array {
        $plan->loadMissing('family');
        $caregiver->loadMissing('caregiverProfile');

        $application = new CareRequestApplication([
            'caregiver_user_id' => $caregiver->id,
            'proposed_rate' => (float) $plan->hourly_rate,
        ]);
        $booking = new CareBooking([
            'family_account_id' => $plan->family_account_id,
            'family_user_id' => $plan->family_user_id,
            'caregiver_user_id' => $caregiver->id,
            'expected_minutes' => max(1, $workedMinutes),
        ]);
        $booking->setRelation('application', $application);
        $booking->setRelation('family', $plan->family);
        $booking->setRelation('caregiver', $caregiver);

        return $this->payments->quoteForWorkedMinutes($booking, $workedMinutes);
    }

    /**
     * @return array{worked_minutes:int,hourly_rate:float,subtotal_cents:int,platform_fee_percent:float,platform_fee_cents:int,total_charge_cents:int,caregiver_amount_cents:int}
     */
    public function quoteForShift(ContinuousCoverageShift $shift, ?User $caregiver = null): array
    {
        $shift->loadMissing([
            'plan.family',
            'assignedCaregiver.caregiverProfile',
            'booking.application',
            'booking.family',
            'booking.caregiver.caregiverProfile',
        ]);

        if ($shift->booking) {
            return $this->payments->quoteForWorkedMinutes($shift->booking, $shift->scheduled_minutes);
        }

        $caregiver ??= $shift->assignedCaregiver;
        if (! $caregiver) {
            throw ValidationException::withMessages([
                'shift' => 'A caregiver is required before estimating this shift.',
            ]);
        }

        return $this->quoteForPlan($shift->plan, $caregiver, $shift->scheduled_minutes);
    }

    public function caregiverEarningsLabel(
        ContinuousCoveragePlan $plan,
        User $caregiver,
        int $workedMinutes,
    ): string {
        $quote = $this->quoteForPlan($plan, $caregiver, $workedMinutes);

        return '$'.number_format($quote['caregiver_amount_cents'] / 100, 2)
            .' estimated for '.$this->duration($workedMinutes);
    }

    private function duration(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return trim(($hours ? $hours.' hour'.($hours === 1 ? '' : 's') : '')
            .($remaining ? ' '.$remaining.' minutes' : ''));
    }
}
