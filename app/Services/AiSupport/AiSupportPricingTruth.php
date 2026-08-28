<?php

namespace App\Services\AiSupport;

class AiSupportPricingTruth
{
    public const FAMILY_CARE_HOURLY_CENTS = 3000;

    public const FAMILY_PROCESSING_FEE_HOURLY_CENTS = 0;

    public const FAMILY_TOTAL_HOURLY_CENTS = 3000;

    public const CAREGIVER_HOURLY_CENTS = 2700;

    public const PLATFORM_HOURLY_CENTS = 300;

    public function isPricingQuestion(string $message): bool
    {
        return preg_match(
            '/\b(?:price|pricing|rate|cost|costs|fee|fees|platform portion|earn|earns|earning|earnings|make per hour|paid per hour|caregiver pay|family (?:pay|pays)|tax|taxes|tip|tips|mileage|holiday charge|surcharge)\b/i',
            $message,
        ) === 1;
    }

    public function answer(string $role, string $message): string
    {
        return $role === 'caregiver'
            ? $this->caregiverAnswer($message)
            : $this->familyAnswer($message);
    }

    public function familyAnswer(string $message): string
    {
        $answer = 'Care costs $30 per hour total for the Family. The caregiver earns $27 per hour gross, minus the actual Stripe processing fees on successful family charges, and LoLo’s gross platform portion is $3 per hour. Refund costs, dispute fees, and optional instant-payout fees are not deducted from the caregiver rate.'
            .$this->additionalChargeBoundary($message);
        $minutes = $this->explicitMinutes($message);
        if ($minutes === null) {
            return $answer;
        }

        $duration = $minutes % 60 === 0
            ? ($minutes / 60).' hour'.($minutes === 60 ? '' : 's')
            : ($minutes < 60 ? $minutes.' minutes' : intdiv($minutes, 60).' hr '.($minutes % 60).' min');

        return $answer.' For '.$duration.', the Family total is '
            .$this->amount(self::FAMILY_TOTAL_HOURLY_CENTS, $minutes)
            .', caregiver gross earnings are '
            .$this->amount(self::CAREGIVER_HOURLY_CENTS, $minutes).' before actual Stripe processing fees, and LoLo’s gross platform portion is '
            .$this->amount(self::PLATFORM_HOURLY_CENTS, $minutes).'.';
    }

    private function caregiverAnswer(string $message): string
    {
        $answer = 'The caregiver earns $27 per hour gross, minus actual Stripe processing fees on successful family charges. The Family pays $30 per hour total, and LoLo’s gross platform portion is $3 per hour. Refund costs, dispute fees, and optional instant-payout fees are not deducted from the caregiver rate.'
            .$this->additionalChargeBoundary($message);
        $minutes = $this->explicitMinutes($message);
        if ($minutes === null) {
            return $answer;
        }

        $duration = $minutes % 60 === 0
            ? ($minutes / 60).' hour'.($minutes === 60 ? '' : 's')
            : ($minutes < 60 ? $minutes.' minutes' : intdiv($minutes, 60).' hr '.($minutes % 60).' min');

        return $answer.' For '.$duration.', caregiver earnings are '
            .$this->amount(self::CAREGIVER_HOURLY_CENTS, $minutes).' gross before actual Stripe processing fees, the Family total is '
            .$this->amount(self::FAMILY_TOTAL_HOURLY_CENTS, $minutes).', and LoLo’s gross platform portion is '
            .$this->amount(self::PLATFORM_HOURLY_CENTS, $minutes).'.';
    }

    private function explicitMinutes(string $message): ?int
    {
        if (preg_match('/\b(\d{1,3}(?:\.\d{1,2})?)\s*(?:hours?|hrs?)\b/i', $message, $match) === 1) {
            $minutes = (int) round(((float) $match[1]) * 60);
        } elseif (preg_match('/\b(one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve)\s*[- ]\s*hours?\b/i', $message, $match) === 1) {
            $hours = array_search(mb_strtolower($match[1]), [
                1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six',
                7 => 'seven', 8 => 'eight', 9 => 'nine', 10 => 'ten', 11 => 'eleven', 12 => 'twelve',
            ], true);
            $minutes = ((int) $hours) * 60;
        } elseif (preg_match('/\b(\d{1,4})\s*(?:minutes?|mins?)\b/i', $message, $match) === 1) {
            $minutes = (int) $match[1];
        } else {
            return null;
        }

        return $minutes >= 1 && $minutes <= (168 * 60) ? $minutes : null;
    }

    private function additionalChargeBoundary(string $message): string
    {
        return preg_match('/\b(?:tax|taxes|tip|tips|mileage|holiday charge|surcharge)\b/i', $message) === 1
            ? ' For support calculations, I do not add taxes, tips, mileage, holiday charges, or surcharges.'
            : '';
    }

    private function amount(int $hourlyCents, int $minutes): string
    {
        return '$'.number_format(($hourlyCents * $minutes) / 6000, 2);
    }
}
