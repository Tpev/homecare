<?php

namespace App\Services\Payments;

use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CarePricingAgreement;
use App\Models\FamilyAccount;
use App\Models\User;
use App\Services\Booking\BookingTrustService;
use App\Support\MarketplacePricing;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class DonFamilyPricingService
{
    public const REASON = 'Don account-wide pricing: family $15.75/hour total, every caregiver $15/hour, LoLo covers processing. Support ticket #88.';

    public function __construct(
        private readonly DonLegacyPricingService $legacy,
        private readonly MarketplacePricing $pricing,
        private readonly BookingPaymentV2Service $payments,
        private readonly BookingTrustService $trust,
    ) {}

    public function blockedReason(CareBooking $booking): ?string
    {
        if ($booking->timeCorrections()->active()->exists()) {
            return 'An active time correction needs review before repricing';
        }
        if (Schema::hasTable('care_hour_booking_fundings') && DB::table('care_hour_booking_fundings')
            ->where('care_booking_id', $booking->id)->whereNotNull('committed_at')->exists()) {
            return 'This visit has prepaid funding; reconcile that ledger separately';
        }

        return $this->legacy->blockedReason($booking);
    }

    /** @return Collection<int, CareBooking> */
    public function candidates(CareBooking $source): Collection
    {
        $this->legacy->assertSource($source);

        return CareBooking::query()->where('family_account_id', $source->family_account_id)
            ->where(function ($query) use ($source): void {
                $query->whereKey($source->id)->orWhere(function ($pending): void {
                    $pending->where('status', '!=', CareBooking::STATUS_CANCELLED)
                        ->whereDoesntHave('payment', fn ($payment) => $payment
                            ->whereNotNull('captured_at')->orWhere('amount_captured_cents', '>', 0)
                            ->orWhereNotNull('transferred_at')->orWhereNotNull('stripe_transfer_id'));
                });
            })->with(['payment.operations', 'caregiver:id,name'])->orderBy('id')->get();
    }

    /** Register the family-wide policy and repair only uncharged snapshots. Never call Stripe. */
    public function apply(CareBooking $source, User $admin, int $expectedFamilyAccountId): array
    {
        abort_unless($admin->isAdministrator(), 403);

        return DB::transaction(function () use ($source, $admin, $expectedFamilyAccountId): array {
            $source = CareBooking::query()->lockForUpdate()->findOrFail($source->id);
            $this->legacy->assertSource($source);
            if ((int) $source->family_account_id !== $expectedFamilyAccountId) {
                throw ValidationException::withMessages(['pricing' => 'The family account differs from the reviewed preview.']);
            }
            FamilyAccount::query()->whereKey($source->family_account_id)->lockForUpdate()->firstOrFail();
            $agreements = CarePricingAgreement::query()->where('family_account_id', $source->family_account_id)
                ->orderByRaw('CASE WHEN all_caregivers = 1 THEN 0 ELSE 1 END')->orderBy('id')->lockForUpdate()->get();
            $agreement = $agreements->first();
            $rates = $this->legacy->agreedRates();
            if ($agreements->contains(fn ($item) => ! $item->active || $item->only(array_keys($rates)) !== $rates)) {
                throw ValidationException::withMessages(['pricing' => 'This account has different or inactive agreements. Review them before extending the pricing.']);
            }
            $previousScope = $agreement?->all_caregivers;
            if (! $agreement) {
                $agreement = CarePricingAgreement::query()->create(array_merge($rates, [
                    'family_account_id' => $source->family_account_id,
                    'caregiver_user_id' => $source->caregiver_user_id,
                    'source_booking_id' => $source->id,
                    'created_by_user_id' => $admin->id,
                    'reason' => self::REASON, 'active' => true, 'all_caregivers' => true,
                ]));
            } elseif (! $agreement->all_caregivers) {
                $agreement->update(['all_caregivers' => true, 'reason' => self::REASON]);
            }
            if (! $previousScope) {
                $this->trust->recordEvent($source, $admin->id, 'admin', 'family_pricing_agreement_extended', [
                    'reason' => self::REASON, 'pricing_agreement_id' => $agreement->id,
                    'family_account_id' => $source->family_account_id, 'all_caregivers' => true,
                    'rates' => $rates, 'money_moved' => false,
                ]);
            }

            $repaired = [];
            $skipped = [];
            foreach ($this->candidates($source) as $candidate) {
                $booking = CareBooking::query()->lockForUpdate()->findOrFail($candidate->id);
                $payment = CareBookingPayment::query()->where('care_booking_id', $booking->id)->lockForUpdate()->first();
                $booking->setRelation('payment', $payment);
                if ($reason = $this->blockedReason($booking)) {
                    $skipped[$booking->id] = $reason;
                    continue;
                }
                $attributes = array_merge($agreement->snapshotAttributes(), ['pricing_version' => $this->pricing->currentVersion()]);
                $before = $booking->only(array_keys($attributes));
                $paymentBefore = $payment?->only(array_keys($attributes));
                $application = $booking->application()->lockForUpdate()->first();
                $applicationRateBefore = $application?->proposed_rate;
                $caregiverRate = (float) ($agreement->caregiver_gross_rate_cents / 100);
                if ($before == $attributes && (! $payment || $paymentBefore == $attributes)
                    && (! $application || (float) $applicationRateBefore === $caregiverRate)) {
                    continue;
                }
                $booking->forceFill(array_merge($attributes, ['pricing_snapshotted_at' => now()]))->save();
                if ($payment) {
                    $payment->forceFill($this->payments->paymentSnapshotAttributes($booking))->save();
                }
                if ($application) {
                    $application->update(['proposed_rate' => $caregiverRate]);
                }
                $this->trust->recordEvent($booking, $admin->id, 'admin', 'family_pricing_agreement_applied', [
                    'reason' => self::REASON, 'pricing_agreement_id' => $agreement->id,
                    'before' => $before, 'payment_before' => $paymentBefore, 'after' => $attributes,
                    'application_rate_before' => $applicationRateBefore, 'caregiver_rate_after' => $caregiverRate,
                    'authorization_preserved_cents' => $payment?->amount_authorized_cents,
                    'money_moved' => false,
                ]);
                $repaired[] = $booking->id;
            }

            return compact('agreement', 'repaired', 'skipped');
        });
    }
}
