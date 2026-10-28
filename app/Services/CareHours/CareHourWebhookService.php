<?php

namespace App\Services\CareHours;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareHourAccount;
use App\Models\CareHourPurchase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Return false only for an event owned by the pre-existing payment integration. */
class CareHourWebhookService
{
    public function __construct(
        private readonly CareHourPurchaseService $purchases,
        private readonly CareHourPaymentGateway $gateway,
        private readonly CareHourLedgerService $ledger,
    ) {}

    public function handle(string $type, array $object, ?bool $livemode = null, ?string $connectedAccount = null): bool
    {
        $domain = (string) data_get($object, 'metadata.care_hour_domain', '');
        // Existing booking/setup objects continue through their original handler without new lookups.
        if ($domain === '' && (data_get($object, 'metadata.care_booking_id') || ($object['mode'] ?? '') === 'setup')) {
            return false;
        }
        if (! Schema::hasTable('care_hour_purchases')) {
            return false;
        }
        if (str_starts_with($type, 'charge.dispute.')
            && app(PrepaidShortfallDisputeService::class)->handle($object, $livemode, $connectedAccount)) {
            return true;
        }
        if (str_starts_with($type, 'transfer.') && class_exists(PrepaidTransferPlanner::class)
            && app(PrepaidTransferPlanner::class)->handleProviderEvent($type, $object, $livemode, $connectedAccount)) {
            return true;
        }
        if (str_starts_with($type, 'refund.') && class_exists(PrepaidBookingSettlementService::class)
            && app(PrepaidBookingSettlementService::class)->handleRefundEvent($object, $livemode, $connectedAccount)) {
            return true;
        }
        if ($type === 'charge.refunded' && class_exists(PrepaidBookingSettlementService::class)
            && app(PrepaidBookingSettlementService::class)->handleChargeRefundEvent($object, $livemode, $connectedAccount)) {
            return true;
        }
        if ($domain === 'shortfall') {
            $this->assertPlatform($connectedAccount);
            if ($type === 'charge.refunded') {
                // This refund is ours, but the charge-to-payment link can lag the signed
                // event. Keep it retryable; only capture completion may establish that link.
                $operation = \App\Models\CareHourPaymentOperation::where('kind', 'shortfall')
                    ->where('public_id', (string) data_get($object, 'metadata.care_hour_operation_id', ''))->first();
                if (! $operation) {
                    throw new PaymentException('Visit refund dependencies are not ready for reconciliation.');
                }
                $operation->assertProviderScope($livemode ?? (isset($object['livemode']) ? (bool) $object['livemode'] : null), $connectedAccount);
                if (empty($object['id']) || empty($object['payment_intent'])
                    || ($operation->stripe_payment_intent_id && $operation->stripe_payment_intent_id !== $object['payment_intent'])
                    || ($operation->stripe_charge_id && $operation->stripe_charge_id !== $object['id'])) {
                    throw new PaymentException('Refunded charge does not match the recorded visit payment.');
                }
                throw new PaymentException('Visit refund is waiting for the original payment charge to be recorded. Retry reconciliation.');
            }
            $this->handleShortfall($type, $object, $livemode);

            return true;
        }
        $publicId = (string) data_get($object, 'metadata.care_hour_purchase_id', '');
        $purchase = $publicId !== '' ? CareHourPurchase::query()->where('public_id', $publicId)->first() : null;
        if (! $purchase && $domain === 'purchase') {
            throw new PaymentException('Package payment dependencies are not ready for reconciliation.');
        }
        if (! $purchase) {
            $chargeId = is_array($object['charge'] ?? null) ? (string) data_get($object, 'charge.id') : (string) ($object['charge'] ?? '');
            if ($type === 'charge.refunded') {
                $chargeId = (string) ($object['id'] ?? '');
            }
            if ($chargeId !== '') {
                $purchase = CareHourPurchase::query()->where('stripe_charge_id', $chargeId)->first();
            } elseif (str_starts_with($type, 'payment_intent.')) {
                $purchase = CareHourPurchase::query()->where('stripe_payment_intent_id', (string) ($object['id'] ?? ''))->first();
            }
        }
        if (! $purchase) {
            return false;
        }
        $this->assertPlatform($connectedAccount);
        if ($purchase->livemode !== null && $livemode !== null && $purchase->livemode !== $livemode) {
            throw new PaymentException('Package payment environment does not match the purchase.');
        }
        if (str_starts_with($type, 'checkout.session.')) {
            if (! $purchase->checkout_requested_at || ($purchase->stripe_checkout_session_id && $purchase->stripe_checkout_session_id !== ($object['id'] ?? ''))) {
                throw new PaymentException('The checkout session does not match this purchase attempt.');
            }
            if (! $purchase->stripe_checkout_session_id) {
                $purchase->forceFill(['stripe_checkout_session_id' => (string) $object['id']])->save();
            }
            // Never issue from the event's payment_status or the browser return alone.
            $this->purchases->refresh($purchase->fresh());
            if ($type === 'checkout.session.async_payment_failed') {
                $this->purchases->queueBuyerAttention($purchase->fresh());
            }
        } elseif (str_starts_with($type, 'payment_intent.')) {
            $result = $this->gateway->financials((string) $object['id']);
            $this->assertEnvironment($result, $livemode);
            if (($result['status'] ?? '') === 'succeeded') {
                $this->purchases->acceptPaid($purchase, $result);
            } elseif (($result['status'] ?? '') === 'canceled') {
                $this->purchases->markFailed($purchase);
            } elseif ($type === 'payment_intent.payment_failed' && ($result['status'] ?? '') === 'requires_payment_method') {
                $this->purchases->queueBuyerAttention($purchase->fresh());
            }
        } elseif (str_starts_with($type, 'charge.dispute.')) {
            $dispute = config('services.stripe.bypass') ? $object : $this->gateway->retrieveDispute((string) $object['id']);
            $disputeCharge = is_array($dispute['charge'] ?? null) ? (string) data_get($dispute, 'charge.id') : (string) ($dispute['charge'] ?? '');
            if ($disputeCharge !== $purchase->stripe_charge_id) {
                throw new PaymentException('This dispute does not belong to the purchase.');
            }
            DB::transaction(function () use ($purchase, $dispute): void {
                if ($purchase->care_hour_account_id) {
                    CareHourAccount::query()->lockForUpdate()->findOrFail($purchase->care_hour_account_id);
                }
                $current = CareHourPurchase::query()->lockForUpdate()->findOrFail($purchase->id);
                if ((int) $current->care_hour_account_id !== (int) $purchase->care_hour_account_id) {
                    throw new PaymentException('The gift was just claimed. Retry dispute reconciliation.');
                }
                $state = (string) ($dispute['status'] ?? 'needs_response');
                $current->forceFill(['dispute_id' => (string) $dispute['id'], 'dispute_status' => $state])->save();
                if ($current->correction_state !== null) {
                    return;
                }
                if ($state === 'won' && $current->amount_reversed_cents === 0) {
                    $this->ledger->releasePurchaseHold($current, 'Package payment dispute won.');
                    $current->forceFill(['status' => $current->issued_at ? 'issued' : 'paid', 'last_error' => null])->save();
                } else {
                    $this->ledger->holdPurchase($current, 'Package payment dispute requires funding review.');
                    $current->forceFill(['status' => 'held', 'last_error' => 'Package payment dispute requires review.'])->save();
                    $this->purchases->queueHoldNotice($current);
                }
            }, 3);
        } elseif ($type === 'charge.refunded' || str_starts_with($type, 'refund.')) {
            if ($purchase->correction_state === 'pending') {
                $this->purchases->reconcileCorrection($purchase);
            } elseif ($purchase->correction_state === null && $purchase->stripe_payment_intent_id) {
                $this->purchases->acceptPaid($purchase, $this->gateway->financials($purchase->stripe_payment_intent_id));
            }
        }

        return true;
    }

