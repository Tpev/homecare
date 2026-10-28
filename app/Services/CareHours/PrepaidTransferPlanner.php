<?php

namespace App\Services\CareHours;

use App\Exceptions\Payments\PaymentException;
use App\Models\CaregiverPayout;
use App\Models\CaregiverPayoutItem;
use App\Models\CareHourPaymentOperation;
use App\Models\CareHourPurchase;
use App\Models\CareHourSettlement;
use App\Services\Payments\StripeClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Persist transfer intent and reserve source capacity before calling the provider. */
class PrepaidTransferPlanner
{
    public function __construct(private readonly StripeClient $stripe, private readonly CareHourLedgerService $ledger) {}

    public function pay(CareHourSettlement $settlement, bool $settlementLockHeld = false): void
    {
        if (! $settlementLockHeld) {
            Cache::lock('care-hours:settlement:'.$settlement->care_booking_id, 300)
                ->block(5, fn () => $this->pay($settlement->fresh(), settlementLockHeld: true));

            return;
        }
        if (app(PrepaidBookingSettlementService::class)->holdForVisitState($settlement)) {
            $this->recoverHeldOperations($settlement);

            return;
        }
        if (app(PrepaidBookingFundingService::class)->hasDisputedShortfall($settlement->funding)) {
            $settlement->update(['status' => 'funding_on_hold', 'payable_cents' => null, 'paid_at' => null,
                'last_error' => 'A visit card payment is disputed. Support review is required.']);
            $this->syncPayout($settlement->fresh());

            return;
        }
        if ($this->holdForUnmatchedReversals($settlement)) {
            return;
        }
        // A resolved hold still needs reconciliation to establish a new approved payout target.
        if ($settlement->payable_cents === null) {
            return;
        }
        // Resolve old provider work before calculating a new correction or reserving more capacity.
        try {
            foreach ($settlement->operations()->whereIn('kind', ['transfer', 'reversal'])->whereIn('status', ['unknown', 'requested'])->orderBy('id')->get() as $operation) {
                $this->recoverOperation($operation, $settlement);
                if (app(PrepaidBookingSettlementService::class)->holdForVisitState($settlement)) {
                    $this->syncPayout($settlement->fresh());

                    return;
                }
                if ($this->holdForUnmatchedReversals($settlement)) {
                    return;
                }
            }
        } catch (\Throwable $error) {
            $settlement->update(['status' => 'payout_pending', 'last_error' => $error->getMessage(), 'paid_at' => null]);
            $this->syncPayout($settlement->fresh());

            return;
        }
        $profile = $settlement->booking->caregiver?->caregiverProfile;
        if (! $profile?->stripeConnectIsReady() && (int) $settlement->payable_cents > $this->netPaid($settlement)) {
            $settlement->update(['status' => 'payout_pending', 'last_error' => 'Caregiver payout setup needs completion.']);
            $this->syncPayout($settlement);

            return;
        }
        $operations = DB::transaction(function () use ($settlement, $profile) {
            if (app(PrepaidBookingSettlementService::class)->holdForVisitState($settlement)) {
                return collect();
            }
            \App\Models\CareHourAccount::whereKey($settlement->funding->care_hour_account_id)->lockForUpdate()->firstOrFail();
            $expectedRevision = (int) $settlement->revision;
            $settlement = CareHourSettlement::lockForUpdate()->findOrFail($settlement->id);
            if ((int) $settlement->revision !== $expectedRevision) {
                throw new PaymentException('Funding changed before payout; reconciliation will retry.');
            }
            $netPaid = $this->netPaid($settlement);
            $target = (int) $settlement->payable_cents;
            $settlement->operations()->where('kind', 'transfer')->where('status', 'pending')->where('metadata->revision', '!=', $settlement->revision)->update(['status' => 'cancelled']);
            $uncertain = $settlement->operations()->whereIn('kind', ['transfer', 'reversal'])->whereIn('status', ['unknown', 'requested'])->exists();
            if ($uncertain && $target < $netPaid + (int) $settlement->operations()->where('kind', 'transfer')->whereIn('status', ['unknown', 'requested'])->sum('amount_cents')) {
                throw new PaymentException('Reconcile the outstanding transfer before applying reduced earnings.');
            }
            if ($target < $netPaid) {
                return collect();
            }
            $pending = $settlement->operations()->where('kind', 'transfer')->whereIn('status', ['pending', 'unknown', 'requested'])->sum('amount_cents');
            $remaining = max(0, $target - $netPaid - $pending);
            if ($remaining > 0) {
                $groups = $this->ledger->allocationsFor($settlement->booking)->where('status', 'used')->groupBy(fn ($a) => $a->lot->purchase->id)->sortKeys();
                foreach ($groups as $purchaseId => $allocations) {
                    $purchase = $allocations->first()->lot->purchase;
                    $purchase = CareHourPurchase::lockForUpdate()->findOrFail($purchase->id);
                    if (! $purchase->stripe_charge_id || ! $remaining) {
                        continue;
                    }
                    // Include pending/unknown transfers. Never assume a reversal restores provider source capacity.
                    $committed = CareHourPaymentOperation::where('kind', 'transfer')->where('source_purchase_id', $purchase->id)
                        ->whereNotIn('status', ['failed', 'cancelled'])->sum('amount_cents');
                    $minutes = (int) $allocations->sum('minutes');
                    $gross = $minutes > 0 ? app(\App\Support\MarketplacePricing::class)->prorateHourlyCents((int) $settlement->booking->caregiver_gross_rate_cents, $minutes) : 0;
                    $attributed = max(0, $gross - (int) $allocations->sum(fn ($a) => $a->feeCents()));
                    $alreadyForVisit = (int) $settlement->operations()->where('kind', 'transfer')->where('source_purchase_id', $purchase->id)->whereNotIn('status', ['cancelled', 'failed'])->sum('amount_cents');
                    $take = min($remaining, max(0, (int) $purchase->amount_cents - (int) $committed), max(0, $attributed - $alreadyForVisit));
                    if ($take > 0) {
                        $this->newTransfer($settlement, $profile->stripe_connect_account_id, $take, $purchase);
                        $remaining -= $take;
                    }
                }
                $cashPayments = $settlement->funding->operations()->where('kind', 'shortfall')->where('status', 'captured')->orderBy('id')->lockForUpdate()->get()
                    ->filter(fn ($payment) => app(PrepaidBookingFundingService::class)->operationIsUsable($payment));
                $cashTotal = (int) $cashPayments->sum(fn ($p) => max(0, (int) $p->captured_cents - (int) $p->refunded_cents));
                $prepaidGross = $settlement->prepaid_minutes > 0 ? app(\App\Support\MarketplacePricing::class)->prorateHourlyCents((int) $settlement->booking->caregiver_gross_rate_cents, (int) $settlement->prepaid_minutes) : 0;
                $cashTarget = max(0, (int) $settlement->payable_cents - max(0, $prepaidGross - (int) $settlement->package_fee_cents));
                $cashPrefix = 0;
                foreach ($cashPayments as $payment) {
                    $capacity = max(0, (int) $payment->captured_cents - (int) $payment->refunded_cents);
                    $attributed = $cashTotal > 0 ? intdiv($cashTarget * ($cashPrefix + $capacity), $cashTotal) - intdiv($cashTarget * $cashPrefix, $cashTotal) : 0;
                    $cashPrefix += $capacity;
                    if (! $remaining || ! $payment->stripe_charge_id) {
                        continue;
                    }
                    $committed = (int) CareHourPaymentOperation::where('kind', 'transfer')->where('source_charge_id', $payment->stripe_charge_id)
                        ->whereNotIn('status', ['failed', 'cancelled'])->sum('amount_cents');
                    $take = min($remaining, max(0, $capacity - $committed), max(0, $attributed - $committed));
                    if ($take > 0) {
                        $this->newTransfer($settlement, $profile->stripe_connect_account_id, $take, null, $payment->stripe_charge_id);
                        $remaining -= $take;
                    }
                }
                // Price protection or exhausted original charge capacity is funded by LoLo, never by another donor.
                if ($remaining > 0) {
                    $this->newTransfer($settlement, $profile->stripe_connect_account_id, $remaining);
                }
            }

            return $settlement->operations()->where('kind', 'transfer')->whereIn('status', ['pending', 'unknown', 'requested'])->orderBy('id')->get();
        }, 3);

        if (app(PrepaidBookingSettlementService::class)->holdForVisitState($settlement)) {
            $this->syncPayout($settlement->fresh());

            return;
        }
        if ((int) $settlement->payable_cents < $this->netPaid($settlement)) {
            $this->reverseExcess($settlement);
        }
        foreach ($operations as $operation) {
            try {
                if ((int) $settlement->fresh()->revision !== (int) $settlement->revision) {
                    return;
                }
                if ($operation->created_at->lt(now()->subHours(23))) {
                    $this->recoverOperation($operation, $settlement);
                } else {
                    $this->sendTransfer($operation, $settlement);
                }
                if (app(PrepaidBookingSettlementService::class)->holdForVisitState($settlement)) {
                    $this->syncPayout($settlement->fresh());

                    return;
                }
                if ($this->holdForUnmatchedReversals($settlement)) {
                    return;
                }
                if ((int) $settlement->fresh()->revision !== (int) $settlement->revision) {
                    return;
                }
            } catch (\Throwable $error) {
                // Unknown must retain reserved source capacity and the exact idempotency key.
                if ($operation->fresh()->status !== 'succeeded') {
                    $operation->update(['status' => 'unknown', 'last_error' => $error->getMessage()]);
                }
                $settlement->update(['status' => 'payout_pending', 'last_error' => $error->getMessage()]);
                $this->syncPayout($settlement->fresh());

                return;
            }
        }
        if ($this->holdForUnmatchedReversals($settlement)) {
            return;
        }
        if (app(PrepaidBookingSettlementService::class)->holdForVisitState($settlement)) {
            $this->syncPayout($settlement->fresh());

            return;
        }
        $paid = $this->netPaid($settlement);
        $complete = $paid === (int) $settlement->caregiver_net_cents && (int) $settlement->amount_due_cents === 0;
        $updated = CareHourSettlement::whereKey($settlement->id)->where('revision', $settlement->revision)
            ->whereDoesntHave('operations', fn ($query) => $query->where('kind', 'transfer')->where('metadata->provider_reversal_attention', true))
            ->update(['paid_cents' => $paid, 'status' => $complete ? 'paid' : ((int) $settlement->amount_due_cents > 0 ? 'partially_settled' : 'payout_pending'), 'paid_at' => $complete ? now() : null, 'last_error' => null]);
        if (! $updated) {
            return;
        }
        $this->syncPayout($settlement->fresh());
    }

