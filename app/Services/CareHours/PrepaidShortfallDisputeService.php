<?php

namespace App\Services\CareHours;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareHourBookingFunding;
use App\Models\CareHourPaymentOperation;
use App\Models\CareHourSettlement;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Disputes concern collected card money, never the recipient's prepaid-hour ledger. */
class PrepaidShortfallDisputeService
{
    public function __construct(private readonly CareHourPaymentGateway $gateway) {}

    public function handle(array $object, ?bool $livemode = null, ?string $connectedAccount = null): bool
    {
        $charge = $this->objectId($object['charge'] ?? null);
        $intent = $this->objectId($object['payment_intent'] ?? null);
        if ($charge === '' && $intent === '') {
            return false;
        }
        $payment = CareHourPaymentOperation::where('kind', 'shortfall')->where(function ($query) use ($charge, $intent) {
            if ($charge !== '') {
                $query->where('stripe_charge_id', $charge);
            }
            if ($intent !== '') {
                $query->orWhere('stripe_payment_intent_id', $intent);
            }
        })->first();
        if (! $payment) {
            return false;
        }
        $payment->assertProviderScope($livemode ?? (isset($object['livemode']) ? (bool) $object['livemode'] : null), $connectedAccount);
        $funding = $payment->funding;
        Cache::lock('care-hours:settlement:'.$funding->care_booking_id, 300)->block(5, function () use ($payment, $funding, $object, $livemode, $connectedAccount) {
            // Retrieve inside the settlement lock: delayed event bodies cannot roll back a newer outcome.
            $dispute = config('services.stripe.bypass') ? $object : $this->gateway->retrieveDispute((string) ($object['id'] ?? ''));
            $payment = $payment->fresh();
            $payment->assertProviderScope(isset($dispute['livemode']) ? (bool) $dispute['livemode'] : $livemode, $connectedAccount);
            $charge = $this->objectId($dispute['charge'] ?? null);
            $intent = $this->objectId($dispute['payment_intent'] ?? null);
            $state = (string) ($dispute['status'] ?? '');
            if (empty($dispute['id']) || ($dispute['id'] !== ($object['id'] ?? null)) || $charge === ''
                || ($payment->stripe_charge_id && $payment->stripe_charge_id !== $charge)
                || ($intent !== '' && $payment->stripe_payment_intent_id !== $intent)
                || strtolower($dispute['currency'] ?? '') !== strtolower($payment->currency)
                || ! in_array($state, ['warning_needs_response', 'warning_under_review', 'warning_closed', 'needs_response', 'under_review', 'won', 'lost'], true)
                || (int) ($dispute['amount'] ?? 0) <= 0 || (int) ($dispute['amount'] ?? 0) > (int) $payment->captured_cents) {
                throw new PaymentException('The dispute does not match a captured visit payment. Retry after payment reconciliation.');
            }
            DB::transaction(function () use ($payment, $funding, $dispute, $charge, $state) {
                CareHourBookingFunding::lockForUpdate()->findOrFail($funding->id);
                $current = CareHourPaymentOperation::lockForUpdate()->findOrFail($payment->id);
                $operation = CareHourPaymentOperation::firstOrNew(['operation_key' => 'shortfall-dispute:'.$dispute['id']]);
                if ($operation->exists && (int) $operation->care_hour_booking_funding_id !== (int) $funding->id) {
                    throw new PaymentException('The dispute is already linked to another visit.');
                }
                if ($operation->exists && (int) $operation->amount_cents !== (int) $dispute['amount']) {
                    throw new PaymentException('The dispute amount changed. Support review is required before adjusting transfers.');
                }
                // Preserve terminal decisions against stale test/replay payloads; a real late win is permitted.
                if ($operation->exists && ($operation->status === 'won' && $state !== 'won'
                    || in_array($operation->status, ['lost', 'warning_closed'], true) && ! in_array($state, ['won', 'lost', 'warning_closed'], true))) {
                    return;
                }
                $changed = ! $operation->exists || $operation->status !== $state || (int) $operation->amount_cents !== (int) $dispute['amount'];
                $reversed = $operation->exists && CareHourPaymentOperation::where('kind', 'reversal')
                    ->where('metadata->dispute_operation_id', $operation->id)->whereNotIn('status', ['cancelled', 'failed'])->exists();
                $operation->fill(['public_id' => $operation->public_id ?: (string) Str::uuid(),
                    'care_hour_booking_funding_id' => $funding->id,
                    'care_hour_settlement_id' => CareHourSettlement::where('care_booking_id', $funding->care_booking_id)->value('id'),
                    'kind' => 'dispute', 'status' => $state, 'amount_cents' => (int) $dispute['amount'],
                    'currency' => $current->currency, 'livemode' => $current->livemode,
                    'stripe_charge_id' => $charge, 'stripe_payment_intent_id' => $current->stripe_payment_intent_id,
                    'metadata' => array_merge($operation->metadata ?? [], ['dispute_id' => $dispute['id'], 'payment_operation_id' => $current->id,
                        'reason' => $dispute['reason'] ?? null])])->save();
                $metadata = $current->metadata ?? [];
                $metadata['disputes'][$dispute['id']] = $state;
                if ($state === 'won' && $reversed) {
                    $metadata['dispute_recovery_required'] = true;
                }
                $current->update(['stripe_charge_id' => $charge, 'metadata' => $metadata]);
                if ($changed) {
                    CareHourSettlement::where('care_booking_id', $funding->care_booking_id)->increment('revision', 1);
                }
                if (app(PrepaidBookingFundingService::class)->hasDisputedShortfall($funding)) {
                    if ($funding->fresh()->status !== 'released') {
                        $funding->update(['status' => 'payment_attention', 'last_error' => 'A visit card payment is disputed. Support review is required.']);
                    }
                    CareHourSettlement::where('care_booking_id', $funding->care_booking_id)->update([
                        'status' => 'funding_on_hold', 'payable_cents' => null, 'paid_at' => null,
                        'last_error' => 'A visit card payment is disputed. Support review is required.',
                    ]);
                }
            }, 3);
            $this->reconcile($funding);
        });
        // Do not automatically recharge a disputed amount or repay a reversed loss after a late win.
        if (! app(PrepaidBookingFundingService::class)->hasDisputedShortfall($funding)) {
            $settlement = CareHourSettlement::where('care_booking_id', $funding->care_booking_id)->first();
            if ($settlement) {
                app(PrepaidBookingSettlementService::class)->reconcileSafely($settlement->id);
            } elseif ($funding->fresh()->status !== 'released') {
                app(PrepaidBookingFundingService::class)->prepare($funding->booking);
            }
        }

        return true;
    }

    /** Called under the settlement lock by both webhook and scheduled reconciliation. */
    public function reconcile(CareHourBookingFunding $funding): void
    {
        foreach ($funding->operations()->where('kind', 'dispute')->where('status', 'lost')->orderBy('id')->get() as $dispute) {
            $payment = $funding->operations()->where('kind', 'shortfall')->findOrFail(data_get($dispute->metadata, 'payment_operation_id'));
            app(PrepaidTransferPlanner::class)->reverseDisputedShortfall($payment, $dispute);
        }
    }

    private function objectId(mixed $value): string
    {
        return is_array($value) ? (string) ($value['id'] ?? '') : (string) ($value ?? '');
    }
}
