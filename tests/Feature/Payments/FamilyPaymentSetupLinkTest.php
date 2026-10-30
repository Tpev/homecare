<?php

namespace Tests\Feature\Payments;

use App\Exceptions\Payments\PaymentException;
use App\Models\FamilyAccount;
use App\Models\FamilyPaymentSetupLink;
use App\Models\User;
use App\Services\CustomerOnboarding\FamilyPaymentSetupService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\Payments\FamilyBillingService;
use App\Services\Payments\StripeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Tests\TestCase;

class FamilyPaymentSetupLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        ApiRequestor::resetTelemetry();
        parent::tearDown();
    }

    public function test_link_is_encrypted_admin_only_and_scoped_to_payment_setup(): void
    {
        [$admin, $account, $service, $link] = $this->setupLink();
        $this->assertSame(64, strlen($link->token));
        $this->assertSame(hash('sha256', $link->token), $link->token_hash);
        $this->assertNotSame($link->token, DB::table('family_payment_setup_links')->where('id', $link->id)->value('token'));
        $this->assertSame(now()->addDays(7)->toDateString(), $link->expires_at->toDateString());
        $this->assertArrayNotHasKey('token', $link->toArray());

        $this->get($service->urlFor($link))->assertOk()
            ->assertSee('Add your payment method')->assertSee('No payment is collected now.')
            ->assertDontSee($account->owner->email)->assertDontSee($account->owner->name)
            ->assertDontSee('googletagmanager')->assertDontSee('facebook.net')
            ->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->assertGuest();
        $this->assertNull($link->fresh()->completed_at);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->generate($account->owner, $account);
    }

    public function test_guest_must_consent_and_repeated_start_reuses_the_checkout(): void
    {
        [, , $service, $link] = $this->setupLink();
        $this->post($service->urlFor($link), [])->assertStatus(422)->assertSee('Please agree');
        $this->assertNull($link->fresh()->stripe_checkout_session_id);
        $this->post($service->urlFor($link), ['consent' => 1])->assertRedirect(route('public.family-payment-setup.return', ['token' => $link->token]));
        $sessionId = $link->fresh()->stripe_checkout_session_id;
        $this->post($service->urlFor($link), ['consent' => 1])->assertRedirect();
        $this->assertSame($sessionId, $link->fresh()->stripe_checkout_session_id);
        $this->assertSame(1, $link->fresh()->checkout_attempt);
        $this->assertNotNull($link->fresh()->consented_at);
        $this->assertGuest();
    }

    public function test_return_query_flags_never_mark_the_payment_ready(): void
    {
        [, $account, $service, $link] = $this->setupLink();
        $service->start($link);
        $this->get(route('public.family-payment-setup.return', [
            'token' => $link->token, 'checkout' => 'success', 'checkout_session_id' => 'cs_somebody_else',
        ]))->assertOk()->assertSee('confirming your payment method');
        $this->assertNull($link->fresh()->completed_at);
        $this->assertFalse($service->summaryForAccount($account->fresh())['ready']);
    }

    public function test_regeneration_revokes_the_previous_link_and_checkout(): void
    {
        [$admin, $account, $service, $link] = $this->setupLink();
        $service->start($link);
        $oldSession = $link->fresh()->stripe_checkout_session_id;
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldReceive('expireGuestFamilySetupCheckoutSession')->once()->with($oldSession);
        $service = new FamilyPaymentSetupService($stripe);
        $replacement = $service->generate($admin, $account);
        $this->assertSame('revoked', $link->fresh()->status());
        $this->assertNotSame($replacement->token, $link->token);
        $this->get($service->urlFor($link))->assertStatus(410)->assertSee('no longer available');
        $this->post($service->urlFor($link), ['consent' => 1])->assertStatus(410);
        $this->get($service->urlFor($replacement))->assertOk();
    }

    public function test_expired_link_and_unknown_token_cannot_start_checkout(): void
    {
        [, , $service, $link] = $this->setupLink();
        $link->forceFill(['expires_at' => now()->subSecond()])->save();
        $this->post($service->urlFor($link), ['consent' => 1])->assertStatus(410);
        $this->get(route('public.family-payment-setup.show', ['token' => str_repeat('f', 64)]))->assertStatus(410);
        $this->assertNull($link->fresh()->stripe_checkout_session_id);
    }

    public function test_verified_webhook_saves_default_and_snapshot_once_without_browser_return(): void
    {
        [, $account, , $link] = $this->setupLink();
        [$link, $session] = $this->started($link, $account);
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldReceive('retrieveGuestFamilySetupCheckoutSession')->once()->with($session['id'])->andReturn($session);
        $stripe->shouldReceive('setGuestFamilyDefaultPaymentMethod')->once()->with('cus_guest', 'pm_guest', $session['id']);
        $service = new FamilyPaymentSetupService($stripe);
        $this->app->instance(FamilyPaymentSetupService::class, $service);
        $billing = new FamilyBillingService($stripe);
        $billing->handleCheckoutSessionCompletedWebhook($session);
        $billing->handleCheckoutSessionCompletedWebhook($session);
        $this->assertNotNull($link->fresh()->completed_at);
        $this->assertSame('ready', $account->fresh()->payment_method_status);
        $this->assertSame('4242', $account->fresh()->payment_method_summary['last4']);
        $this->get($service->urlFor($link))->assertOk()->assertSee('Payment method saved')->assertDontSee('name="consent"', false);
        $this->post($service->urlFor($link), ['consent' => 1])->assertOk()->assertSee('Payment method saved');
    }

    public function test_delayed_signed_webhook_after_link_expiry_completes_once_and_rejects_bad_signature(): void
    {
        [, $account, , $link] = $this->setupLink();
        [$link, $session] = $this->started($link, $account);
        $link->forceFill([
            'expires_at' => now()->subMinute(),
            'stripe_checkout_expires_at' => now()->subHour(),
        ])->save();
        $session['expires_at'] = $link->stripe_checkout_expires_at->timestamp;
        config(['services.stripe.bypass' => false, 'services.stripe.webhook_secret' => 'whsec_guest_test']);
        $stripe = Mockery::mock(StripeClient::class)->makePartial();
        $stripe->shouldReceive('retrieveGuestFamilySetupCheckoutSession')->once()->with($session['id'])->andReturn($session);
        $stripe->shouldReceive('setGuestFamilyDefaultPaymentMethod')->once()->with('cus_guest', 'pm_guest', $session['id']);
        $this->app->instance(StripeClient::class, $stripe);
        $this->app->instance(FamilyPaymentSetupService::class, new FamilyPaymentSetupService($stripe));
        $payload = json_encode([
            'id' => 'evt_guest_signed', 'object' => 'event', 'type' => 'checkout.session.completed',
            'livemode' => false, 'data' => ['object' => $session],
        ]);
        $timestamp = time();
        $signature = 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_guest_test');
        $this->call('POST', route('webhooks.stripe'), [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1=bad',
        ], $payload)->assertStatus(401);
        $this->assertNull($link->fresh()->completed_at);
        $this->call('POST', route('webhooks.stripe'), [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature,
        ], $payload)->assertOk();
        $this->call('POST', route('webhooks.stripe'), [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature,
        ], $payload)->assertOk()->assertJson(['duplicate' => true]);
        $this->assertNotNull($link->fresh()->completed_at);
        $this->assertSame('ready', $account->fresh()->payment_method_status);
        $this->assertDatabaseHas('stripe_webhook_events', ['stripe_event_id' => 'evt_guest_signed', 'status' => 'processed']);
        $this->assertSame(1, $account->activityLogs()->where('action', 'payment_method_saved')->count());
    }

    public function test_checkout_expiry_cannot_outlive_link_expiry(): void
    {
        [, $account, , $link] = $this->setupLink();
        [, $session] = $this->started($link, $account);
        $link->forceFill(['expires_at' => now()->subDay()])->save();
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldReceive('retrieveGuestFamilySetupCheckoutSession')->once()->andReturn($session);
        $stripe->shouldNotReceive('setGuestFamilyDefaultPaymentMethod');
        $this->expectException(PaymentException::class);
        (new FamilyPaymentSetupService($stripe))->handleCheckoutCompleted($session);
    }

    public static function invalidSessions(): array
    {
        return [
            'wrong session id' => ['id', 'cs_foreign'],
            'wrong customer' => ['customer', 'cus_foreign'],
            'wrong family' => ['metadata.family_account_id', '999999'],
            'wrong link' => ['metadata.onboarding_payment_link_id', '999999'],
            'incomplete session' => ['status', 'open'],
            'wrong mode' => ['mode', 'payment'],
            'unconfirmed intent' => ['setup_intent.status', 'requires_action'],
            'wrong intent customer' => ['setup_intent.customer', 'cus_foreign'],
            'wrong intent family' => ['setup_intent.metadata.family_account_id', '999999'],
            'wrong intent link' => ['setup_intent.metadata.onboarding_payment_link_id', '999999'],
            'wrong payment owner' => ['setup_intent.payment_method.customer', 'cus_foreign'],
            'unsupported method' => ['setup_intent.payment_method.type', 'cashapp'],
        ];
    }

    #[DataProvider('invalidSessions')]
    public function test_unverified_session_cannot_change_the_default(string $path, mixed $value): void
    {
        [, $account, , $link] = $this->setupLink();
        [$link, $session] = $this->started($link, $account);
        $invalid = $session;
        data_set($invalid, $path, $value);
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldReceive('retrieveGuestFamilySetupCheckoutSession')->once()->andReturn($invalid);
        $stripe->shouldNotReceive('setGuestFamilyDefaultPaymentMethod');
        try {
            (new FamilyPaymentSetupService($stripe))->handleCheckoutCompleted($session);
            $this->fail('Expected verification to reject the session.');
        } catch (PaymentException) {
            $this->assertNull($link->fresh()->completed_at);
            $this->assertNull($account->fresh()->payment_method_status);
        }
    }

    public function test_revoked_checkout_webhook_cannot_fall_through_to_legacy_billing(): void
    {
        [$admin, $account, $service, $link] = $this->setupLink();
        [$link, $session] = $this->started($link, $account);
        $service->revoke($admin, $link);
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldNotReceive('retrieveGuestFamilySetupCheckoutSession');
        $stripe->shouldNotReceive('syncFamilySetupCheckoutSession');
        $this->app->instance(FamilyPaymentSetupService::class, new FamilyPaymentSetupService($stripe));
        (new FamilyBillingService($stripe))->handleCheckoutSessionCompletedWebhook($session);
        (new FamilyBillingService($stripe))->handleCheckoutSessionCompletedWebhook($session);
        $this->assertNull($account->fresh()->payment_method_status);
        $this->assertNull($link->fresh()->completed_at);
    }

    public function test_metadata_removed_from_guest_webhook_is_rejected_without_legacy_fallback(): void
    {
        [, $account, , $link] = $this->setupLink();
        [, $session] = $this->started($link, $account);
        unset($session['metadata']['onboarding_payment_link_id']);
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldNotReceive('syncFamilySetupCheckoutSession');
        $this->app->instance(FamilyPaymentSetupService::class, new FamilyPaymentSetupService($stripe));
        $this->expectException(PaymentException::class);
        (new FamilyBillingService($stripe))->handleCheckoutSessionCompletedWebhook($session);
    }

    public function test_known_customer_is_unknown_until_refreshed_and_expired_cards_are_missing(): void
    {
        [, $account, $service] = $this->setupLink();
        $this->assertSame('missing', $service->summaryForAccount($account)['status']);
        $account->forceFill(['stripe_customer_id' => 'cus_existing'])->save();
        $this->assertSame('unknown', $service->summaryForAccount($account)['status']);
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldReceive('isBypass')->once()->andReturn(false);
        $stripe->shouldReceive('defaultPaymentMethodForCustomer')->once()->with('cus_existing')->andReturn([
            'id' => 'pm_expired', 'brand' => 'visa', 'last4' => '4242', 'exp_month' => 1, 'exp_year' => 2020,
        ]);
        $summary = (new FamilyPaymentSetupService($stripe))->summaryForAccount($account, true);
        $this->assertSame('missing', $summary['status']);
        $this->assertSame('missing', $account->fresh()->payment_method_status);
    }

    public function test_checkout_creation_passes_setup_metadata_currency_and_stable_idempotency(): void
    {
        [, $account, $service, $link] = $this->setupLink();
        $account->forceFill(['stripe_customer_id' => 'cus_guest'])->save();
        config(['services.stripe.bypass' => false, 'services.stripe.secret' => 'sk_test_guest_setup']);
        $params = [];
        $http = Mockery::mock(ClientInterface::class);
        $http->shouldReceive('request')->once()->with('post', 'https://api.stripe.com/v1/checkout/sessions', Mockery::any(), Mockery::any(), false, 'v1')
            ->andReturnUsing(function ($method, $url, $headers, $request) use (&$params): array {
                $params = $request;

                return [json_encode(['id' => 'cs_sdk_guest', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/cs_sdk_guest', 'expires_at' => $request['expires_at']]), 200, []];
            });
        ApiRequestor::setHttpClient($http);
        $this->assertSame('https://checkout.stripe.com/c/pay/cs_sdk_guest', $service->start($link));
        $this->assertSame('setup', $params['mode']);
        $this->assertSame('usd', $params['currency']);
        // Checkout accepts only a subset of SetupIntent fields; usage is not one.
        $this->assertArrayNotHasKey('usage', $params['setup_intent_data']);
        $this->assertSame((string) $link->id, $params['metadata']['onboarding_payment_link_id']);
        $this->assertSame($params['metadata'], $params['setup_intent_data']['metadata']);
        $this->assertArrayNotHasKey('payment_method_types', $params);
        $this->assertLessThanOrEqual($link->expires_at->timestamp, $params['expires_at']);
        $this->assertSame('https://checkout.stripe.com/c/pay/cs_sdk_guest', $service->start($link));
    }

    public function test_transient_checkout_failure_reuses_the_reserved_attempt_and_expiry(): void
    {
        [, $account, , $link] = $this->setupLink();
        $account->forceFill(['stripe_customer_id' => 'cus_guest'])->save();
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldReceive('ensureFamilyAccountCustomer')->twice()->andReturn('cus_guest');
        $attempts = [];
        $stripe->shouldReceive('createGuestFamilySetupCheckoutSession')->twice()
            ->andReturnUsing(function ($account, $id, $attempt, $success, $cancel, $expiry) use (&$attempts): array {
                $attempts[] = [$attempt, $expiry];
                if (count($attempts) === 1) {
                    throw new PaymentException('Temporary failure');
                }

                return ['id' => 'cs_retry', 'url' => 'https://checkout.stripe.com/c/pay/cs_retry', 'expires_at' => $expiry];
            });
        $service = new FamilyPaymentSetupService($stripe);
        try {
            $service->start($link);
            $this->fail('Expected a transient error.');
        } catch (PaymentException) {
            $this->assertSame(1, $link->fresh()->checkout_attempt);
        }
        $this->travel(1)->minutes();
        $service->start($link);
        $this->assertSame($attempts[0], $attempts[1]);
    }

    public function test_authenticated_billing_cannot_bypass_a_guest_links_revocation(): void
    {
        [, $account] = $this->setupLink();
        config(['services.stripe.bypass' => false, 'services.stripe.secret' => 'sk_test_guest_setup']);
        $http = Mockery::mock(ClientInterface::class);
        $http->shouldReceive('request')->once()->andReturn([json_encode([
            'id' => 'cs_guest', 'object' => 'checkout.session', 'mode' => 'setup',
            'status' => 'complete', 'metadata' => ['onboarding_payment_link_id' => '1'],
        ]), 200, []]);
        ApiRequestor::setHttpClient($http);
        $this->expectException(PaymentException::class);
        app(StripeClient::class)->syncFamilySetupCheckoutSession($account->owner, 'cs_guest');
    }

    public function test_refresh_command_includes_legacy_owner_customer_without_guessing_readiness(): void
    {
        [, $account] = $this->setupLink();
        $account->owner->forceFill(['stripe_customer_id' => 'cus_legacy'])->save();
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldReceive('isBypass')->andReturn(false);
        $stripe->shouldReceive('defaultPaymentMethodForCustomer')->once()->with('cus_legacy')->andReturn(null);
        $this->app->instance(StripeClient::class, $stripe);
        $this->artisan('customer-onboarding:refresh-payments', ['--limit' => 1])->assertSuccessful();
        $this->assertSame('cus_legacy', $account->fresh()->stripe_customer_id);
        $this->assertSame('missing', $account->fresh()->payment_method_status);
        $this->assertNotNull($account->fresh()->payment_method_refresh_attempted_at);
    }

    public function test_failed_refresh_preserves_known_payment_state_and_records_attempt(): void
    {
        [, $account] = $this->setupLink();
        $account->forceFill([
            'stripe_customer_id' => 'cus_unavailable',
            'payment_method_status' => 'ready',
            'payment_method_checked_at' => now()->subDays(2),
        ])->save();
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldReceive('isBypass')->andReturn(false);
        $stripe->shouldReceive('defaultPaymentMethodForCustomer')->once()->with('cus_unavailable')
            ->andThrow(new PaymentException('Stripe is unavailable'));
        $this->app->instance(StripeClient::class, $stripe);
        $this->artisan('customer-onboarding:refresh-payments', ['--limit' => 1])->assertFailed();
        $this->assertSame('ready', $account->fresh()->payment_method_status);
        $this->assertNotNull($account->fresh()->payment_method_refresh_attempted_at);
    }

    public static function snapshotReaders(): array
    {
        return [
            'customer onboarding detail or command' => [false],
            'authenticated family billing' => [true],
        ];
    }

    #[DataProvider('snapshotReaders')]
    public function test_stale_stripe_read_cannot_overwrite_a_newer_verified_webhook_snapshot(bool $authenticated): void
    {
        [, $account, , $link] = $this->setupLink();
        [$link, $session] = $this->started($link, $account);
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldReceive('isBypass')->andReturn(false);
        $stripe->shouldReceive('retrieveGuestFamilySetupCheckoutSession')->once()->with($session['id'])->andReturn($session);
        $stripe->shouldReceive('setGuestFamilyDefaultPaymentMethod')->once()->with('cus_guest', 'pm_guest', $session['id']);
        $setup = new FamilyPaymentSetupService($stripe);
        $this->app->instance(FamilyPaymentSetupService::class, $setup);
        $stripe->shouldReceive('defaultPaymentMethodForCustomer')->once()->with('cus_guest')
            ->andReturnUsing(function () use ($setup, $session): ?array {
                // The Stripe read started while no default existed. Before its
                // response arrives, the signed webhook completes and saves one.
                $setup->handleCheckoutCompleted($session);

                return null;
            });

        $summary = $authenticated
            ? (new FamilyBillingService($stripe))->summaryFor($account->owner)
            : $setup->summaryForAccount($account, true);

        $this->assertTrue($summary['ready']);
        $this->assertSame('pm_guest', $summary['card']['id']);
        $this->assertSame('ready', $account->fresh()->payment_method_status);
        $this->assertSame('pm_guest', $account->fresh()->payment_method_summary['id']);
        $this->assertSame(1, $account->fresh()->payment_method_snapshot_version);
        $this->assertNotNull($link->fresh()->completed_at);
    }

    private function setupLink(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $family = User::factory()->create(['role' => 'family']);
        $account = app(FamilyAccountContext::class)->account($family);
        $service = app(FamilyPaymentSetupService::class);

        return [$admin, $account, $service, $service->generate($admin, $account)];
    }

    private function started(FamilyPaymentSetupLink $link, FamilyAccount $account): array
    {
        $account->forceFill(['stripe_customer_id' => 'cus_guest'])->save();
        $link->forceFill([
            'stripe_customer_id' => 'cus_guest', 'stripe_checkout_session_id' => 'cs_guest_'.$link->id,
            'consented_at' => now(), 'consent_version' => FamilyPaymentSetupService::CONSENT_VERSION,
            'stripe_checkout_expires_at' => now()->addHours(23),
        ])->save();
        $metadata = ['family_account_id' => (string) $account->id, 'onboarding_payment_link_id' => (string) $link->id];
        $session = [
            'id' => $link->stripe_checkout_session_id, 'mode' => 'setup', 'status' => 'complete',
            'expires_at' => $link->stripe_checkout_expires_at->timestamp,
            'customer' => 'cus_guest', 'metadata' => $metadata,
            'setup_intent' => [
                'id' => 'seti_guest_'.$link->id, 'customer' => 'cus_guest', 'status' => 'succeeded',
                'usage' => 'off_session', 'metadata' => $metadata,
                'payment_method' => [
                    'id' => 'pm_guest', 'customer' => 'cus_guest', 'type' => 'card',
                    'card' => ['brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => now()->year + 2],
                ],
            ],
        ];

        return [$link, $session];
    }
}
