<?php

namespace App\Services\Partners;

use App\Exceptions\Payments\PaymentException;
use App\Models\PartnerProfile;
use App\Models\PartnerSubscription;
use Stripe\StripeClient;

class PartnerStripeGateway
{
    private ?StripeClient $client = null;

    protected function client(): StripeClient
    {
        if ((bool) config('services.stripe.bypass')) {
            throw new PaymentException('Payments are unavailable in preview mode. No payment has been taken.');
        }
        $secret = (string) config('services.stripe.secret');
        if ($secret === '') {
            throw new PaymentException('Payments are temporarily unavailable. Please try again later.');
        }

        return $this->client ??= new StripeClient($secret);
    }

    public function createCustomer(PartnerProfile $profile): array
    {
        return $this->client()->customers->create([
            'name' => $profile->business_name, 'email' => $profile->contact_email,
            'metadata' => ['partner_profile_id' => (string) $profile->id, 'partner_user_id' => (string) $profile->user_id],
        ], ['idempotency_key' => 'partner-customer:'.$profile->id])->toArray();
    }

    public function createSetupCheckout(PartnerProfile $profile, string $successUrl, string $cancelUrl): array
    {
        $metadata = ['partner_domain' => 'setup', 'partner_profile_id' => (string) $profile->id];

        return $this->client()->checkout->sessions->create([
            'mode' => 'setup', 'currency' => config('partners.currency'), 'customer' => $profile->stripe_customer_id,
            'success_url' => $successUrl, 'cancel_url' => $cancelUrl,
            'metadata' => $metadata, 'setup_intent_data' => ['metadata' => $metadata],
        ])->toArray();
    }

    public function retrieveCheckout(string $id): array
    {
        return $this->client()->checkout->sessions->retrieve($id, ['expand' => ['setup_intent', 'subscription']])->toArray();
    }

    public function retrievePaymentMethod(string $id): array
    {
        return $this->client()->paymentMethods->retrieve($id)->toArray();
    }

    public function retrievePaymentIntent(string $id): array
    {
        return $this->client()->paymentIntents->retrieve($id)->toArray();
    }

    public function setDefaultPaymentMethod(string $customer, string $paymentMethod, string $sessionId): void
    {
        $this->client()->customers->update($customer, ['invoice_settings' => ['default_payment_method' => $paymentMethod]],
            ['idempotency_key' => 'partner-payment-method:'.$sessionId]);
    }

    public function setSubscriptionDefaultPaymentMethod(string $subscription, string $paymentMethod, string $sessionId): void
    {
        $this->client()->subscriptions->update($subscription, ['default_payment_method' => $paymentMethod],
            ['idempotency_key' => 'partner-subscription-method:'.$subscription.':'.$sessionId]);
    }

    public function createSubscriptionCheckout(PartnerSubscription $subscription, string $successUrl, string $cancelUrl): array
    {
        $metadata = ['partner_domain' => 'purchase', 'partner_subscription_key' => $subscription->public_id,
            'partner_profile_id' => (string) $subscription->partner_profile_id];
        $monthly = $subscription->plan === 'monthly';
        $parameters = [
            'mode' => $monthly ? 'subscription' : 'payment', 'customer' => $subscription->stripe_customer_id,
            'client_reference_id' => $subscription->public_id,
            'success_url' => $successUrl, 'cancel_url' => $cancelUrl,
            'metadata' => $metadata,
            'line_items' => [['quantity' => $subscription->quantity, 'price_data' => array_filter([
                'currency' => $subscription->currency, 'unit_amount' => $subscription->unit_price_cents,
                'recurring' => $monthly ? ['interval' => 'month'] : null,
                'product_data' => ['name' => 'LoLo '.(config('partners.plans.'.$subscription->plan.'.name') ?? 'lead package'),
                    'description' => 'Unused purchased leads carry over until used.'.($monthly ? ' Renews monthly until canceled.' : ' One-time payment. Does not renew automatically.')],
            ], fn ($value) => $value !== null)]],
        ];
        if ($monthly) {
            $parameters['subscription_data'] = ['metadata' => $metadata];
        } else {
            $parameters['payment_intent_data'] = ['metadata' => $metadata];
            $parameters['invoice_creation'] = ['enabled' => true, 'invoice_data' => ['metadata' => $metadata]];
        }

        return $this->client()->checkout->sessions->create($parameters,
            ['idempotency_key' => 'partner-subscription:'.$subscription->public_id])->toArray();
    }

    public function retrieveSubscription(string $id): array
    {
        return $this->client()->subscriptions->retrieve($id, ['expand' => ['items.data.price']])->toArray();
    }

    public function cancelSubscription(string $id): array
    {
        return $this->client()->subscriptions->update($id, ['cancel_at_period_end' => true],
            ['idempotency_key' => 'partner-subscription-cancel:'.$id])->toArray();
    }

    public function retrieveInvoice(string $id): array
    {
        return $this->client()->invoices->retrieve($id, ['expand' => ['lines.data.price', 'payment_intent', 'charge']])->toArray();
    }

    public function listInvoices(string $customer): array
    {
        return $this->client()->invoices->all(['customer' => $customer, 'limit' => 50])->toArray()['data'];
    }

    public function retrieveCharge(string $id): array
    {
        return $this->client()->charges->retrieve($id)->toArray();
    }
}