    /** Record existing provider truth on a held visit, without creating a new payout. */
    public function recoverHeldOperations(CareHourSettlement $settlement): void
    {
        foreach ($settlement->operations()->whereIn('kind', ['transfer', 'reversal'])
            ->whereIn('status', ['unknown', 'requested'])->orderBy('id')->get() as $operation) {
            try {
                if ($operation->kind === 'transfer') {
                    $result = $this->stripe->findPrepaidTransfer($operation->stripe_transfer_id, $operation->public_id,
                        $operation->destination_account_id, $operation->created_at->timestamp - 60);
                    if ($result) {
                        $this->recordTransfer($operation, $result);
                    }
                } else {
                    // An already requested reversal can still recover its original outcome/key.
                    $this->recoverOperation($operation, $settlement);
                }
            } catch (\Throwable $error) {
                $operation->update(['last_error' => $error->getMessage()]);
                report($error);
            }
        }
        app(PrepaidBookingSettlementService::class)->holdForVisitState($settlement);
        $this->syncPayout($settlement->fresh());
    }

    private function newTransfer(CareHourSettlement $settlement, string $destination, int $amount, ?CareHourPurchase $purchase = null, ?string $sourceCharge = null): void
    {
        $id = (string) Str::uuid();
        CareHourPaymentOperation::create([
            'public_id' => $id, 'operation_key' => 'transfer:'.$id,
            'care_hour_booking_funding_id' => $settlement->care_hour_booking_funding_id,
            'care_hour_settlement_id' => $settlement->id, 'kind' => 'transfer', 'status' => 'pending',
            'amount_cents' => $amount, 'currency' => $settlement->currency,
            'source_purchase_id' => $purchase?->id, 'source_charge_id' => $purchase?->stripe_charge_id ?: $sourceCharge,
            'destination_account_id' => $destination,
            'metadata' => ['revision' => $settlement->revision, 'source_transfer_group' => 'inherit'],
        ]);
    }

