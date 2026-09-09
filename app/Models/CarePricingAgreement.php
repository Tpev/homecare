<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CarePricingAgreement extends Model
{
    public const PLATFORM_PAYS_PROCESSING = 'platform_pays_processing';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'family_care_rate_cents' => 'integer',
            'family_processing_fee_rate_cents' => 'integer',
            'caregiver_gross_rate_cents' => 'integer',
        ];
    }

    public function appliesToBooking(CareBooking $booking): bool
    {
        // The source visit is also the inclusive booking-ID boundary; unsaved visits are new care.
        return ! $booking->getKey() || (int) $booking->getKey() >= (int) $this->source_booking_id;
    }

    /** @return array<string, int|string> */
    public function snapshotAttributes(): array
    {
        return [
            'pricing_agreement_id' => $this->id,
            'family_care_rate_cents' => $this->family_care_rate_cents,
            'family_processing_fee_rate_cents' => $this->family_processing_fee_rate_cents,
            'caregiver_gross_rate_cents' => $this->caregiver_gross_rate_cents,
            'caregiver_fee_policy' => $this->caregiver_fee_policy,
        ];
    }
}