    private function handleShortfall(string $type, array $object, ?bool $livemode): void
    {
        $operationId = (string) data_get($object, 'metadata.care_hour_operation_id', '');
        $service = app(PrepaidBookingFundingService::class);
        if ($operationId === '' || ! $service->ownsOperation($operationId)) {
            throw new PaymentException('Visit payment dependencies are not ready for reconciliation.');
        }
        $operation = \App\Models\CareHourPaymentOperation::where('public_id', $operationId)->where('kind', 'shortfall')->firstOrFail();
        $operation->assertProviderScope($livemode ?? (isset($object['livemode']) ? (bool) $object['livemode'] : null), null);
        if (str_starts_with($type, 'checkout.session.')) {
            $session = $this->gateway->retrieveCheckout((string) $object['id']);
            $this->assertEnvironment($session, $livemode);
            $operation->assertProviderScope(isset($session['livemode']) ? (bool) $session['livemode'] : null, null);
            if (($session['id'] ?? '') !== ($object['id'] ?? '')
                || ($operation->stripe_checkout_session_id && $operation->stripe_checkout_session_id !== ($session['id'] ?? ''))
                || data_get($session, 'metadata.care_hour_operation_id') !== $operationId || data_get($session, 'metadata.care_hour_domain') !== 'shortfall') {
                throw new PaymentException('This checkout does not belong to the visit payment.');
            }
            if (($session['status'] ?? '') === 'expired' && ($session['payment_status'] ?? '') !== 'paid') {
                $service->failShortfall($operationId);

                return;
            }
            $intentId = is_array($session['payment_intent'] ?? null) ? (string) data_get($session, 'payment_intent.id') : (string) ($session['payment_intent'] ?? '');
        } else {
            $intentId = str_starts_with($type, 'payment_intent.') ? (string) ($object['id'] ?? '') : '';
        }
        if ($intentId === '') {
            return;
        }
        $result = $this->gateway->financials($intentId);
        $this->assertEnvironment($result, $livemode);
        if (data_get($result, 'metadata.care_hour_operation_id') !== $operationId || data_get($result, 'metadata.care_hour_domain') !== 'shortfall') {
            throw new PaymentException('This payment does not belong to the visit.');
        }
        if (in_array($result['status'] ?? '', ['requires_capture', 'succeeded'], true)) {
            $service->completeShortfall($operationId, $result);
        } elseif (($result['status'] ?? '') === 'canceled') {
            $service->cancelShortfall($operationId, $result);
        }
    }

    private function assertPlatform(?string $connectedAccount): void
    {
        if ($connectedAccount !== null && $connectedAccount !== '') {
            throw new PaymentException('Care-hour payments must belong to the platform account.');
        }
    }

    private function assertEnvironment(array $result, ?bool $livemode): void
    {
        if ($livemode !== null && (bool) ($result['livemode'] ?? false) !== $livemode) {
            throw new PaymentException('Payment event environment does not match the retrieved payment.');
        }
    }
}
