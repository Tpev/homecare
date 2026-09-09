<?php

namespace App\Services\Payments;

use App\Models\CareBooking;
use App\Models\CareBookingCorrection;
use App\Models\CareBookingPayment;
use App\Models\CareBookingPaymentOperation;
use App\Models\CarePricingAgreement;
use App\Models\FamilyAccount;
use App\Models\User;
use App\Services\Booking\BookingTrustService;
use App\Support\MarketplacePricing;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DonLegacyPricingService
{
    public const FAMILY_EMAIL = 'donrjohn22@yahoo.com';

    public const REASON = 'Honor the existing Don Johnson agreement: family pays $15.75/hour total; the caregiver receives $15/hour; LoLo pays processing costs. Support ticket #52.';

    public function __construct(
        private readonly MarketplacePricing $pricing,
        private readonly BookingPaymentV2Service $payments,
        private readonly BookingTrustService $trust,
    ) {}

    /** @return array<string, int|string> */
    public function agreedRates(): array
    {
        return [
            'family_care_rate_cents' => 1575,
            'family_processing_fee_rate_cents' => 0,
            'caregiver_gross_rate_cents' => 1500,
            'caregiver_fee_policy' => CarePricingAgreement::PLATFORM_PAYS_PROCESSING,
        ];
    }

    public function assertSource(CareBooking $source): void
    {
        $source->loadMissing(['family', 'caregiver']);
        if (strtolower(trim((string) $source->family?->email)) !== self::FAMILY_EMAIL
            || ! $source->family_account_id
            || $source->caregiver?->role !== 'caregiver') {
            throw ValidationException::withMessages(['booking' => 'The source must belong to Don and identify his agreed caregiver.']);
        }
    }

    /** @return Collection<int, CareBooking> */
    public function affectedBookings(CareBooking $source): Collection
    {
        $this->assertSource($source);

        return CareBooking::query()
            ->where('family_account_id', $source->family_account_id)
            ->where('caregiver_user_id', $source->caregiver_user_id)
            ->where('id', '>=', $source->id)
            ->where(function ($query) use ($source): void {
                $query->whereKey($source->id)
                    ->orWhere(function ($future): void {
                        $future->where('scheduled_start_at', '>=', now())
                            ->where('status', CareBooking::STATUS_SCHEDULED);
                    });
            })
            ->with('payment.operations')
            ->orderBy('id')->get();
    }

    public function blockedReason(CareBooking $booking): ?string
    {
        $payment = $booking->payment;
        if ($booking->status === CareBooking::STATUS_CANCELLED) {
            return 'Cancelled visit';
        }
        if ($payment && (! $this->payments->usesV2($payment)
            || strtolower((string) $payment->currency) !== 'usd'
            || ! in_array($payment->status, [
                CareBookingPayment::STATUS_DRAFT,
                CareBookingPayment::STATUS_AUTHORIZED,
                CareBookingPayment::STATUS_AUTHORIZATION_REQUIRED,
                CareBookingPayment::STATUS_REAUTH_REQUIRED,
            ], true)
            || $payment->captured_at || $payment->transferred_at
            || (int) $payment->amount_captured_cents > 0
            || (int) $payment->amount_refunded_cents > 0
            || (int) $payment->amount_overage_cents > 0
            || $payment->stripe_transfer_id || $payment->stripe_primary_charge_id
            || $payment->stripe_overage_payment_intent_id
            || data_get($payment->metadata, 'authorization_in_progress_at')
            || $payment->operations()->where('type', '!=', CareBookingPaymentOperation::TYPE_AUTHORIZATION)->exists())) {
            return 'Payment has settled, uncertain, or legacy activity; reconcile separately';
        }
        if (CareBookingCorrection::query()->where('care_booking_id', $booking->id)
            ->where('status', '!=', CareBookingCorrection::STATUS_SUCCEEDED)->exists()) {
            return 'A visit correction needs review before repricing';
        }

        return null;
    }

    /** @return array{agreement:CarePricingAgreement, repaired:array<int>, skipped:array<int,string>} */
    public function apply(CareBooking $source, User $admin, int $expectedCaregiverId): array
    {
        abort_unless($admin->isAdministrator(), 403);

        return DB::transaction(function () use ($source, $admin, $expectedCaregiverId): array {
            $source = CareBooking::query()->lockForUpdate()->findOrFail($source->id);
            $this->assertSource($source);
            if ((int) $source->caregiver_user_id !== $expectedCaregiverId) {
                throw ValidationException::withMessages(['caregiver' => 'The caregiver differs from the reviewed preview.']);
            }
            FamilyAccount::query()->whereKey($source->family_account_id)->lockForUpdate()->firstOrFail();
            $agreement = CarePricingAgreement::query()
                ->where('family_account_id', $source->family_account_id)
                ->where('caregiver_user_id', $source->caregiver_user_id)
                ->lockForUpdate()->first();
            if ($agreement && (! $agreement->active || $agreement->only(array_keys($this->agreedRates())) !== $this->agreedRates())) {
                throw ValidationException::withMessages(['agreement' => 'A different agreement already exists; review it before making changes.']);
            }
            if ($agreement && (int) $agreement->source_booking_id !== (int) $source->id) {
                throw ValidationException::withMessages(['booking' => 'The agreement already starts at booking #'.$agreement->source_booking_id.'. Rerun with --booking='.$agreement->source_booking_id.' to preserve that cutoff.']);
            }
            $agreement ??= CarePricingAgreement::query()->create(array_merge($this->agreedRates(), [
                'family_account_id' => $source->family_account_id,
                'caregiver_user_id' => $source->caregiver_user_id,
                'source_booking_id' => $source->id,
                'created_by_user_id' => $admin->id,
                'reason' => self::REASON,
                'active' => true,
            ]));

            $repaired = [];
            $skipped = [];
            foreach ($this->affectedBookings($source) as $candidate) {
                $booking = CareBooking::query()->lockForUpdate()->findOrFail($candidate->id);
                $payment = CareBookingPayment::query()->where('care_booking_id', $booking->id)->lockForUpdate()->first();
                $booking->setRelation('payment', $payment);
                $block = $this->blockedReason($booking);
                if ($block) {
                    $skipped[$booking->id] = $block;

                    continue;
                }
                $attributes = array_merge($agreement->snapshotAttributes(), ['pricing_version' => $this->pricing->currentVersion()]);
                $before = $booking->only(array_keys($attributes));
                if ($before == $attributes && (! $payment || $payment->only(array_keys($attributes)) == $attributes)) {
                    continue;
                }
                $booking->forceFill(array_merge($attributes, ['pricing_snapshotted_at' => now()]))->save();
                if ($payment) {
                    $payment->forceFill(array_merge($this->payments->paymentSnapshotAttributes($booking), [
                        'metadata' => array_merge($payment->metadata ?? [], [
                            'pricing_agreement_repair' => [
                                'agreement_id' => $agreement->id,
                                'previous_pricing' => $before,
                                'repaired_at' => now()->toIso8601String(),
                            ],
                        ]),
                    ]))->save();
                }
                $this->trust->recordEvent($booking, $admin->id, 'admin', 'legacy_pricing_agreement_applied', [
                    'reason' => self::REASON,
                    'pricing_agreement_id' => $agreement->id,
                    'from_booking_id' => $agreement->source_booking_id,
                    'before' => $before,
                    'after' => $attributes,
                    'money_moved' => false,
                ]);
                $repaired[] = $booking->id;
            }

            return compact('agreement', 'repaired', 'skipped');
        });
    }
}
