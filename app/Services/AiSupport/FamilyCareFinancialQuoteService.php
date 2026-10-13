<?php

namespace App\Services\AiSupport;

use App\Models\CareBooking;
use App\Models\CarePlan;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\Payments\BookingPaymentService;
use App\Services\RegularCare\CarePlanOccurrenceService;
use App\Support\MarketplacePricing;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/** Read-only financial facts for confirmations; never creates a booking or payment. */
class FamilyCareFinancialQuoteService
{
    public function __construct(
        private readonly MarketplacePricing $pricing,
        private readonly BookingPaymentService $payments,
        private readonly CarePlanOccurrenceService $occurrences,
        private readonly FamilyAccountContext $familyAccounts,
    ) {}

    public function hire(CareRequestApplication $application): array
    {
        $request = $application->careRequest;
        if ($request->request_type === CareRequest::TYPE_RECURRING) {
            [$start, $end] = $this->firstOccurrence([
                'starts_on' => $request->recurring_starts_on,
                'ends_on' => $request->recurring_ends_on,
                'schedule_days' => $request->recurring_days,
                'schedule_start_time' => $request->recurring_start_time,
                'schedule_end_time' => $request->recurring_end_time,
                'schedule_slots' => $request->recurringScheduleSlots(),
            ]);
        } else {
            $start = $request->requested_start_at;
            $end = $request->requested_end_at;
        }

        return $this->newVisit($application, $start, $end, 'hire');
    }

    public function hours(CareBooking $booking): array
    {
        $booking->loadMissing('payment');

        return $this->facts($booking, (int) $booking->worked_minutes, 'hours');
    }

    public function recurringOffer(CareRequestApplication $application, array $schedule, ?User $actor = null): array
    {
        [$start, $end] = $this->firstOccurrence($schedule, limitToBookingWindow: false);

        // Generated legacy visits use the plan's platform/family rate, not the old application rate.
        $application = clone $application;
        $request = $application->careRequest;
        $request->loadMissing('family');
        $accountId = $this->accountId($request);
        $agreement = $this->pricing->agreementForPair($accountId, (int) $application->caregiver_user_id);
        $application->proposed_rate = $agreement
            ? $agreement->family_care_rate_cents / 100
            : $this->pricing->hourlyRateForFamily($actor ?? $request->family,
                $this->pricing->rateForTier((string) config('marketplace.default_pricing_tier', 'standard')));

        return $this->newVisit($application, $start, $end, 'offer');
    }

    public function assertUnchanged(mixed $expected, array $actual): void
    {
        if (! is_array($expected) || ! hash_equals(
            hash('sha256', json_encode($expected, JSON_THROW_ON_ERROR)),
            hash('sha256', json_encode($actual, JSON_THROW_ON_ERROR)),
        )) {
            throw ValidationException::withMessages([
                'confirmation' => 'The visit or payment quote changed. Review a fresh recap and its current total before confirming.',
            ]);
        }
    }

    public function fields(array $quote): array
    {
        $money = fn (int $cents): string => strtoupper($quote['currency']).' $'.number_format($cents / 100, 2);
        $fields = [
            ['label' => $quote['purpose'] === 'hours' ? 'Visit' : 'First visit', 'value' => Carbon::parse($quote['start_at'])->format('M j, Y g:i A').' – '.Carbon::parse($quote['end_at'])->format('M j, Y g:i A').' ('.$quote['timezone'].')'],
            ['label' => $quote['purpose'] === 'hours' ? 'Submitted time' : 'Visit duration', 'value' => $quote['minutes'].' minutes'],
            ['label' => 'Care rate', 'value' => $money($quote['care_rate_cents']).'/hour'],
            ['label' => 'Care amount', 'value' => $money($quote['care_cents'])],
            ['label' => $quote['pricing_version'] === 'legacy' ? 'Platform fee' : 'Processing fee', 'value' => $money($quote['fee_cents'])],
            ['label' => $quote['purpose'] === 'hours' ? 'Payment total' : 'Estimated first visit total', 'value' => $money($quote['total_cents'])],
        ];
        if ($quote['purpose'] === 'hire') {
            $fields[] = ['label' => 'Estimated authorization hold', 'value' => $money($quote['authorization_cents']).' including the '.$quote['authorization_buffer_percent'].'% buffer; final payment follows approved hours.'];
        } elseif ($quote['purpose'] === 'offer') {
            $fields[] = ['label' => 'Payment timing', 'value' => 'No charge from sending this offer. Each visit is handled separately after acceptance; future estimates depend on visit duration and applicable pricing.'];
        }

        return $fields;
    }