    /** Called under the visit settlement lock. Never reverse package-funded transfers. */
    public function reverseDisputedShortfall(CareHourPaymentOperation $payment, CareHourPaymentOperation $dispute): void
    {
        $settlement = CareHourSettlement::where('care_hour_booking_funding_id', $payment->care_hour_booking_funding_id)->first();
        if (! $settlement || $dispute->status !== 'lost' || ! $payment->stripe_charge_id) {
            return;
        }
        $source = $settlement->operations()->where('kind', 'transfer')->where('source_charge_id', $payment->stripe_charge_id);
        $source->clone()->where('status', 'pending')->update(['status' => 'cancelled']);
        foreach ($source->clone()->whereIn('status', ['requested', 'unknown'])->get() as $transfer) {
            // Look up an uncertain transfer, but never create new payout money after a loss.
            $result = $this->stripe->findPrepaidTransfer($transfer->stripe_transfer_id, $transfer->public_id,
                $transfer->destination_account_id, $transfer->created_at->timestamp - 60);
            if (! $result) {
                throw new PaymentException('A disputed transfer outcome needs provider review before reversal.');
            }
            $this->recordTransfer($transfer, $result);
        }
        $transfers = $source->clone()->where('status', 'succeeded')->orderBy('id')->get();
        $transferred = (int) $transfers->sum('amount_cents');
        $captured = (int) $payment->captured_cents;
        $target = $captured > 0 ? min($transferred, intdiv($transferred * min($captured, (int) $dispute->amount_cents) + intdiv($captured, 2), $captured)) : 0;
        $reserved = (int) $settlement->operations()->where('kind', 'reversal')->where('metadata->dispute_operation_id', $dispute->id)->sum('amount_cents');
        $remaining = max(0, $target - $reserved);
        foreach ($transfers as $transfer) {
            $existing = $settlement->operations()->where('kind', 'reversal')->where('stripe_transfer_id', $transfer->stripe_transfer_id)->sum('amount_cents');
            $amount = min($remaining, max(0, (int) $transfer->amount_cents - (int) $existing));
            if ($amount > 0) {
                CareHourPaymentOperation::firstOrCreate(['operation_key' => 'dispute-reversal:'.$dispute->id.':'.$transfer->id], [
                    'public_id' => (string) Str::uuid(), 'care_hour_booking_funding_id' => $settlement->care_hour_booking_funding_id,
                    'care_hour_settlement_id' => $settlement->id, 'kind' => 'reversal', 'status' => 'pending',
                    'stripe_transfer_id' => $transfer->stripe_transfer_id, 'source_charge_id' => $payment->stripe_charge_id,
                    'amount_cents' => $amount, 'currency' => $payment->currency, 'livemode' => $payment->livemode,
                    'metadata' => ['dispute_operation_id' => $dispute->id],
                ]);
                $remaining -= $amount;
            }
        }
        foreach ($settlement->operations()->where('kind', 'reversal')->where('metadata->dispute_operation_id', $dispute->id)->where('status', '!=', 'succeeded')->get() as $reversal) {
            $this->recoverOperation($reversal, $settlement);
        }
        // Completed care and package allocations remain unchanged; only collected payout changes.
        $settlement->update(['paid_cents' => $this->netPaid($settlement), 'status' => 'funding_on_hold',
            'payable_cents' => null, 'paid_at' => null, 'last_error' => 'A visit card dispute was lost. Support review is required.']);
        $this->syncPayout($settlement->fresh());
    }

