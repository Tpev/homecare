<?php

namespace App\Support;

use App\Models\CareBooking;
use App\Models\CareRequest;
use App\Models\User;

class MarketplacePricing
{
    /**
     * @return array{pricing_version:string,family_care_rate_cents:int,family_processing_fee_rate_cents:int,caregiver_gross_rate_cents:int,caregiver_fee_policy:string,pricing_snapshotted_at:\Illuminate\Support\Carbon}
     */
    public function currentSnapshotAttributes(): array
    {
        return [
            'pricing_version' => $this->currentVersion(),
            'family_care_rate_cents' => $this->familyCareHourlyCents(),
            'family_processing_fee_rate_cents' => $this->familyProcessingFeeHourlyCents(),
            'caregiver_gross_rate_cents' => $this->caregiverGrossHourlyCents(),
            'caregiver_fee_policy' => (string) config(
                'marketplace.pricing_v2.caregiver_fee_policy',
                'successful_charge_balance_transaction'
            ),
            'pricing_snapshotted_at' => now(),
        ];
    }

    public function currentPricingEnabled(): bool
    {
        return (bool) config('marketplace.pricing_v2.enabled', true);
    }

    public function currentVersion(): string
    {
        return (string) config('marketplace.pricing_v2.version', '2026-08-v2');
    }

    public function familyCareHourlyCents(): int
    {
        return max(0, (int) config('marketplace.pricing_v2.family_care_hourly_cents', 3000));
    }

    public function familyProcessingFeeHourlyCents(): int
    {
        return max(0, (int) config('marketplace.pricing_v2.family_processing_fee_hourly_cents', 100));
    }

    public function caregiverGrossHourlyCents(): int
    {
        return max(0, (int) config('marketplace.pricing_v2.caregiver_gross_hourly_cents', 2700));
    }

    public function usesCurrentPricing(CareBooking $booking): bool
    {
        return (string) $booking->pricing_version === $this->currentVersion();
    }

    public function ensureCurrentSnapshot(CareBooking $booking): CareBooking
    {
        if ($this->usesCurrentPricing($booking)) {
            return $booking;
        }

        if (! $this->currentPricingEnabled()) {
            return $booking;
        }

        $booking->forceFill($this->currentSnapshotAttributes())->saveQuietly();

        return $booking->refresh();
    }

    /**
     * @return array{
     *   pricing_version:string,worked_minutes:int,hourly_rate:float,subtotal_cents:int,
     *   platform_fee_percent:float,platform_fee_cents:int,total_charge_cents:int,
     *   caregiver_amount_cents:int,family_care_rate_cents:int,
     *   family_processing_fee_rate_cents:int,caregiver_gross_rate_cents:int,
     *   family_care_amount_cents:int,family_processing_fee_cents:int,
     *   caregiver_gross_amount_cents:int,stripe_processing_fee_cents:int
     * }
     */
    public function currentQuoteForMinutes(int $workedMinutes, int $stripeProcessingFeeCents = 0): array
    {
        $workedMinutes = max(1, $workedMinutes);
        $familyCare = $this->prorateHourlyCents($this->familyCareHourlyCents(), $workedMinutes);
        $familyProcessingFee = $this->prorateHourlyCents($this->familyProcessingFeeHourlyCents(), $workedMinutes);
        $caregiverGross = $this->prorateHourlyCents($this->caregiverGrossHourlyCents(), $workedMinutes);
        $stripeProcessingFeeCents = max(0, $stripeProcessingFeeCents);
        $total = $familyCare + $familyProcessingFee;
        $platformMargin = max(0, $total - $caregiverGross);

        return [
            'pricing_version' => $this->currentVersion(),
            'worked_minutes' => $workedMinutes,
            'hourly_rate' => $this->familyCareHourlyCents() / 100,
            'subtotal_cents' => $familyCare,
            'platform_fee_percent' => 0.0,
            'platform_fee_cents' => $platformMargin,
            'total_charge_cents' => $total,
            'caregiver_amount_cents' => max(0, $caregiverGross - $stripeProcessingFeeCents),
            'family_care_rate_cents' => $this->familyCareHourlyCents(),
            'family_processing_fee_rate_cents' => $this->familyProcessingFeeHourlyCents(),
            'caregiver_gross_rate_cents' => $this->caregiverGrossHourlyCents(),
            'family_care_amount_cents' => $familyCare,
            'family_processing_fee_cents' => $familyProcessingFee,
            'caregiver_gross_amount_cents' => $caregiverGross,
            'stripe_processing_fee_cents' => $stripeProcessingFeeCents,
        ];
    }

