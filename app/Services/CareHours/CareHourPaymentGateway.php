<?php

namespace App\Services\CareHours;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareHourPaymentOperation;
use App\Models\CareHourPurchase;
use App\Models\FamilyAccountActivityLog;
use App\Services\Payments\StripeClient;
use Illuminate\Support\Carbon;
use Stripe\StripeClient as StripeSdk;

/** Package operations never create or modify a family's saved billing customer. */
class CareHourPaymentGateway
{
    private ?StripeSdk $client = null;

    public function createCheckout(CareHourPurchase $purchase): array
    {
        $metadata = ['care_hour_domain' => 'purchase', 'care_hour_purchase_id' => $purchase->public_id];
        if ($this->bypass()) {
            return ['id' => 'cs_bypass_care_hours_'.$purchase->public_id, 'url' => $purchase->success_url];
        }

        return $this->sdk()->checkout->sessions->create([
            'mode' => 'payment',
            'customer_email' => $purchase->buyer_email,
            'success_url' => $purchase->success_url,
            'cancel_url' => $purchase->cancel_url,
            'client_reference_id' => $purchase->public_id,
            'metadata' => $metadata,
            'payment_intent_data' => ['metadata' => $metadata],
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => $purchase->currency,
                    'unit_amount' => $purchase->amount_cents,
                    'product_data' => [
                        'name' => ($purchase->paid_minutes / 60).' care hours'.($purchase->bonus_minutes ? ' + '.($purchase->bonus_minutes / 60).' welcome hours' : ''),
                        'description' => $purchase->gift()->exists()
                            ? (str_starts_with($purchase->terms_version, '2026-10-30-family-wallet')
                                ? 'Gift claimed once into a family care balance. Welcome hours depend on family eligibility at claim. Non-refundable. No expiry. Includes the hourly family fee.'
                                : 'Gift claimed once for an existing recipient. Welcome hours depend on their eligibility at claim. Non-refundable. No expiry. Includes the hourly family fee.')
                            : 'Non-refundable. No expiry. Includes the hourly family fee.',
                    ],
                ],
            ]],
        ], ['idempotency_key' => 'care-hours:purchase:'.$purchase->public_id])->toArray();
    }

    public function createShortfallCheckout(string $operationId, int $amountCents, string $currency, string $successUrl, string $cancelUrl, ?string $email = null): array
    {
        if ($amountCents <= 0 || strtolower($currency) !== 'usd') {
            throw new PaymentException('The visit payment amount is invalid.');
        }
        if ($this->bypass()) {
            return ['id' => 'cs_bypass_care_shortfall_'.$operationId, 'url' => $successUrl];
        }
        $metadata = ['care_hour_domain' => 'shortfall', 'care_hour_operation_id' => $operationId];

        return $this->sdk()->checkout->sessions->create(array_filter([
            'mode' => 'payment', 'customer_email' => $email,
            'success_url' => $successUrl, 'cancel_url' => $cancelUrl,
            'metadata' => $metadata,
            'payment_intent_data' => ['capture_method' => 'manual', 'metadata' => $metadata],
            'line_items' => [[
                'quantity' => 1,
                'price_data' => ['currency' => 'usd', 'unit_amount' => $amountCents, 'product_data' => ['name' => 'Visit payment coverage']],
            ]],
        ], static fn ($value) => $value !== null), ['idempotency_key' => 'care-hours:shortfall:'.$operationId])->toArray();
    }

    public function retrieveCheckout(string $sessionId): array
    {
        if ($this->bypass()) {
            $purchase = CareHourPurchase::query()->where('stripe_checkout_session_id', $sessionId)->first();
            if (! $purchase) {
                $operation = CareHourPaymentOperation::query()->where('stripe_checkout_session_id', $sessionId)->where('kind', 'shortfall')->first();
                if ($operation) {
                    return [
                        'id' => $sessionId, 'mode' => 'payment', 'status' => 'complete', 'payment_status' => 'unpaid',
                        'amount_total' => (int) $operation->amount_cents, 'currency' => $operation->currency, 'livemode' => false,
                        'payment_intent' => 'pi_bypass_care_shortfall_'.$operation->public_id,
                        'metadata' => ['care_hour_domain' => 'shortfall', 'care_hour_operation_id' => $operation->public_id],
                    ];
                }
                throw new PaymentException('This payment is still being confirmed.');
            }

            return [
                'id' => $sessionId, 'mode' => 'payment', 'status' => 'complete', 'payment_status' => 'paid',
                'amount_total' => $purchase->amount_cents, 'currency' => $purchase->currency, 'livemode' => false,
                'payment_intent' => 'pi_bypass_care_hours_'.$purchase->public_id,
                'metadata' => ['care_hour_domain' => 'purchase', 'care_hour_purchase_id' => $purchase->public_id],
            ];
        }

        return $this->sdk()->checkout->sessions->retrieve($sessionId, [])->toArray();
    }

    public function financials(string $intentId): array
    {
        if ($this->bypass()) {
            if (str_starts_with($intentId, 'pi_bypass_care_shortfall_')) {
                $operation = CareHourPaymentOperation::query()->where('public_id', substr($intentId, strlen('pi_bypass_care_shortfall_')))->where('kind', 'shortfall')->firstOrFail();
                $captured = in_array($operation->status, ['captured', 'refunded'], true);
                $fee = (int) round((int) $operation->amount_cents * (float) config('services.stripe.bypass_processing_fee_percent', 0) / 100)
                    + (int) config('services.stripe.bypass_processing_fee_fixed_cents', 0);

                return [
                    'payment_intent_id' => $intentId, 'charge_id' => 'ch_bypass_care_shortfall_'.$operation->public_id,
                    'amount_cents' => (int) $operation->amount_cents, 'amount_received' => $captured ? (int) $operation->captured_cents : 0,
                    'authorized_cents' => $captured ? 0 : (int) $operation->amount_cents,
                    'currency' => $operation->currency, 'status' => $captured ? 'succeeded' : 'requires_capture', 'livemode' => false,
                    'processing_fee_cents' => $captured ? $fee : null, 'fee_finalized' => $captured,
                    'authorization_expires_at' => now()->addDays(6),
                    'metadata' => ['care_hour_domain' => 'shortfall', 'care_hour_operation_id' => $operation->public_id],
                    'amount_refunded_cents' => (int) ($operation->refunded_cents ?? 0),
                    'refund_financials_finalized' => true,
                ];
            }
            $publicId = str_replace('pi_bypass_care_hours_', '', $intentId);
            $purchase = CareHourPurchase::query()->where('public_id', $publicId)->firstOrFail();
            $fee = (int) round($purchase->amount_cents * (float) config('services.stripe.bypass_processing_fee_percent', 0) / 100)
                + (int) config('services.stripe.bypass_processing_fee_fixed_cents', 0);

            return [
                'payment_intent_id' => $intentId, 'charge_id' => 'ch_bypass_care_hours_'.$publicId,
                'amount_cents' => $purchase->amount_cents, 'amount_received' => $purchase->amount_cents,
                'currency' => $purchase->currency, 'status' => 'succeeded', 'livemode' => false,
                'processing_fee_cents' => $fee, 'fee_finalized' => true,
                'metadata' => ['care_hour_domain' => 'purchase', 'care_hour_purchase_id' => $publicId],
                'amount_refunded_cents' => 0,
            ];
        }
        $intent = $this->sdk()->paymentIntents->retrieve($intentId, ['expand' => ['latest_charge.balance_transaction']])->toArray();
        $fees = app(StripeClient::class)->retrieveCareHourPaymentFinancials($intentId);
        $expires = data_get($intent, 'latest_charge.payment_method_details.card.capture_before');
        $disputeState = [];
        if (data_get($intent, 'metadata.care_hour_domain') === 'purchase') {
            // Dispute events may arrive before we have persisted the charge-to-purchase link.
            // Reconcile from the charge itself, and never add this lookup to legacy/shortfall payments.
            $disputed = (bool) data_get($intent, 'latest_charge.disputed', false);
            $dispute = null;
            $purchase = CareHourPurchase::where('public_id', data_get($intent, 'metadata.care_hour_purchase_id'))->first();
            if ($disputed || $purchase?->dispute_id) {
                $disputes = $this->sdk()->disputes->all(['charge' => (string) ($fees['latest_charge_id'] ?? ''), 'limit' => 1])->toArray();
                $dispute = $disputes['data'][0] ?? null;
                if ($dispute && ($dispute['charge'] ?? '') !== ($fees['latest_charge_id'] ?? '')) {
                    throw new PaymentException('The dispute does not belong to this purchase.');
                }
            }
            $disputeState = ['disputed' => $disputed || $dispute !== null,
                'dispute_id' => $dispute['id'] ?? null, 'dispute_status' => $dispute['status'] ?? null];
        }

        return $disputeState + [
            'payment_intent_id' => (string) $intent['id'],
            'charge_id' => (string) ($fees['latest_charge_id'] ?? ''),
            'amount_cents' => (int) ($intent['amount'] ?? 0),
            'amount_received' => (int) ($intent['amount_received'] ?? 0),
            'authorized_cents' => (int) ($intent['amount_capturable'] ?? 0),
            'currency' => (string) ($intent['currency'] ?? ''),
            'status' => (string) ($intent['status'] ?? ''), 'livemode' => (bool) ($intent['livemode'] ?? false),
            'metadata' => (array) ($intent['metadata'] ?? []),
            'client_secret' => (string) ($intent['client_secret'] ?? ''),
            'authorization_expires_at' => is_numeric($expires) ? Carbon::createFromTimestampUTC((int) $expires) : null,
            'processing_fee_cents' => $fees['processing_fee_cents'] ?? null,
            'fee_components' => $fees['processing_fee_components'] ?? [],
            'fee_finalized' => (bool) ($fees['fee_finalized'] ?? false),
            'amount_refunded_cents' => data_get($intent, 'metadata.care_hour_domain') === 'shortfall'
                ? ($fees['cash_refunded_cents'] ?? null) : (int) data_get($intent, 'latest_charge.amount_refunded', 0),
            'refund_financials_finalized' => (bool) ($fees['refund_financials_finalized'] ?? false),
        ];
    }

    public function retrieveDispute(string $disputeId): array
    {
        return $this->sdk()->disputes->retrieve($disputeId, [])->toArray();
    }

    /** Look up this exact attempt before deciding whether another refund can be sent. */
    public function findShortfallRefund(CareHourPaymentOperation $operation): ?array
    {
        if ($this->bypass()) {
            return $operation->stripe_refund_id ? $this->bypassShortfallRefund($operation, $operation->status) : null;
        }
        if ($operation->stripe_refund_id) {
            return $this->sdk()->refunds->retrieve($operation->stripe_refund_id, [])->toArray();
        }
        foreach ($this->sdk()->refunds->all(['payment_intent' => $operation->stripe_payment_intent_id, 'limit' => 100])->autoPagingIterator() as $refund) {
            if (($refund->metadata['care_hour_operation_id'] ?? '') === $operation->public_id) {
                return $refund->toArray();
            }
        }

        return null;
    }

    public function createShortfallRefund(CareHourPaymentOperation $operation): array
    {
        if ($this->bypass()) {
            return $this->bypassShortfallRefund($operation, 'succeeded');
        }

        return $this->sdk()->refunds->create([
            'payment_intent' => $operation->stripe_payment_intent_id,
            'amount' => (int) $operation->amount_cents,
            'reason' => 'requested_by_customer',
            'metadata' => ['care_hour_domain' => 'shortfall', 'care_hour_operation_id' => $operation->public_id],
        ], ['idempotency_key' => 'care-hours:refund:'.$operation->public_id])->toArray();
    }

    private function bypassShortfallRefund(CareHourPaymentOperation $operation, string $status): array
    {
        return ['id' => $operation->stripe_refund_id ?: 're_bypass_care_shortfall_'.$operation->public_id,
            'status' => $status, 'amount' => (int) $operation->amount_cents, 'currency' => $operation->currency,
            'payment_intent' => $operation->stripe_payment_intent_id, 'livemode' => false,
            'metadata' => ['care_hour_domain' => 'shortfall', 'care_hour_operation_id' => $operation->public_id]];
    }

    public function correctionRefund(CareHourPurchase $purchase, bool $allowCreate): ?array
    {
        $legacyKey = 'care-hours:invalid-purchase:'.$purchase->public_id;
        $attempt = FamilyAccountActivityLog::where('family_account_id', $purchase->family_account_id)
            ->where('action', 'care_hours_correction_attempt')->where('metadata->purchase_id', $purchase->id)->latest('id')->first();
        $key = (string) ($attempt?->metadata['attempt_key'] ?? $legacyKey);
        if ($this->bypass()) {
            return ['id' => 're_bypass_care_hours_'.substr(hash('sha256', $key), 0, 32), 'status' => 'succeeded', 'amount' => $purchase->amount_cents, 'charge' => $purchase->stripe_charge_id];
        }
        if ($purchase->correction_refund_id) {
            return $this->sdk()->refunds->retrieve($purchase->correction_refund_id, [])->toArray();
        }
        // Recover a response lost after the provider accepted the refund, without a second refund.
        foreach ($this->sdk()->refunds->all(['charge' => $purchase->stripe_charge_id, 'limit' => 100])->autoPagingIterator() as $refund) {
            if (($refund->metadata['care_hour_correction_id'] ?? '') === $purchase->public_id
                && ($refund->metadata['care_hour_correction_attempt'] ?? $legacyKey) === $key) {
                return $refund->toArray();
            }
        }
        if (! $allowCreate) {
            return null;
        }

        return app(StripeClient::class)->createRefundForCharge(
            $purchase->stripe_charge_id, $purchase->amount_cents, 'requested_by_customer',
            ['care_hour_domain' => 'purchase', 'care_hour_purchase_id' => $purchase->public_id,
                'care_hour_correction_id' => $purchase->public_id, 'care_hour_correction_attempt' => $key], $key,
        ) + ['charge' => $purchase->stripe_charge_id];
    }

    private function bypass(): bool
    {
        return (bool) config('services.stripe.bypass', false);
    }

    private function sdk(): StripeSdk
    {
        $secret = (string) config('services.stripe.secret');
        if ($secret === '') {
            throw new PaymentException('Payments are temporarily unavailable. Please try again later.');
        }

        return $this->client ??= new StripeSdk($secret);
    }
}