    private function reverseExcess(CareHourSettlement $settlement): void
    {
        $remaining = $this->netPaid($settlement) - (int) $settlement->payable_cents;
        foreach ($settlement->operations()->where('kind', 'transfer')->where('status', 'succeeded')->latest('id')->get() as $transfer) {
            if ($remaining <= 0) {
                break;
            }
            $already = $settlement->operations()->where('kind', 'reversal')->where('stripe_transfer_id', $transfer->stripe_transfer_id)->where('status', 'succeeded')->sum('amount_cents');
            $amount = min($remaining, max(0, (int) $transfer->amount_cents - (int) $already));
            if (! $amount) {
                continue;
            }
            $operation = CareHourPaymentOperation::firstOrCreate(['operation_key' => 'reversal:'.$transfer->id.':'.$settlement->revision], [
                'public_id' => (string) Str::uuid(), 'care_hour_booking_funding_id' => $settlement->care_hour_booking_funding_id,
                'care_hour_settlement_id' => $settlement->id, 'kind' => 'reversal', 'status' => 'pending',
                'stripe_transfer_id' => $transfer->stripe_transfer_id, 'amount_cents' => $amount, 'currency' => $settlement->currency,
            ]);
            if ($operation->status !== 'succeeded') {
                try {
                    if ($operation->created_at->lt(now()->subHours(23))) {
                        $this->recoverOperation($operation, $settlement);
                    } else {
                        $this->sendReversal($operation);
                    }
                } catch (\Throwable $error) {
                    if ($operation->fresh()->status !== 'succeeded') {
                        $operation->update(['status' => 'unknown', 'last_error' => $error->getMessage()]);
                    }
                    throw $error;
                }
            }
            $remaining -= $amount;
        }
        if ($remaining > 0) {
            throw new PaymentException('The earnings adjustment needs finance review.');
        }
    }

