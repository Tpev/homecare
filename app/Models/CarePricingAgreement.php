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
