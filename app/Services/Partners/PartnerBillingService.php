<?php

namespace App\Services\Partners;

use App\Exceptions\Payments\PaymentException;
use App\Models\PartnerLeadCredit;
use App\Models\PartnerProfile;
use App\Models\PartnerSubscription;
use App\Services\Payments\StripeClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PartnerBillingService
{
    public function __construct(private readonly PartnerStripeGateway $gateway, private readonly StripeClient $stripe) {}

    public function packageOptions(): array
    {
        return collect(config('partners.plans'))->map(fn (array $plan, string $key): array => $plan + [
            'key' => $key, 'title' => $plan['name'], 'amount_cents' => $plan['quantity'] * $plan['unit_price_cents'],
            'billing_label' => $plan['recurring'] ? 'per month' : 'one-time payment',
        ])->all();
    }

    public function subscriptionFor(PartnerProfile $profile): ?PartnerSubscription
    {
        return PartnerSubscription::where('partner_profile_id', $profile->id)->where('plan', 'monthly')->latest('id')->first();
    }

    public function summaryFor(PartnerProfile $profile): array
    {
        if (! $profile->stripe_customer_id) {
            return ['ready' => false, 'card' => null];
        }
        $this->require(! $this->stripe->isBypass(), 'Saved payment methods cannot be checked in preview mode.');
        $card = $this->stripe->defaultPaymentMethodForCustomer($profile->stripe_customer_id);
        // Store display information only; payment method identifiers remain at Stripe.
        $summary = $card ? array_intersect_key($card, array_flip(['type', 'brand', 'last4', 'exp_month', 'exp_year'])) : null;
        $profile->forceFill(['payment_method_summary' => $summary, 'payment_method_updated_at' => now()])->save();

        return ['ready' => $card !== null, 'card' => $summary];
    }

    public function invoicesFor(PartnerProfile $profile): Collection
    {
        if (! $profile->stripe_customer_id) {
            return collect();
        }

        return collect($this->gateway->listInvoices($profile->stripe_customer_id))
            ->filter(fn (array $invoice): bool => $this->id($invoice['customer'] ?? '') === $profile->stripe_customer_id)
            ->map(fn (array $invoice): array => [
                'id' => $invoice['id'], 'number' => $invoice['number'] ?? $invoice['id'],
                'date' => Carbon::createFromTimestamp((int) ($invoice['created'] ?? 0)),
                'description' => ($invoice['description'] ?? null) ?: (data_get($invoice, 'lines.data.0.description') ?: 'Lead package'),
                'amount_cents' => (int) ($invoice['total'] ?? 0), 'currency' => $invoice['currency'] ?? 'usd',
                'status' => $invoice['status'] ?? 'draft',
                'hosted_url' => $this->stripeUrl($invoice['hosted_invoice_url'] ?? null),
                'pdf_url' => $this->stripeUrl($invoice['invoice_pdf'] ?? null),
            ])->values();
    }

    private function ensureCustomer(PartnerProfile $profile): PartnerProfile
    {
        return DB::transaction(function () use ($profile) {
            $locked = PartnerProfile::lockForUpdate()->findOrFail($profile->id);
            if (! $locked->stripe_customer_id) {
                $customer = $this->gateway->createCustomer($locked);
                $this->require(filled($customer['id'] ?? null), 'Unable to create a billing profile.');
                $locked->forceFill(['stripe_customer_id' => $customer['id']])->save();
            }

            return $locked;
        });
    }

    public function createSetupCheckoutUrl(PartnerProfile $profile, string $successUrl, string $cancelUrl): string
    {
        $session = $this->gateway->createSetupCheckout($this->ensureCustomer($profile), $successUrl, $cancelUrl);

        return $this->checkoutUrl($session);
    }

    public function syncSetupCheckoutSession(PartnerProfile $profile, string $sessionId): void
    {
        $session = $this->gateway->retrieveCheckout($sessionId);
        $this->require(($session['mode'] ?? '') === 'setup' && ($session['status'] ?? '') === 'complete', 'Payment method setup is not complete yet.');
        $this->require(data_get($session, 'metadata.partner_domain') === 'setup'
            && (string) data_get($session, 'metadata.partner_profile_id') === (string) $profile->id
            && $profile->stripe_customer_id && $this->id($session['customer'] ?? '') === $profile->stripe_customer_id,
            'This payment setup does not belong to your account.');
        $intent = $session['setup_intent'] ?? null;
        $this->require(is_array($intent) && ($intent['status'] ?? '') === 'succeeded'
            && $this->id($intent['customer'] ?? '') === $profile->stripe_customer_id, 'Payment method setup could not be verified.');
        $methodId = $this->id($intent['payment_method'] ?? '');
        $this->require($methodId !== '', 'No saved payment method was returned.');
        $method = $this->gateway->retrievePaymentMethod($methodId);
        $this->require($this->id($method['customer'] ?? '') === $profile->stripe_customer_id
            && in_array($method['type'] ?? '', ['card', 'link'], true), 'This payment method cannot be saved to your account.');
        $this->gateway->setDefaultPaymentMethod($profile->stripe_customer_id, $methodId, $sessionId);
        PartnerSubscription::where('partner_profile_id', $profile->id)->where('plan', 'monthly')
            ->whereNotNull('stripe_subscription_id')->whereNotIn('status', ['canceled', 'incomplete_expired'])
            ->each(fn (PartnerSubscription $subscription) => $this->gateway->setSubscriptionDefaultPaymentMethod(
                $subscription->stripe_subscription_id, $methodId, $sessionId));
        $this->summaryFor($profile);
    }

    public function createCheckoutUrl(PartnerProfile $profile, string $plan, string $successUrl, string $cancelUrl): string
    {
        $catalog = $this->packageOptions();
        $this->require(isset($catalog[$plan]), 'Choose an available lead package.');
        $this->require($profile->fresh()->status === PartnerProfile::STATUS_APPROVED, 'Your partner profile must be approved before buying leads.');
        $profile = $this->ensureCustomer($profile);
        $purchase = DB::transaction(function () use ($profile, $plan, $catalog) {
            $owner = PartnerProfile::lockForUpdate()->findOrFail($profile->id);
            $this->require($owner->status === PartnerProfile::STATUS_APPROVED, 'Your partner profile must be approved before buying leads.');
            $existing = PartnerSubscription::where('partner_profile_id', $owner->id)->where('plan', $plan)
                ->whereNotIn('status', ['canceled', 'incomplete_expired', 'checkout_expired', 'checkout_failed', 'paid'])->latest('id')->lockForUpdate()->first();
            if ($existing) {
                $this->require(in_array($existing->status, ['checkout_creating', 'checkout_pending'], true), 'You already have a monthly subscription.');

                return $existing;
            }

            return PartnerSubscription::create([
                'public_id' => (string) Str::uuid(), 'partner_profile_id' => $owner->id, 'plan' => $plan,
                'stripe_customer_id' => $owner->stripe_customer_id, 'quantity' => $catalog[$plan]['quantity'],
                'unit_price_cents' => $catalog[$plan]['unit_price_cents'], 'currency' => config('partners.currency'),
                'status' => 'checkout_creating',
            ]);
        });
        if ($purchase->stripe_checkout_session_id) {
            // Refresh expired/canceled browser sessions from Stripe before permitting another purchase.
            $session = $this->gateway->retrieveCheckout($purchase->stripe_checkout_session_id);
            if (($session['status'] ?? '') === 'expired') {
                $purchase->update(['status' => 'checkout_expired']);

                return $this->createCheckoutUrl($profile, $plan, $successUrl, $cancelUrl);
            }
            $this->require(($session['status'] ?? '') === 'open', 'Your checkout is being confirmed. Refresh billing in a moment.');

            return $this->checkoutUrl($session);
        }
        $session = $this->gateway->createSubscriptionCheckout($purchase, $successUrl, $cancelUrl);
        $url = $this->checkoutUrl($session);
        DB::transaction(function () use ($purchase, $session, $url) {
            $locked = PartnerSubscription::lockForUpdate()->findOrFail($purchase->id);
            $this->require(! $locked->stripe_checkout_session_id || $locked->stripe_checkout_session_id === $session['id'], 'Another checkout is already associated with this purchase.');
            $values = ['stripe_checkout_session_id' => $session['id'], 'checkout_url' => $url,
                'checkout_expires_at' => isset($session['expires_at']) ? Carbon::createFromTimestamp($session['expires_at']) : null];
            if ($locked->status === 'checkout_creating') {
                $values['status'] = 'checkout_pending';
            }
            $locked->update($values);
        });

        return $url;
    }

    public function verifyCheckoutReturn(PartnerProfile $profile, string $sessionId): void
    {
        $session = $this->gateway->retrieveCheckout($sessionId);
        $purchase = $this->purchaseFromCheckout($session);
        $this->require($purchase && $purchase->partner_profile_id === $profile->id, 'This checkout does not belong to your account.');
        $this->require(($session['status'] ?? '') === 'complete', 'Checkout is not complete yet.');
        // Credits are issued only by the signed paid-invoice webhook, never this return path.
    }

    public function cancelSubscription(PartnerProfile $profile): void
    {
        $subscription = $this->subscriptionFor($profile);
        $this->require($subscription && $subscription->stripe_subscription_id && $subscription->blocksNewCheckout(), 'There is no active subscription to cancel.');
        $data = $this->gateway->cancelSubscription($subscription->stripe_subscription_id);
        $this->syncSubscription($subscription, $data);
    }

    public function handle(string $type, array $object, bool $livemode = false, ?string $account = null): bool
    {
        $customerId = $type === 'customer.updated' ? (string) ($object['id'] ?? '') : $this->id($object['customer'] ?? '');
        $profile = $customerId !== '' ? PartnerProfile::where('stripe_customer_id', $customerId)->first() : null;
        if ($type === 'charge.refunded' || str_starts_with($type, 'charge.dispute.') || str_starts_with($type, 'refund.') || $type === 'radar.early_fraud_warning.created') {
            return $this->handleRisk($type, $object, $livemode, $account);
        }
        if (! $profile) {
            return false;
        }
        $this->require(blank($account), 'Partner billing events must belong to the platform account.');
        if (str_starts_with($type, 'checkout.session.')) {
            if (($object['mode'] ?? '') === 'setup' && data_get($object, 'metadata.partner_domain') === 'setup') {
                if ($type === 'checkout.session.completed') {
                    $this->syncSetupCheckoutSession($profile, $object['id']);
                }

                return true;
            }
            $session = $this->gateway->retrieveCheckout($object['id']);
            $purchase = $this->purchaseFromCheckout($session);
            if (! $purchase) {
                return true;
            }
            $this->assertMode($purchase, $livemode, $session);
            if (($session['status'] ?? '') === 'expired' && $purchase->status !== 'paid') {
                $purchase->update(['status' => 'checkout_expired']);
            } elseif ($type === 'checkout.session.async_payment_failed' && $purchase->plan !== 'monthly'
                && ($session['payment_status'] ?? '') === 'unpaid' && $purchase->status !== 'paid') {
                $intent = $this->gateway->retrievePaymentIntent($this->id($session['payment_intent'] ?? ''));
                $this->require($this->id($intent['customer'] ?? '') === $purchase->stripe_customer_id,
                    'The failed payment does not belong to this partner.');
                if (in_array($intent['status'] ?? '', ['canceled', 'requires_payment_method'], true)) {
                    $purchase->update(['status' => 'checkout_failed']);
                }
            } elseif ($purchase->plan === 'monthly' && filled($session['subscription'] ?? null)) {
                $this->syncSubscription($purchase, $this->gateway->retrieveSubscription($this->id($session['subscription'])));
            }
            // invoice.paid is the only event that grants purchased credits.
            return true;
        }
        if (str_starts_with($type, 'customer.subscription.')) {
            $data = $this->gateway->retrieveSubscription($object['id']);
            $purchase = $this->purchaseFromSubscription($data);
            if ($purchase) {
                $this->assertMode($purchase, $livemode, $data);
                $this->syncSubscription($purchase, $data);
            }

            return true;
        }
        if (str_starts_with($type, 'invoice.')) {
            $invoice = $this->gateway->retrieveInvoice($object['id']);
            $subscriptionId = $this->id($invoice['subscription'] ?? data_get($invoice, 'parent.subscription_details.subscription', ''));
            $purchase = null;
            if ($subscriptionId !== '') {
                $data = $this->gateway->retrieveSubscription($subscriptionId);
                $purchase = $this->purchaseFromSubscription($data);
                if ($purchase) {
                    $this->syncSubscription($purchase, $data);
                }
            } else {
                $purchase = PartnerSubscription::where('public_id', (string) data_get($invoice, 'metadata.partner_subscription_key'))->first();
            }
            if ($purchase) {
                $this->assertMode($purchase, $livemode, $invoice);
                $this->require($purchase->partner_profile_id === $profile->id, 'Invoice ownership does not match this partner.');
                if ($type === 'invoice.paid') {
                    $this->grantInvoice($purchase, $invoice);
                }
            }

            return true;
        }
        // Partner-owned payment events must not reach the family booking handlers.
        if ($type === 'customer.updated') {
            $this->summaryFor($profile);
        }

        return true;
    }

    private function purchaseFromCheckout(array $session): ?PartnerSubscription
    {
        $purchase = PartnerSubscription::where('stripe_checkout_session_id', $session['id'])->first()
            ?: PartnerSubscription::where('public_id', (string) data_get($session, 'metadata.partner_subscription_key'))->first();
        if (! $purchase) {
            return null;
        }
        $this->require($this->id($session['customer'] ?? '') === $purchase->stripe_customer_id
            && (string) data_get($session, 'metadata.partner_profile_id') === (string) $purchase->partner_profile_id
            && (string) data_get($session, 'metadata.partner_subscription_key') === $purchase->public_id
            && (! $purchase->stripe_checkout_session_id || $purchase->stripe_checkout_session_id === $session['id'])
            && ($session['mode'] ?? '') === ($purchase->plan === 'monthly' ? 'subscription' : 'payment'), 'Checkout ownership could not be verified.');
        if (! $purchase->stripe_checkout_session_id) {
            $purchase->update(['stripe_checkout_session_id' => $session['id']]);
        }

        return $purchase;
    }

    private function purchaseFromSubscription(array $data): ?PartnerSubscription
    {
        $purchase = PartnerSubscription::where('stripe_subscription_id', $data['id'])->first()
            ?: PartnerSubscription::where('public_id', (string) data_get($data, 'metadata.partner_subscription_key'))->where('plan', 'monthly')->first();
        if ($purchase) {
            $this->require($this->id($data['customer'] ?? '') === $purchase->stripe_customer_id
                && (string) data_get($data, 'metadata.partner_profile_id') === (string) $purchase->partner_profile_id,
                'Subscription ownership could not be verified.');
        }

        return $purchase;
    }

    private function syncSubscription(PartnerSubscription $purchase, array $data): void
    {
        $this->require($this->id($data['customer'] ?? '') === $purchase->stripe_customer_id
            && (! $purchase->stripe_subscription_id || $purchase->stripe_subscription_id === $data['id']), 'Subscription does not match this billing account.');
        $items = data_get($data, 'items.data', []);
        $this->require(count($items) === 1, 'The subscription contains an unexpected package.');
        $item = $items[0];
        $price = $item['price'] ?? [];
        $this->require((int) ($item['quantity'] ?? 0) === $purchase->quantity
            && (int) ($price['unit_amount'] ?? 0) === $purchase->unit_price_cents
            && ($price['currency'] ?? '') === $purchase->currency
            && data_get($price, 'recurring.interval') === 'month'
            && (int) data_get($price, 'recurring.interval_count', 1) === 1, 'The subscription price or quantity does not match the package.');
        $start = $data['current_period_start'] ?? $item['current_period_start'] ?? null;
        $end = $data['current_period_end'] ?? $item['current_period_end'] ?? null;
        $purchase->update([
            'stripe_subscription_id' => $data['id'], 'stripe_price_id' => $price['id'],
            'status' => $data['status'], 'cancel_at_period_end' => (bool) ($data['cancel_at_period_end'] ?? false),
            'current_period_start' => $start ? Carbon::createFromTimestamp($start) : null,
            'current_period_end' => $end ? Carbon::createFromTimestamp($end) : null,
            'canceled_at' => filled($data['canceled_at'] ?? null) ? Carbon::createFromTimestamp($data['canceled_at']) : null,
        ]);
    }

    private function grantInvoice(PartnerSubscription $purchase, array $invoice): void
    {
        $this->require(($invoice['status'] ?? '') === 'paid' && ($invoice['paid'] ?? false)
            && $this->id($invoice['customer'] ?? '') === $purchase->stripe_customer_id, 'The invoice has not been verified as paid.');
        $expected = $purchase->quantity * $purchase->unit_price_cents;
        $this->require(($invoice['currency'] ?? '') === $purchase->currency
            && (int) ($invoice['subtotal'] ?? -1) === $expected
            && (int) ($invoice['amount_paid'] ?? 0) >= $expected, 'The paid invoice amount does not match the package.');
        $lines = data_get($invoice, 'lines.data', []);
        $this->require(count($lines) === 1 && ! data_get($invoice, 'lines.has_more', false), 'The invoice contains unexpected line items.');
        $line = $lines[0];
        $price = $line['price'] ?? [];
        $this->require((int) ($line['quantity'] ?? 0) === $purchase->quantity
            && (int) ($line['amount'] ?? -1) === $expected && ! ($line['proration'] ?? false)
            && (int) ($price['unit_amount'] ?? 0) === $purchase->unit_price_cents
            && ($price['currency'] ?? '') === $purchase->currency, 'The invoice line does not match the purchased package.');
        if ($purchase->plan === 'monthly') {
            $this->require(in_array($invoice['billing_reason'] ?? '', ['subscription_create', 'subscription_cycle'], true)
                && $this->id($invoice['subscription'] ?? data_get($invoice, 'parent.subscription_details.subscription', '')) === $purchase->stripe_subscription_id
                && ($price['id'] ?? '') === $purchase->stripe_price_id
                && data_get($price, 'recurring.interval') === 'month', 'This invoice is not a monthly package renewal.');
        }
        $start = (int) data_get($line, 'period.start', $invoice['created'] ?? 0);
        $end = (int) data_get($line, 'period.end', $start);
        $this->require($start > 0 && $end >= $start && ($purchase->plan !== 'monthly' || $end > $start), 'The invoice period is missing or invalid.');
        $chargeId = $this->id($invoice['charge'] ?? '');
        $paymentIntentId = $this->id($invoice['payment_intent'] ?? '');
        $charge = $chargeId !== '' ? $this->gateway->retrieveCharge($chargeId) : null;
        if ($charge) {
            $this->require($this->id($charge['customer'] ?? '') === $purchase->stripe_customer_id, 'The invoice charge belongs to another account.');
        }
        DB::transaction(function () use ($purchase, $invoice, $start, $end, $chargeId, $paymentIntentId, $charge) {
            PartnerProfile::lockForUpdate()->findOrFail($purchase->partner_profile_id);
            $locked = PartnerSubscription::lockForUpdate()->findOrFail($purchase->id);
            if (PartnerLeadCredit::where('stripe_invoice_id', $invoice['id'])->exists()) {
                return;
            }
            $this->require($locked->plan === 'monthly' || ! $locked->credits()->exists(), 'This package already has a paid lead allocation.');
            $duplicatePeriod = PartnerLeadCredit::where('partner_subscription_id', $locked->id)
                ->where('period_start', Carbon::createFromTimestamp($start))->where('period_end', Carbon::createFromTimestamp($end))->exists();
            $this->require(! $duplicatePeriod, 'This billing period already has a paid lead allocation.');
            $held = ($charge && ((int) ($charge['amount_refunded'] ?? 0) > 0 || ($charge['disputed'] ?? false)))
                || ($chargeId && DB::table('partner_payment_holds')->where('stripe_charge_id', $chargeId)->exists());
            PartnerLeadCredit::create([
                'partner_profile_id' => $locked->partner_profile_id, 'partner_subscription_id' => $locked->id,
                'stripe_invoice_id' => $invoice['id'], 'stripe_payment_intent_id' => $paymentIntentId ?: null,
                'purchase_once_key' => $locked->plan !== 'monthly' ? $locked->id : null,
                'stripe_charge_id' => $chargeId ?: null, 'quantity' => $locked->quantity, 'used_quantity' => 0,
                'amount_cents' => $invoice['amount_paid'], 'currency' => $locked->currency,
                'invoice_number' => $invoice['number'] ?? null,
                'hosted_url' => $this->stripeUrl($invoice['hosted_invoice_url'] ?? null),
                'pdf_url' => $this->stripeUrl($invoice['invoice_pdf'] ?? null),
                'period_start' => Carbon::createFromTimestamp($start), 'period_end' => Carbon::createFromTimestamp($end),
                'paid_at' => Carbon::createFromTimestamp((int) data_get($invoice, 'status_transitions.paid_at', time())),
                'suspended_at' => $held ? now() : null, 'suspension_reason' => $held ? 'Payment requires review' : null,
            ]);
            if ($locked->plan !== 'monthly') {
                $locked->update(['status' => 'paid']);
            }
        });
    }

    private function handleRisk(string $type, array $object, bool $livemode, ?string $account): bool
    {
        if (filled($account)) {
            return false;
        }
        $chargeId = str_starts_with($type, 'charge.dispute.') || str_starts_with($type, 'refund.') || str_starts_with($type, 'radar.')
            ? $this->id($object['charge'] ?? '') : (string) ($object['id'] ?? '');
        if ($chargeId === '') {
            return false;
        }
        $knownCustomer = $this->id($object['customer'] ?? '');
        if ($knownCustomer !== '' && ! PartnerProfile::where('stripe_customer_id', $knownCustomer)->exists()) {
            return false;
        }
        if (! PartnerProfile::whereNotNull('stripe_customer_id')->exists()) {
            return false;
        }
        if (\App\Models\CareBookingPayment::where('stripe_charge_id', $chargeId)->exists()) {
            return false;
        }
        $charge = $this->gateway->retrieveCharge($chargeId);
        $profile = PartnerProfile::where('stripe_customer_id', $this->id($charge['customer'] ?? ''))->first();
        if (! $profile) {
            return false;
        }
        $this->require(blank($account) && (bool) ($charge['livemode'] ?? false) === $livemode, 'Payment risk event environment does not match.');
        $shouldHold = (int) ($charge['amount_refunded'] ?? 0) > 0 || ($charge['disputed'] ?? false)
            || str_starts_with($type, 'charge.dispute.') || $type === 'radar.early_fraud_warning.created';
        if ($shouldHold) {
            DB::transaction(function () use ($profile, $chargeId, $type) {
                PartnerProfile::lockForUpdate()->findOrFail($profile->id);
                DB::table('partner_payment_holds')->updateOrInsert(['stripe_charge_id' => $chargeId], [
                    'partner_profile_id' => $profile->id, 'reason' => $type, 'created_at' => now(), 'updated_at' => now(),
                ]);
                PartnerLeadCredit::where('partner_profile_id', $profile->id)->where('stripe_charge_id', $chargeId)
                    ->update(['suspended_at' => now(), 'suspension_reason' => 'Payment requires review']);
            });
        }

        return true;
    }

    private function assertMode(PartnerSubscription $purchase, bool $livemode, array $object): void
    {
        $this->require((bool) ($object['livemode'] ?? false) === $livemode
            && ($purchase->livemode === null || $purchase->livemode === $livemode), 'The payment environment does not match the purchase.');
        if ($purchase->livemode === null) {
            $purchase->update(['livemode' => $livemode]);
        }
    }

    private function checkoutUrl(array $session): string
    {
        $url = (string) ($session['url'] ?? '');
        $this->require(parse_url($url, PHP_URL_SCHEME) === 'https' && parse_url($url, PHP_URL_HOST) === 'checkout.stripe.com', 'Unable to open secure checkout. Please try again.');

        return $url;
    }

    private function stripeUrl(?string $url): ?string
    {
        $host = (string) parse_url((string) $url, PHP_URL_HOST);

        return $url && parse_url($url, PHP_URL_SCHEME) === 'https' && ($host === 'stripe.com' || str_ends_with($host, '.stripe.com')) ? $url : null;
    }

    private function id(mixed $value): string
    {
        return is_array($value) ? (string) ($value['id'] ?? '') : (string) $value;
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new PaymentException($message);
        }
    }
}
