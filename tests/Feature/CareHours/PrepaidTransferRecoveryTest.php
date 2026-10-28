<?php

namespace Tests\Feature\CareHours;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareBooking;
use App\Models\CaregiverPayoutItem;
use App\Models\CaregiverProfile;
use App\Models\CareHourAccount;
use App\Models\CareHourBookingFunding;
use App\Models\CareHourPaymentOperation;
use App\Models\CareHourSettlement;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\User;
use App\Services\CareHours\PrepaidBookingSettlementService;
use App\Services\CareHours\PrepaidTransferPlanner;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\Payments\StripeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Tests\TestCase;

class PrepaidTransferRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        ApiRequestor::resetTelemetry();
        parent::tearDown();
    }

    public function test_aged_unknown_transfer_recovers_its_original_provider_identity_without_another_transfer(): void
    {
        $settlement = $this->settlement();
        $transfer = $this->transfer($settlement, ['status' => 'unknown', 'stripe_transfer_id' => null, 'created_at' => now()->subDays(2)]);
        $stripe = $this->readOnlyStripe();
        $stripe->shouldReceive('findPrepaidTransfer')->once()->with(null, $transfer->public_id, 'acct_care_hours', $transfer->created_at->timestamp - 60)
            ->andReturn($this->transferResult($transfer));
        $planner = app(PrepaidTransferPlanner::class);

        $planner->pay($settlement);
        $planner->pay($settlement->fresh());

        $this->assertSame('succeeded', $transfer->fresh()->status);
        $this->assertSame('tr_'.$transfer->public_id, $transfer->fresh()->stripe_transfer_id);
        $this->assertSame($transfer->operation_key, $transfer->fresh()->operation_key);
        $this->assertSame('paid', $settlement->fresh()->status);
        $this->assertSame(5400, $settlement->fresh()->paid_cents);
        $this->assertSame(1, $settlement->operations()->count());
    }

    public function test_aged_unknown_reversal_recovers_before_calculating_a_downward_adjustment(): void
    {
        $settlement = $this->settlement(2700);
        $transfer = $this->transfer($settlement);
        $reversal = $this->reversal($transfer, 2700, ['status' => 'unknown', 'created_at' => now()->subDays(2)]);
        $stripe = $this->readOnlyStripe();
        $stripe->shouldReceive('findPrepaidTransferReversal')->once()->with($transfer->stripe_transfer_id, $reversal->public_id, null)
            ->andReturn($this->reversalResult($reversal));
        $planner = app(PrepaidTransferPlanner::class);

        $planner->pay($settlement);
        $planner->pay($settlement->fresh());

        $this->assertSame('succeeded', $reversal->fresh()->status);
        $this->assertSame('trr_'.$reversal->public_id, $reversal->fresh()->metadata['provider_reversal_id']);
        $this->assertSame(2700, $settlement->fresh()->paid_cents);
        $this->assertSame('paid', $settlement->fresh()->status);
        $this->assertSame(2, $settlement->operations()->count());
    }

    public function test_unconfirmed_aged_operations_keep_their_original_identity_and_reserved_capacity(): void
    {
        foreach (['transfer', 'reversal'] as $kind) {
            $settlement = $this->settlement($kind === 'reversal' ? 2700 : 5400);
            $transfer = $this->transfer($settlement);
            $operation = $kind === 'reversal' ? $this->reversal($transfer, 2700) : $transfer;
            $operation->update(['status' => 'unknown', 'created_at' => now()->subDays(2)]);
            $stripe = $this->readOnlyStripe();
            $stripe->shouldReceive($kind === 'transfer' ? 'findPrepaidTransfer' : 'findPrepaidTransferReversal')->once()->andReturnNull();
            $before = $settlement->operations()->count();

            app(PrepaidTransferPlanner::class)->pay($settlement);

            $this->assertSame('unknown', $operation->fresh()->status);
            $this->assertSame($operation->operation_key, $operation->fresh()->operation_key);
            $this->assertSame($before, $settlement->operations()->count());
            $this->assertSame('payout_pending', $settlement->fresh()->status);
            $this->assertNull($settlement->fresh()->paid_at);
        }
    }

    public function test_recent_unknown_transfer_only_replays_its_existing_key_and_exact_amount(): void
    {
        $settlement = $this->settlement();
        $transfer = $this->transfer($settlement, ['status' => 'unknown', 'stripe_transfer_id' => null]);
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldReceive('findPrepaidTransfer')->once()->andReturnNull();
        $stripe->shouldReceive('createTransferForCharge')->once()->withArgs(fn ($destination, $charge, $group, $amount, $currency, $metadata, $key) => $destination === 'acct_care_hours' && $charge === 'ch_care_hours' && $amount === 5400 && $currency === 'usd'
            && $metadata['care_hour_operation_id'] === $transfer->public_id && $key === 'care-hours:transfer:'.$transfer->public_id)
            ->andReturn(['id' => 'tr_recovered']);
        $this->app->instance(StripeClient::class, $stripe);

        app(PrepaidTransferPlanner::class)->pay($settlement);

        $this->assertSame('tr_recovered', $transfer->fresh()->stripe_transfer_id);
        $this->assertSame(1, $settlement->operations()->count());
        $this->assertSame('paid', $settlement->fresh()->status);
    }

    public function test_unmatched_provider_reversal_holds_payout_and_stale_events_cannot_clear_it(): void
    {
        $settlement = $this->settlement();
        $settlement->update(['status' => 'paid', 'paid_cents' => 5400, 'paid_at' => now()]);
        $transfer = $this->transfer($settlement);
        $this->readOnlyStripe();
        $settler = Mockery::mock(PrepaidBookingSettlementService::class);
        $settler->shouldReceive('holdForVisitState')->andReturnFalse();
        $settler->shouldNotReceive('reconcileSafely');
        $this->app->instance(PrepaidBookingSettlementService::class, $settler);
        $planner = app(PrepaidTransferPlanner::class);
        $event = $this->transferResult($transfer, 2700, [[
            'id' => 'trr_dashboard', 'amount' => 2700, 'currency' => 'usd', 'transfer' => $transfer->stripe_transfer_id, 'metadata' => [],
        ]]);

        $this->assertTrue($planner->handleProviderEvent('transfer.reversed', $event, false));
        $this->assertTrue($planner->handleProviderEvent('transfer.reversed', $event, false));
        $this->assertTrue($planner->handleProviderEvent('transfer.created', $this->transferResult($transfer), false));
        $planner->pay($settlement->fresh());

        $this->assertSame(2700, $transfer->fresh()->metadata['unmatched_reversal_cents']);
        $this->assertTrue($transfer->fresh()->metadata['provider_reversal_attention']);
        $this->assertSame('payment_attention', $settlement->fresh()->status);
        $this->assertNull($settlement->fresh()->paid_at);
        $this->assertSame(CaregiverPayoutItem::STATUS_SCHEDULED, CaregiverPayoutItem::where('care_booking_id', $settlement->care_booking_id)->firstOrFail()->status);
        $this->assertSame(1, $settlement->operations()->count());
    }

    public function test_reversal_metadata_cannot_confirm_an_operation_for_another_parent_transfer(): void
    {
        $settlement = $this->settlement();
        $transfer = $this->transfer($settlement);
        $other = $this->transfer($settlement, ['stripe_transfer_id' => 'tr_other']);
        $reversal = $this->reversal($other, 2700, ['status' => 'unknown']);
        $this->readOnlyStripe();
        $wrongParent = array_replace($this->reversalResult($reversal), ['transfer' => $transfer->stripe_transfer_id]);

        app(PrepaidTransferPlanner::class)->handleProviderEvent('transfer.reversed', $this->transferResult($transfer, 2700, [$wrongParent]), false);

        $this->assertSame('unknown', $reversal->fresh()->status);
        $this->assertTrue($transfer->fresh()->metadata['provider_reversal_attention']);
        $this->assertSame('payment_attention', $settlement->fresh()->status);
    }

    public function test_paginated_provider_event_recovers_all_owned_reversals_before_clearing_temporary_hold(): void
    {
        $settlement = $this->settlement(2700);
        $transfer = $this->transfer($settlement);
        $first = $this->reversal($transfer, 1000);
        $second = $this->reversal($transfer, 1700);
        $event = $this->transferResult($transfer, 2700, [$this->reversalResult($first)]);
        $event['reversals']['has_more'] = true;
        $stripe = $this->readOnlyStripe();
        $stripe->shouldReceive('findPrepaidTransfer')->once()->andReturn($this->transferResult($transfer, 2700,
            [$this->reversalResult($first), $this->reversalResult($second)]));
        $settler = Mockery::mock(PrepaidBookingSettlementService::class);
        $settler->shouldReceive('reconcileSafely')->once()->with($settlement->id);
        $this->app->instance(PrepaidBookingSettlementService::class, $settler);

        app(PrepaidTransferPlanner::class)->handleProviderEvent('transfer.reversed', $event, false);

        $this->assertSame('succeeded', $first->fresh()->status);
        $this->assertSame('succeeded', $second->fresh()->status);
        $this->assertFalse($transfer->fresh()->metadata['provider_reversal_attention']);
        $this->assertSame(0, $transfer->fresh()->metadata['unmatched_reversal_cents']);
    }

    public function test_authoritative_lookup_must_match_recorded_amount_destination_and_environment(): void
    {
        foreach ([['amount' => 1], ['destination' => 'acct_other'], ['livemode' => true], ['source_transaction' => 'ch_other']] as $wrong) {
            $settlement = $this->settlement();
            $transfer = $this->transfer($settlement, ['status' => 'unknown', 'created_at' => now()->subDays(2)]);
            $stripe = $this->readOnlyStripe();
            $stripe->shouldReceive('findPrepaidTransfer')->once()->andReturn(array_replace($this->transferResult($transfer), $wrong));

            app(PrepaidTransferPlanner::class)->pay($settlement);

            $this->assertSame('unknown', $transfer->fresh()->status);
            $this->assertSame('payout_pending', $settlement->fresh()->status);
            $this->assertSame(1, $settlement->operations()->count());
        }
    }

    public function test_failed_reversal_history_lookup_keeps_both_settlement_and_payout_item_on_hold(): void
    {
        $settlement = $this->settlement();
        $settlement->update(['status' => 'paid', 'paid_cents' => 5400, 'paid_at' => now()]);
        $transfer = $this->transfer($settlement);
        $event = $this->transferResult($transfer, 2700);
        $event['reversals']['has_more'] = true;
        $stripe = $this->readOnlyStripe();
        $stripe->shouldReceive('findPrepaidTransfer')->once()->andThrow(new PaymentException('Provider unavailable.'));

        try {
            app(PrepaidTransferPlanner::class)->handleProviderEvent('transfer.reversed', $event, false);
            $this->fail('Incomplete provider history must remain retryable.');
        } catch (PaymentException $error) {
            $this->assertSame('Provider unavailable.', $error->getMessage());
        }

        $this->assertSame('payment_attention', $settlement->fresh()->status);
        $this->assertNull($settlement->fresh()->paid_at);
        $this->assertTrue($transfer->fresh()->metadata['provider_reversal_attention']);
        $this->assertSame(CaregiverPayoutItem::STATUS_SCHEDULED, CaregiverPayoutItem::where('care_booking_id', $settlement->care_booking_id)->firstOrFail()->status);
    }

    public function test_connected_account_event_cannot_record_a_platform_transfer(): void
    {
        $settlement = $this->settlement();
        $transfer = $this->transfer($settlement, ['status' => 'unknown']);
        $this->expectException(PaymentException::class);
        app(PrepaidTransferPlanner::class)->handleProviderEvent('transfer.created', $this->transferResult($transfer), false, 'acct_other');
    }

    public function test_provider_lookup_paginates_transfers_and_reversals_without_writes(): void
    {
        config(['services.stripe.bypass' => false, 'services.stripe.secret' => 'sk_test_prepaid_recovery']);
        $calls = 0;
        $http = Mockery::mock(ClientInterface::class);
        $http->shouldReceive('request')->times(5)->andReturnUsing(function ($method, $url, $headers, $params) use (&$calls) {
            $this->assertSame('get', $method);
            $calls++;
            $data = match ($calls) {
                1 => [['id' => 'tr_other', 'object' => 'transfer', 'metadata' => []]],
                2 => [['id' => 'tr_found', 'object' => 'transfer', 'metadata' => ['care_hour_domain' => 'transfer', 'care_hour_operation_id' => 'operation'],
                    'reversals' => ['data' => [], 'has_more' => true]]],
                3 => [['id' => 'trr_first', 'object' => 'transfer_reversal', 'metadata' => []]],
                4 => [['id' => 'trr_second', 'object' => 'transfer_reversal', 'metadata' => []]],
                5 => [['id' => 'trr_target', 'object' => 'transfer_reversal', 'metadata' => ['care_hour_domain' => 'transfer', 'care_hour_operation_id' => 'reversal-operation']]],
            };
            $path = $calls <= 2 ? '/v1/transfers' : '/v1/transfers/tr_found/reversals';
            $this->assertStringContainsString($path, $url);
            if ($calls === 2) {
                $this->assertSame('tr_other', $params['starting_after']);
            }
            if ($calls === 4) {
                $this->assertSame('trr_first', $params['starting_after']);
            }

            return [json_encode(['object' => 'list', 'url' => $path, 'has_more' => in_array($calls, [1, 3], true), 'data' => $data]), 200, []];
        });
        ApiRequestor::setHttpClient($http);

        $stripe = new StripeClient;
        $transfer = $stripe->findPrepaidTransfer(null, 'operation', 'acct_care_hours', 100);
        $this->assertSame('tr_found', $transfer['id']);
        $this->assertSame(['trr_first', 'trr_second'], array_column($transfer['reversals']['data'], 'id'));
        $this->assertFalse($transfer['reversals']['has_more']);
        $this->assertSame('trr_target', $stripe->findPrepaidTransferReversal('tr_found', 'reversal-operation')['id']);
    }

    public function test_two_prepaid_bookings_inherit_the_same_source_charge_group_while_legacy_payload_is_unchanged(): void
    {
        config(['services.stripe.bypass' => false, 'services.stripe.secret' => 'sk_test_prepaid_groups']);
        $first = $this->settlement();
        $second = $this->settlement();
        foreach ([$first, $second] as $settlement) {
            $this->transfer($settlement, ['status' => 'pending', 'stripe_transfer_id' => null,
                'metadata' => ['revision' => $settlement->revision, 'source_transfer_group' => 'inherit']]);
        }
        $calls = [];
        $http = Mockery::mock(ClientInterface::class);
        $http->shouldReceive('request')->times(3)->andReturnUsing(function ($method, $url, $headers, $params) use (&$calls): array {
            $this->assertSame('post', $method);
            $this->assertSame('https://api.stripe.com/v1/transfers', $url);
            $calls[] = $params;
            if (count($calls) <= 2) {
                $this->assertSame('ch_care_hours', $params['source_transaction']);
                $this->assertArrayNotHasKey('transfer_group', $params);
            } else {
                $this->assertSame('existing-card-booking-group', $params['transfer_group']);
            }

            return [json_encode(['id' => 'tr_group_'.count($calls), 'object' => 'transfer', 'amount' => $params['amount'],
                'source_transaction' => $params['source_transaction'], 'transfer_group' => $params['transfer_group'] ?? 'inherited-source-group']), 200, []];
        });
        ApiRequestor::setHttpClient($http);
        app(PrepaidTransferPlanner::class)->pay($first);
        app(PrepaidTransferPlanner::class)->pay($second);
        $this->assertSame('paid', $first->fresh()->status);
        $this->assertSame('paid', $second->fresh()->status);
        $this->assertNotSame($calls[0]['metadata']['care_booking_id'], $calls[1]['metadata']['care_booking_id']);
        app(StripeClient::class)->createTransferForCharge('acct_care_hours', 'ch_legacy', 'existing-card-booking-group', 5400, 'usd', [], 'existing-card-idempotency');
    }

    private function readOnlyStripe(): \Mockery\MockInterface
    {
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldNotReceive('createTransfer');
        $stripe->shouldNotReceive('createTransferForCharge');
        $stripe->shouldNotReceive('createPrepaidTransferForCharge');
        $stripe->shouldNotReceive('createTransferReversal');
        $this->app->instance(StripeClient::class, $stripe);

        return $stripe;
    }

    private function transferResult(CareHourPaymentOperation $transfer, int $reversed = 0, array $reversals = []): array
    {
        return ['id' => $transfer->stripe_transfer_id ?: 'tr_'.$transfer->public_id, 'amount' => (int) $transfer->amount_cents,
            'destination' => $transfer->destination_account_id, 'currency' => $transfer->currency, 'livemode' => false,
            'source_transaction' => $transfer->source_charge_id, 'amount_reversed' => $reversed,
            'reversals' => ['data' => $reversals, 'has_more' => false],
            'metadata' => ['care_hour_domain' => 'transfer', 'care_hour_operation_id' => $transfer->public_id]];
    }

    private function reversalResult(CareHourPaymentOperation $reversal): array
    {
        return ['id' => 'trr_'.$reversal->public_id, 'amount' => (int) $reversal->amount_cents, 'currency' => $reversal->currency,
            'transfer' => $reversal->stripe_transfer_id,
            'metadata' => ['care_hour_domain' => 'transfer', 'care_hour_operation_id' => $reversal->public_id]];
    }

    private function transfer(CareHourSettlement $settlement, array $overrides = []): CareHourPaymentOperation
    {
        $id = (string) Str::uuid();

        return CareHourPaymentOperation::create(array_replace(['public_id' => $id, 'operation_key' => 'transfer:'.$id,
            'care_hour_booking_funding_id' => $settlement->care_hour_booking_funding_id, 'care_hour_settlement_id' => $settlement->id,
            'kind' => 'transfer', 'status' => 'succeeded', 'amount_cents' => 5400, 'currency' => 'usd',
            'stripe_transfer_id' => 'tr_'.$id, 'destination_account_id' => 'acct_care_hours', 'source_charge_id' => 'ch_care_hours',
            'metadata' => ['revision' => $settlement->revision]], $overrides));
    }

    private function reversal(CareHourPaymentOperation $transfer, int $amount, array $overrides = []): CareHourPaymentOperation
    {
        $id = (string) Str::uuid();

        return CareHourPaymentOperation::create(array_replace(['public_id' => $id, 'operation_key' => 'reversal:'.$id,
            'care_hour_booking_funding_id' => $transfer->care_hour_booking_funding_id, 'care_hour_settlement_id' => $transfer->care_hour_settlement_id,
            'kind' => 'reversal', 'status' => 'pending', 'amount_cents' => $amount, 'currency' => 'usd',
            'stripe_transfer_id' => $transfer->stripe_transfer_id], $overrides));
    }

    private function settlement(int $target = 5400): CareHourSettlement
    {
        $owner = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        CaregiverProfile::create(['user_id' => $caregiver->id, 'stripe_connect_account_id' => 'acct_care_hours',
            'stripe_charges_enabled' => true, 'stripe_payouts_enabled' => true]);
        $family = app(FamilyAccountContext::class)->account($owner);
        $account = CareHourAccount::create(['family_account_id' => $family->id, 'display_name' => 'Margaret', 'currency' => 'usd', 'active' => true]);
        $request = CareRequest::create(['family_user_id' => $owner->id, 'title' => 'Care', 'status' => 'filled',
            'address_line1' => '123 Test St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601', 'request_type' => 'one_time',
            'requested_start_at' => now()->subDay(), 'requested_end_at' => now()->subDay()->addHours(2)]);
        $application = CareRequestApplication::create(['care_request_id' => $request->id, 'caregiver_user_id' => $caregiver->id, 'status' => 'hired', 'proposed_rate' => 27]);
        $booking = CareBooking::create(['care_request_id' => $request->id, 'care_request_application_id' => $application->id,
            'family_user_id' => $owner->id, 'caregiver_user_id' => $caregiver->id, 'status' => 'completed', 'worked_minutes' => 120,
            'scheduled_start_at' => $request->requested_start_at, 'scheduled_end_at' => $request->requested_end_at, 'expected_minutes' => 120]);
        $funding = CareHourBookingFunding::create(['care_booking_id' => $booking->id, 'care_hour_account_id' => $account->id,
            'selected_by_user_id' => $owner->id, 'status' => 'ready', 'committed_at' => now()]);

        return CareHourSettlement::create(['care_booking_id' => $booking->id, 'care_hour_booking_funding_id' => $funding->id,
            'status' => 'settled', 'revision' => 1, 'worked_minutes' => 120, 'prepaid_minutes' => 120, 'package_fee_cents' => 0,
            'card_fee_cents' => 0, 'caregiver_gross_cents' => $target, 'caregiver_net_cents' => $target, 'payable_cents' => $target]);
    }
}
