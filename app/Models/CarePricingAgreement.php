<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarePricingAgreement extends Model
{
    public const PLATFORM_PAYS_PROCESSING = 'platform_pays_processing';

    public const CARE_ASSISTANCE = 'care_assistance';

    protected $guarded = ['id'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(CareAssistanceApplication::class, 'care_assistance_application_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function endedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by_user_id');
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caregiver_user_id');
    }

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'all_caregivers' => 'boolean',
            'family_care_rate_cents' => 'integer',
            'family_processing_fee_rate_cents' => 'integer',
            'caregiver_gross_rate_cents' => 'integer',
            'ended_at' => 'datetime',
        ];
    }

    public function appliesToBooking(CareBooking $booking): bool
    {
        // Pair agreements retain their original cutoff; account-wide agreements cover every new or unpaid visit.
        return $this->program === self::CARE_ASSISTANCE || $this->all_caregivers || ! $booking->getKey() || (int) $booking->getKey() >= (int) $this->source_booking_id;
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
