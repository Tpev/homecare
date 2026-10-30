<?php

namespace App\Services\CareHours;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareBooking;
use App\Models\CareHourPaymentOperation;
use App\Models\CareHourSettlement;
use App\Services\Payments\StripeClient;
use App\Support\MarketplacePricing;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PrepaidBookingSettlementService
{
    public function __construct(
        private readonly CareHourLedgerService $ledger,
        private readonly PrepaidBookingFundingService $funding,
        private readonly StripeClient $stripe,
        private readonly MarketplacePricing $pricing,
        private readonly CareHourPaymentGateway $gateway,
    ) {}

    public function settle(CareBooking $booking): CareHourSettlement
    {
        return Cache::lock('care-hours:settlement:'.$booking->id, 300)->block(5, fn () => $this->settleLocked($booking->id));
    }

    private function settleLocked(int $bookingId): CareHourSettlement
    {
        $settlement = DB::transaction(function () use ($bookingId) {
            // A callback may have loaded this visit before waiting for a correction to finish.
            // Keep the authoritative booking locked through the ledger and settlement changes.
            $booking = CareBooking::query()->lockForUpdate()->findOrFail($bookingId);
            $record = $this->funding->forBooking($booking) ?? throw new PaymentException('This visit has no committed care-hours funding.');
            if ($booking->dispute_status === 'open') {
                throw new PaymentException('Resolve the visit dispute before settling care hours.');
            }
            if (! in_array($booking->status, [CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED], true)) {
                throw new PaymentException('Approve completed care before using prepaid hours.');
            }
            if ($record->status === 'released') {
                throw new PaymentException('This visit funding was released. Review it before settlement.');
            }
            $minutes = max(0, (int) ($booking->worked_minutes ?? $booking->expected_minutes ?? 0));
            \App\Models\FamilyAccount::whereKey($record->account->family_account_id)->lockForUpdate()->firstOrFail();
            \App\Models\CareHourAccount::whereKey($record->care_hour_account_id)->lockForUpdate()->firstOrFail();
            $current = CareHourSettlement::where('care_booking_id', $booking->id)->lockForUpdate()->first();
            $original = $current ? (int) $current->prepaid_minutes : (int) $record->reserved_minutes;
            // Preserve the initial mixed split. Only an actual overrun can seek more available hours.
            $prepaidTarget = min($minutes, $original + max(0, $minutes - max((int) $record->planned_minutes, (int) ($current?->worked_minutes ?? 0))));
            $usage = $this->ledger->settle($booking, $prepaidTarget);
            $used = (int) $usage['used_minutes'];
            $changed = $current && ((int) $current->worked_minutes !== $minutes || (int) $current->prepaid_minutes !== $used
                || $current->package_fee_cents !== $usage['processing_fee_cents']);

            return CareHourSettlement::updateOrCreate(['care_booking_id' => $booking->id], [
                'care_hour_booking_funding_id' => $record->id, 'worked_minutes' => $minutes,
                'prepaid_minutes' => $used, 'card_minutes' => max(0, $minutes - $used),
                'caregiver_gross_cents' => $minutes === 0 ? 0 : $this->pricing->prorateHourlyCents((int) ($booking->caregiver_gross_rate_cents ?? $this->pricing->caregiverGrossHourlyCents()), $minutes),
                'package_fee_cents' => $usage['processing_fee_cents'], 'currency' => $record->currency,
                'revision' => (int) ($current?->revision ?? 1) + ($changed ? 1 : 0),
                'approved_at' => $current?->approved_at ?: now(),
                'allocation_snapshot' => $usage['allocations']->map(fn ($a) => ['id' => $a->id, 'lot_id' => $a->care_hour_lot_id, 'minutes' => $a->minutes, 'start_minute' => $a->start_minute, 'fee_cents' => $a->feeCents()])->all(),
            ]);
        }, 3);

        // Some legacy approval callers own a DB transaction; never perform provider I/O under that lock.
        if (DB::transactionLevel() > 0) {
            DB::afterCommit(fn () => $this->reconcileSafely($settlement->id));

            return $settlement;
        }

        return $this->reconcileLocked($settlement);
    }

    public function reconcileSafely(int $id): void
    {
        try {
            $this->reconcile(CareHourSettlement::findOrFail($id));
        } catch (\Throwable $error) {
            CareHourSettlement::whereKey($id)->where('status', '!=', 'reconciliation_pending')->update(['status' => 'payment_attention', 'last_error' => $error->getMessage()]);
            report($error);
        }
    }

    public function reconcile(CareHourSettlement $settlement): CareHourSettlement
    {
        return Cache::lock('care-hours:settlement:'.$settlement->care_booking_id, 300)->block(5, fn () => $this->reconcileLocked($settlement->fresh()));
    }

    /** Serialize prepaid dispute/admin state changes with settlement and direct payout attempts. */
    public function updateVisitState(CareBooking $booking, array $attributes, ?array $allowedStatuses = null): bool
    {
        return Cache::lock('care-hours:settlement:'.$booking->id, 300)->block(5, function () use ($booking, $attributes, $allowedStatuses) {
            return DB::transaction(function () use ($booking, $attributes, $allowedStatuses) {
                $current = CareBooking::lockForUpdate()->findOrFail($booking->id);
                if ($allowedStatuses !== null && ! in_array($current->status, $allowedStatuses, true)) {
                    return false;
                }
                $current->update($attributes);
                if ($current->status === CareBooking::STATUS_CANCELLED) {
                    // release() defers provider recovery until after this transaction commits.
                    $this->funding->release($current);
                }
                if ($settlement = CareHourSettlement::where('care_booking_id', $current->id)->first()) {
                    $this->holdForVisitState($settlement);
                }
                $booking->refresh();

                return true;
            }, 3);
        });
    }

    /** Recheck live state at each money-moving boundary, never an eager-loaded booking. */
    public function holdForVisitState(CareHourSettlement $settlement): bool
    {
        $booking = CareBooking::findOrFail($settlement->care_booking_id);
        $funding = $settlement->funding()->firstOrFail();
        $settlement->setRelation('booking', $booking);
        $settlement->setRelation('funding', $funding);
        $reason = match (true) {
            $booking->dispute_status === 'open', $booking->status === CareBooking::STATUS_DISPUTED => 'Resolve the visit dispute before settling care hours.',
            $funding->status === 'released' => 'This visit funding was released. Review it before settlement.',
            ! in_array($booking->status, [CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED], true) => 'Approve completed care before using prepaid hours.',
            default => null,
        };
        if ($reason === null) {
            return false;
        }
        // Keep financial history and reserved operation identities for recovery; a hold is not a reversal.
        $paid = max(0, (int) $settlement->operations()->where('kind', 'transfer')->where('status', 'succeeded')->sum('amount_cents')
            - (int) $settlement->operations()->where('kind', 'reversal')->where('status', 'succeeded')->sum('amount_cents'));
        $settlement->update(['status' => 'funding_on_hold', 'payable_cents' => null, 'paid_at' => null,
            'paid_cents' => $paid, 'last_error' => $reason]);

        return true;
    }

    private function reconcileLocked(CareHourSettlement $settlement): CareHourSettlement
    {
        if ($this->funding->hasDisputedShortfall($settlement->funding)) {
            app(PrepaidShortfallDisputeService::class)->reconcile($settlement->funding);
            $settlement->update([
                'status' => 'funding_on_hold', 'payable_cents' => null, 'paid_at' => null,
                'last_error' => 'A visit payment has a dispute. Support must review it before settlement.',
            ]);

            return $settlement->fresh();
        }
        if ($this->holdForVisitState($settlement)) {
            app(PrepaidTransferPlanner::class)->recoverHeldOperations($settlement);

            return $settlement->fresh();
        }
        $booking = $settlement->booking;
        $allocations = $this->ledger->allocationsFor($booking)->where('status', 'used');
        if ($allocations->contains(fn ($a) => $a->lot->status !== 'available')) {
            $settlement->update(['status' => 'funding_on_hold', 'last_error' => 'A contributing purchase is on hold. Review it before any new payout.']);

            return $settlement->fresh();
        }
        $packageFee = $allocations->contains(fn ($a) => $a->feeCents() === null) ? null : (int) $allocations->sum(fn ($a) => $a->feeCents());
        $settlement->update(['package_fee_cents' => $packageFee]);
        $target = $this->funding->amountForMinutes($booking, (int) $settlement->card_minutes);
        $operations = $settlement->funding->operations()->where('kind', 'shortfall')->orderBy('id')->get();
        $remaining = $target;
        $cash = 0;
        $fees = 0;
        $feesKnown = true;
        foreach ($operations as $operation) {
            if ($this->holdForVisitState($settlement)) {
                return $settlement->fresh();
            }
            if (! $this->funding->operationIsUsable($operation)) {
                continue;
            }
            $take = min($remaining, (int) $operation->amount_cents);
            if ($operation->status === 'authorized') {
                if ($take === 0) {
                    $this->stripe->cancelPaymentIntent($operation->stripe_payment_intent_id);
                    $operation->update(['status' => 'cancelled']);
                } else {
                    // Persist immutable capture parameters before any provider call.
                    $capture = CareHourPaymentOperation::firstOrCreate(['operation_key' => 'capture:'.$operation->id], [
                        'public_id' => (string) Str::uuid(), 'care_hour_booking_funding_id' => $operation->care_hour_booking_funding_id,
                        'care_hour_settlement_id' => $settlement->id, 'kind' => 'capture', 'amount_cents' => $take,
                        'currency' => $operation->currency, 'stripe_payment_intent_id' => $operation->stripe_payment_intent_id,
                    ]);
                    if ($capture->created_at->lt(now()->subHours(23))) {
                        $known = $this->stripe->retrieveCareHourPaymentFinancials($operation->stripe_payment_intent_id, $capture->amount_cents);
                        if (($known['status'] ?? '') !== 'succeeded') {
                            throw new PaymentException('Payment outcome needs support review before retrying.');
                        }
                        $result = $known;
                    } else {
                        if ($this->holdForVisitState($settlement)) {
                            return $settlement->fresh();
                        }
                        $result = $this->stripe->capturePaymentIntent($operation->stripe_payment_intent_id, $capture->amount_cents, 'care-hours:capture:'.$capture->public_id);
                    }
                    if (($result['status'] ?? '') !== 'succeeded') {
                        throw new PaymentException('The visit shortfall has not finished payment.');
                    }
                    $capture->update(['status' => 'succeeded']);
                    $operation->update(['status' => 'captured', 'captured_cents' => (int) ($result['amount_received'] ?? $take),
                        'stripe_charge_id' => $result['latest_charge_id'] ?? $operation->stripe_charge_id]);
                }
            }
            if ($operation->status !== 'captured') {
                continue;
            }
            $net = max(0, (int) $operation->captured_cents - (int) $operation->refunded_cents);
            if ($net > $take) {
                $this->refundExcess($settlement, $operation, $net - $take);
                $operation->refresh();
                $net = max(0, $operation->captured_cents - $operation->refunded_cents);
            }
            $financials = $this->stripe->retrieveCareHourPaymentFinancials($operation->stripe_payment_intent_id, $operation->captured_cents);
            if (($financials['fee_finalized'] ?? false) && isset($financials['processing_fee_cents'])) {
                $operation->update(['processing_fee_cents' => max(0, (int) $financials['processing_fee_cents']), 'stripe_charge_id' => $financials['latest_charge_id'] ?? $operation->stripe_charge_id]);
            }
            if ($net > 0 && $operation->processing_fee_cents === null) {
                $feesKnown = false;
            } elseif ($net > 0) {
                $fees += $operation->processing_fee_cents;
            }
            $cash += $net;
            $remaining = max(0, $remaining - $net);
        }
        $netEarnings = $feesKnown && $settlement->package_fee_cents !== null
            ? (int) $settlement->caregiver_gross_cents - (int) $settlement->package_fee_cents - $fees : null;
        $coveredMinutes = (int) $settlement->prepaid_minutes + ($target > 0 ? (int) floor($settlement->card_minutes * min($cash, $target) / $target) : 0);
        $coveredGross = $coveredMinutes > 0 ? $this->pricing->prorateHourlyCents((int) ($booking->caregiver_gross_rate_cents ?? $this->pricing->caregiverGrossHourlyCents()), $coveredMinutes) : 0;
        $payable = $netEarnings === null || $netEarnings < 0 ? null : max(0, min($netEarnings, $coveredGross - (int) $packageFee - $fees));
        if ($this->holdForVisitState($settlement)) {
            return $settlement->fresh();
        }
        if ((int) $settlement->fresh()->revision !== (int) $settlement->revision) {
            throw new PaymentException('Visit funding changed during reconciliation. It will be checked again safely.');
        }
        $updated = CareHourSettlement::whereKey($settlement->id)->where('revision', $settlement->revision)->update([
            'amount_due_cents' => max(0, $target - $cash), 'card_fee_cents' => $feesKnown ? $fees : null,
            'caregiver_net_cents' => $netEarnings === null || $netEarnings < 0 ? null : $netEarnings,
            'payable_cents' => $payable,
            'status' => $netEarnings !== null && $netEarnings < 0 ? 'payment_attention' : ($remaining > 0 ? 'partially_settled' : ($netEarnings === null ? 'fee_pending' : 'settled')),
            'last_error' => $netEarnings !== null && $netEarnings < 0 ? 'Processing fees exceed earnings; finance review is required.' : null,
        ]);
        if (! $updated) {
            throw new PaymentException('Visit funding changed during reconciliation. It will be checked again safely.');
        }
        $settlement->refresh();
        // Pay collected coverage; preserve all approved uncollected earnings separately.
        if ($settlement->payable_cents !== null) {
            app(PrepaidTransferPlanner::class)->pay($settlement->fresh(), settlementLockHeld: true);
        }
        if ((int) $settlement->amount_due_cents > 0) {
            $this->funding->notifyPaymentAttention($settlement->funding, $settlement);
        }
        $this->notifySettledVisit($settlement->fresh());

        return $settlement->fresh();
    }

    private function notifySettledVisit(CareHourSettlement $settlement): void
    {
        if ((int) $settlement->amount_due_cents !== 0
            || ! in_array($settlement->status, ['settled', 'fee_pending', 'payout_pending', 'paid'], true)
            || ! $settlement->booking->family) {
            return;
        }
        $notify = function () use ($settlement): void {
            try {
                $current = $settlement->fresh();
                if (! $current || (int) $current->revision !== (int) $settlement->revision
                    || (int) $current->amount_due_cents !== 0
                    || ! in_array($current->status, ['settled', 'fee_pending', 'payout_pending', 'paid'], true)) {
                    return;
                }
                $settlement = $current;
                $booking = $settlement->booking;
                $cash = $this->funding->amountForMinutes($booking, (int) $settlement->card_minutes);
                app(\App\Services\Notifications\MarketplaceNotificationService::class)->notify(
                    recipients: $booking->family, eventKey: \App\Support\MarketplaceEvent::CARE_HOURS_VISIT_SETTLED,
                    title: 'Care hours used for your visit',
                    body: \App\Models\CareHourAccount::formatMinutes((int) $settlement->prepaid_minutes).' of care hours were used for your approved visit. '
                        .($cash > 0 ? 'Additional visit payment: $'.number_format($cash / 100, 2).' USD.' : 'No additional visit payment was needed.'),
                    url: route('family.requests.show', $booking->care_request_id), subject: $booking,
                    payload: ['care_booking_id' => $booking->id, 'care_hour_settlement_id' => $settlement->id,
                        'prepaid_minutes' => (int) $settlement->prepaid_minutes, 'card_cents' => $cash],
                    dedupeKey: 'care-hours-settled:'.$settlement->id.':revision:'.$settlement->revision,
                );
            } catch (\Throwable $error) {
                // Delivery failure cannot turn confirmed payment into a failed settlement.
                report($error);
            }
        };
        if (DB::transactionLevel() > 0) {
            DB::afterCommit($notify);
        } else {
            $notify();
        }
    }

    private function refundExcess(CareHourSettlement $settlement, CareHourPaymentOperation $payment, int $amount): void
    {
        $this->refundPayment($payment, (int) $payment->refunded_cents + $amount, $settlement);
    }

    public function refundUnused(CareHourPaymentOperation $payment): void
    {
        $this->refundPayment($payment, (int) $payment->captured_cents);
    }

    private function refundPayment(CareHourPaymentOperation $payment, int $targetRefundCents, ?CareHourSettlement $settlement = null): void
    {
        Cache::lock('care-hours:refund:'.$payment->id, 300)->block(5, function () use ($payment, $targetRefundCents, $settlement) {
            while (($remaining = $targetRefundCents - (int) $payment->refresh()->refunded_cents) > 0) {
                if ($this->funding->operationHasDispute($payment)) {
                    throw new PaymentException('This visit payment has a dispute. Support must resolve it before issuing a refund.');
                }
                // A later revision/cancellation must also resolve an older attempt before issuing money.
                $attempts = CareHourPaymentOperation::where('kind', 'refund')
                    ->where('stripe_payment_intent_id', $payment->stripe_payment_intent_id)->orderBy('id')->get();
                $replaced = $attempts->pluck('metadata.retry_of_operation_id')->filter()->all();
                $operation = $attempts->first(fn ($attempt) => $attempt->status !== 'succeeded' && ! in_array($attempt->id, $replaced, true));
                $retryOf = null;
                if ($operation) {
                    $wasTerminal = in_array($operation->status, ['failed', 'canceled'], true);
                    $result = $this->gateway->findShortfallRefund($operation);
                    if ($result) {
                        $this->recordRefundResult($operation, $result);
                        $operation->refresh();
                        if ($operation->status === 'succeeded') {
                            continue;
                        }
                        if (in_array($operation->status, ['failed', 'canceled'], true)) {
                            // Only the current provider result can authorize a distinct retry attempt.
                            $retryOf = $operation;
                            $operation = null;
                        } else {
                            throw new PaymentException('The shortfall refund is still processing.');
                        }
                    } elseif ($wasTerminal || $operation->created_at->lt(now()->subHours(23))) {
                        throw new PaymentException('Refund outcome needs support review before retrying.');
                    }
                }
                if (! $operation) {
                    if ($settlement && $this->holdForVisitState($settlement)) {
                        return;
                    }
                    $amount = $retryOf ? (int) $retryOf->amount_cents : $remaining;
                    if ($amount > $remaining) {
                        throw new PaymentException('The failed refund amount no longer matches the visit. Support review is required.');
                    }
                    $publicId = (string) Str::uuid();
                    $operation = CareHourPaymentOperation::create([
                        'public_id' => $publicId, 'operation_key' => 'shortfall-refund:'.$payment->id.':'.$publicId,
                        'care_hour_booking_funding_id' => $payment->care_hour_booking_funding_id,
                        'care_hour_settlement_id' => $settlement?->id ?? $retryOf?->care_hour_settlement_id,
                        'kind' => 'refund', 'amount_cents' => $amount, 'currency' => $payment->currency,
                        'stripe_payment_intent_id' => $payment->stripe_payment_intent_id,
                        'stripe_charge_id' => $payment->stripe_charge_id, 'livemode' => $payment->livemode,
                        'metadata' => $retryOf ? ['retry_of_operation_id' => $retryOf->id, 'confirmed_terminal_refund_id' => $retryOf->stripe_refund_id] : [],
                    ]);
                }
                // Unknown attempts may only replay their original immutable key within its safe window.
                if ((int) $operation->amount_cents > $remaining) {
                    throw new PaymentException('The unresolved refund needs support review before the visit can be reconciled.');
                }
                if ($settlement && $this->holdForVisitState($settlement)) {
                    return;
                }
                $this->recordRefundResult($operation, $this->gateway->createShortfallRefund($operation));
                if ($operation->fresh()->status !== 'succeeded') {
                    throw new PaymentException('The shortfall refund needs another provider status check.');
                }
            }
        });
    }

    private function recordRefundResult(CareHourPaymentOperation $operation, array $result): void
    {
        if (data_get($result, 'metadata.care_hour_operation_id') !== $operation->public_id) {
            throw new PaymentException('Refund does not match its recorded operation.');
        }
        $this->handleRefundEvent($result);
    }

    public function handleRefundEvent(array $object, ?bool $livemode = null, ?string $connectedAccount = null): bool
    {
        $id = data_get($object, 'metadata.care_hour_operation_id');
        $operation = CareHourPaymentOperation::where('kind', 'refund')->where(function ($q) use ($id, $object) {
            $q->where('public_id', $id ?: '__none__')->orWhere('stripe_refund_id', $object['id'] ?? '__none__');
        })->first();
        if (! $operation) {
            return false;
        }
        $operation->assertProviderScope($livemode ?? (isset($object['livemode']) ? (bool) $object['livemode'] : null), $connectedAccount);
        if (empty($object['id']) || (int) ($object['amount'] ?? 0) !== (int) $operation->amount_cents
            || (isset($object['currency']) && strtolower($object['currency']) !== strtolower($operation->currency))
            || (isset($object['payment_intent']) && $object['payment_intent'] !== $operation->stripe_payment_intent_id)
            || (isset($object['charge']) && $operation->stripe_charge_id && $object['charge'] !== $operation->stripe_charge_id)
            || ($id && $id !== $operation->public_id)
            || (data_get($object, 'metadata.care_hour_domain') && data_get($object, 'metadata.care_hour_domain') !== 'shortfall')
            || ($operation->stripe_refund_id && $operation->stripe_refund_id !== ($object['id'] ?? null))) {
            throw new PaymentException('Refund does not match its recorded operation.');
        }
        DB::transaction(function () use ($operation, $object) {
            $locked = CareHourPaymentOperation::lockForUpdate()->findOrFail($operation->id);
            if ($locked->status === 'succeeded') {
                return;
            }
            $status = in_array($object['status'] ?? '', ['succeeded', 'failed', 'canceled'], true) ? $object['status'] : 'pending';
            $locked->update(['status' => $status, 'stripe_refund_id' => $object['id'],
                'last_error' => in_array($status, ['failed', 'canceled'], true) ? 'Provider refund '.$status.'. Recheck before retrying.' : null]);
            if ($status !== 'succeeded') {
                return;
            }
            $sum = (int) CareHourPaymentOperation::where('kind', 'refund')->where('stripe_payment_intent_id', $locked->stripe_payment_intent_id)->where('status', 'succeeded')->sum('amount_cents');
            $payment = CareHourPaymentOperation::where('kind', 'shortfall')->where('stripe_payment_intent_id', $locked->stripe_payment_intent_id)->lockForUpdate()->first();
            $payment?->update(['refunded_cents' => max((int) $payment->refunded_cents, $sum)]);
        });

        return true;
    }

    public function handleChargeRefundEvent(array $object, ?bool $livemode = null, ?string $connectedAccount = null): bool
    {
        $payment = CareHourPaymentOperation::where('kind', 'shortfall')->where('stripe_charge_id', $object['id'] ?? '__none__')->first();
        if (! $payment) {
            return false;
        }
        $payment->assertProviderScope($livemode ?? (isset($object['livemode']) ? (bool) $object['livemode'] : null), $connectedAccount);
        if ((isset($object['currency']) && strtolower($object['currency']) !== strtolower($payment->currency))
            || (isset($object['payment_intent']) && $object['payment_intent'] !== $payment->stripe_payment_intent_id)) {
            throw new PaymentException('Refunded charge does not match the recorded visit payment.');
        }
        $amount = (int) ($object['amount_refunded'] ?? 0);
        if ($amount < 0 || $amount > (int) $payment->amount_cents) {
            throw new PaymentException('Refund total exceeds the authorized visit payment.');
        }
        // Stripe also emits charge.refunded when a manual authorization is voided. No cash was
        // captured in that case: confirm the original canceled intent before recording cancellation.
        if (($object['captured'] ?? null) === false && ($object['amount_captured'] ?? null) === 0
            && (int) $payment->captured_cents === 0) {
            $financials = $this->gateway->financials($payment->stripe_payment_intent_id);
            $payment->assertProviderScope(isset($financials['livemode']) ? (bool) $financials['livemode'] : null, $financials['connected_account'] ?? null);
            if (($financials['status'] ?? '') !== 'canceled' || ($financials['amount_received'] ?? null) !== 0
                || ($financials['authorized_cents'] ?? null) !== 0 || ! isset($financials['livemode'])
                || ($financials['payment_intent_id'] ?? '') !== $payment->stripe_payment_intent_id
                || ($financials['charge_id'] ?? '') !== $payment->stripe_charge_id
                || (int) ($financials['amount_cents'] ?? -1) !== (int) $payment->amount_cents
                || strtolower((string) ($financials['currency'] ?? '')) !== strtolower($payment->currency)
                || data_get($financials, 'metadata.care_hour_domain') !== 'shortfall'
                || data_get($financials, 'metadata.care_hour_operation_id') !== $payment->public_id
                || $amount < 0 || $amount > (int) $payment->amount_cents) {
                throw new PaymentException('The authorization release does not match the recorded uncaptured visit payment.');
            }
            DB::transaction(function () use ($payment): void {
                $locked = CareHourPaymentOperation::query()->lockForUpdate()->findOrFail($payment->id);
                if ((int) $locked->captured_cents !== 0 || (int) $locked->refunded_cents !== 0) {
                    throw new PaymentException('The visit payment changed during authorization release. Retry reconciliation.');
                }
                $locked->update(['status' => 'cancelled', 'last_error' => null]);
            });
            $booking = $payment->funding->booking;
            if ($booking && $booking->status !== CareBooking::STATUS_CANCELLED && $payment->funding->status !== 'released') {
                $this->funding->prepare($booking);
            }

            return true;
        }
        // Partial captures can include an automatic authorization release in the raw charge
        // total. Use only provider-classified cash refunds, including paginated history.
        $financials = $this->gateway->financials($payment->stripe_payment_intent_id);
        $payment->assertProviderScope(isset($financials['livemode']) ? (bool) $financials['livemode'] : null, $financials['connected_account'] ?? null);
        if (($financials['status'] ?? '') !== 'succeeded' || ! isset($financials['livemode'])
            || ($financials['payment_intent_id'] ?? '') !== $payment->stripe_payment_intent_id
            || ($financials['charge_id'] ?? '') !== $payment->stripe_charge_id
            || (int) ($financials['amount_cents'] ?? -1) !== (int) $payment->amount_cents
            || (int) ($financials['amount_received'] ?? -1) !== (int) $payment->captured_cents
            || strtolower((string) ($financials['currency'] ?? '')) !== strtolower($payment->currency)
            || data_get($financials, 'metadata.care_hour_domain') !== 'shortfall'
            || data_get($financials, 'metadata.care_hour_operation_id') !== $payment->public_id
            || ! ($financials['refund_financials_finalized'] ?? false) || ! isset($financials['amount_refunded_cents'])) {
            throw new PaymentException('The captured visit refund is not ready for authoritative reconciliation.');
        }
        $amount = (int) $financials['amount_refunded_cents'];
        if ($amount < 0 || $amount > (int) $payment->captured_cents) {
            throw new PaymentException('Refund total exceeds the captured visit payment.');
        }
        $payment->update(['refunded_cents' => max((int) $payment->refunded_cents, $amount)]);
        if ($payment->funding->booking) {
            $settlement = CareHourSettlement::where('care_booking_id', $payment->funding->care_booking_id)->first();
            if ($settlement) {
                $this->reconcileSafely($settlement->id);
            }
        }

        return true;
    }
}
