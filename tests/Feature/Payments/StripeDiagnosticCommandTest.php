<?php

namespace Tests\Feature\Payments;

use App\Services\Payments\StripeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeDiagnosticCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_stripe_diagnostic_passes_offline_with_valid_test_config(): void
    {
        config([
            'services.stripe.publishable_key' => 'pk_test_homecare_diagnostic',
            'services.stripe.secret' => 'sk_test_homecare_diagnostic',
            'services.stripe.webhook_secret' => 'whsec_homecare_diagnostic',
            'services.stripe.currency' => 'usd',
            'services.stripe.bypass' => false,
        ]);

        $this->artisan('stripe:diagnose --skip-api')
            ->expectsOutputToContain('Stripe diagnostic passed.')
            ->assertExitCode(0);
    }

    public function test_stripe_diagnostic_fails_live_mode_when_test_keys_are_configured(): void
    {
        config([
            'services.stripe.publishable_key' => 'pk_test_homecare_diagnostic',
            'services.stripe.secret' => 'sk_test_homecare_diagnostic',
            'services.stripe.webhook_secret' => 'whsec_homecare_diagnostic',
            'services.stripe.currency' => 'usd',
            'services.stripe.bypass' => false,
        ]);

        $this->artisan('stripe:diagnose --skip-api --live --expected-url=https://carelolo.com/webhooks/stripe')
            ->expectsOutputToContain('Stripe diagnostic failed:')
            ->assertExitCode(1);
    }

    public function test_stripe_client_accepts_comma_separated_webhook_secrets(): void
    {
        config([
            'services.stripe.webhook_secret' => 'whsec_wrong, whsec_right',
            'services.stripe.bypass' => false,
        ]);

        $payload = json_encode([
            'id' => 'evt_homecare_test',
            'object' => 'event',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_homecare_test']],
        ]);

        $this->assertIsString($payload);

        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_right');
        $header = 't='.$timestamp.',v1='.$signature;

        $event = app(StripeClient::class)->constructWebhookEvent($payload, $header);

        $this->assertSame('evt_homecare_test', $event->id);
    }
}