    private function recoverOperation(CareHourPaymentOperation $operation, CareHourSettlement $settlement): void
    {
        try {
            if ($operation->kind === 'transfer') {
                $result = $this->stripe->findPrepaidTransfer($operation->stripe_transfer_id, $operation->public_id,
                    $operation->destination_account_id, $operation->created_at->timestamp - 60);
                if ($result) {
                    $this->recordTransfer($operation, $result);

                    return;
                }
            } else {
                $result = $this->stripe->findPrepaidTransferReversal($operation->stripe_transfer_id, $operation->public_id,
                    data_get($operation->metadata, 'provider_reversal_id'));
                if ($result) {
                    $this->recordReversal($operation, $result);

                    return;
                }
            }
            if ($operation->created_at->lt(now()->subHours(23))) {
                throw new PaymentException('The original '.$operation->kind.' remains unconfirmed. Provider review is required before retrying.');
            }
            // Within the provider's safe window, replay only the original immutable operation.
            if ($operation->kind === 'transfer') {
                $this->sendTransfer($operation, $settlement);
            } else {
                $this->sendReversal($operation);
            }
        } catch (\Throwable $error) {
            if ($operation->fresh()->status !== 'succeeded') {
                $operation->update(['status' => 'unknown', 'last_error' => $error->getMessage()]);
            }
            throw $error;
        }
    }

    private function sendTransfer(CareHourPaymentOperation $operation, CareHourSettlement $settlement): void
    {
        if (app(PrepaidBookingSettlementService::class)->holdForVisitState($settlement)) {
            return;
        }
        $operation->update(['status' => 'requested']);
        $metadata = ['care_hour_domain' => 'transfer', 'care_hour_operation_id' => $operation->public_id, 'care_booking_id' => (string) $settlement->care_booking_id];
        if ($operation->source_charge_id) {
            // Persist the request policy at creation. Existing uncertain attempts keep their exact original payload.
            $result = data_get($operation->metadata, 'source_transfer_group') === 'inherit'
                ? $this->stripe->createPrepaidTransferForCharge($operation->destination_account_id, $operation->source_charge_id, $operation->amount_cents, $operation->currency, $metadata, 'care-hours:transfer:'.$operation->public_id)
                : $this->stripe->createTransferForCharge($operation->destination_account_id, $operation->source_charge_id, 'care-hours-booking-'.$settlement->care_booking_id, $operation->amount_cents, $operation->currency, $metadata, 'care-hours:transfer:'.$operation->public_id);
        } else {
            $result = $this->stripe->createTransfer($operation->destination_account_id, $operation->amount_cents, $operation->currency, $metadata, 'care-hours:transfer:'.$operation->public_id);
        }
        if (empty($result['id'])) {
            throw new PaymentException('The transfer response is incomplete. Reconcile this attempt before retrying.');
        }
        $operation->update(['status' => 'succeeded', 'stripe_transfer_id' => $result['id'], 'last_error' => null]);
    }

