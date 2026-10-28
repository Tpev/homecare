<?php

namespace App\Services\CareHours;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareHourAccount;
use App\Models\CareHourContributionLink;
use App\Models\CareHourPurchase;
use App\Models\FamilyAccount;
use App\Models\FamilyAccountActivityLog;
use App\Models\User;
use App\Notifications\CareHourGiftNotification;
use App\Notifications\CareHourPurchaseNotification;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CareHourPurchaseService
{
    public function __construct(
        private readonly CareHourPaymentGateway $gateway,
        private readonly CareHourLedgerService $ledger,
        private readonly FamilyAccountContext $families,
        private readonly CareHourContributionService $contributions,
    ) {}

    public function catalog(CareHourAccount $account): array
    {
        $catalog = [];
        foreach (array_keys((array) config('care_hours.packages', [])) as $hours) {
            $catalog[(int) $hours] = $this->quote($account, (int) $hours);
        }

        return $catalog;
    }

    public function quote(CareHourAccount $account, int $hours): array
    {
        app(CareHourEligibilityService::class)->assertFamilyEligible((int) $account->family_account_id);
        $package = config('care_hours.packages.'.$hours);
        if (! is_array($package) || ! in_array($hours, [5, 10, 20, 40], true)) {
            throw ValidationException::withMessages(['package' => 'Choose one of the available packages.']);
        }
        $claim = DB::table('care_hour_welcome_claims')->where('care_hour_account_id', $account->id)->first();
        $eligible = config('care_hours.welcome_enabled', false) && (! $claim || $claim->status === 'available');
        $bonus = $eligible && $hours !== 5 ? (int) $package['bonus_minutes'] : 0;
        $quote = [
            'paid_hours' => $hours, 'paid_minutes' => (int) $package['paid_minutes'], 'bonus_minutes' => $bonus,
            'total_minutes' => (int) $package['paid_minutes'] + $bonus, 'amount_cents' => (int) $package['amount_cents'],
            'currency' => 'usd', 'terms_version' => (string) config('care_hours.terms_version', '2026-10-16'),
            'welcome_pending' => $claim?->status === 'reserved',
        ];
        $quote['quote_key'] = hash_hmac('sha256', $account->id.'|'.json_encode($quote), (string) config('app.key'));

        return $quote;
    }

    public function start(CareHourAccount $account, int $hours, User $buyer, string $quoteKey, string $requestKey, string $successUrl, string $cancelUrl): CareHourPurchase
    {
        if (! $this->families->canAccessAccount($buyer, (int) $account->family_account_id)) {
            throw new AuthorizationException('You do not have access to this care balance.');
        }

        return $this->startForBuyer($account, $hours, [
            'buyer_user_id' => $buyer->id, 'buyer_email' => strtolower($buyer->email), 'buyer_name' => $buyer->name,
        ], $quoteKey, $requestKey, $successUrl, $cancelUrl, actor: $buyer);
    }

    public function startContribution(CareHourContributionLink $link, string $verifiedToken, int $hours, string $quoteKey, string $requestKey, string $successUrl, string $cancelUrl): CareHourPurchase
    {
        $buyer = $this->contributions->verifiedBuyer($link, $verifiedToken);

        return $this->startForBuyer($link->account, $hours, [
            'buyer_user_id' => null, 'buyer_email' => $buyer['email'], 'buyer_name' => $buyer['name'],
            'care_hour_contribution_link_id' => $link->id,
        ], $quoteKey, $requestKey, $successUrl, $cancelUrl, $link, auth()->user());
    }

    private function startForBuyer(CareHourAccount $account, int $hours, array $buyer, string $quoteKey, string $requestKey, string $successUrl, string $cancelUrl, ?CareHourContributionLink $link = null, ?User $actor = null): CareHourPurchase
    {
        if (strlen($requestKey) < 16 || strlen($requestKey) > 200) {
            throw ValidationException::withMessages(['purchase' => 'Refresh the purchase page and try again.']);
        }
        $identity = ($buyer['buyer_user_id'] ?? 'guest:'.$buyer['buyer_email']).'|'.($link?->id ?? 0);
        $key = hash('sha256', $identity.'|'.$requestKey);
        $purchase = DB::transaction(function () use ($account, $hours, $buyer, $quoteKey, $key, $successUrl, $cancelUrl, $link, $actor): CareHourPurchase {
            // Account-wide pricing changes take the same family lock.
            FamilyAccount::query()->whereKey($account->family_account_id)->lockForUpdate()->firstOrFail();
            $locked = CareHourAccount::query()->lockForUpdate()->findOrFail($account->id);
            $existing = CareHourPurchase::query()->where('request_key', $key)->first();
            if ($existing) {
                if ((int) $existing->care_hour_account_id !== (int) $locked->id || $existing->paid_minutes !== $hours * 60) {
                    throw ValidationException::withMessages(['purchase' => 'This purchase request already belongs to another selection.']);
                }

                return $existing;
            }
            if (! app(CareHourAccess::class)->canBuy($actor) || ! $locked->active
                || $locked->familyAccount->status !== FamilyAccount::STATUS_ACTIVE) {
                throw ValidationException::withMessages(['purchase' => 'New care-hour purchases are currently unavailable.']);
            }
            if ($link) {
                $this->contributions->assertAvailable($link);
                $this->contributions->assertBuyerAllowed($buyer['buyer_email']);
            }
            $quote = $this->quote($locked, $hours);
            if (! hash_equals($quote['quote_key'], $quoteKey)) {
                throw ValidationException::withMessages(['package' => 'The available welcome offer changed. Review the updated package before paying.']);
            }
            $publicId = (string) Str::uuid();
            $purchase = CareHourPurchase::query()->create(array_merge($buyer, [
                'public_id' => $publicId, 'request_key' => $key, 'care_hour_account_id' => $locked->id,
                'family_account_id' => $locked->family_account_id,
                'paid_minutes' => $quote['paid_minutes'], 'bonus_minutes' => $quote['bonus_minutes'],
                'total_minutes' => $quote['total_minutes'], 'amount_cents' => $quote['amount_cents'],
                'currency' => 'usd', 'status' => 'pending', 'terms_version' => $quote['terms_version'],
                'quote' => $quote, 'terms_accepted_at' => now(),
                'success_url' => str_replace('{PURCHASE_ID}', $publicId, $successUrl),
                'cancel_url' => str_replace('{PURCHASE_ID}', $publicId, $cancelUrl),
            ]));
            if ($purchase->bonus_minutes > 0) {
                DB::table('care_hour_welcome_claims')->updateOrInsert(['care_hour_account_id' => $locked->id], [
                    'care_hour_purchase_id' => $purchase->id, 'status' => 'reserved', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            return $purchase;
        });

        return $this->startCheckout($purchase);
    }

    public function startCheckout(CareHourPurchase $purchase): CareHourPurchase
    {
        $purchase->refresh();
        if ($purchase->stripe_checkout_session_id || $purchase->status !== 'pending') {
            return $purchase;
        }
        // Recheck a new checkout, but preserve recovery of an immutable attempt already sent
        // while eligible: its payment may have succeeded even if the response was lost.
        if (! $purchase->checkout_requested_at && $purchase->care_hour_account_id) {
            app(CareHourEligibilityService::class)->assertFamilyEligible((int) $purchase->family_account_id);
        }
        if ($purchase->checkout_requested_at?->lt(now()->subHours(23))) {
            $purchase->forceFill(['status' => 'recovery', 'last_error' => 'Checkout outcome unknown beyond the safe automatic retry window.'])->save();

            return $purchase;
        }
        // Persist the immutable attempt before requesting a provider session. Repeated calls use the same key and body.
        DB::table('care_hour_purchases')->where('id', $purchase->id)->whereNull('checkout_requested_at')->update(['checkout_requested_at' => now()]);
        try {
            $result = $this->gateway->createCheckout($purchase);
            if (empty($result['id']) || empty($result['url'])) {
                throw new PaymentException('The purchase is still being confirmed. Please try again shortly.');
            }
            $purchase->forceFill([
                'stripe_checkout_session_id' => (string) $result['id'], 'checkout_url' => (string) $result['url'], 'last_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $purchase->forceFill(['last_error' => 'Checkout could not be confirmed. Reconcile this attempt before replacing it.'])->save();
            report($exception);
        }

        return $purchase->fresh();
    }

    public function refresh(CareHourPurchase $purchase): CareHourPurchase
    {
        if (! $purchase->stripe_checkout_session_id) {
            return $purchase;
        }
        $session = $this->gateway->retrieveCheckout($purchase->stripe_checkout_session_id);
        $this->assertSession($purchase, $session);
        if (($session['status'] ?? '') === 'expired' && ($session['payment_status'] ?? '') !== 'paid') {
            $this->markFailed($purchase, 'expired');

            return $purchase->fresh();
        }
        $intentId = is_array($session['payment_intent'] ?? null) ? (string) data_get($session, 'payment_intent.id') : (string) ($session['payment_intent'] ?? '');
        if ($intentId === '') {
            return $purchase;
        }
        $financials = $this->gateway->financials($intentId);
        if (($session['payment_status'] ?? '') === 'paid' && ($financials['status'] ?? '') === 'succeeded') {
            return $this->acceptPaid($purchase, $financials);
        }
        if (($financials['status'] ?? '') === 'canceled'
            || (($session['status'] ?? '') === 'complete' && ($financials['status'] ?? '') === 'requires_payment_method')) {
            $this->markFailed($purchase, 'failed');
        }

        return $purchase->fresh();
    }

    public function acceptPaid(CareHourPurchase $purchase, array $financials): CareHourPurchase
    {
        $this->assertFinancials($purchase, $financials);
        DB::transaction(function () use ($purchase, $financials): void {
            $account = $purchase->care_hour_account_id ? CareHourAccount::query()->lockForUpdate()->findOrFail($purchase->care_hour_account_id) : null;
            $current = CareHourPurchase::query()->lockForUpdate()->findOrFail($purchase->id);
            $this->assertFinancials($current, $financials);
            // A claim may assign an account while this reconciliation waits for the purchase lock.
            // Retry with the new account first instead of inverting the ledger's lock order.
            if ((int) $current->care_hour_account_id !== (int) $purchase->care_hour_account_id) {
                throw new PaymentException('The gift was just claimed. Retry payment reconciliation.');
            }
            $unclaimedGift = ! $account && $current->gift()->exists();
            if (! $account && ! $unclaimedGift) {
                throw new PaymentException('This purchase has no valid recipient or gift entitlement.');
            }
            if ($current->correction_state !== null || $current->status === 'corrected') {
                return;
            }
            $current->forceFill([
                'stripe_payment_intent_id' => $financials['payment_intent_id'], 'stripe_charge_id' => $financials['charge_id'],
                'livemode' => (bool) $financials['livemode'], 'paid_at' => $current->paid_at ?: now(),
                'last_reconciled_at' => now(),
            ])->save();
            if ($unclaimedGift) {
                if ($current->status !== 'held') {
                    $current->forceFill(['status' => 'paid', 'last_error' => null])->save();
                }
                if (($financials['fee_finalized'] ?? false) && is_numeric($financials['processing_fee_cents'] ?? null)) {
                    $current->forceFill(['processing_fee_cents' => max(0, (int) $financials['processing_fee_cents']), 'fee_components' => $financials['fee_components'] ?? []])->save();
                }
                if ((int) ($financials['amount_refunded_cents'] ?? 0) > 0) {
                    $this->holdForReversal($current, (int) $financials['amount_refunded_cents']);
                }
                $this->reconcileDispute($current, $financials);
                if ($current->status === 'paid' && $current->amount_reversed_cents === 0) {
                    app(CareHourGiftService::class)->preparePaidGift($current);
                    $this->queueNotice($current, 'gift_receipt', $current->buyer_email);
                }

                return;
            }
            if (! $current->issued_at) {
                $claim = DB::table('care_hour_welcome_claims')->where('care_hour_account_id', $account->id)->first();
                if (! $account->active || $account->familyAccount->status !== FamilyAccount::STATUS_ACTIVE
                    || ($current->bonus_minutes > 0 && (int) ($claim?->care_hour_purchase_id ?? 0) !== (int) $current->id)) {
                    $current->forceFill(['status' => 'recovery', 'last_error' => 'Paid purchase requires correction because its accepted entitlement cannot be fulfilled.'])->save();

                    return;
                }
                $current->forceFill(['status' => 'paid'])->save();
                $this->ledger->issuePurchase($current);
                $current->forceFill(['status' => 'issued', 'issued_at' => now(), 'last_error' => null])->save();
                if ($current->bonus_minutes > 0) {
                    DB::table('care_hour_welcome_claims')->where('care_hour_account_id', $account->id)
                        ->where('care_hour_purchase_id', $current->id)->update(['status' => 'awarded', 'updated_at' => now()]);
                }
                $this->queueNotices($current);
            }
            if (($financials['fee_finalized'] ?? false) && is_numeric($financials['processing_fee_cents'] ?? null)) {
                $this->ledger->updatePurchaseFee($current, max(0, (int) $financials['processing_fee_cents']));
                $current->forceFill(['processing_fee_cents' => max(0, (int) $financials['processing_fee_cents']), 'fee_components' => $financials['fee_components'] ?? []])->save();
            }
            if ((int) ($financials['amount_refunded_cents'] ?? 0) > 0) {
                $this->holdForReversal($current, (int) $financials['amount_refunded_cents']);
            }
            $this->reconcileDispute($current, $financials);
        }, 3);

        return $purchase->fresh();
    }

    public function markFailed(CareHourPurchase $purchase, string $status = 'failed'): void
    {
        DB::transaction(function () use ($purchase, $status): void {
            if ($purchase->care_hour_account_id) {
                CareHourAccount::query()->lockForUpdate()->findOrFail($purchase->care_hour_account_id);
            }
            $current = CareHourPurchase::query()->lockForUpdate()->findOrFail($purchase->id);
            if ($current->paid_at || $current->issued_at) {
                return;
            }
            $current->forceFill(['status' => $status === 'expired' ? 'expired' : 'failed', 'last_reconciled_at' => now()])->save();
            DB::table('care_hour_welcome_claims')->where('care_hour_account_id', $current->care_hour_account_id)
                ->where('care_hour_purchase_id', $current->id)->where('status', 'reserved')
                ->update(['care_hour_purchase_id' => null, 'status' => 'available', 'updated_at' => now()]);
        });
    }

    public function holdForReversal(CareHourPurchase $purchase, int $amountCents): void
    {
        DB::transaction(function () use ($purchase, $amountCents): void {
            if ($purchase->care_hour_account_id) {
                CareHourAccount::query()->lockForUpdate()->findOrFail($purchase->care_hour_account_id);
            }
            $current = CareHourPurchase::query()->lockForUpdate()->findOrFail($purchase->id);
            $this->ledger->holdPurchase($current, 'Package payment reversed; affected care needs funding review.');
            $current->forceFill(['status' => 'held', 'amount_reversed_cents' => max($current->amount_reversed_cents, $amountCents), 'last_error' => 'Package payment reversal requires review.'])->save();
            $this->queueHoldNotice($current);
            $purchase->refresh();
        }, 3);
    }

    public function authorizeReceipt(CareHourPurchase $purchase, ?User $user = null, ?string $verifiedToken = null): void
    {
        if ($purchase->gift()->exists()) {
            app(CareHourGiftService::class)->authorizeReceipt($purchase, $user, $verifiedToken);

            return;
        }
        if ($user && ($user->isAdministrator() || ((int) $purchase->buyer_user_id > 0 && (int) $purchase->buyer_user_id === (int) $user->id))) {
            return;
        }
        if ($purchase->care_hour_contribution_link_id && $verifiedToken) {
            $buyer = $this->contributions->verifiedBuyer($purchase->contributionLink, $verifiedToken);
            if (hash_equals(strtolower($purchase->buyer_email), $buyer['email'])) {
                return;
            }
        }
        throw new AuthorizationException('Confirm the buyer email address to view this receipt.');
    }

    /** Only an unfulfilled paid purchase may enter this correction, never valid unused care hours. */
    public function correctUnfulfillablePurchase(CareHourPurchase $purchase, User $admin, string $reason): CareHourPurchase
    {
        if (! $admin->isAdministrator()) {
            throw new AuthorizationException('Administrator access is required.');
        }
        if (mb_strlen(trim($reason)) < 10 || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages(['reason' => 'Explain the billing error in 10 to 2,000 characters.']);
        }
        $purchase->refresh();
        if (! $purchase->care_hour_account_id) {
            throw ValidationException::withMessages(['purchase' => 'Valid unclaimed gifts are non-refundable. Payment reversals must be reconciled with the original charge.']);
        }
        $failedRefund = null;
        if ($purchase->correction_state === 'failed') {
            // A local failure flag is not enough to create another provider operation.
            $failedRefund = $this->gateway->correctionRefund($purchase, false);
            if (! $failedRefund || ! $purchase->correction_refund_id
                || ($failedRefund['id'] ?? '') !== $purchase->correction_refund_id
                || ($failedRefund['charge'] ?? '') !== $purchase->stripe_charge_id
                || (int) ($failedRefund['amount'] ?? -1) !== $purchase->amount_cents
                || ! in_array($failedRefund['status'] ?? '', ['failed', 'canceled'], true)) {
                throw ValidationException::withMessages(['purchase' => 'The previous refund is not confirmed failed. Reconcile that attempt before requesting another.']);
            }
        }
        DB::transaction(function () use ($purchase, $admin, $reason, $failedRefund): void {
            CareHourAccount::query()->lockForUpdate()->findOrFail($purchase->care_hour_account_id);
            $current = CareHourPurchase::query()->lockForUpdate()->findOrFail($purchase->id);
            if ($current->correction_state !== null && $current->correction_state !== 'failed') {
                return;
            }
            if ($current->correction_state === 'failed'
                && (! $failedRefund || $current->correction_refund_id !== ($failedRefund['id'] ?? null))) {
                return;
            }
            if ($current->status !== 'recovery' || ! $current->paid_at || ! $current->stripe_charge_id || $current->issued_at
                || DB::table('care_hour_lots')->where('care_hour_purchase_id', $current->id)->exists()) {
                throw ValidationException::withMessages(['purchase' => 'Only a paid purchase that could not issue any hours can be corrected here. Valid packages are non-refundable.']);
            }
            $current->forceFill([
                'correction_state' => 'pending', 'correction_reason' => trim($reason),
                'corrected_by_user_id' => $admin->id, 'correction_requested_at' => now(), 'correction_refund_id' => null,
            ])->save();
            FamilyAccountActivityLog::create([
                'family_account_id' => $current->family_account_id, 'actor_user_id' => $admin->id,
                'action' => 'care_hours_correction_attempt',
                'metadata' => [
                    'purchase_id' => $current->id, 'reason' => trim($reason),
                    'attempt_key' => 'care-hours:invalid-purchase:'.$current->public_id.':'.Str::uuid(),
                    'previous_refund' => $failedRefund ? array_intersect_key($failedRefund, array_flip(['id', 'charge', 'amount', 'status'])) : null,
                ],
            ]);
        });

        return $this->reconcileCorrection($purchase->fresh());
    }

    public function reconcileCorrection(CareHourPurchase $purchase): CareHourPurchase
    {
        if ($purchase->correction_state !== 'pending') {
            return $purchase;
        }
        $attemptKey = $this->correctionAttemptKey($purchase);
        try {
            $result = $this->gateway->correctionRefund($purchase, $purchase->correction_requested_at?->gt(now()->subHours(23)) === true);
            if (! $result) {
                $purchase->forceFill(['last_error' => 'Refund outcome remains unknown. Provider review is required before another request.'])->save();

                return $purchase->fresh();
            }
            if ((int) ($result['amount'] ?? -1) !== $purchase->amount_cents || ($result['charge'] ?? '') !== $purchase->stripe_charge_id) {
                throw new PaymentException('The correction result does not match this purchase.');
            }
            DB::transaction(function () use ($purchase, $result, $attemptKey): void {
                CareHourAccount::query()->lockForUpdate()->findOrFail($purchase->care_hour_account_id);
                $current = CareHourPurchase::query()->lockForUpdate()->findOrFail($purchase->id);
                if ($current->correction_state !== 'pending' || $this->correctionAttemptKey($current) !== $attemptKey) {
                    return;
                }
                if ($current->correction_refund_id !== ($result['id'] ?? null)
                    || in_array($result['status'] ?? '', ['succeeded', 'failed', 'canceled'], true)) {
                    FamilyAccountActivityLog::create([
                        'family_account_id' => $current->family_account_id, 'actor_user_id' => $current->corrected_by_user_id,
                        'action' => 'care_hours_correction_result',
                        'metadata' => ['purchase_id' => $current->id, 'attempt_key' => $attemptKey,
                            'refund' => array_intersect_key($result, array_flip(['id', 'charge', 'amount', 'status']))],
                    ]);
                }
                $current->forceFill(['correction_refund_id' => (string) $result['id']])->save();
                if (($result['status'] ?? '') === 'succeeded') {
                    $current->forceFill([
                        'correction_state' => 'succeeded', 'status' => 'corrected', 'corrected_at' => now(),
                        'amount_reversed_cents' => $current->amount_cents, 'last_error' => null,
                    ])->save();
                    DB::table('care_hour_welcome_claims')->where('care_hour_account_id', $current->care_hour_account_id)
                        ->where('care_hour_purchase_id', $current->id)->where('status', 'reserved')
                        ->update(['care_hour_purchase_id' => null, 'status' => 'available', 'updated_at' => now()]);
                    $this->queueNotice($current, 'correction', $current->buyer_email);
                } elseif (in_array($result['status'] ?? '', ['failed', 'canceled'], true)) {
                    $current->forceFill(['correction_state' => 'failed', 'last_error' => 'Billing correction failed and requires provider review.'])->save();
                }
            });
        } catch (Throwable $exception) {
            $purchase->forceFill(['last_error' => 'Billing correction outcome is pending. Reconcile before trying again.'])->save();
            report($exception);
        }

        return $purchase->fresh();
    }

    private function correctionAttemptKey(CareHourPurchase $purchase): string
    {
        $attempt = FamilyAccountActivityLog::where('family_account_id', $purchase->family_account_id)
            ->where('action', 'care_hours_correction_attempt')->where('metadata->purchase_id', $purchase->id)->latest('id')->first();

        return (string) ($attempt?->metadata['attempt_key'] ?? 'care-hours:invalid-purchase:'.$purchase->public_id);
    }

    private function reconcileDispute(CareHourPurchase $purchase, array $financials): void
    {
        if (! array_key_exists('disputed', $financials)) {
            return;
        }
        $state = $financials['dispute_status'] ?? null;
        $purchase->forceFill([
            'dispute_id' => $financials['dispute_id'] ?? $purchase->dispute_id,
            'dispute_status' => $state ?? $purchase->dispute_status,
        ])->save();
        if (($financials['disputed'] ?? false) && $state !== 'won') {
            $this->ledger->holdPurchase($purchase, 'Package payment dispute requires funding review.');
            $purchase->forceFill(['status' => 'held', 'last_error' => 'Package payment dispute requires review.'])->save();
            $this->queueHoldNotice($purchase);
        } elseif ($state === 'won' && $purchase->amount_reversed_cents === 0) {
            $this->ledger->releasePurchaseHold($purchase, 'Package payment dispute won.');
            $purchase->forceFill(['status' => $purchase->issued_at ? 'issued' : 'paid', 'last_error' => null])->save();
        }
    }

    private function assertSession(CareHourPurchase $purchase, array $session): void
    {
        if (($session['id'] ?? '') !== $purchase->stripe_checkout_session_id
            || data_get($session, 'metadata.care_hour_purchase_id') !== $purchase->public_id
            || data_get($session, 'metadata.care_hour_domain') !== 'purchase'
            || (int) ($session['amount_total'] ?? -1) !== $purchase->amount_cents
            || strtolower((string) ($session['currency'] ?? '')) !== $purchase->currency
            || ($purchase->livemode !== null && $purchase->livemode !== (bool) ($session['livemode'] ?? false))) {
            throw new PaymentException('This purchase payment needs review before hours can be added.');
        }
    }

    private function assertFinancials(CareHourPurchase $purchase, array $financials): void
    {
        if (($financials['status'] ?? '') !== 'succeeded' || empty($financials['payment_intent_id']) || empty($financials['charge_id'])
            || ! array_key_exists('livemode', $financials)
            || (int) ($financials['amount_received'] ?? -1) !== $purchase->amount_cents
            || strtolower((string) ($financials['currency'] ?? '')) !== $purchase->currency
            || data_get($financials, 'metadata.care_hour_domain') !== 'purchase'
            || data_get($financials, 'metadata.care_hour_purchase_id') !== $purchase->public_id
            || ($purchase->stripe_payment_intent_id && $purchase->stripe_payment_intent_id !== $financials['payment_intent_id'])
            || ($purchase->stripe_charge_id && $purchase->stripe_charge_id !== $financials['charge_id'])
            || ($purchase->livemode !== null && $purchase->livemode !== (bool) ($financials['livemode'] ?? false))) {
            throw new PaymentException('This purchase payment needs review before hours can be added.');
        }
    }

    private function queueNotices(CareHourPurchase $purchase): void
    {
        $this->queueNotice($purchase, $purchase->gift()->exists() ? 'gift_receipt' : 'receipt', $purchase->buyer_email);
        $this->queueFamilyNotices($purchase);
    }

    public function queueFamilyNotices(CareHourPurchase $purchase): void
    {
        $recipients = [];
        foreach ($purchase->familyAccount->activeMemberships()->pluck('user_id') as $userId) {
            $recipients[] = ['kind' => 'family', 'recipient' => (string) $userId];
        }
        foreach ($recipients as $recipient) {
            DB::table('care_hour_purchase_notices')->insertOrIgnore(array_merge($recipient, [
                'care_hour_purchase_id' => $purchase->id,
                'dedupe_key' => hash('sha256', $purchase->id.'|'.$recipient['kind'].'|'.$recipient['recipient']),
                'created_at' => now(), 'updated_at' => now(),
            ]));
        }
    }

    public function queueBuyerAttention(CareHourPurchase $purchase): void
    {
        if ($purchase->issued_at || $purchase->paid_at) {
            return;
        }
        $this->queueNotice($purchase, 'attention', $purchase->buyer_email);
    }

    public function queueHoldNotice(CareHourPurchase $purchase): void
    {
        if ($purchase->gift()->exists()) {
            $this->queueNotice($purchase, 'gift_hold', $purchase->buyer_email);
        }
        if (! $purchase->family_account_id) {
            return;
        }
        foreach ($purchase->familyAccount->activeMemberships()->pluck('user_id') as $userId) {
            $this->queueNotice($purchase, 'hold', (string) $userId);
        }
    }

    private function queueNotice(CareHourPurchase $purchase, string $kind, string $recipient): void
    {
        DB::table('care_hour_purchase_notices')->insertOrIgnore([
            'care_hour_purchase_id' => $purchase->id, 'kind' => $kind, 'recipient' => $recipient,
            'dedupe_key' => hash('sha256', $purchase->id.'|'.$kind.'|'.$recipient),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function dispatchNotices(int $limit = 100): int
    {
        $sent = 0;
        $notices = DB::table('care_hour_purchase_notices')->whereNull('sent_at')
            ->where(fn ($query) => $query->whereNull('processing_at')->orWhere('processing_at', '<', now()->subMinutes(10)))
            ->orderBy('updated_at')->orderBy('id')->limit($limit)->get();
        foreach ($notices as $notice) {
            $claimed = DB::table('care_hour_purchase_notices')->where('id', $notice->id)->whereNull('sent_at')
                ->where(fn ($query) => $query->whereNull('processing_at')->orWhere('processing_at', '<', now()->subMinutes(10)))
                ->update(['processing_at' => now(), 'attempts' => DB::raw('attempts + 1'), 'updated_at' => now()]);
            if (! $claimed) {
                continue;
            }
            try {
                $purchase = CareHourPurchase::query()->with('account')->findOrFail($notice->care_hour_purchase_id);
                if (in_array($notice->kind, ['gift_receipt', 'gift_hold'], true) || ($notice->kind === 'attention' && $purchase->gift()->exists())) {
                    if (($notice->kind !== 'attention' || ! $purchase->paid_at)
                        && ($notice->kind !== 'gift_hold' || $purchase->status === 'held')) {
                        Notification::route('mail', [$notice->recipient => $purchase->buyer_name])->notify(new CareHourGiftNotification($purchase, $notice->kind === 'gift_hold' ? 'hold' : ($notice->kind === 'attention' ? 'attention' : 'success')));
                    }
                } elseif (in_array($notice->kind, ['receipt', 'attention', 'correction'], true)) {
                    if ($notice->kind !== 'attention' || ! $purchase->issued_at) {
                        Notification::route('mail', [$notice->recipient => $purchase->buyer_name])->notify(new CareHourPurchaseNotification($purchase, true, in_array($notice->kind, ['attention', 'correction'], true) ? $notice->kind : 'success'));
                    }
                } elseif ($notice->kind !== 'hold' || $purchase->status === 'held') {
                    $user = User::query()->find($notice->recipient);
                    if ($user && $this->families->canAccessAccount($user, (int) $purchase->family_account_id)) {
                        $notification = new CareHourPurchaseNotification($purchase, false, $notice->kind === 'hold' ? 'hold' : 'success');
                        // The outbox key is also the database notification identity for crash-safe replay.
                        $notification->id = substr($notice->dedupe_key, 0, 8).'-'.substr($notice->dedupe_key, 8, 4).'-'.substr($notice->dedupe_key, 12, 4).'-'.substr($notice->dedupe_key, 16, 4).'-'.substr($notice->dedupe_key, 20, 12);
                        if (! $user->notifications()->whereKey($notification->id)->exists()) {
                            $user->notify($notification);
                        }
                    }
                }
                DB::table('care_hour_purchase_notices')->where('id', $notice->id)->update(['sent_at' => now(), 'processing_at' => null, 'last_error' => null, 'updated_at' => now()]);
                $sent++;
            } catch (Throwable $exception) {
                DB::table('care_hour_purchase_notices')->where('id', $notice->id)->update(['processing_at' => null, 'last_error' => 'Delivery failed; retry pending.', 'updated_at' => now()]);
                report($exception);
            }
        }

        return $sent;
    }
}
