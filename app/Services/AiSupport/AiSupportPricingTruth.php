<?php

namespace App\Services\AiSupport;

use App\Support\MarketplacePricing;

class AiSupportPricingTruth
{
    public function __construct(private readonly MarketplacePricing $pricing) {}

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
        return $this->defaultPricingAnswer($message, false);
    }

    private function caregiverAnswer(string $message): string
    {
        return $this->defaultPricingAnswer($message, true);
    }

    private function defaultPricingAnswer(string $message, bool $caregiver): string
    {
        if (! $this->pricing->currentPricingEnabled()
            || preg_match('/\b(?:my|our|booking|request|agreement|receipt|charged|paid|refund|refunded|historical|last|previous|past)\b/i', $message)) {
            return 'For specific care, open the exact request or visit review. Its pricing agreement, booking snapshot and payment ledger determine the care rate, Family processing fee and total. Current default rates do not replace those terms. Exact caregiver earnings and processing deductions come from the payment ledger.'
                .$this->additionalChargeBoundary($message);
        }
        $hour = $this->pricing->currentQuoteForMinutes(60);
        $answer = 'Current default pricing is '.$this->amount($hour['family_care_amount_cents']).' care plus '
            .$this->amount($hour['family_processing_fee_cents']).' Family processing fee per hour, for a Family total of '
            .$this->amount($hour['total_charge_cents']).' per hour.';
        if ($caregiver) {
            $answer .= ' Default caregiver gross earnings are '.$this->amount($hour['caregiver_gross_amount_cents']).' per hour; exact net and processing deductions come from the payment ledger.';
        }
        $minutes = $this->explicitMinutes($message);
        if ($minutes !== null) {
            $quote = $this->pricing->currentQuoteForMinutes($minutes);
            $answer .= ' At these defaults for '.$minutes.' minutes, care is '.$this->amount($quote['family_care_amount_cents'])
                .', the Family processing fee is '.$this->amount($quote['family_processing_fee_cents'])
                .', and the Family total is '.$this->amount($quote['total_charge_cents']).'.';
            if ($caregiver) {
                $answer .= ' Caregiver gross earnings are '.$this->amount($quote['caregiver_gross_amount_cents']).' before any applicable recorded processing deduction.';
            }
        }

        return $answer.' An applicable pricing agreement or an existing booking may differ. Review the exact care before confirming.'
            .$this->additionalChargeBoundary($message);
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

    private function amount(int $cents): string
    {
        return '$'.number_format($cents / 100, 2);
    }
}
