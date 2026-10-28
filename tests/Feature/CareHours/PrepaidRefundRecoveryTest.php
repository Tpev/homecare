<?php

namespace Tests\Feature\CareHours;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareBooking;
use App\Models\CareHourAccount;
use App\Models\CareHourBookingFunding;
use App\Models\CareHourPaymentOperation;
use App\Models\CareHourSettlement;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\User;
use App\Services\CareHours\CareHourPaymentGateway;
use App\Services\CareHours\CareHourWebhookService;
use App\Services\CareHours\PrepaidBookingFundingService;
use App\Services\CareHours\PrepaidBookingSettlementService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Tests\TestCase;

class PrepaidRefundRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        ApiRequestor::resetTelemetry();
        parent::tearDown();
    }

    public function test_voided_authorization_charge_refunded_event_is_verified_and_replay_safe_without_a_cash_refund(): void
    {
        $payment = $this->payment();
        $payment->update(['status' => 'authorized', 'captured_cents' => 0]);
        $gateway = Mockery::mock(CareHourPaymentGateway::class);
        $gateway->shouldReceive('financials')->twice()->with($payment->stripe_payment_intent_id)
            ->andReturn($this->releasedAuthorization($payment));
        $this->app->instance(CareHourPaymentGateway::class, $gateway);

        $event = ['id' => $payment->stripe_charge_id, 'payment_intent' => $payment->stripe_payment_intent_id,
            'currency' => 'usd', 'livemode' => false, 'amount' => 3100, 'captured' => false,
            'amount_captured' => 0, 'amount_refunded' => 3100];
        $webhooks = app(CareHourWebhookService::class);
        $this->assertTrue($webhooks->handle('charge.refunded', $event, false));
        $this->assertTrue($webhooks->handle('charge.refunded', $event, false));

        $this->assertSame('cancelled', $payment->fresh()->status);
        $this->assertSame(0, $payment->fresh()->captured_cents);
        $this->assertSame(0, $payment->fresh()->refunded_cents);
        $this->assertSame('released', $payment->funding->fresh()->status);
        $this->assertSame(0, CareHourPaymentOperation::where('kind', 'refund')->count());
        $this->assertDatabaseCount('care_hour_ledger_entries', 0);
        $this->assertSame(0, CareHourPaymentOperation::where('kind', 'transfer')->count());
    }

    public function test_authorization_release_does_not_trust_event_capture_flags_or_mismatched_provider_identity(): void
    {
        $payment = $this->payment();
        $payment->update(['status' => 'authorized', 'captured_cents' => 0]);
        foreach ([['status' => 'succeeded', 'amount_received' => 3100], ['payment_intent_id' => 'pi_other'],
            ['charge_id' => 'ch_other'], ['livemode' => true], ['connected_account' => 'acct_other'],
            ['metadata' => ['care_hour_domain' => 'shortfall', 'care_hour_operation_id' => 'other']]] as $wrong) {
            $gateway = Mockery::mock(CareHourPaymentGateway::class);
            $gateway->shouldReceive('financials')->once()->andReturn(array_replace($this->releasedAuthorization($payment), $wrong));
            $this->app->instance(CareHourPaymentGateway::class, $gateway);
            $this->assertPaymentAttention(fn () => app(PrepaidBookingSettlementService::class)->handleChargeRefundEvent([
                'id' => $payment->stripe_charge_id, 'captured' => false, 'amount_captured' => 0, 'amount_refunded' => 3100,
            ], false));
        }
        $this->assertSame('authorized', $payment->fresh()->status);
        $this->assertSame(0, $payment->fresh()->refunded_cents);
        $this->assertSame(0, CareHourPaymentOperation::where('kind', 'refund')->count());
    }

    public function test_captured_charge_still_cannot_report_a_refund_above_the_recorded_capture(): void
    {
        $payment = $this->payment();
        $gateway = Mockery::mock(CareHourPaymentGateway::class);
        $gateway->shouldNotReceive('financials');
        $this->app->instance(CareHourPaymentGateway::class, $gateway);
        $this->assertPaymentAttention(fn () => app(PrepaidBookingSettlementService::class)->handleChargeRefundEvent([
            'id' => $payment->stripe_charge_id, 'captured' => true, 'amount_captured' => 3100, 'amount_refunded' => 3101,
        ], false));
        $this->assertSame('captured', $payment->fresh()->status);
        $this->assertSame(0, $payment->fresh()->refunded_cents);
    }

    private function releasedAuthorization(CareHourPaymentOperation $payment): array
    {
        return ['status' => 'canceled', 'payment_intent_id' => $payment->stripe_payment_intent_id,
            'charge_id' => $payment->stripe_charge_id, 'livemode' => false, 'connected_account' => null,
            'amount_cents' => 3100, 'amount_received' => 0, 'authorized_cents' => 0, 'currency' => 'usd',
            'metadata' => ['care_hour_domain' => 'shortfall', 'care_hour_operation_id' => $payment->public_id]];
    }

    public function test_partial_capture_release_and_later_cash_refund_use_only_classified_refunded_cash(): void
    {
        $payment = $this->payment();
        $payment->update(['captured_cents' => 1550]);
        $base = array_replace($this->releasedAuthorization($payment), ['status' => 'succeeded', 'amount_received' => 1550,
            'refund_financials_finalized' => true]);
        $gateway = Mockery::mock(CareHourPaymentGateway::class);
        $gateway->shouldReceive('financials')->once()->ordered()->andReturn($base + ['amount_refunded_cents' => 0]);
        $gateway->shouldReceive('financials')->twice()->ordered()->andReturn($base + ['amount_refunded_cents' => 100]);
        $this->app->instance(CareHourPaymentGateway::class, $gateway);
        $webhooks = app(CareHourWebhookService::class);
        $event = ['id' => $payment->stripe_charge_id, 'payment_intent' => $payment->stripe_payment_intent_id,
            'currency' => 'usd', 'captured' => true, 'amount_captured' => 1550, 'amount_refunded' => 1550];
        $this->assertTrue($webhooks->handle('charge.refunded', $event, false));
        $this->assertSame(0, $payment->fresh()->refunded_cents);
        $event['amount_refunded'] = 1650;
        $this->assertTrue($webhooks->handle('charge.refunded', $event, false));
        $this->assertTrue($webhooks->handle('charge.refunded', $event, false));
        $this->assertSame(100, $payment->fresh()->refunded_cents);
        $this->assertSame(1550, $payment->fresh()->captured_cents);
        $this->assertSame('captured', $payment->fresh()->status);
        $this->assertSame(0, CareHourPaymentOperation::where('kind', 'refund')->count());
    }

    public function test_partial_capture_refund_waits_for_complete_provider_classification(): void
    {
        $payment = $this->payment();
        $payment->update(['captured_cents' => 1550]);
        $gateway = Mockery::mock(CareHourPaymentGateway::class);
        $gateway->shouldReceive('financials')->once()->andReturn(array_replace($this->releasedAuthorization($payment), [
            'status' => 'succeeded', 'amount_received' => 1550, 'refund_financials_finalized' => false,
            'amount_refunded_cents' => null,
        ]));
        $this->app->instance(CareHourPaymentGateway::class, $gateway);
        $this->assertPaymentAttention(fn () => app(PrepaidBookingSettlementService::class)->handleChargeRefundEvent([
            'id' => $payment->stripe_charge_id, 'captured' => true, 'amount_captured' => 1550, 'amount_refunded' => 1550,
        ], false));
        $this->assertSame(0, $payment->fresh()->refunded_cents);
    }

    public function test_signed_owned_refund_before_charge_linkage_or_capture_retries_until_original_completion(): void
    {
        $payment = $this->payment();
        $charge = $payment->stripe_charge_id;
        $payment->update(['status' => 'checkout', 'captured_cents' => 0, 'stripe_charge_id' => null]);
        $payment->funding->update(['status' => 'ready']);
        $payment->funding->booking->update(['status' => CareBooking::STATUS_SCHEDULED]);
        config(['services.stripe.bypass' => false, 'services.stripe.webhook_secret' => 'whsec_prepaid_refund_ordering']);
        $result = array_replace($this->releasedAuthorization($payment), ['status' => 'succeeded', 'charge_id' => $charge,
            'amount_received' => 3100, 'refund_financials_finalized' => true, 'amount_refunded_cents' => 100]);
        $gateway = Mockery::mock(CareHourPaymentGateway::class);
        $gateway->shouldReceive('financials')->twice()->with($payment->stripe_payment_intent_id)->andReturn($result);
        $this->app->instance(CareHourPaymentGateway::class, $gateway);
        $event = ['id' => 'evt_refund_before_link', 'object' => 'event', 'type' => 'charge.refunded', 'livemode' => false,
            'data' => ['object' => ['id' => $charge, 'object' => 'charge', 'payment_intent' => $payment->stripe_payment_intent_id,
                'currency' => 'usd', 'amount_refunded' => 100, 'captured' => true, 'amount_captured' => 3100,
                'metadata' => ['care_hour_domain' => 'shortfall', 'care_hour_operation_id' => $payment->public_id]]]];
        $send = function () use ($event) {
            $body = json_encode($event, JSON_THROW_ON_ERROR);
            $timestamp = time();
            $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_prepaid_refund_ordering');

            return $this->call('POST', route('webhooks.stripe'), [], [], [], [
                'CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
            ], $body);
        };
        $send()->assertStatus(500);
        $this->assertNull($payment->fresh()->stripe_charge_id);
        $this->assertSame(0, $payment->fresh()->captured_cents);
        $this->assertSame(0, $payment->fresh()->refunded_cents);

        // Exercise the original completion writer; only its unrelated reservation refresh is stubbed.
        $funding = Mockery::mock(PrepaidBookingFundingService::class)->makePartial();
        $funding->shouldReceive('prepare')->twice()->andReturn($payment->funding);
        $funding->completeShortfall($payment->public_id, array_replace($result, [
            'status' => 'requires_capture', 'authorized_cents' => 3100, 'amount_received' => 0,
        ]));
        $send()->assertStatus(500);
        $this->assertSame($charge, $payment->fresh()->stripe_charge_id);
        $this->assertSame('authorized', $payment->fresh()->status);
        $this->assertSame(0, $payment->fresh()->captured_cents);
        $this->assertSame(0, $payment->fresh()->refunded_cents);
        $this->assertDatabaseHas('stripe_webhook_events', ['stripe_event_id' => $event['id'], 'status' => 'failed', 'attempts' => 2]);
        $funding->completeShortfall($payment->public_id, $result);
        $send()->assertOk()->assertJsonMissing(['duplicate' => true]);
        $send()->assertOk()->assertJsonPath('duplicate', true);
        $this->assertSame(3100, $payment->fresh()->captured_cents);
        $this->assertSame(100, $payment->fresh()->refunded_cents);
        $this->assertDatabaseHas('stripe_webhook_events', ['stripe_event_id' => $event['id'], 'status' => 'processed', 'attempts' => 3]);
        $this->assertDatabaseCount('care_hour_ledger_entries', 0);
        $this->assertSame(0, CareHourPaymentOperation::where('kind', 'refund')->count());
    }

    public function test_unlinked_refund_metadata_cannot_attach_another_payment_and_unknown_legacy_charge_is_not_owned(): void
    {
        $payment = $this->payment();
        $payment->update(['status' => 'checkout', 'captured_cents' => 0, 'stripe_charge_id' => null]);
        $gateway = Mockery::mock(CareHourPaymentGateway::class);
        $gateway->shouldNotReceive('financials');
        $this->app->instance(CareHourPaymentGateway::class, $gateway);
        $webhooks = app(CareHourWebhookService::class);
        $this->assertPaymentAttention(fn () => $webhooks->handle('charge.refunded', ['id' => 'ch_other', 'payment_intent' => 'pi_other',
            'metadata' => ['care_hour_domain' => 'shortfall', 'care_hour_operation_id' => $payment->public_id]], false));
        $this->assertNull($payment->fresh()->stripe_charge_id);
        $this->assertSame(0, $payment->fresh()->captured_cents);
        $this->assertFalse($webhooks->handle('charge.refunded', ['id' => 'ch_unknown_legacy', 'payment_intent' => 'pi_unknown_legacy'], false));
    }

    public function test_failed_unused_refund_retries_only_after_provider_confirmation_and_preserves_history(): void
    {
        $payment = $this->payment();
        $original = $this->attempt($payment);
        $service = app(PrepaidBookingSettlementService::class);
        $service->handleRefundEvent($this->refundResult($original, 'failed'));
        $this->assertSame('failed', $original->fresh()->status);
        $this->assertSame(0, $payment->fresh()->refunded_cents);
        $original->update(['created_at' => now()->subDays(3)]);

        $gateway = Mockery::mock(CareHourPaymentGateway::class);
        $gateway->shouldReceive('findShortfallRefund')->once()->withArgs(fn ($operation) => $operation->id === $original->id)
            ->andReturn($this->refundResult($original, 'failed'));
        $gateway->shouldReceive('createShortfallRefund')->once()->andReturnUsing(function ($retry) use ($original) {
            $this->assertNotSame($original->public_id, $retry->public_id);
            $this->assertNotSame($original->operation_key, $retry->operation_key);
            $this->assertSame($original->amount_cents, $retry->amount_cents);
            $this->assertSame($original->id, $retry->metadata['retry_of_operation_id']);

            return $this->refundResult($retry, 'succeeded');
        });
        $this->app->instance(CareHourPaymentGateway::class, $gateway);
        $service = app(PrepaidBookingSettlementService::class);
        $service->refundUnused($payment);
        $service->refundUnused($payment->fresh());

        $this->assertSame('failed', $original->fresh()->status);
        $this->assertSame(3100, $original->fresh()->amount_cents);
        $this->assertSame(3100, $payment->fresh()->refunded_cents);
        $this->assertSame(2, CareHourPaymentOperation::where('kind', 'refund')->count());
        $this->assertSame(3100, (int) CareHourPaymentOperation::where('kind', 'refund')->where('status', 'succeeded')->sum('amount_cents'));
    }

    public function test_canceled_excess_refund_recovers_across_a_new_settlement_revision(): void
    {
        $payment = $this->payment();
        $settlement = $this->settlement($payment, 2);
        $original = $this->attempt($payment, ['care_hour_settlement_id' => $settlement->id, 'operation_key' => 'shortfall-refund:'.$payment->id.':1']);
        // Even a missed terminal webhook is recoverable from the authoritative provider response.
        $gateway = Mockery::mock(CareHourPaymentGateway::class);
        $gateway->shouldReceive('findShortfallRefund')->once()->andReturn($this->refundResult($original, 'canceled'));
        $gateway->shouldReceive('createShortfallRefund')->once()->andReturnUsing(fn ($retry) => $this->refundResult($retry, 'succeeded'));
        $this->app->instance(CareHourPaymentGateway::class, $gateway);

        app(PrepaidBookingSettlementService::class)->reconcile($settlement);

        $this->assertSame('canceled', $original->fresh()->status);
        $this->assertSame(3100, $payment->fresh()->refunded_cents);
        $this->assertSame(2, CareHourPaymentOperation::where('kind', 'refund')->count());
    }

    public function test_new_terminal_failure_is_preserved_and_requires_another_authoritative_check(): void
    {
        $payment = $this->payment();
        $first = null;
        $gateway = Mockery::mock(CareHourPaymentGateway::class);
        $gateway->shouldReceive('createShortfallRefund')->twice()->andReturnUsing(function ($operation) use (&$first) {
            if (! $first) {
                $first = $operation;

                return $this->refundResult($operation, 'failed');
            }
            $this->assertSame($first->id, $operation->metadata['retry_of_operation_id']);

            return $this->refundResult($operation, 'succeeded');
        });
        $gateway->shouldReceive('findShortfallRefund')->once()->andReturnUsing(function ($operation) use (&$first) {
            $this->assertSame($first->id, $operation->id);

            return $this->refundResult($operation, 'failed');
        });
        $this->app->instance(CareHourPaymentGateway::class, $gateway);
        $service = app(PrepaidBookingSettlementService::class);

        $this->assertPaymentAttention(fn () => $service->refundUnused($payment));
        $this->assertSame('failed', $first->fresh()->status);
        $this->assertSame(0, $payment->fresh()->refunded_cents);
        $service->refundUnused($payment);
        $this->assertSame(3100, $payment->fresh()->refunded_cents);
        $this->assertSame(2, CareHourPaymentOperation::where('kind', 'refund')->count());
    }

    public function test_recent_unknown_response_only_replays_the_same_attempt(): void
    {
        $payment = $this->payment();
        $original = $this->attempt($payment);
        $gateway = Mockery::mock(CareHourPaymentGateway::class);
        $gateway->shouldReceive('findShortfallRefund')->once()->andReturnNull();
        $gateway->shouldReceive('createShortfallRefund')->once()->andReturnUsing(function ($operation) use ($original) {
            $this->assertSame($original->public_id, $operation->public_id);
            $this->assertSame($original->operation_key, $operation->operation_key);

            return $this->refundResult($operation, 'succeeded');
        });
        $this->app->instance(CareHourPaymentGateway::class, $gateway);

        app(PrepaidBookingSettlementService::class)->refundUnused($payment);

        $this->assertSame(3100, $payment->fresh()->refunded_cents);
        $this->assertSame(1, CareHourPaymentOperation::where('kind', 'refund')->count());
    }

    public function test_unknown_expired_attempt_is_not_replaced_or_reissued(): void
    {
        $payment = $this->payment();
        $original = $this->attempt($payment, ['created_at' => now()->subDays(2)]);
        $gateway = Mockery::mock(CareHourPaymentGateway::class);
        $gateway->shouldReceive('findShortfallRefund')->once()->andReturnNull();
        $gateway->shouldNotReceive('createShortfallRefund');
        $this->app->instance(CareHourPaymentGateway::class, $gateway);

        $this->assertPaymentAttention(fn () => app(PrepaidBookingSettlementService::class)->refundUnused($payment));

        $this->assertSame('pending', $original->fresh()->status);
        $this->assertSame(0, $payment->fresh()->refunded_cents);
        $this->assertSame(1, CareHourPaymentOperation::where('kind', 'refund')->count());
    }

    public function test_local_failed_flag_cannot_replace_pending_or_unknown_provider_refund(): void
    {
        foreach ([null, 'pending'] as $providerStatus) {
            $payment = $this->payment();
            $original = $this->attempt($payment, ['status' => 'failed']);
            $gateway = Mockery::mock(CareHourPaymentGateway::class);
            $gateway->shouldReceive('findShortfallRefund')->once()->andReturn($providerStatus ? $this->refundResult($original, $providerStatus) : null);
            $gateway->shouldNotReceive('createShortfallRefund');
            $this->app->instance(CareHourPaymentGateway::class, $gateway);

            $this->assertPaymentAttention(fn () => app(PrepaidBookingSettlementService::class)->refundUnused($payment));

            $this->assertSame(0, $payment->fresh()->refunded_cents);
            $this->assertSame(1, CareHourPaymentOperation::where('kind', 'refund')->where('stripe_payment_intent_id', $payment->stripe_payment_intent_id)->count());
        }
    }

    public function test_pending_refund_from_earlier_revision_blocks_a_second_refund(): void
    {
        $payment = $this->payment();
        $settlement = $this->settlement($payment, 3);
        $original = $this->attempt($payment, ['amount_cents' => 1550, 'care_hour_settlement_id' => $settlement->id]);
        $gateway = Mockery::mock(CareHourPaymentGateway::class);
        $gateway->shouldReceive('findShortfallRefund')->once()->andReturn($this->refundResult($original, 'pending'));
        $gateway->shouldNotReceive('createShortfallRefund');
        $this->app->instance(CareHourPaymentGateway::class, $gateway);

        $this->assertPaymentAttention(fn () => app(PrepaidBookingSettlementService::class)->reconcile($settlement));

        $this->assertSame(0, $payment->fresh()->refunded_cents);
        $this->assertSame(1, CareHourPaymentOperation::where('kind', 'refund')->count());
    }

    public function test_lost_success_is_reconciled_before_refunding_only_the_remaining_cash(): void
    {
        $payment = $this->payment();
        $original = $this->attempt($payment, ['amount_cents' => 1550, 'created_at' => now()->subDays(2)]);
        $gateway = Mockery::mock(CareHourPaymentGateway::class);
        $gateway->shouldReceive('findShortfallRefund')->once()->andReturn($this->refundResult($original, 'succeeded'));
        $gateway->shouldReceive('createShortfallRefund')->once()->andReturnUsing(function ($operation) {
            $this->assertSame(1550, $operation->amount_cents);

            return $this->refundResult($operation, 'succeeded');
        });
        $this->app->instance(CareHourPaymentGateway::class, $gateway);

        app(PrepaidBookingSettlementService::class)->refundUnused($payment);

        $this->assertSame(3100, $payment->fresh()->refunded_cents);
        $this->assertSame(2, CareHourPaymentOperation::where('kind', 'refund')->where('status', 'succeeded')->count());
    }

    public function test_refund_recovery_keeps_provider_scope_and_identity_validation(): void
    {
        $payment = $this->payment();
        $original = $this->attempt($payment);
        $service = app(PrepaidBookingSettlementService::class);
        foreach ([['livemode' => true], ['amount' => 1], ['payment_intent' => 'pi_other'], ['charge' => 'ch_other'],
            ['metadata' => ['care_hour_domain' => 'purchase', 'care_hour_operation_id' => $original->public_id]]] as $wrong) {
            $this->assertPaymentAttention(fn () => $service->handleRefundEvent(array_replace($this->refundResult($original, 'failed'), $wrong)));
        }
        $this->assertPaymentAttention(fn () => $service->handleRefundEvent($this->refundResult($original, 'failed'), false, 'acct_other'));
        $this->assertSame('pending', $original->fresh()->status);
        $this->assertSame(0, $payment->fresh()->refunded_cents);
    }

    public function test_gateway_recovers_exact_attempt_by_metadata_and_uses_a_distinct_retry_key(): void
    {
        $payment = $this->payment();
        $original = $this->attempt($payment);
        $retry = $this->attempt($payment, ['metadata' => ['retry_of_operation_id' => $original->id]]);
        config(['services.stripe.bypass' => false, 'services.stripe.secret' => 'sk_test_refund_recovery']);
        $http = Mockery::mock(ClientInterface::class);
        $http->shouldReceive('request')->once()->ordered()->andReturnUsing(function ($method, $url) use ($original, $retry) {
            $this->assertSame('get', $method);
            $this->assertStringStartsWith('https://api.stripe.com/v1/refunds', $url);

            return [json_encode(['object' => 'list', 'has_more' => false, 'url' => '/v1/refunds', 'data' => [
                $this->refundResult($original, 'failed') + ['object' => 'refund'], $this->refundResult($retry, 'pending') + ['object' => 'refund'],
            ]]), 200, []];
        });
        $http->shouldReceive('request')->once()->ordered()->andReturnUsing(function ($method, $url, $headers, $body) use ($retry) {
            $this->assertSame('post', $method);
            $this->assertContains('Idempotency-Key: care-hours:refund:'.$retry->public_id, $headers);
            $this->assertSame($retry->public_id, $body['metadata']['care_hour_operation_id']);
            $this->assertSame(3100, $body['amount']);

            return [json_encode($this->refundResult($retry, 'succeeded') + ['object' => 'refund']), 200, []];
        });
        ApiRequestor::setHttpClient($http);

        $gateway = new CareHourPaymentGateway;
        $this->assertSame('re_'.$retry->public_id, $gateway->findShortfallRefund($retry)['id']);
        $this->assertSame('succeeded', $gateway->createShortfallRefund($retry)['status']);
    }

    private function assertPaymentAttention(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Unconfirmed refund must stop for reconciliation.');
        } catch (PaymentException $error) {
            $this->assertNotEmpty($error->getMessage());
        }
    }

    private function refundResult(CareHourPaymentOperation $operation, string $status): array
    {
        return ['id' => $operation->stripe_refund_id ?: 're_'.$operation->public_id, 'status' => $status,
            'amount' => (int) $operation->amount_cents, 'currency' => $operation->currency,
            'payment_intent' => $operation->stripe_payment_intent_id, 'charge' => $operation->stripe_charge_id, 'livemode' => false,
            'metadata' => ['care_hour_domain' => 'shortfall', 'care_hour_operation_id' => $operation->public_id]];
    }

    private function attempt(CareHourPaymentOperation $payment, array $overrides = []): CareHourPaymentOperation
    {
        return CareHourPaymentOperation::create(array_replace(['public_id' => (string) Str::uuid(), 'operation_key' => (string) Str::uuid(),
            'care_hour_booking_funding_id' => $payment->care_hour_booking_funding_id, 'kind' => 'refund', 'status' => 'pending',
            'amount_cents' => 3100, 'currency' => 'usd', 'stripe_payment_intent_id' => $payment->stripe_payment_intent_id,
            'stripe_charge_id' => $payment->stripe_charge_id], $overrides));
    }

    private function settlement(CareHourPaymentOperation $payment, int $revision): CareHourSettlement
    {
        // Excess-refund reconciliation belongs to an approved visit; cancellation uses refundUnused().
        $payment->funding->update(['status' => 'ready']);

        return CareHourSettlement::create(['care_booking_id' => $payment->funding->care_booking_id,
            'care_hour_booking_funding_id' => $payment->care_hour_booking_funding_id,
            'revision' => $revision, 'package_fee_cents' => 0, 'worked_minutes' => 0, 'card_minutes' => 0]);
    }

    private function payment(): CareHourPaymentOperation
    {
        $owner = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $family = app(FamilyAccountContext::class)->account($owner);
        $account = CareHourAccount::create(['family_account_id' => $family->id, 'display_name' => 'Margaret', 'currency' => 'usd', 'active' => true]);
        $request = CareRequest::create(['family_user_id' => $owner->id, 'title' => 'Care', 'status' => 'filled',
            'address_line1' => '123 Test St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601', 'request_type' => 'one_time',
            'requested_start_at' => now()->subDay(), 'requested_end_at' => now()->subDay()->addHours(2)]);
        $application = CareRequestApplication::create(['care_request_id' => $request->id, 'caregiver_user_id' => $caregiver->id, 'status' => 'hired', 'proposed_rate' => 27]);
        $booking = CareBooking::create(['care_request_id' => $request->id, 'care_request_application_id' => $application->id,
            'family_user_id' => $owner->id, 'caregiver_user_id' => $caregiver->id, 'status' => 'completed', 'worked_minutes' => 0,
            'scheduled_start_at' => $request->requested_start_at, 'scheduled_end_at' => $request->requested_end_at, 'expected_minutes' => 120]);
        $funding = CareHourBookingFunding::create(['care_booking_id' => $booking->id, 'care_hour_account_id' => $account->id,
            'selected_by_user_id' => $owner->id, 'status' => 'released', 'committed_at' => now()]);
        $id = (string) Str::uuid();

        return CareHourPaymentOperation::create(['public_id' => $id, 'operation_key' => $id,
            'care_hour_booking_funding_id' => $funding->id, 'kind' => 'shortfall', 'status' => 'captured',
            'amount_cents' => 3100, 'captured_cents' => 3100, 'currency' => 'usd', 'processing_fee_cents' => 0,
            'stripe_payment_intent_id' => 'pi_'.$id, 'stripe_charge_id' => 'ch_'.$id]);
    }
}
