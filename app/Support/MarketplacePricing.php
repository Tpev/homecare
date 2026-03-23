<?php

namespace App\Support;

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
}
