<?php

namespace App\Support;

use App\Models\CareBooking;
use App\Models\CareRequest;
use App\Models\User;

class MarketplacePricing
{
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