    private function sendReversal(CareHourPaymentOperation $operation): void
    {
        $operation->update(['status' => 'requested']);
        $metadata = ['care_hour_domain' => 'transfer', 'care_hour_operation_id' => $operation->public_id];
        $result = $this->stripe->createTransferReversal($operation->stripe_transfer_id, $operation->amount_cents,
            $metadata, 'care-hours:reversal:'.$operation->public_id);
        $this->recordReversal($operation, $result + ['transfer' => $operation->stripe_transfer_id,
            'currency' => $operation->currency, 'metadata' => $metadata]);
    }

    private function recordReversal(CareHourPaymentOperation $operation, array $object): void
    {
        if (empty($object['id']) || ($object['transfer'] ?? '') !== $operation->stripe_transfer_id
            || (int) ($object['amount'] ?? -1) !== (int) $operation->amount_cents
            || strtolower($object['currency'] ?? '') !== strtolower($operation->currency)
            || data_get($object, 'metadata.care_hour_operation_id') !== $operation->public_id
            || data_get($object, 'metadata.care_hour_domain') !== 'transfer'
            || (data_get($operation->metadata, 'provider_reversal_id') && data_get($operation->metadata, 'provider_reversal_id') !== $object['id'])) {
            throw new PaymentException('Reversal does not match its recorded transfer operation.');
        }
        $operation->update(['status' => 'succeeded', 'last_error' => null,
            'metadata' => array_merge($operation->metadata ?? [], ['provider_reversal_id' => $object['id']])]);
    }

    private function holdForUnmatchedReversals(CareHourSettlement $settlement): bool
    {
        if (! $settlement->operations()->where('kind', 'transfer')->where('metadata->provider_reversal_attention', true)->exists()) {
            return false;
        }
        $settlement->update(['status' => 'payment_attention', 'paid_at' => null,
            'last_error' => 'A provider transfer reversal is not matched to an approved adjustment. Finance review is required before further payout.']);
        $this->syncPayout($settlement->fresh());

        return true;
    }

    private function netPaid(CareHourSettlement $settlement): int
    {
        return max(0, (int) $settlement->operations()->where('kind', 'transfer')->where('status', 'succeeded')->sum('amount_cents')
            - (int) $settlement->operations()->where('kind', 'reversal')->where('status', 'succeeded')->sum('amount_cents'));
    }

