<?php

namespace Tests\Feature\CareHours;

use App\Services\CareHours\CareHourPaymentGateway;
use App\Services\Payments\StripeClient;
use Mockery;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Tests\TestCase;

class PrepaidFeeFinalizationTest extends TestCase
{
    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        ApiRequestor::resetTelemetry();
        parent::tearDown();
    }

    public function test_failed_attempt_without_balance_transaction_does_not_block_prepaid_fees_or_change_legacy_behavior(): void
    {
        $payload = $this->intent([
            ['id' => 're_failed', 'object' => 'refund', 'status' => 'failed', 'balance_transaction' => null],
            ['id' => 're_canceled', 'object' => 'refund', 'status' => 'canceled', 'balance_transaction' => null],
            $this->refund('re_success', -29),
        ]);
        $this->mockHttp([['/payment_intents/pi_fees', $payload], ['/payment_intents/pi_fees', $payload]]);

        $stripe = app(StripeClient::class);
        $prepaid = $stripe->retrieveCareHourPaymentFinancials('pi_fees');
        $this->assertTrue($prepaid['fee_finalized']);
        $this->assertSame(900, $prepaid['processing_fee_cents']);
        $this->assertFalse($stripe->retrievePaymentIntentFinancials('pi_fees')['fee_finalized']);
    }

    public function test_prepaid_refund_fee_history_is_paginated_before_finalization(): void
    {
        $this->mockHttp([
            ['/payment_intents/pi_fees', $this->intent([], true)],
            ['/refunds', ['object' => 'list', 'url' => '/v1/refunds', 'has_more' => true, 'data' => [$this->refund('re_first', -29)]],
                ['charge' => 'ch_fees', 'expand' => ['data.balance_transaction', 'data.failure_balance_transaction']]],
            ['/refunds', ['object' => 'list', 'url' => '/v1/refunds', 'has_more' => false, 'data' => [$this->refund('re_second', -10)]],
                ['charge' => 'ch_fees', 'starting_after' => 're_first', 'expand' => ['data.balance_transaction', 'data.failure_balance_transaction']]],
        ]);

        $result = app(StripeClient::class)->retrieveCareHourPaymentFinancials('pi_fees');
        $this->assertTrue($result['fee_finalized']);
        $this->assertSame(890, $result['processing_fee_cents']);
        $this->assertCount(3, $result['processing_fee_components']);
    }

    public function test_pending_refund_without_accounting_still_holds_fee_finalization(): void
    {
        $this->mockHttp([['/payment_intents/pi_fees', $this->intent([
            ['id' => 're_pending', 'object' => 'refund', 'status' => 'pending', 'balance_transaction' => null],
        ])]]);
        $this->assertFalse(app(StripeClient::class)->retrieveCareHourPaymentFinancials('pi_fees')['fee_finalized']);
    }

    public function test_failed_refund_fee_credit_is_reversed_before_finalizing_and_gateway_preserves_the_result(): void
    {
        $failed = $this->refund('re_failed', -29);
        $failed['status'] = 'failed';
        $failed['failure_balance_transaction'] = $this->failureTransaction(29);
        $payload = $this->intent([$failed]);
        // Gateway first retrieves payment identity, then the complete prepaid financials.
        $this->mockHttp([['/payment_intents/pi_fees', $payload], ['/payment_intents/pi_fees', $payload,
            ['expand' => ['latest_charge.balance_transaction', 'latest_charge.refunds.data.balance_transaction',
                'latest_charge.refunds.data.failure_balance_transaction']]]]);

        $result = app(CareHourPaymentGateway::class)->financials('pi_fees');

        $this->assertTrue($result['fee_finalized']);
        $this->assertSame(929, $result['processing_fee_cents']);
        $this->assertSame([929, -29, 29], array_column($result['fee_components'], 'fee_cents'));
        $this->assertSame('txn_failure', $result['fee_components'][2]['balance_transaction_id']);
    }

    public function test_failed_refund_with_original_accounting_waits_for_known_failure_accounting(): void
    {
        $responses = [];
        foreach ([null, 'txn_failure', ['id' => 'txn_failure', 'reporting_category' => 'refund_failure']] as $failure) {
            $refund = $this->refund('re_failed', -29);
            $refund['status'] = 'failed';
            $refund['failure_balance_transaction'] = $failure;
            $responses[] = ['/payment_intents/pi_fees', $this->intent([$refund])];
        }
        $this->mockHttp($responses);

        foreach ($responses as $_) {
            $this->assertFalse(app(StripeClient::class)->retrieveCareHourPaymentFinancials('pi_fees')['fee_finalized']);
        }
    }

    public function test_extra_refund_costs_are_excluded_but_real_net_fee_returns_are_retained(): void
    {
        $failed = $this->refund('re_failed', -29);
        $failed['status'] = 'failed';
        $failed['failure_balance_transaction'] = $this->failureTransaction(39);
        $this->mockHttp([['/payment_intents/pi_fees', $this->intent([
            $failed, $this->refund('re_fee', 20), $this->refund('re_return', -10),
        ])]]);

        $result = app(StripeClient::class)->retrieveCareHourPaymentFinancials('pi_fees');

        $this->assertTrue($result['fee_finalized']);
        $this->assertSame(919, $result['processing_fee_cents']);
        $this->assertCount(5, $result['processing_fee_components']);
    }

    public function test_pending_refund_with_a_fee_credit_remains_unfinalized(): void
    {
        $refund = $this->refund('re_pending', -29);
        $refund['status'] = 'pending';
        $this->mockHttp([['/payment_intents/pi_fees', $this->intent([$refund])]]);

        $this->assertFalse(app(StripeClient::class)->retrieveCareHourPaymentFinancials('pi_fees')['fee_finalized']);
    }

    public function test_known_refund_credit_cannot_finalize_an_unknown_original_charge_fee(): void
    {
        $payload = $this->intent([$this->refund('re_return', -29)]);
        $payload['latest_charge']['balance_transaction'] = null;
        $this->mockHttp([['/payment_intents/pi_fees', $payload]]);

        $this->assertFalse(app(StripeClient::class)->retrieveCareHourPaymentFinancials('pi_fees')['fee_finalized']);
    }

    public function test_partial_capture_waits_for_full_authorization_release_and_nets_its_fee_credit(): void
    {
        $pending = $this->intent([]);
        $pending['amount_received'] = 15500;
        $release = $this->refund('re_release', -449);
        $release['amount'] = 15500;
        $release['balance_transaction']['amount'] = -15500;
        $release['balance_transaction']['reporting_category'] = 'partial_capture_reversal';
        $complete = $pending;
        $complete['latest_charge']['refunds']['data'] = [$release,
            ['id' => 're_failed', 'object' => 'refund', 'status' => 'failed', 'balance_transaction' => null]];
        $this->mockHttp([['/payment_intents/pi_fees', $pending], ['/payment_intents/pi_fees', $complete]]);

        $this->assertFalse(app(StripeClient::class)->retrieveCareHourPaymentFinancials('pi_fees')['fee_finalized']);
        $result = app(StripeClient::class)->retrieveCareHourPaymentFinancials('pi_fees');
        $this->assertTrue($result['fee_finalized']);
        $this->assertSame(480, $result['processing_fee_cents']);
        $this->assertSame(15500, $result['amount_received']);
    }

    public function test_partial_capture_with_a_captured_amount_balance_transaction_needs_no_release(): void
    {
        $payload = $this->intent([]);
        $payload['amount_received'] = 15500;
        $payload['latest_charge']['balance_transaction']['amount'] = 15500;
        $payload['latest_charge']['balance_transaction']['fee'] = 480;
        $this->mockHttp([['/payment_intents/pi_fees', $payload]]);

        $result = app(StripeClient::class)->retrieveCareHourPaymentFinancials('pi_fees');

        $this->assertTrue($result['fee_finalized']);
        $this->assertSame(480, $result['processing_fee_cents']);
    }

    public function test_gateway_cash_refunds_exclude_paginated_partial_capture_releases(): void
    {
        $release = $this->refund('re_release', -449);
        $release['amount'] = 15500;
        $release['balance_transaction']['amount'] = -15500;
        $release['balance_transaction']['reporting_category'] = 'partial_capture_reversal';
        $payload = $this->intent([], true);
        $payload['amount_received'] = 15500;
        $payload['metadata'] = ['care_hour_domain' => 'shortfall'];
        $payload['latest_charge']['amount_refunded'] = 16500;
        $this->mockHttp([
            ['/payment_intents/pi_fees', $payload], ['/payment_intents/pi_fees', $payload],
            ['/refunds', ['object' => 'list', 'url' => '/v1/refunds', 'has_more' => true, 'data' => [$this->refund('re_cash', 0)]]],
            ['/refunds', ['object' => 'list', 'url' => '/v1/refunds', 'has_more' => false, 'data' => [$release]]],
        ]);

        $result = app(CareHourPaymentGateway::class)->financials('pi_fees');

        $this->assertTrue($result['refund_financials_finalized']);
        $this->assertSame(1000, $result['amount_refunded_cents']);
        $this->assertSame(15500, $result['amount_received']);
        $this->assertSame(480, $result['processing_fee_cents']);
    }

    public function test_cash_refund_classification_waits_for_unknown_or_incomplete_provider_history(): void
    {
        $unknown = $this->refund('re_unknown', 0);
        $unknown['balance_transaction']['reporting_category'] = 'unknown';
        $payloads = [$this->intent([$unknown]), $this->intent([])];
        foreach ($payloads as &$payload) {
            $payload['latest_charge']['amount_refunded'] = 1000;
        }
        unset($payload);
        $this->mockHttp(array_map(fn ($payload) => ['/payment_intents/pi_fees', $payload], $payloads));

        foreach ($payloads as $_) {
            $result = app(StripeClient::class)->retrieveCareHourPaymentFinancials('pi_fees');
            $this->assertFalse($result['refund_financials_finalized']);
            $this->assertNull($result['cash_refunded_cents']);
        }
    }

    private function intent(array $refunds, bool $more = false): array
    {
        return ['id' => 'pi_fees', 'object' => 'payment_intent', 'status' => 'succeeded', 'amount' => 31000, 'amount_received' => 31000,
            'latest_charge' => ['id' => 'ch_fees', 'object' => 'charge',
                'balance_transaction' => ['id' => 'txn_charge', 'object' => 'balance_transaction', 'amount' => 31000, 'fee' => 929, 'reporting_category' => 'charge'],
                'refunds' => ['object' => 'list', 'url' => '/v1/refunds', 'has_more' => $more, 'data' => $refunds]]];
    }

    private function refund(string $id, int $fee): array
    {
        return ['id' => $id, 'object' => 'refund', 'status' => 'succeeded', 'amount' => 1000,
            'balance_transaction' => ['id' => 'txn_'.$id, 'object' => 'balance_transaction', 'amount' => -1000, 'fee' => $fee, 'reporting_category' => 'refund']];
    }

    private function failureTransaction(int $fee): array
    {
        return ['id' => 'txn_failure', 'object' => 'balance_transaction', 'amount' => 1000,
            'fee' => $fee, 'reporting_category' => 'refund_failure'];
    }

    private function mockHttp(array $responses): void
    {
        config(['services.stripe.bypass' => false, 'services.stripe.secret' => 'sk_test_prepaid_fee_fixture']);
        $http = Mockery::mock(ClientInterface::class);
        foreach ($responses as $response) {
            [$path, $payload] = $response;
            $expectedParams = $response[2] ?? [];
            $http->shouldReceive('request')->once()->ordered()->andReturnUsing(function ($method, $url, $headers, $params) use ($path, $payload, $expectedParams) {
                $this->assertSame('get', $method);
                $this->assertStringContainsString($path, $url);
                foreach ($expectedParams as $key => $value) {
                    $this->assertSame($value, $params[$key]);
                }

                return [json_encode($payload), 200, []];
            });
        }
        ApiRequestor::setHttpClient($http);
    }
}