    /**
     * @return array<string, int|float|string>
     */
    public function quoteForCurrentBooking(CareBooking $booking, int $workedMinutes, int $stripeProcessingFeeCents = 0): array
    {
        $workedMinutes = max(1, $workedMinutes);
        $familyCareRate = max(0, (int) ($booking->family_care_rate_cents ?? $this->familyCareHourlyCents()));
        $familyFeeRate = max(0, (int) ($booking->family_processing_fee_rate_cents ?? $this->familyProcessingFeeHourlyCents()));
        $caregiverGrossRate = max(0, (int) ($booking->caregiver_gross_rate_cents ?? $this->caregiverGrossHourlyCents()));
        $familyCare = $this->prorateHourlyCents($familyCareRate, $workedMinutes);
        $familyFee = $this->prorateHourlyCents($familyFeeRate, $workedMinutes);
        $caregiverGross = $this->prorateHourlyCents($caregiverGrossRate, $workedMinutes);
        $total = $familyCare + $familyFee;
        $stripeProcessingFeeCents = max(0, $stripeProcessingFeeCents);

        return [
            'pricing_version' => (string) ($booking->pricing_version ?: $this->currentVersion()),
            'worked_minutes' => $workedMinutes,
            'hourly_rate' => $familyCareRate / 100,
            'subtotal_cents' => $familyCare,
            'platform_fee_percent' => 0.0,
            'platform_fee_cents' => max(0, $total - $caregiverGross),
            'total_charge_cents' => $total,
            'caregiver_amount_cents' => max(0, $caregiverGross - $stripeProcessingFeeCents),
            'family_care_rate_cents' => $familyCareRate,
            'family_processing_fee_rate_cents' => $familyFeeRate,
            'caregiver_gross_rate_cents' => $caregiverGrossRate,
            'family_care_amount_cents' => $familyCare,
            'family_processing_fee_cents' => $familyFee,
            'caregiver_gross_amount_cents' => $caregiverGross,
            'stripe_processing_fee_cents' => $stripeProcessingFeeCents,
        ];
    }

    public function prorateHourlyCents(int $hourlyCents, int $minutes): int
    {
        return (int) round((max(0, $hourlyCents) * max(0, $minutes)) / 60, 0, PHP_ROUND_HALF_UP);
    }

    /**
     * @return array<string, array{label:string,rate:float}>
     */
    public function tiers(): array
    {
        /** @var array<string, array{label:string,rate:float}> $tiers */
        $tiers = config('marketplace.pricing_tiers', []);

        return $tiers;
    }

    public function defaultTier(): string
    {
        $default = (string) config('marketplace.default_pricing_tier', 'standard');

        if (array_key_exists($default, $this->tiers())) {
            return $default;
        }

        return array_key_first($this->tiers()) ?? 'standard';
    }

    public function normalizeTier(?string $tier): string
    {
        $tier = (string) $tier;

        if ($tier !== '' && array_key_exists($tier, $this->tiers())) {
            return $tier;
        }

        return $this->defaultTier();
    }

    public function rateForTier(?string $tier): float
    {
        $tier = $this->normalizeTier($tier);
        $rate = data_get($this->tiers(), $tier.'.rate');

        return is_numeric($rate) ? (float) $rate : 30.00;
    }

    public function labelForTier(?string $tier): string
    {
        $tier = $this->normalizeTier($tier);

        return (string) data_get($this->tiers(), $tier.'.label', ucfirst($tier));
    }

    /**
     * @return array<string, mixed>
     */
    public function familyPricingOverrideForEmail(?string $email): array
    {
        $email = strtolower(trim((string) $email));
        if ($email === '') {
            return [];
        }

        $overrides = config('marketplace.family_pricing_overrides', []);
        if (! is_array($overrides)) {
            return [];
        }

        foreach ($overrides as $configuredEmail => $override) {
            if (
                strtolower(trim((string) $configuredEmail)) === $email
                && is_array($override)
            ) {
                return $override;
            }
        }

        return [];
    }

    public function hourlyRateForFamilyEmail(?string $email, float $fallback): float
    {
        $overrideRate = data_get($this->familyPricingOverrideForEmail($email), 'hourly_rate');

        if (is_numeric($overrideRate) && (float) $overrideRate > 0) {
            return round((float) $overrideRate, 2);
        }

        return round(max(0, $fallback), 2);
    }

    public function hourlyRateForFamily(?User $family, float $fallback): float
    {
        return $this->hourlyRateForFamilyEmail($family?->email, $fallback);
    }

    public function hourlyRateForRequest(CareRequest $request, float $fallback): float
    {
        return $this->hourlyRateForFamily($request->family, $fallback);
    }

    public function hourlyRateForBooking(CareBooking $booking, float $fallback): float
    {
        return $this->hourlyRateForFamily($booking->family, $fallback);
    }

    public function platformFeePercentForFamilyEmail(?string $email, float $fallback): float
    {
        $override = $this->familyPricingOverrideForEmail($email);

        if (array_key_exists('platform_fee_percent', $override)) {
            return max(0, (float) $override['platform_fee_percent']);
        }

        return max(0, $fallback);
    }

    public function platformFeePercentForFamily(?User $family, float $fallback): float
    {
        return $this->platformFeePercentForFamilyEmail($family?->email, $fallback);
    }

    public function platformFeePercentForBooking(CareBooking $booking, float $fallback): float
    {
        return $this->platformFeePercentForFamily($booking->family, $fallback);
    }
}