    private function syncPayout(CareHourSettlement $settlement): void
    {
        DB::transaction(function () use ($settlement) {
            $booking = $settlement->booking;
            $item = CaregiverPayoutItem::where('care_booking_id', $booking->id)->first();
            $paid = $settlement->status === 'paid';
            $date = ($booking->completed_at ?: now())->toDateString();
            $payout = $item?->payout ?: CaregiverPayout::create([
                'caregiver_user_id' => $booking->caregiver_user_id, 'period_start_on' => $date, 'period_end_on' => $date,
                'scheduled_for' => now(), 'status' => CaregiverPayout::STATUS_PROCESSING,
                'currency' => $settlement->currency, 'gross_amount' => 0, 'adjustments_amount' => 0, 'net_amount' => 0,
            ]);
            CaregiverPayoutItem::updateOrCreate(['care_booking_id' => $booking->id], [
                'caregiver_payout_id' => $payout->id, 'caregiver_user_id' => $booking->caregiver_user_id,
                'care_booking_payment_id' => null, 'financial_reference' => 'CHS-'.$settlement->id,
                'status' => $paid ? CaregiverPayoutItem::STATUS_PAID : CaregiverPayoutItem::STATUS_SCHEDULED,
                'currency' => $settlement->currency, 'gross_amount' => $settlement->caregiver_gross_cents / 100,
                'processing_fee_amount' => (($settlement->package_fee_cents ?? 0) + ($settlement->card_fee_cents ?? 0)) / 100,
                'amount' => ($settlement->caregiver_net_cents ?? 0) / 100,
                'stripe_transfer_ids' => $settlement->operations()->where('kind', 'transfer')->where('status', 'succeeded')->pluck('stripe_transfer_id')->all(),
                'included_at' => now(), 'paid_at' => $paid ? now() : null, 'notes' => 'Care-hours settlement '.$settlement->id,
            ]);
            $all = CaregiverPayoutItem::where('caregiver_payout_id', $payout->id)->get();
            $allPaid = $all->every(fn ($i) => $i->status === CaregiverPayoutItem::STATUS_PAID);
            $payout->update(['status' => $allPaid ? CaregiverPayout::STATUS_PAID : CaregiverPayout::STATUS_PROCESSING,
                'gross_amount' => $all->sum('gross_amount'), 'net_amount' => $all->sum('amount'),
                'adjustments_amount' => $all->sum('amount') - $all->sum('gross_amount'), 'paid_at' => $allPaid ? now() : null]);
        });
        if ($settlement->status !== 'paid' || (int) $settlement->paid_cents <= 0 || ! $settlement->booking->caregiver) {
            return;
        }
        $transfers = $settlement->operations()->where('kind', 'transfer')->where('status', 'succeeded')->orderBy('id')->pluck('id');
        if ($transfers->isEmpty()) {
            return;
        }
        $notify = function () use ($settlement, $transfers): void {
            try {
                $current = $settlement->fresh();
                if (! $current || $current->status !== 'paid' || (int) $current->paid_cents <= 0
                    || (int) $current->revision !== (int) $settlement->revision) {
                    return;
                }
                $settlement = $current;
                $booking = $settlement->booking;
                app(\App\Services\Notifications\MarketplaceNotificationService::class)->notify(
                    recipients: $booking->caregiver, eventKey: \App\Support\MarketplaceEvent::PAYOUT_TRANSFERRED,
                    title: 'Earnings available in Stripe',
                    body: '$'.number_format($settlement->paid_cents / 100, 2).' USD in net earnings for this care-hours visit is available in your Stripe balance. Bank arrival follows your Stripe payout schedule.',
                    url: route('caregiver.earnings.index'), subject: $booking,
                    payload: ['care_booking_id' => $booking->id, 'care_hour_settlement_id' => $settlement->id,
                        'paid_cents' => (int) $settlement->paid_cents],
                    // Reconciliation replay and downward corrections do not represent new money sent.
                    dedupeKey: 'care-hours-payout:'.$settlement->id.':'.hash('sha256', $transfers->implode(',')),
                );
            } catch (\Throwable $error) {
                report($error);
            }
        };
        if (DB::transactionLevel() > 0) {
            DB::afterCommit($notify);
        } else {
            $notify();
        }
    }

    public function handleProviderEvent(string $type, array $object, ?bool $livemode = null, ?string $connectedAccount = null): bool
    {
        $id = data_get($object, 'metadata.care_hour_operation_id');
        $operation = CareHourPaymentOperation::where('kind', 'transfer')->where(function ($q) use ($id, $object) {
            $q->where('public_id', $id ?: '__none__')->orWhere('stripe_transfer_id', $object['id'] ?? '__none__');
        })->first();
        if (! $operation) {
            return false;
        }
        $this->recordTransfer($operation, $object, $livemode, $connectedAccount);
        if (data_get($object, 'reversals.has_more', false)) {
            // Persist any hold before retrieving pages, so a failed lookup cannot leave this marked paid.
            try {
                $current = $this->stripe->findPrepaidTransfer($operation->fresh()->stripe_transfer_id, $operation->public_id,
                    $operation->destination_account_id, $operation->created_at->timestamp - 60);
                if (! $current) {
                    throw new PaymentException('The complete transfer reversal history needs provider review.');
                }
                $this->recordTransfer($operation->fresh(), $current);
            } catch (\Throwable $error) {
                if ($operation->care_hour_settlement_id) {
                    $this->holdForUnmatchedReversals($operation->settlement);
                }
                throw $error;
            }
        }
        if ($operation->care_hour_settlement_id) {
            $settlement = $operation->settlement;
            if (! $this->holdForUnmatchedReversals($settlement)) {
                app(PrepaidBookingSettlementService::class)->reconcileSafely($settlement->id);
            }
        }

        return true;
    }

