<?php

namespace Tests\Feature\Payments;

use App\Exceptions\Payments\PaymentException;
use App\Models\PartnerLeadCredit;
use App\Models\PartnerProfile;
use App\Models\PartnerSubscription;
use App\Models\User;
use App\Services\Partners\PartnerBillingService;
use App\Services\Partners\PartnerStripeGateway;
use App\Services\Payments\StripeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Tests\TestCase;

class PartnerBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.bypass' => false, 'services.stripe.secret' => 'test-placeholder']);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        ApiRequestor::resetTelemetry();
        parent::tearDown();
    }

    private function profile(array $attributes = []): PartnerProfile
    {
        $customer = $attributes['stripe_customer_id'] ?? 'cus_partner_'.Str::random(8);
        unset($attributes['stripe_customer_id']);
        $profile = PartnerProfile::create(array_replace([
            'user_id' => User::factory()->create(['role' => 'partner'])->id,
            'business_name' => 'Example Partner', 'contact_name' => 'Alex Example',
            'contact_email' => 'partner@example.test', 'contact_phone' => '9195550100',
            'categories' => ['meal_delivery'], 'coverage_states' => ['NC'], 'coverage_type' => 'entire_states',
            'status' => 'approved',
        ], $attributes));
        $profile->forceFill(['stripe_customer_id' => $customer])->save();

        return $profile;
    }

    private function purchase(PartnerProfile $profile, string $plan = 'monthly', array $attributes = []): PartnerSubscription
    {
        $option = config('partners.plans.'.$plan);

        return PartnerSubscription::create(array_replace([
            'public_id' => (string) Str::uuid(), 'partner_profile_id' => $profile->id, 'plan' => $plan,
            'stripe_customer_id' => $profile->stripe_customer_id,
            'stripe_subscription_id' => $plan === 'monthly' ? 'sub_'.Str::random(10) : null,
            'quantity' => $option['quantity'], 'unit_price_cents' => $option['unit_price_cents'],
            'currency' => 'usd', 'status' => $plan === 'monthly' ? 'active' : 'checkout_pending',
            'stripe_price_id' => 'price_partner', 'livemode' => false,
        ], $attributes));
    }

    private function subscriptionData(PartnerSubscription $purchase, array $attributes = []): array
    {
        return array_replace_recursive([
            'id' => $purchase->stripe_subscription_id, 'customer' => $purchase->stripe_customer_id,
            'status' => 'active', 'livemode' => false, 'cancel_at_period_end' => false,
            'current_period_start' => 1794960000, 'current_period_end' => 1797552000,
            'metadata' => ['partner_profile_id' => (string) $purchase->partner_profile_id, 'partner_subscription_key' => $purchase->public_id],
            'items' => ['data' => [['quantity' => $purchase->quantity, 'price' => [
                'id' => 'price_partner', 'unit_amount' => $purchase->unit_price_cents, 'currency' => 'usd',
                'recurring' => ['interval' => 'month', 'interval_count' => 1],
            ]]]],
        ], $attributes);
    }

    private function invoice(PartnerSubscription $purchase, array $attributes = []): array
    {
        $amount = $purchase->quantity * $purchase->unit_price_cents;

        return array_replace_recursive([
            'id' => 'in_partner_first', 'customer' => $purchase->stripe_customer_id,
            'subscription' => $purchase->stripe_subscription_id, 'status' => 'paid', 'paid' => true, 'livemode' => false,
            'currency' => 'usd', 'subtotal' => $amount, 'amount_paid' => $amount, 'total' => $amount,
            'created' => 1794960000, 'number' => 'PARTNER-001', 'billing_reason' => $purchase->plan === 'monthly' ? 'subscription_cycle' : 'manual',
            'metadata' => ['partner_profile_id' => (string) $purchase->partner_profile_id, 'partner_subscription_key' => $purchase->public_id],
            'status_transitions' => ['paid_at' => 1794960020],
            'hosted_invoice_url' => 'https://invoice.stripe.com/i/example', 'invoice_pdf' => 'https://pay.stripe.com/invoice/example/pdf',
            'lines' => ['has_more' => false, 'data' => [[
                'quantity' => $purchase->quantity, 'amount' => $amount, 'proration' => false,
                'period' => ['start' => 1794960000, 'end' => 1797552000],
                'price' => ['id' => 'price_partner', 'unit_amount' => $purchase->unit_price_cents, 'currency' => 'usd', 'recurring' => ['interval' => 'month']],
            ]]],
        ], $attributes);
    }

    private function mockInvoice(PartnerSubscription $purchase, array $invoice)
    {
        $gateway = $this->mock(PartnerStripeGateway::class);
        $gateway->shouldReceive('retrieveInvoice')->with($invoice['id'])->andReturn($invoice);
        if ($purchase->plan === 'monthly') {
            $gateway->shouldReceive('retrieveSubscription')->with($purchase->stripe_subscription_id)->andReturn($this->subscriptionData($purchase));
        }

        return $gateway;
    }

    private function service(): PartnerBillingService
    {
        return app(PartnerBillingService::class);
    }

    public function test_catalog_has_only_the_three_agreed_prices_and_annual_is_not_recurring(): void
    {
        $plans = $this->service()->packageOptions();
        $this->assertSame(['one_time', 'monthly', 'annual'], array_keys($plans));
        $this->assertSame(100000, $plans['one_time']['amount_cents']);
        $this->assertSame(75000, $plans['monthly']['amount_cents']);
        $this->assertSame(750000, $plans['annual']['amount_cents']);
        $this->assertSame(300, $plans['annual']['quantity']);
        $this->assertFalse($plans['annual']['recurring']);
    }

    public function test_checkout_uses_server_catalog_and_reuses_a_pending_attempt(): void
    {
        $profile = $this->profile();
        $gateway = $this->mock(PartnerStripeGateway::class);
        $gateway->shouldReceive('createSubscriptionCheckout')->once()->withArgs(function ($purchase) {
            $this->assertSame('monthly', $purchase->plan);
            $this->assertSame(25, $purchase->quantity);
            $this->assertSame(3000, $purchase->unit_price_cents);

            return true;
        })->andReturn(['id' => 'cs_partner', 'url' => 'https://checkout.stripe.com/c/pay/example']);
        $gateway->shouldReceive('retrieveCheckout')->once()->with('cs_partner')
            ->andReturn(['id' => 'cs_partner', 'status' => 'open', 'url' => 'https://checkout.stripe.com/c/pay/example']);
        $this->actingAs($profile->user)->post(route('partner.billing.subscribe'), ['plan' => 'monthly', 'quantity' => 1, 'amount' => 1])
            ->assertRedirect('https://checkout.stripe.com/c/pay/example');
        $this->post(route('partner.billing.subscribe'), ['plan' => 'monthly'])
            ->assertRedirect('https://checkout.stripe.com/c/pay/example');
        $this->assertDatabaseCount('partner_subscriptions', 1);
        $this->assertDatabaseCount('partner_lead_credits', 0);
    }

    public function test_unapproved_partner_and_unknown_plan_cannot_buy(): void
    {
        $gateway = $this->mock(PartnerStripeGateway::class);
        $gateway->shouldNotReceive('createSubscriptionCheckout');
        $profile = $this->profile(['status' => 'new']);
        $this->actingAs($profile->user)->post(route('partner.billing.subscribe'), ['plan' => 'monthly'])->assertSessionHasErrors('billing');
        $this->post(route('partner.billing.subscribe'), ['plan' => 'made_up'])->assertSessionHasErrors('plan');
        $this->assertDatabaseCount('partner_subscriptions', 0);
    }

    public function test_active_monthly_subscription_blocks_duplicate_but_allows_upfront_purchase(): void
    {
        $profile = $this->profile();
        $this->purchase($profile);
        $gateway = $this->mock(PartnerStripeGateway::class);
        $gateway->shouldReceive('createSubscriptionCheckout')->once()->withArgs(fn ($purchase) => $purchase->plan === 'one_time')
            ->andReturn(['id' => 'cs_once', 'url' => 'https://checkout.stripe.com/c/pay/once']);
        $this->actingAs($profile->user)->post(route('partner.billing.subscribe'), ['plan' => 'monthly'])->assertSessionHasErrors('billing');
        $this->post(route('partner.billing.subscribe'), ['plan' => 'one_time'])->assertRedirect('https://checkout.stripe.com/c/pay/once');
        $this->assertDatabaseCount('partner_subscriptions', 2);
    }

    public function test_card_setup_is_available_before_partner_approval(): void
    {
        $profile = $this->profile(['status' => 'paused']);
        $this->mock(PartnerStripeGateway::class)->shouldReceive('createSetupCheckout')->once()
            ->andReturn(['id' => 'cs_setup', 'url' => 'https://checkout.stripe.com/c/pay/setup']);
        $this->actingAs($profile->user)->post(route('partner.billing.setup'))->assertRedirect('https://checkout.stripe.com/c/pay/setup');
    }

    public function test_verified_card_setup_updates_customer_subscription_and_safe_display_cache(): void
    {
        $profile = $this->profile();
        $subscription = $this->purchase($profile);
        $gateway = $this->mock(PartnerStripeGateway::class);
        $gateway->shouldReceive('retrieveCheckout')->with('cs_setup')->once()->andReturn([
            'id' => 'cs_setup', 'mode' => 'setup', 'status' => 'complete', 'customer' => $profile->stripe_customer_id,
            'metadata' => ['partner_domain' => 'setup', 'partner_profile_id' => (string) $profile->id],
            'setup_intent' => ['status' => 'succeeded', 'customer' => $profile->stripe_customer_id, 'payment_method' => 'pm_new'],
        ]);
        $gateway->shouldReceive('retrievePaymentMethod')->with('pm_new')->once()->andReturn(['customer' => $profile->stripe_customer_id, 'type' => 'card']);
        $gateway->shouldReceive('setDefaultPaymentMethod')->with($profile->stripe_customer_id, 'pm_new', 'cs_setup')->once();
        $gateway->shouldReceive('setSubscriptionDefaultPaymentMethod')->with($subscription->stripe_subscription_id, 'pm_new', 'cs_setup')->once();
        $stripe = $this->mock(StripeClient::class);
        $stripe->shouldReceive('isBypass')->andReturn(false);
        $stripe->shouldReceive('defaultPaymentMethodForCustomer')->with($profile->stripe_customer_id)->once()
            ->andReturn(['id' => 'pm_new', 'brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2030]);
        $this->service()->syncSetupCheckoutSession($profile, 'cs_setup');
        $summary = $profile->fresh()->payment_method_summary;
        $this->assertSame('4242', $summary['last4']);
        $this->assertArrayNotHasKey('id', $summary);
        $this->assertNotNull($profile->fresh()->payment_method_updated_at);
    }

    public function test_forged_setup_return_cannot_claim_another_customers_card(): void
    {
        $profile = $this->profile();
        $gateway = $this->mock(PartnerStripeGateway::class);
        $gateway->shouldReceive('retrieveCheckout')->once()->andReturn([
            'id' => 'cs_other', 'mode' => 'setup', 'status' => 'complete', 'customer' => 'cus_someone_else',
            'metadata' => ['partner_domain' => 'setup', 'partner_profile_id' => (string) $profile->id],
        ]);
        $gateway->shouldNotReceive('setDefaultPaymentMethod');
        $this->actingAs($profile->user)->get(route('partner.billing.show', ['flow' => 'setup', 'checkout_session_id' => 'cs_other']))
            ->assertRedirect(route('partner.billing.show'))->assertSessionHasErrors('billing');
        $this->assertNull($profile->fresh()->payment_method_summary);
    }

    public function test_completed_return_never_issues_lead_credits(): void
    {
        $profile = $this->profile();
        $purchase = $this->purchase($profile, 'annual', ['stripe_checkout_session_id' => 'cs_annual']);
        $this->mock(PartnerStripeGateway::class)->shouldReceive('retrieveCheckout')->once()->andReturn([
            'id' => 'cs_annual', 'mode' => 'payment', 'status' => 'complete', 'payment_status' => 'paid', 'customer' => $profile->stripe_customer_id,
            'metadata' => ['partner_profile_id' => (string) $profile->id, 'partner_subscription_key' => $purchase->public_id],
        ]);
        $this->service()->verifyCheckoutReturn($profile, 'cs_annual');
        $this->assertDatabaseCount('partner_lead_credits', 0);
    }

    public function test_failed_delayed_upfront_payment_can_be_retried_without_credits(): void
    {
        $profile = $this->profile();
        $purchase = $this->purchase($profile, 'one_time', ['stripe_checkout_session_id' => 'cs_failed']);
        $gateway = $this->mock(PartnerStripeGateway::class);
        $gateway->shouldReceive('retrieveCheckout')->with('cs_failed')->andReturn([
            'id' => 'cs_failed', 'mode' => 'payment', 'status' => 'complete', 'payment_status' => 'unpaid',
            'payment_intent' => 'pi_failed', 'customer' => $profile->stripe_customer_id, 'livemode' => false,
            'metadata' => ['partner_profile_id' => (string) $profile->id, 'partner_subscription_key' => $purchase->public_id],
        ]);
        $gateway->shouldReceive('retrievePaymentIntent')->with('pi_failed')->once()
            ->andReturn(['status' => 'requires_payment_method', 'customer' => $profile->stripe_customer_id]);
        $this->service()->handle('checkout.session.async_payment_failed', [
            'id' => 'cs_failed', 'customer' => $profile->stripe_customer_id, 'mode' => 'payment',
        ]);
        $this->assertSame('checkout_failed', $purchase->fresh()->status);
        $this->assertDatabaseCount('partner_lead_credits', 0);
        $gateway->shouldReceive('createSubscriptionCheckout')->once()
            ->andReturn(['id' => 'cs_retry', 'url' => 'https://checkout.stripe.com/c/pay/retry']);
        $this->actingAs($profile->user)->post(route('partner.billing.subscribe'), ['plan' => 'one_time'])
            ->assertRedirect('https://checkout.stripe.com/c/pay/retry');
        $this->assertDatabaseCount('partner_subscriptions', 2);
        $purchase->update(['status' => 'paid']);
        $this->service()->handle('checkout.session.async_payment_failed', [
            'id' => 'cs_failed', 'customer' => $profile->stripe_customer_id, 'mode' => 'payment',
        ]);
        $this->assertSame('paid', $purchase->fresh()->status);
    }

    public function test_signed_invoice_endpoint_fulfills_once_and_rejects_bad_signatures(): void
    {
        config(['services.stripe.webhook_secret' => 'partner-webhook-test-secret']);
        $profile = $this->profile();
        $purchase = $this->purchase($profile);
        $invoice = $this->invoice($purchase);
        $gateway = $this->mock(PartnerStripeGateway::class);
        $gateway->shouldReceive('retrieveInvoice')->with($invoice['id'])->once()->andReturn($invoice);
        $gateway->shouldReceive('retrieveSubscription')->with($purchase->stripe_subscription_id)->once()->andReturn($this->subscriptionData($purchase));
        $body = json_encode(['id' => 'evt_partner_paid', 'object' => 'event', 'type' => 'invoice.paid',
            'livemode' => false, 'data' => ['object' => $invoice + ['object' => 'invoice']]]);
        $time = time();
        $signature = 't='.$time.',v1='.hash_hmac('sha256', $time.'.'.$body, 'partner-webhook-test-secret');
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature];
        $this->call('POST', route('webhooks.stripe'), [], [], [], $server, $body)->assertOk();
        $this->call('POST', route('webhooks.stripe'), [], [], [], $server, $body)->assertOk()->assertJson(['duplicate' => true]);
        $this->assertSame(25, $profile->remainingLeadCredits());
        $this->assertDatabaseCount('partner_lead_credits', 1);
        $this->assertDatabaseHas('stripe_webhook_events', ['stripe_event_id' => 'evt_partner_paid', 'status' => 'processed']);
        $this->call('POST', route('webhooks.stripe'), [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't='.$time.',v1=invalid',
        ], $body)->assertStatus(401);
        $this->assertDatabaseCount('partner_lead_credits', 1);
    }

    public function test_monthly_paid_invoice_issues_once_and_renewal_retains_unused_leads(): void
    {
        $profile = $this->profile();
        $purchase = $this->purchase($profile);
        $invoice = $this->invoice($purchase);
        $gateway = $this->mockInvoice($purchase, $invoice);
        $this->service()->handle('invoice.paid', $invoice);
        $credit = PartnerLeadCredit::firstOrFail();
        $credit->update(['used_quantity' => 3]);
        $this->service()->handle('invoice.paid', $invoice);
        $renewal = $this->invoice($purchase, ['id' => 'in_renewal', 'lines' => ['data' => [['period' => ['start' => 1797552000, 'end' => 1800230400]]]]]);
        $gateway->shouldReceive('retrieveInvoice')->with('in_renewal')->andReturn($renewal);
        $this->service()->handle('invoice.paid', $renewal);
        $this->assertDatabaseCount('partner_lead_credits', 2);
        $this->assertSame(3, $credit->fresh()->used_quantity);
        $this->assertSame(47, $profile->remainingLeadCredits());
    }

    public static function upfrontPlans(): array
    {
        return [['one_time', 25], ['annual', 300]];
    }

    #[DataProvider('upfrontPlans')]
    public function test_upfront_paid_invoice_grants_full_quantity_without_subscription(string $plan, int $quantity): void
    {
        $profile = $this->profile();
        $purchase = $this->purchase($profile, $plan);
        $invoice = $this->invoice($purchase);
        $this->mockInvoice($purchase, $invoice);
        $this->service()->handle('invoice.paid', $invoice);
        $this->assertSame($quantity, $profile->remainingLeadCredits());
        $this->assertSame('paid', $purchase->fresh()->status);
        $this->assertNull($this->service()->subscriptionFor($profile));
    }

    public function test_upfront_purchase_cannot_issue_again_from_another_invoice_or_period(): void
    {
        $profile = $this->profile();
        $purchase = $this->purchase($profile, 'one_time');
        $invoice = $this->invoice($purchase);
        $gateway = $this->mockInvoice($purchase, $invoice);
        $this->service()->handle('invoice.paid', $invoice);
        $second = $this->invoice($purchase, ['id' => 'in_second', 'lines' => ['data' => [['period' => ['start' => 1797552000, 'end' => 1800230400]]]]]);
        $gateway->shouldReceive('retrieveInvoice')->with('in_second')->andReturn($second);
        try {
            $this->service()->handle('invoice.paid', $second);
            $this->fail('A second invoice must not grant the same package twice.');
        } catch (PaymentException $exception) {
            $this->assertStringContainsString('already has', $exception->userMessage);
        }
        $this->assertSame(25, $profile->remainingLeadCredits());
    }

    public static function invalidInvoices(): array
    {
        return [
            'unpaid' => [['status' => 'open', 'paid' => false, 'amount_paid' => 0]],
            'wrong amount' => [['amount_paid' => 100]],
            'wrong currency' => [['currency' => 'eur']],
            'wrong quantity' => [['lines' => ['data' => [['quantity' => 1]]]]],
            'prorated' => [['lines' => ['data' => [['proration' => true]]]]],
            'wrong period' => [['lines' => ['data' => [['period' => ['end' => 1]]]]]],
        ];
    }

    #[DataProvider('invalidInvoices')]
    public function test_unpaid_or_mismatched_invoice_never_grants(array $changes): void
    {
        $profile = $this->profile();
        $purchase = $this->purchase($profile);
        $invoice = $this->invoice($purchase, $changes);
        $this->mockInvoice($purchase, $invoice);
        try {
            $this->service()->handle('invoice.paid', $invoice);
            $this->fail('Invalid paid invoice must be rejected.');
        } catch (PaymentException) {
            $this->assertDatabaseCount('partner_lead_credits', 0);
        }
    }

    public function test_risk_event_before_fulfillment_holds_later_credit_without_reopening_used_leads(): void
    {
        $profile = $this->profile();
        $purchase = $this->purchase($profile);
        $invoice = $this->invoice($purchase, ['charge' => 'ch_partner']);
        $gateway = $this->mockInvoice($purchase, $invoice);
        $gateway->shouldReceive('retrieveCharge')->with('ch_partner')->andReturn([
            'id' => 'ch_partner', 'customer' => $profile->stripe_customer_id, 'amount_refunded' => 100, 'livemode' => false,
        ]);
        $this->service()->handle('charge.refunded', ['id' => 'ch_partner', 'customer' => $profile->stripe_customer_id]);
        $this->service()->handle('invoice.paid', $invoice);
        $credit = PartnerLeadCredit::firstOrFail();
        $credit->update(['used_quantity' => 2]);
        $this->service()->handle('charge.dispute.created', ['id' => 'dp_example', 'charge' => 'ch_partner']);
        $this->assertNotNull($credit->fresh()->suspended_at);
        $this->assertSame(2, $credit->fresh()->used_quantity);
        $this->assertSame(0, $profile->remainingLeadCredits());
    }

    public function test_unrelated_risk_events_pass_through_without_stripe_calls(): void
    {
        $gateway = $this->mock(PartnerStripeGateway::class);
        $gateway->shouldNotReceive('retrieveCharge');
        $this->assertFalse($this->service()->handle('charge.refunded', ['id' => 'ch_family', 'customer' => 'cus_family']));
        $this->profile();
        $this->assertFalse($this->service()->handle('charge.refunded', ['id' => 'ch_family', 'customer' => 'cus_family']));
        $this->assertFalse($this->service()->handle('charge.dispute.created', ['id' => 'dp_other', 'charge' => 'ch_other'], false, 'acct_connected'));
    }

    public function test_invoice_list_is_customer_scoped_and_handles_missing_description(): void
    {
        $profile = $this->profile();
        $this->mock(PartnerStripeGateway::class)->shouldReceive('listInvoices')->with($profile->stripe_customer_id)->once()->andReturn([
            ['id' => 'in_own', 'customer' => $profile->stripe_customer_id, 'created' => 1794960000, 'total' => 75000, 'currency' => 'usd', 'status' => 'paid'],
            ['id' => 'in_other', 'customer' => 'cus_other', 'created' => 1794960000, 'total' => 999999],
        ]);
        $invoices = $this->service()->invoicesFor($profile);
        $this->assertCount(1, $invoices);
        $this->assertSame('in_own', $invoices->first()['id']);
        $this->assertSame('Lead package', $invoices->first()['description']);
    }

    public function test_cancellation_stops_future_renewal_without_removing_credits(): void
    {
        $profile = $this->profile();
        $purchase = $this->purchase($profile);
        $invoice = $this->invoice($purchase);
        $gateway = $this->mockInvoice($purchase, $invoice);
        $this->service()->handle('invoice.paid', $invoice);
        $gateway->shouldReceive('cancelSubscription')->with($purchase->stripe_subscription_id)->once()
            ->andReturn($this->subscriptionData($purchase, ['cancel_at_period_end' => true]));
        $this->actingAs($profile->user)->post(route('partner.billing.cancel'))->assertSessionHasNoErrors();
        $this->assertTrue($purchase->fresh()->cancel_at_period_end);
        $this->assertSame(25, $profile->remainingLeadCredits());
    }

    public function test_nonpartner_cannot_start_setup_purchase_or_cancel(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $this->actingAs($family)->post(route('partner.billing.setup'))->assertForbidden();
        $this->post(route('partner.billing.subscribe'), ['plan' => 'monthly'])->assertForbidden();
        $this->post(route('partner.billing.cancel'))->assertForbidden();
    }

    #[DataProvider('upfrontPlans')]
    public function test_gateway_upfront_checkout_creates_invoice_and_never_auto_renews(string $plan, int $quantity): void
    {
        $profile = $this->profile();
        $purchase = $this->purchase($profile, $plan);
        $http = Mockery::mock(ClientInterface::class);
        $http->shouldReceive('request')->once()->andReturnUsing(function ($method, $url, $headers, $params) use ($quantity): array {
            $this->assertSame('payment', $params['mode']);
            $this->assertSame($quantity, $params['line_items'][0]['quantity']);
            $this->assertSame('true', $params['invoice_creation']['enabled']);
            $this->assertArrayNotHasKey('recurring', $params['line_items'][0]['price_data']);
            $this->assertArrayNotHasKey('payment_method_types', $params);

            return [json_encode(['id' => 'cs_mocked', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/mock']), 200, []];
        });
        ApiRequestor::setHttpClient($http);
        app(PartnerStripeGateway::class)->createSubscriptionCheckout($purchase, 'https://example.test/success', 'https://example.test/cancel');
    }
}
