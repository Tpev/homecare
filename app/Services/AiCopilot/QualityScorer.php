<?php

namespace App\Services\AiCopilot;

use Illuminate\Support\Arr;

class QualityScorer
{
    /**
     * @param  array<string,mixed>  $draft
     * @param  array<int,string>  $missingRequired
     */
    public function score(array $draft, array $missingRequired): int
    {
        $requiredTotal = 15; // Typical required footprint across one_time/recurring.
        $requiredDone = max(0, $requiredTotal - count($missingRequired));
        $requiredPercent = (int) round(($requiredDone / $requiredTotal) * 80);

        $bonusChecks = [
            'address_line2',
            'recipient.date_of_birth',
            'recipient.gender',
            'recipient.mobility_level',
            'third_party_contact.email',
        ];
        $bonusDone = collect($bonusChecks)
            ->filter(fn (string $path) => $this->hasValue($draft, $path))
            ->count();
        $bonusPercent = (int) round(($bonusDone / count($bonusChecks)) * 20);

        return min(100, $requiredPercent + $bonusPercent);
    }

    /**
     * @param  array<string,mixed>  $draft
     */
    private function hasValue(array $draft, string $path): bool
    {
        $value = Arr::get($draft, $path);
        if (is_array($value)) {
            return $value !== [];
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return $value !== null;
    }
}

