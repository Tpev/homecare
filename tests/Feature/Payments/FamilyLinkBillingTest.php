<?php

namespace Tests\Feature\Payments;

use App\Exceptions\Payments\PaymentException;
use App\Models\AiSupportGuidedTask;
use App\Models\CareBooking;
use App\Models\FamilyAccountMember;
use App\Models\User;
use App\Services\AiSupport\FamilyPaymentMethodCompletionVerifier;
use App\Services\AiSupport\FamilyPaymentMethodStatusReader;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\Payments\FamilyBillingService;
use App\Services\Payments\StripeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Tests\TestCase;

class FamilyLinkBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.bypass' => false, 'services.stripe.secret' => 'sk_test_link_regression']);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        ApiRequestor::resetTelemetry();
        parent::tearDown();
    }

    public static function actorsAndExpansion(): array
    {
        return [
            'owner, expanded' => [false, true],
            'member, expanded' => [true, true],
            'owner, retrieved separately' => [false, false],
            'member, retrieved separately' => [true, false],
        ];
    }

    #[DataProvider('actorsAndExpansion')]
    public function test_existing_link_is_ready_without_another_setup_or_fake_card_details(bool $asMember, bool $expanded): void
    {
        $owner = User::factory()->create(['role' => 'family', 'stripe_customer_id' => 'cus_link_regression']);
        $account = app(FamilyAccountContext::class)->account($owner);
        $actor = $owner;
        if ($asMember) {
            $actor = User::factory()->create(['role' => 'family']);
            $account->memberships()->create([
                'user_id' => $actor->id, 'access_level' => FamilyAccountMember::ACCESS_MEMBER,
                'status' => FamilyAccountMember::STATUS_ACTIVE, 'joined_at' => now(),
            ]);
        }

        $this->mockSavedMethod('link', $expanded);
        $summary = app(FamilyBillingService::class)->summaryFor($actor);
        $this->assertTrue($summary['ready']);
        $this->assertSame('pm_link_regression', $summary['card']['id']);
        $this->assertSame('link', $summary['card']['type']);
        $this->assertNull($summary['card']['last4']);
        $this->assertNull($summary['card']['exp_month']);
        $this->assertNull($summary['card']['exp_year']);

        $this->actingAs($actor)->get(route('family.billing.show'))
            ->assertOk()->assertSee('READY')->assertSee('saved securely with Link')
            ->assertSee('Update payment method')->assertDontSee('No card on file yet.')
            ->assertDontSee('Expires')->assertDontSee('ending in');

        $status = app(FamilyPaymentMethodStatusReader::class)->read($actor);
        $this->assertTrue($status['ready']);
        $this->assertSame('ready', $status['attention']);
        $this->assertArrayNotHasKey('id', $status['card']);
        $this->assertArrayNotHasKey('email', $status['card']);

        $verified = app(FamilyPaymentMethodCompletionVerifier::class)->verify($actor, new AiSupportGuidedTask);
        $this->assertTrue($verified->verified());
        $this->assertStringContainsString('Link payment method is ready', $verified->message);
        $this->assertStringNotContainsString('ending in', $verified->message);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_card_summary_and_display_are_preserved(): void
    {
        $owner = User::factory()->create(['role' => 'family', 'stripe_customer_id' => 'cus_link_regression']);
        app(FamilyAccountContext::class)->account($owner);
        $this->mockSavedMethod('card');
        $this->actingAs($owner)->get(route('family.billing.show'))
            ->assertOk()->assertSee('VISA ending in 4242')->assertSee('Expires 12/2035')->assertSee('Update card');
        $this->assertSame('ready', app(FamilyPaymentMethodStatusReader::class)->read($owner)['attention']);
    }

    public function test_other_wallets_are_not_silently_treated_as_supported_link_methods(): void
    {
        $this->mockSavedMethod('cashapp');
        $this->assertNull(app(StripeClient::class)->defaultPaymentMethodForCustomer('cus_link_regression'));
    }

    public function test_link_method_lookup_failure_is_a_recoverable_payment_exception(): void
    {
        $http = $this->mockSavedMethod('link', false, false);
        $http->shouldReceive('request')->once()
            ->with('get', 'https://api.stripe.com/v1/payment_methods/pm_link_regression', Mockery::any(), Mockery::any(), false, 'v1')
            ->andReturn([json_encode(['error' => ['type' => 'invalid_request_error', 'message' => 'No such payment method.']]), 404, []]);
        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('No such payment method.');
        app(StripeClient::class)->defaultPaymentMethodForCustomer('cus_link_regression');
    }

    public function test_saved_link_id_is_used_for_later_manual_authorization_and_capture(): void
    {
        $http = $this->mockSavedMethod('link');
        $method = app(StripeClient::class)->defaultPaymentMethodForCustomer('cus_link_regression');
        $http->shouldReceive('request')->once()
            ->with('post', 'https://api.stripe.com/v1/payment_intents', Mockery::any(), Mockery::on(function ($params): bool {
                $this->assertSame('pm_link_regression', $params['payment_method']);
                $this->assertSame('cus_link_regression', $params['customer']);
                $this->assertSame('manual', $params['capture_method']);
                // The Stripe SDK serializes booleans before calling the HTTP client.
                $this->assertSame('true', $params['off_session']);
                $this->assertSame('true', $params['confirm']);
                $this->assertSame(10000, $params['amount']);
                $this->assertArrayNotHasKey('payment_method_types', $params);

                return true;
            }), false, 'v1')
            ->andReturn([json_encode(['id' => 'pi_link_regression', 'object' => 'payment_intent', 'status' => 'requires_capture', 'amount' => 10000]), 200, []]);
        $http->shouldReceive('request')->once()
            ->with('post', 'https://api.stripe.com/v1/payment_intents/pi_link_regression/capture', Mockery::any(), ['amount_to_capture' => 8000], false, 'v1')
            ->andReturn([json_encode(['id' => 'pi_link_regression', 'object' => 'payment_intent', 'status' => 'succeeded', 'amount_received' => 8000]), 200, []]);

        $booking = new CareBooking(['family_user_id' => 1, 'family_account_id' => 1, 'caregiver_user_id' => 2, 'care_request_id' => 1]);
        $booking->id = 1;
        $stripe = app(StripeClient::class);
        $authorization = $stripe->createManualAuthorization($booking, 'cus_link_regression', $method['id'], 10000, 'usd', 'link-auth-test');
        $this->assertSame('requires_capture', $authorization['status']);
        $capture = $stripe->capturePaymentIntent($authorization['payment_intent_id'], 8000, 'link-capture-test');
        $this->assertSame('succeeded', $capture['status']);
        $this->assertSame(8000, $capture['amount_received']);
    }

    public function test_setup_errors_are_reported_when_starting_and_returning_from_checkout(): void
    {
        $owner = User::factory()->create(['role' => 'family']);
        $billing = $this->mock(FamilyBillingService::class);
        $startError = new PaymentException('Unable to open setup.', 'Stripe setup diagnostic');
        $returnError = new PaymentException('Unable to finalize setup.', 'Stripe return diagnostic');
        $billing->shouldReceive('createSetupCheckoutUrl')->once()->andThrow($startError);
        $billing->shouldReceive('syncSetupCheckoutSession')->once()->andThrow($returnError);
        \Illuminate\Support\Facades\Exceptions::fake();
        $this->actingAs($owner)->post(route('family.billing.checkout'))->assertSessionHasErrors('billing');
        $this->get(route('family.billing.show', ['checkout_session_id' => 'cs_return_error']))->assertSessionHasErrors('billing');
        \Illuminate\Support\Facades\Exceptions::assertReported(fn (PaymentException $e) => $e === $startError);
        \Illuminate\Support\Facades\Exceptions::assertReported(fn (PaymentException $e) => $e === $returnError);
    }

    private function mockSavedMethod(string $type, bool $expanded = true, bool $mockRetrieval = true): \Mockery\MockInterface
    {
        $method = ['id' => 'pm_link_regression', 'object' => 'payment_method', 'type' => $type, 'customer' => 'cus_link_regression'];
        $method[$type] = $type === 'card'
            ? ['brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2035]
            : ['email' => 'private@example.test'];
        $customer = ['id' => 'cus_link_regression', 'object' => 'customer', 'invoice_settings' => [
            'default_payment_method' => $expanded ? $method : $method['id'],
        ]];
        $http = Mockery::mock(ClientInterface::class);
        $http->shouldReceive('request')
            ->with('get', 'https://api.stripe.com/v1/customers/cus_link_regression', Mockery::any(), Mockery::any(), false, 'v1')
            ->andReturn([json_encode($customer), 200, []]);
        if (! $expanded && $mockRetrieval) {
            $http->shouldReceive('request')
                ->with('get', 'https://api.stripe.com/v1/payment_methods/pm_link_regression', Mockery::any(), Mockery::any(), false, 'v1')
                ->andReturn([json_encode($method), 200, []]);
        }
        ApiRequestor::setHttpClient($http);

        return $http;
    }
}