    private function recordTransfer(CareHourPaymentOperation $operation, array $object, ?bool $livemode = null, ?string $connectedAccount = null): void
    {
        $operation->assertProviderScope($livemode ?? (isset($object['livemode']) ? (bool) $object['livemode'] : null), $connectedAccount);
        $operationId = data_get($object, 'metadata.care_hour_operation_id');
        if (empty($object['id']) || (int) ($object['amount'] ?? 0) !== (int) $operation->amount_cents || ($object['destination'] ?? '') !== $operation->destination_account_id
            || strtolower($object['currency'] ?? '') !== strtolower($operation->currency)
            || ($operationId && $operationId !== $operation->public_id)
            || (data_get($object, 'metadata.care_hour_domain') && data_get($object, 'metadata.care_hour_domain') !== 'transfer')
            || ($operation->stripe_transfer_id && $operation->stripe_transfer_id !== ($object['id'] ?? null))
            || ($object['source_transaction'] ?? null) !== $operation->source_charge_id) {
            throw new PaymentException('Transfer does not match its recorded operation.');
        }
        DB::transaction(function () use ($operation, $object) {
            \App\Models\CareHourAccount::whereKey($operation->funding->care_hour_account_id)->lockForUpdate()->firstOrFail();
            if ($operation->care_hour_settlement_id) {
                CareHourSettlement::whereKey($operation->care_hour_settlement_id)->lockForUpdate()->firstOrFail();
            }
            $current = CareHourPaymentOperation::lockForUpdate()->findOrFail($operation->id);
            if ($current->stripe_transfer_id && $current->stripe_transfer_id !== $object['id']) {
                throw new PaymentException('Transfer does not match its recorded operation.');
            }
            $nestedTotal = 0;
            foreach ((array) data_get($object, 'reversals.data', []) as $reversal) {
                $nestedTotal += (int) ($reversal['amount'] ?? 0);
                $reversalId = data_get($reversal, 'metadata.care_hour_operation_id');
                $owned = CareHourPaymentOperation::where('kind', 'reversal')->where('stripe_transfer_id', $object['id'])
                    ->where('public_id', $reversalId ?: '__none__')->lockForUpdate()->first();
                if ($owned && (int) $owned->amount_cents === (int) ($reversal['amount'] ?? 0)
                    && ($reversal['transfer'] ?? '') === $object['id']
                    && strtolower($reversal['currency'] ?? '') === strtolower($owned->currency)
                    && data_get($reversal, 'metadata.care_hour_domain') === 'transfer') {
                    $this->recordReversal($owned, $reversal);
                }
            }
            $reported = (int) ($object['amount_reversed'] ?? $nestedTotal);
            if ($reported < $nestedTotal || $reported > (int) $current->amount_cents || $reported < 0) {
                throw new PaymentException('Reversal total does not match the recorded transfer.');
            }
            // Reversals cannot be undone by a stale transfer.created/updated event.
            $reversed = max((int) data_get($current->metadata, 'provider_reversed_cents', 0), $reported);
            $matched = (int) CareHourPaymentOperation::where('kind', 'reversal')->where('stripe_transfer_id', $object['id'])
                ->where('status', 'succeeded')->sum('amount_cents');
            $unmatched = max(0, $reversed - $matched);
            $current->update(['status' => 'succeeded', 'stripe_transfer_id' => $object['id'], 'last_error' => null,
                'metadata' => array_merge($current->metadata ?? [], ['provider_reversed_cents' => $reversed,
                    'unmatched_reversal_cents' => $unmatched, 'provider_reversal_attention' => $unmatched > 0])]);
            if ($unmatched > 0 && $current->care_hour_settlement_id) {
                CareHourSettlement::whereKey($current->care_hour_settlement_id)->update(['status' => 'payment_attention', 'paid_at' => null,
                    'last_error' => 'A provider transfer reversal is not matched to an approved adjustment. Finance review is required before further payout.']);
            }
        }, 3);
    }
}