    private function newVisit(CareRequestApplication $application, ?Carbon $start, ?Carbon $end, string $purpose): array
    {
        if (! $start || ! $end || $start->lte(now()) || $end->lte($start)) {
            throw ValidationException::withMessages(['confirmation' => 'No future visit can be quoted from this schedule. Open the request and update its dates first.']);
        }
        $request = $application->careRequest;
        $request->loadMissing('family');
        $booking = new CareBooking([
            'family_account_id' => $this->accountId($request),
            'family_user_id' => $request->family_user_id,
            'caregiver_user_id' => $application->caregiver_user_id,
            'scheduled_start_at' => $start,
            'scheduled_end_at' => $end,
        ]);
        $booking->setRelation('application', $application);
        $booking->setRelation('family', $request->family);
        $booking->setRelation('caregiver', $application->caregiver);
        $booking->setRelation('payment', null);
        if ($this->pricing->currentPricingEnabled()) {
            $booking->forceFill($this->pricing->currentSnapshotAttributes($booking));
        }

        return $this->facts($booking, (int) $start->diffInMinutes($end, false), $purpose);
    }

    private function facts(CareBooking $booking, int $minutes, string $purpose): array
    {
        $quote = $this->payments->quoteForWorkedMinutes($booking, $minutes);
        $current = $this->pricing->usesCurrentPricing($booking);
        $care = (int) ($quote['family_care_amount_cents'] ?? $quote['subtotal_cents']);
        $total = (int) $quote['total_charge_cents'];
        $buffer = max(0, (float) config('marketplace.payments.authorization_buffer_percent', 20));
        // Matches BookingPaymentService's hold calculation, including legacy rounding/minimum.
        $unbuffered = $current ? $total : $care * (1 + ((float) $quote['platform_fee_percent'] / 100));

        return [
            'purpose' => $purpose,
            'start_at' => $booking->scheduled_start_at?->toIso8601String(),
            'end_at' => $booking->scheduled_end_at?->toIso8601String(),
            'timezone' => (string) config('app.timezone', 'America/New_York'),
            'minutes' => (int) $quote['worked_minutes'],
            'currency' => strtolower((string) ($booking->payment?->currency ?: config('services.stripe.currency', 'usd'))),
            'pricing_version' => (string) ($booking->pricing_version ?: 'legacy'),
            'pricing_agreement_id' => $booking->pricing_agreement_id,
            'care_rate_cents' => (int) round((float) $quote['hourly_rate'] * 100),
            'fee_rate_cents' => $quote['family_processing_fee_rate_cents'] ?? null,
            'fee_percent' => (float) $quote['platform_fee_percent'],
            'care_cents' => $care,
            // Legacy platform_fee_cents is the platform's share, not the fee added to care.
            'fee_cents' => $total - $care,
            'total_cents' => $total,
            'authorization_buffer_percent' => $purpose === 'hire' ? $buffer : null,
            'authorization_cents' => $purpose === 'hire' ? max(100, (int) round($unbuffered * (1 + $buffer / 100))) : null,
        ];
    }

    private function accountId(CareRequest $request): int
    {
        return (int) ($request->family_account_id ?: ($request->family
            ? $this->familyAccounts->membershipFor($request->family, false)?->family_account_id : 0));
    }

    private function firstOccurrence(array $schedule, bool $limitToBookingWindow = true): array
    {
        if (empty($schedule['starts_on'])) {
            throw ValidationException::withMessages(['confirmation' => 'Choose a recurring care start date before reviewing its cost.']);
        }
        $timezone = (string) config('app.timezone', 'America/New_York');
        $plan = new CarePlan(array_merge(array_intersect_key($schedule, array_flip([
            'starts_on', 'ends_on', 'schedule_days', 'schedule_start_time', 'schedule_end_time', 'schedule_slots',
        ])), ['status' => CarePlan::STATUS_ACTIVE, 'timezone' => $timezone]));
        $through = now($timezone)->addWeeks(max(1, min(12, (int) config('marketplace.regular_care.visit_window_weeks', 6))));
        if (! $limitToBookingWindow) {
            // An offer may start beyond the window in which accepted plans create bookings.
            $through = $through->max(Carbon::parse($plan->starts_on->toDateString(), $timezone)->addWeek());
        }
        $occurrence = $this->occurrences->scheduledOccurrences($plan, $through)[0] ?? null;
        if (! $occurrence) {
            throw ValidationException::withMessages(['confirmation' => 'No future visit can be quoted from this schedule. Open the request and update its dates first.']);
        }

        return [$occurrence['start'], $occurrence['end']];
    }
}
