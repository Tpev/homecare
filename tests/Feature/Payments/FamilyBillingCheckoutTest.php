<?php

namespace Tests\Feature\Payments;

use App\Models\FamilyAccountMember;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Tests\TestCase;

class FamilyBillingCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        ApiRequestor::resetTelemetry();

        parent::tearDown();
    }

    public static function checkoutActorsAndCurrencies(): array
    {
        return [
            'owner with USD' => [false, 'USD', 'usd'],
            'member with USD' => [true, 'USD', 'usd'],
            'owner with EUR' => [false, 'EUR', 'eur'],
            'member with EUR' => [true, 'EUR', 'eur'],
        ];
    }

    #[DataProvider('checkoutActorsAndCurrencies')]
    public function test_card_setup_sends_configured_currency_and_redirects_to_stripe(
        bool $asMember,
        string $configuredCurrency,
        string $expectedCurrency,
    ): void {
        config([
            'services.stripe.bypass' => false,
            'services.stripe.secret' => 'sk_test_checkout_regression',
            'services.stripe.currency' => $configuredCurrency,
        ]);

        $owner = User::factory()->create([
            'role' => 'family',
            'stripe_customer_id' => 'cus_checkout_regression',
        ]);
        $account = app(FamilyAccountContext::class)->account($owner);
        $actor = $owner;

        if ($asMember) {
            $actor = User::factory()->create(['role' => 'family']);
            $account->memberships()->create([
                'user_id' => $actor->id,
                'access_level' => FamilyAccountMember::ACCESS_MEMBER,
                'status' => FamilyAccountMember::STATUS_ACTIVE,
                'joined_at' => now(),
            ]);
        }

        $checkoutUrl = 'https://checkout.stripe.com/c/pay/cs_test_checkout_regression';
        $requestParams = [];
        $http = Mockery::mock(ClientInterface::class);
        $http->shouldReceive('request')->once()
            ->with('post', 'https://api.stripe.com/v1/checkout/sessions', Mockery::any(), Mockery::any(), false, 'v1')
            ->andReturnUsing(function ($method, $url, $headers, $params) use (&$requestParams, $checkoutUrl): array {
                $requestParams = $params;

                // Stripe requires currency for setup-mode Checkout with dynamic payment methods.
                if (empty($params['currency'])) {
                    return [json_encode(['error' => [
                        'type' => 'invalid_request_error',
                        'param' => 'currency',
                        'message' => 'Currency is required for setup mode with dynamic payment methods.',
                    ]]), 400, []];
                }

                return [json_encode([
                    'id' => 'cs_test_checkout_regression',
                    'object' => 'checkout.session',
                    'url' => $checkoutUrl,
                ]), 200, []];
            });
        ApiRequestor::setHttpClient($http);

        $this->actingAs($actor)
            ->post(route('family.billing.checkout'))
            ->assertSessionHasNoErrors()
            ->assertRedirect($checkoutUrl);

        $this->assertSame('setup', $requestParams['mode']);
        $this->assertSame($expectedCurrency, $requestParams['currency']);
        $this->assertSame('cus_checkout_regression', $requestParams['customer']);
        $this->assertArrayNotHasKey('payment_method_types', $requestParams);
        $this->assertSame([
            'family_account_id' => (string) $account->id,
            'family_user_id' => (string) $owner->id,
            'acting_user_id' => (string) $actor->id,
        ], $requestParams['metadata']);
        $this->assertSame($requestParams['metadata'], $requestParams['setup_intent_data']['metadata']);
        $this->assertSame(route('family.billing.show').'?checkout=success&checkout_session_id={CHECKOUT_SESSION_ID}', $requestParams['success_url']);
        $this->assertSame(route('family.billing.show').'?checkout=cancel', $requestParams['cancel_url']);
    }
}
