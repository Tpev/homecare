<?php

namespace Tests\Feature\CareHours;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareHourAccount;
use App\Models\CareHourPurchase;
use App\Models\FamilyAccountActivityLog;
use App\Models\User;
use App\Notifications\CareHourPurchaseNotification;
use App\Services\CareHours\CareHourPaymentGateway;
use App\Services\CareHours\CareHourPurchaseService;
use App\Services\CareHours\CareHourWebhookService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\Payments\StripeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Tests\TestCase;

class CareHourPurchaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['care_hours.enabled' => true, 'care_hours.sales_enabled' => true, 'care_hours.welcome_enabled' => true, 'care_hours.contributions_enabled' => true]);
        Notification::fake();
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        ApiRequestor::resetTelemetry();
        parent::tearDown();
    }

    public function test_catalog_five_hour_preservation_and_first_qualifying_bonus_are_recipient_scoped(): void
    {
        [$account, $owner] = $this->account();
        $service = app(CareHourPurchaseService::class);
        $catalog = $service->catalog($account);
        $this->assertSame([15500, 31000, 62000, 124000], array_column($catalog, 'amount_cents'));
        $this->assertSame([300, 660, 1320, 2640], array_column($catalog, 'total_minutes'));
        $five = $service->refresh($this->start($account, $owner, 5));
        $this->assertSame(0, $five->bonus_minutes);
        $this->assertDatabaseCount('care_hour_welcome_claims', 0);
        $first = $service->refresh($this->start($account, $owner, 10));
        $this->assertSame(60, $first->bonus_minutes);
        $this->assertSame(0, $service->quote($account, 40)['bonus_minutes']);
        $this->assertSame(960, $account->balance()['available_minutes']);
        $this->assertNull($owner->fresh()->stripe_customer_id);
        $this->assertNull($account->familyAccount->fresh()->stripe_customer_id);
    }

    public function test_distinct_repeated_success_events_issue_paid_and_bonus_minutes_once(): void
    {
        [$account, $owner] = $this->account();
        $purchase = $this->start($account, $owner, 10);
        $service = app(CareHourPurchaseService::class);
        $service->refresh($purchase);
        $service->refresh($purchase->fresh());
        $event = $this->financials($purchase);
        $service->acceptPaid($purchase->fresh(), $event);
        $this->assertDatabaseCount('care_hour_lots', 1);
        $this->assertSame(660, $account->balance()['available_minutes']);
        $this->assertSame('issued', $purchase->fresh()->status);
        $this->assertSame(1, DB::table('care_hour_ledger_entries')->where('type', 'purchase')->count());
    }

    public function test_stale_concurrent_quote_requires_acceptance_of_changed_offer_before_payment(): void
    {
        [$account, $owner] = $this->account();
        $service = app(CareHourPurchaseService::class);
        $quote = $service->quote($account, 10);
        $this->start($account, $owner, 10);
        try {
            $service->start($account, 10, $owner, $quote['quote_key'], (string) Str::uuid(), url('/ok'), url('/cancel'));
            $this->fail('A stale bonus quote must not start checkout.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('care_hour_purchases', 1);
            $this->assertSame('reserved', DB::table('care_hour_welcome_claims')->value('status'));
        }
    }

    public function test_known_failure_releases_welcome_but_does_not_undo_an_issued_purchase(): void
    {
        [$account, $owner] = $this->account();
        $service = app(CareHourPurchaseService::class);
        $purchase = $this->start($account, $owner, 20);
        $service->markFailed($purchase);
        $this->assertSame(120, $service->quote($account, 20)['bonus_minutes']);
        $paid = $service->refresh($this->start($account, $owner, 20));
        $service->markFailed($paid);
        $this->assertSame('issued', $paid->fresh()->status);
        $this->assertSame(0, $service->quote($account, 20)['bonus_minutes']);
    }

    public function test_checkout_timeout_retry_reuses_purchase_and_does_not_free_the_welcome_claim(): void
    {
        [$account, $owner] = $this->account();
        $gateway = Mockery::mock(CareHourPaymentGateway::class);
        $ids = [];
        $gateway->shouldReceive('createCheckout')->twice()->andReturnUsing(function (CareHourPurchase $purchase) use (&$ids): array {
            $ids[] = $purchase->public_id;
            throw new \RuntimeException('Simulated unknown provider outcome');
        });
        $this->app->instance(CareHourPaymentGateway::class, $gateway);
        $service = app(CareHourPurchaseService::class);
        $quote = $service->quote($account, 10);
        $request = (string) Str::uuid();
        $first = $service->start($account, 10, $owner, $quote['quote_key'], $request, url('/ok'), url('/cancel'));
        $again = $service->start($account, 10, $owner, $quote['quote_key'], $request, url('/ok'), url('/cancel'));
        $this->assertSame($first->id, $again->id);
        $this->assertSame($ids[0], $ids[1]);
        $this->assertSame('pending', $again->status);
        $this->assertSame(0, $account->balance()['available_minutes']);
        $this->assertSame('reserved', DB::table('care_hour_welcome_claims')->value('status'));
    }

    public function test_unknown_checkout_is_not_recreated_after_provider_idempotency_window(): void
    {
        [$account, $owner] = $this->account();
        $purchase = $this->start($account, $owner, 10);
        $purchase->update(['stripe_checkout_session_id' => null, 'checkout_url' => null, 'checkout_requested_at' => now()->subDay()]);
        $gateway = Mockery::mock(CareHourPaymentGateway::class);
        $gateway->shouldNotReceive('createCheckout');
        $this->app->instance(CareHourPaymentGateway::class, $gateway);
        $this->assertSame('recovery', app(CareHourPurchaseService::class)->startCheckout($purchase)->status);
        $this->assertSame('reserved', DB::table('care_hour_welcome_claims')->value('status'));
    }

    public function test_payment_success_issues_even_when_sales_are_disabled_after_acceptance(): void
    {
        [$account, $owner] = $this->account();
        $purchase = $this->start($account, $owner, 10);
        config(['care_hours.enabled' => false, 'care_hours.sales_enabled' => false, 'care_hours.welcome_enabled' => false]);
        $this->assertSame('issued', app(CareHourPurchaseService::class)->refresh($purchase)->status);
        $this->assertSame(660, $account->balance()['available_minutes']);
    }

    public function test_wrong_amount_cannot_issue_hours(): void
    {
        [$account, $owner] = $this->account();
        $purchase = $this->start($account, $owner, 10);
        $result = $this->financials($purchase);
        $result['amount_received']--;
        try {
            app(CareHourPurchaseService::class)->acceptPaid($purchase, $result);
            $this->fail('Incorrect payment amount accepted.');
        } catch (PaymentException) {
            $this->assertSame(0, $account->balance()['available_minutes']);
        }
    }

    public function test_fee_can_arrive_after_issuance_without_a_second_credit(): void
    {
        [$account, $owner] = $this->account();
        $purchase = $this->start($account, $owner, 10);
        $result = $this->financials($purchase);
        $result['fee_finalized'] = false;
        $result['processing_fee_cents'] = null;
        $service = app(CareHourPurchaseService::class);
        $service->acceptPaid($purchase, $result);
        $this->assertNull($purchase->fresh()->processing_fee_cents);
        $result['fee_finalized'] = true;
        $result['processing_fee_cents'] = 1100;
        $service->acceptPaid($purchase->fresh(), $result);
        $this->assertSame(1100, $purchase->fresh()->processing_fee_cents);
        $this->assertSame(1100, $account->lots()->first()->processing_fee_cents);
        $this->assertSame(660, $account->balance()['available_minutes']);
    }

    public function test_inactive_recipient_after_payment_requires_recovery_and_issues_nothing(): void
    {
        [$account, $owner] = $this->account();
        $purchase = $this->start($account, $owner, 10);
        $account->update(['active' => false]);
        $result = app(CareHourPurchaseService::class)->refresh($purchase);
        $this->assertSame('recovery', $result->status);
        $this->assertNotNull($result->paid_at);
        $this->assertNull($result->issued_at);
        $this->assertDatabaseCount('care_hour_lots', 0);
    }

    public function test_stale_failure_event_retrieves_authoritative_success_without_undoing_hours(): void
    {
        [$account, $owner] = $this->account();
        $purchase = app(CareHourPurchaseService::class)->refresh($this->start($account, $owner, 10));
        $handled = app(CareHourWebhookService::class)->handle('payment_intent.payment_failed', [
            'id' => $purchase->stripe_payment_intent_id, 'status' => 'requires_payment_method',
            'metadata' => ['care_hour_domain' => 'purchase', 'care_hour_purchase_id' => $purchase->public_id],
        ], false);
        $this->assertTrue($handled);
        $this->assertSame('issued', $purchase->fresh()->status);
        $this->assertSame(660, $account->balance()['available_minutes']);
    }

    public function test_package_dispute_holds_only_its_lot_and_does_not_reset_welcome(): void
    {
        [$account, $owner] = $this->account();
        $service = app(CareHourPurchaseService::class);
        $first = $service->refresh($this->start($account, $owner, 10));
        $service->refresh($this->start($account, $owner, 5));
        app(CareHourWebhookService::class)->handle('charge.dispute.created', ['id' => 'dp_test_hours', 'charge' => $first->stripe_charge_id, 'status' => 'needs_response'], false);
        $this->assertSame(300, $account->balance()['available_minutes']);
        $this->assertSame(660, $account->balance()['held_minutes']);
        $this->assertSame('awarded', DB::table('care_hour_welcome_claims')->value('status'));
        app(CareHourWebhookService::class)->handle('charge.dispute.closed', ['id' => 'dp_test_hours', 'charge' => $first->stripe_charge_id, 'status' => 'won'], false);
        $this->assertSame(960, $account->balance()['available_minutes']);
    }

    public function test_existing_booking_and_setup_events_are_not_claimed(): void
    {
        $service = app(CareHourWebhookService::class);
        $this->assertFalse($service->handle('payment_intent.succeeded', ['id' => 'pi_existing', 'metadata' => ['care_booking_id' => '123']]));
        $this->assertFalse($service->handle('checkout.session.completed', ['id' => 'cs_existing', 'mode' => 'setup']));
        $this->assertDatabaseCount('care_hour_purchases', 0);
    }

    public function test_admin_can_correct_only_paid_unfulfilled_purchase_and_restore_welcome_after_success(): void
    {
        [$account, $owner] = $this->account();
        $service = app(CareHourPurchaseService::class);
        $purchase = $this->start($account, $owner, 10);
        $account->update(['active' => false]);
        $purchase = $service->refresh($purchase);
        $admin = User::factory()->create(['role' => 'admin']);
        $corrected = $service->correctUnfulfillablePurchase($purchase, $admin, 'Recipient archived before the purchase could issue any care time.');
        $this->assertSame('corrected', $corrected->status);
        $this->assertSame(31000, $corrected->amount_reversed_cents);
        $this->assertSame('available', DB::table('care_hour_welcome_claims')->value('status'));
        $this->assertDatabaseCount('care_hour_lots', 0);
        $service->acceptPaid($corrected, $this->financials($corrected));
        $this->assertSame('corrected', $corrected->fresh()->status);
        $this->assertSame($corrected->correction_refund_id, $service->correctUnfulfillablePurchase($corrected, $admin, 'Retry the same billing correction safely.')->correction_refund_id);
    }

    public function test_valid_unused_purchase_cannot_use_admin_billing_correction(): void
    {
        [$account, $owner] = $this->account();
        $service = app(CareHourPurchaseService::class);
        $purchase = $service->refresh($this->start($account, $owner, 10));
        $admin = User::factory()->create(['role' => 'admin']);
        $this->expectException(ValidationException::class);
        $service->correctUnfulfillablePurchase($purchase, $admin, 'Buyer changed their mind about unused hours.');
    }

    public function test_authoritatively_failed_correction_can_be_retried_with_a_new_audited_attempt(): void
    {
        [$account, $owner] = $this->account();
        $purchase = $this->start($account, $owner, 10);
        $account->update(['active' => false]);
        $purchase = app(CareHourPurchaseService::class)->refresh($purchase);
        $failed = ['id' => 're_failed', 'amount' => $purchase->amount_cents, 'charge' => $purchase->stripe_charge_id, 'status' => 'failed'];
        $gateway = Mockery::mock(CareHourPaymentGateway::class);
        $gateway->shouldReceive('correctionRefund')->once()->withArgs(fn ($p, $create) => $create)->andReturn($failed)->ordered();
        $gateway->shouldReceive('correctionRefund')->once()->withArgs(fn ($p, $create) => ! $create && $p->correction_refund_id === 're_failed')->andReturn($failed)->ordered();
        $gateway->shouldReceive('correctionRefund')->once()->withArgs(fn ($p, $create) => $create && $p->correction_refund_id === null)
            ->andReturn(array_replace($failed, ['id' => 're_retry', 'status' => 'succeeded']))->ordered();
        $this->app->instance(CareHourPaymentGateway::class, $gateway);
        $service = app(CareHourPurchaseService::class);
        $admin = User::factory()->create(['role' => 'admin']);
        $failedPurchase = $service->correctUnfulfillablePurchase($purchase, $admin, 'Correct the unfulfilled purchase.');
        $this->assertSame('failed', $failedPurchase->correction_state);
        $corrected = $service->correctUnfulfillablePurchase($failedPurchase, $admin, 'Retry after the provider confirmed the refund failed.');
        $this->assertSame('corrected', $corrected->status);
        $this->assertSame('re_retry', $corrected->correction_refund_id);
        $attempts = FamilyAccountActivityLog::where('action', 'care_hours_correction_attempt')->orderBy('id')->get();
        $this->assertCount(2, $attempts);
        $this->assertNotSame($attempts[0]->metadata['attempt_key'], $attempts[1]->metadata['attempt_key']);
        $this->assertSame('re_failed', $attempts[1]->metadata['previous_refund']['id']);
        $this->assertSame($admin->id, $attempts[1]->actor_user_id);
        $this->assertSame('available', DB::table('care_hour_welcome_claims')->value('status'));
        $this->assertDatabaseCount('care_hour_lots', 0);
    }

    public function test_local_failed_flag_cannot_retry_a_provider_refund_that_is_still_pending(): void
    {
        [$account, $owner] = $this->account();
        $purchase = $this->start($account, $owner, 10);
        $account->update(['active' => false]);
        $purchase = app(CareHourPurchaseService::class)->refresh($purchase);
        $purchase->update(['correction_state' => 'failed', 'correction_refund_id' => 're_unknown']);
        $gateway = Mockery::mock(CareHourPaymentGateway::class);
        $gateway->shouldReceive('correctionRefund')->once()->withArgs(fn ($p, $create) => ! $create)
            ->andReturn(['id' => 're_unknown', 'amount' => $purchase->amount_cents, 'charge' => $purchase->stripe_charge_id, 'status' => 'pending']);
        $this->app->instance(CareHourPaymentGateway::class, $gateway);
        try {
            app(CareHourPurchaseService::class)->correctUnfulfillablePurchase($purchase, User::factory()->create(['role' => 'admin']), 'Retry a locally failed correction.');
            $this->fail('A provider-pending refund cannot start a new attempt.');
        } catch (ValidationException) {
            $this->assertSame('re_unknown', $purchase->fresh()->correction_refund_id);
            $this->assertSame(0, FamilyAccountActivityLog::where('action', 'care_hours_correction_attempt')->count());
            $this->assertSame('reserved', DB::table('care_hour_welcome_claims')->value('status'));
        }
    }

    public function test_refund_recovery_does_not_reuse_the_previous_failed_attempt(): void
    {
        [$account, $owner] = $this->account();
        $purchase = $this->start($account, $owner, 10);
        $account->update(['active' => false]);
        $purchase = app(CareHourPurchaseService::class)->refresh($purchase);
        $key = 'care-hours:invalid-purchase:'.$purchase->public_id.':retry';
        FamilyAccountActivityLog::create(['family_account_id' => $account->family_account_id, 'action' => 'care_hours_correction_attempt',
            'metadata' => ['purchase_id' => $purchase->id, 'attempt_key' => $key]]);
        config(['services.stripe.bypass' => false, 'services.stripe.secret' => 'sk_test_care_hours']);
        $http = Mockery::mock(ClientInterface::class);
        $http->shouldReceive('request')->once()->andReturn([json_encode(['object' => 'list', 'has_more' => false, 'data' => [[
            'id' => 're_old', 'object' => 'refund', 'status' => 'failed',
            'metadata' => ['care_hour_correction_id' => $purchase->public_id],
        ]]]), 200, []]);
        ApiRequestor::setHttpClient($http);
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldReceive('createRefundForCharge')->once()->withArgs(fn ($charge, $amount, $reason, $metadata, $idempotencyKey) => $charge === $purchase->stripe_charge_id && $amount === $purchase->amount_cents
            && $metadata['care_hour_correction_attempt'] === $key && $idempotencyKey === $key)
            ->andReturn(['id' => 're_new', 'status' => 'pending']);
        $this->app->instance(StripeClient::class, $stripe);
        $this->assertSame('re_new', app(CareHourPaymentGateway::class)->correctionRefund($purchase, true)['id']);
    }

    public function test_dispute_before_purchase_linkage_is_recovered_by_authoritative_financials(): void
    {
        [$account, $owner] = $this->account();
        $purchase = $this->start($account, $owner, 10);
        $this->assertFalse(app(CareHourWebhookService::class)->handle('charge.dispute.created', [
            'id' => 'dp_early', 'charge' => 'ch_bypass_care_hours_'.$purchase->public_id, 'status' => 'needs_response',
        ], false));
        $financials = $this->financials($purchase) + ['disputed' => true, 'dispute_id' => 'dp_early', 'dispute_status' => 'needs_response'];
        $service = app(CareHourPurchaseService::class);
        $service->acceptPaid($purchase, $financials);
        $this->assertSame('held', $purchase->fresh()->status);
        $this->assertSame(0, $account->balance()['available_minutes']);
        $this->assertSame(660, $account->balance()['held_minutes']);
        $financials['dispute_status'] = 'won';
        $service->acceptPaid($purchase->fresh(), $financials);
        $this->assertSame('issued', $purchase->fresh()->status);
        $this->assertSame(660, $account->balance()['available_minutes']);
        $this->assertDatabaseCount('care_hour_lots', 1);
    }

    public function test_package_financials_retrieve_current_dispute_from_the_authoritative_charge(): void
    {
        [$account, $owner] = $this->account();
        $purchase = $this->start($account, $owner, 10);
        config(['services.stripe.bypass' => false, 'services.stripe.secret' => 'sk_test_care_hours']);
        $http = Mockery::mock(ClientInterface::class);
        $http->shouldReceive('request')->once()->andReturnUsing(function ($method, $url) use ($purchase): array {
            $this->assertStringContainsString('/payment_intents/pi_disputed', $url);

            return [json_encode(['id' => 'pi_disputed', 'object' => 'payment_intent', 'amount' => 31000, 'amount_received' => 31000,
                'currency' => 'usd', 'status' => 'succeeded', 'livemode' => false,
                'metadata' => ['care_hour_domain' => 'purchase', 'care_hour_purchase_id' => $purchase->public_id],
                'latest_charge' => ['id' => 'ch_disputed', 'object' => 'charge', 'disputed' => true],
            ]), 200, []];
        })->ordered();
        $http->shouldReceive('request')->once()->andReturnUsing(function ($method, $url, $headers, $params): array {
            $this->assertStringContainsString('/disputes', $url);
            $this->assertSame('ch_disputed', $params['charge']);

            return [json_encode(['object' => 'list', 'has_more' => false, 'data' => [[
                'id' => 'dp_current', 'object' => 'dispute', 'charge' => 'ch_disputed', 'status' => 'needs_response',
            ]]]), 200, []];
        })->ordered();
        ApiRequestor::setHttpClient($http);
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldReceive('retrieveCareHourPaymentFinancials')->with('pi_disputed')->once()
            ->andReturn(['latest_charge_id' => 'ch_disputed', 'processing_fee_cents' => 1100, 'fee_finalized' => true]);
        $this->app->instance(StripeClient::class, $stripe);
        $result = app(CareHourPaymentGateway::class)->financials('pi_disputed');
        $this->assertTrue($result['disputed']);
        $this->assertSame('dp_current', $result['dispute_id']);
        $this->assertSame('needs_response', $result['dispute_status']);
    }

    public function test_unknown_correction_keeps_claim_reserved_and_recovers_without_new_refund_after_retry_window(): void
    {
        [$account, $owner] = $this->account();
        $purchase = $this->start($account, $owner, 10);
        $account->update(['active' => false]);
        $purchase = app(CareHourPurchaseService::class)->refresh($purchase);
        $gateway = Mockery::mock(CareHourPaymentGateway::class);
        $gateway->shouldReceive('correctionRefund')->once()->withArgs(fn ($p, $allowCreate) => $p->id === $purchase->id && $allowCreate)
            ->andThrow(new PaymentException('Unknown provider outcome.'));
        $gateway->shouldReceive('correctionRefund')->once()->withArgs(fn ($p, $allowCreate) => $p->id === $purchase->id && ! $allowCreate)
            ->andReturn(['id' => 're_recovered_correction', 'amount' => $purchase->amount_cents, 'charge' => $purchase->stripe_charge_id, 'status' => 'succeeded']);
        $this->app->instance(CareHourPaymentGateway::class, $gateway);
        $service = app(CareHourPurchaseService::class);
        $admin = User::factory()->create(['role' => 'admin']);
        $pending = $service->correctUnfulfillablePurchase($purchase, $admin, 'Recipient archived before care time could be issued.');
        $this->assertSame('pending', $pending->correction_state);
        $this->assertSame('reserved', DB::table('care_hour_welcome_claims')->value('status'));
        $pending->update(['correction_requested_at' => now()->subHours(24)]);
        config(['care_hours.enabled' => false, 'care_hours.sales_enabled' => false]);
        $corrected = $service->reconcileCorrection($pending->fresh());
        $this->assertSame('succeeded', $corrected->correction_state);
        $this->assertSame('available', DB::table('care_hour_welcome_claims')->value('status'));
        $this->assertDatabaseCount('care_hour_lots', 0);
    }

    public function test_verified_checkout_webhook_before_response_and_duplicate_inbox_event_issue_once(): void
    {
        [$account, $owner] = $this->account();
        $purchase = $this->start($account, $owner, 10);
        $sessionId = $purchase->stripe_checkout_session_id;
        $purchase->update(['stripe_checkout_session_id' => null]);
        $object = [
            'id' => $sessionId, 'object' => 'checkout.session', 'mode' => 'payment', 'payment_status' => 'unpaid',
            'metadata' => ['care_hour_domain' => 'purchase', 'care_hour_purchase_id' => $purchase->public_id],
        ];
        $event = ['id' => 'evt_care_hours_once', 'object' => 'event', 'type' => 'checkout.session.completed', 'livemode' => false, 'data' => ['object' => $object]];
        $this->postJson('/webhooks/stripe', $event)->assertOk();
        $this->postJson('/webhooks/stripe', $event)->assertOk();
        $this->assertSame(660, $account->balance()['available_minutes']);
        $this->assertDatabaseCount('care_hour_lots', 1);
        $this->assertSame($sessionId, $purchase->fresh()->stripe_checkout_session_id);
    }

    public function test_authoritative_dispute_state_prevents_stale_event_reholding_won_purchase(): void
    {
        [$account, $owner] = $this->account();
        $purchase = app(CareHourPurchaseService::class)->refresh($this->start($account, $owner, 10));
        config(['services.stripe.bypass' => false]);
        $gateway = Mockery::mock(CareHourPaymentGateway::class);
        $gateway->shouldReceive('retrieveDispute')->with('dp_stale')->andReturn(['id' => 'dp_stale', 'charge' => $purchase->stripe_charge_id, 'status' => 'won']);
        $this->app->instance(CareHourPaymentGateway::class, $gateway);
        app(CareHourWebhookService::class)->handle('charge.dispute.created', ['id' => 'dp_stale', 'charge' => $purchase->stripe_charge_id, 'status' => 'needs_response'], false);
        $this->assertSame('issued', $purchase->fresh()->status);
        $this->assertSame(660, $account->balance()['available_minutes']);
    }

    public function test_receipt_and_family_notices_are_recoverable_and_not_repeated(): void
    {
        [$account, $owner] = $this->account();
        $service = app(CareHourPurchaseService::class);
        $purchase = $service->refresh($this->start($account, $owner, 5));
        $this->assertSame(2, $service->dispatchNotices());
        $this->assertSame(0, $service->dispatchNotices());
        Notification::assertSentOnDemand(CareHourPurchaseNotification::class, fn ($notice) => $notice->receipt && $notice->purchase->id === $purchase->id);
        Notification::assertSentTo($owner, CareHourPurchaseNotification::class, fn ($notice) => $notice->toArray($owner)['event_key'] === 'care_hours_added');
        $this->assertSame(2, DB::table('care_hour_purchase_notices')->whereNotNull('sent_at')->count());
    }

    public function test_resolved_purchase_hold_does_not_send_a_stale_family_warning(): void
    {
        [$account, $owner] = $this->account();
        $service = app(CareHourPurchaseService::class);
        $purchase = $service->refresh($this->start($account, $owner, 5));
        $service->dispatchNotices();
        Notification::fake();
        $purchase->update(['status' => 'held']);
        $service->queueHoldNotice($purchase);
        $purchase->update(['status' => 'issued']);

        $this->assertSame(1, $service->dispatchNotices());
        $this->assertSame(0, $service->dispatchNotices());
        Notification::assertNothingSent();
        $this->assertNotNull(DB::table('care_hour_purchase_notices')->where('kind', 'hold')->value('sent_at'));
    }

    public function test_current_purchase_hold_still_warns_the_family_once(): void
    {
        [$account, $owner] = $this->account();
        $service = app(CareHourPurchaseService::class);
        $purchase = $service->refresh($this->start($account, $owner, 5));
        $service->dispatchNotices();
        Notification::fake();
        $purchase->update(['status' => 'held']);
        $service->queueHoldNotice($purchase);
        $service->dispatchNotices();
        $this->assertSame(0, $service->dispatchNotices());
        Notification::assertSentToTimes($owner, CareHourPurchaseNotification::class, 1);
        Notification::assertSentTo($owner, CareHourPurchaseNotification::class,
            fn ($notice) => $notice->toArray($owner)['event_key'] === 'care_hours_on_hold');
    }

    public function test_completed_purchase_suppresses_queued_buyer_attention_but_delivers_receipt(): void
    {
        [$account, $owner] = $this->account();
        $service = app(CareHourPurchaseService::class);
        $purchase = $this->start($account, $owner, 5);
        $service->queueBuyerAttention($purchase);
        $service->refresh($purchase);
        $service->dispatchNotices();
        Notification::assertNotSentTo(new \Illuminate\Notifications\AnonymousNotifiable, CareHourPurchaseNotification::class, fn ($notice) => $notice->kind === 'attention');
        Notification::assertSentOnDemand(CareHourPurchaseNotification::class, fn ($notice) => $notice->kind === 'success');
    }

    public function test_checkout_uses_exact_price_without_saving_or_replacing_family_payment_method(): void
    {
        [$account, $owner] = $this->account();
        config(['services.stripe.bypass' => false, 'services.stripe.secret' => 'sk_test_care_hours']);
        $params = [];
        $http = Mockery::mock(ClientInterface::class);
        $http->shouldReceive('request')->once()->andReturnUsing(function ($method, $url, $headers, $body) use (&$params): array {
            $this->assertSame('https://api.stripe.com/v1/checkout/sessions', $url);
            $params = $body;

            return [json_encode(['id' => 'cs_test_hours', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/test']), 200, []];
        });
        ApiRequestor::setHttpClient($http);
        $purchase = $this->start($account, $owner, 10);
        $this->assertSame('payment', $params['mode']);
        $this->assertSame(31000, $params['line_items'][0]['price_data']['unit_amount']);
        $this->assertArrayNotHasKey('customer', $params);
        $this->assertArrayNotHasKey('setup_future_usage', $params['payment_intent_data']);
        $this->assertArrayNotHasKey('allow_promotion_codes', $params);
        $this->assertArrayNotHasKey('payment_method_types', $params);
        $this->assertSame('pending', $purchase->status);
        $this->assertNull($owner->fresh()->stripe_customer_id);
    }

    private function account(): array
    {
        $owner = User::factory()->create(['role' => 'family']);
        $family = app(FamilyAccountContext::class)->account($owner);
        $account = CareHourAccount::query()->create(['family_account_id' => $family->id, 'display_name' => 'Margaret', 'currency' => 'usd', 'active' => true]);

        return [$account, $owner];
    }

    private function start(CareHourAccount $account, User $owner, int $hours): CareHourPurchase
    {
        $service = app(CareHourPurchaseService::class);

        return $service->start($account, $hours, $owner, $service->quote($account, $hours)['quote_key'], (string) Str::uuid(), url('/care-hours/receipts/{PURCHASE_ID}'), url('/cancel'));
    }

    private function financials(CareHourPurchase $purchase): array
    {
        return app(CareHourPaymentGateway::class)->financials('pi_bypass_care_hours_'.$purchase->public_id);
    }
}
