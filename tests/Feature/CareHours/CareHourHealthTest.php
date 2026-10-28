<?php

namespace Tests\Feature\CareHours;

use App\Models\CareBooking;
use App\Models\CareHourAccount;
use App\Models\CareHourBookingFunding;
use App\Models\CareHourGift;
use App\Models\CareHourLot;
use App\Models\CareHourPaymentOperation;
use App\Models\CareHourPurchase;
use App\Models\CareHourSettlement;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\User;
use App\Services\CareHours\CareHourHealthService;
use App\Services\CareHours\CareHourPaymentGateway;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class CareHourHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        config(['care_hours.enabled' => false, 'care_hours.sales_enabled' => false, 'care_hours.gifts_enabled' => false]);
        Notification::fake();
        // An unexpected provider call is a test failure. The checker should never resolve this service.
        $this->app->instance(CareHourPaymentGateway::class, Mockery::mock(CareHourPaymentGateway::class));
    }

    public function test_normal_unclaimed_gift_future_care_and_open_checkout_are_healthy(): void
    {
        $gift = $this->purchase(['status' => 'paid', 'paid_at' => now()->subDays(90)]);
        CareHourGift::create(['care_hour_purchase_id' => $gift->id, 'ready_at' => now()->subDays(90),
            'code_hash' => hash('sha256', 'private-gift-code'), 'encrypted_code' => 'private-gift-code']);
        $this->purchase(['created_at' => now()->subHours(23), 'checkout_requested_at' => now()->subHours(23)]);
        $funding = $this->funding();
        $this->operation($funding, ['kind' => 'shortfall', 'status' => 'authorized', 'created_at' => now()->subDays(2)]);
        $this->operation($funding, ['kind' => 'refund', 'status' => 'failed', 'created_at' => now()->subDays(2)]);
        $this->operation($funding, ['kind' => 'transfer', 'status' => 'succeeded', 'created_at' => now()->subDays(2)]);

        $this->assertSame(0, Artisan::call('care-hours:health', ['--json' => true]));
        $result = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('healthy', $result['status']);
        $this->assertSame(1, $result['checks']['purchase_checkout']['count']);
        $this->assertSame(0, $result['checks']['purchase_fulfillment']['count']);
        $this->assertSame(0, $result['checks']['settlements']['count']);
        $this->assertSame(0, $result['checks']['payment_releases']['count']);
        Notification::assertNothingSent();
    }

    public function test_aged_persisted_liabilities_fail_even_when_every_flag_is_off_and_retries_touch_records(): void
    {
        $old = now()->subHours(26);
        $purchase = $this->purchase(['status' => 'recovery', 'paid_at' => $old, 'created_at' => $old]);
        $this->purchase(['status' => 'issued', 'paid_at' => $old, 'issued_at' => $old, 'processing_fee_cents' => null]);
        $this->purchase(['status' => 'held', 'paid_at' => $old, 'created_at' => $old]);
        $this->purchase(['status' => 'recovery', 'correction_state' => 'failed', 'correction_requested_at' => $old]);
        $this->purchase(['created_at' => $old, 'checkout_requested_at' => $old]);
        $funding = $this->funding(['status' => 'released']);
        CareHourLot::create(['care_hour_account_id' => $funding->care_hour_account_id, 'care_hour_purchase_id' => $purchase->id,
            'total_minutes' => 600, 'paid_minutes' => 600, 'status' => 'held', 'issued_at' => $old, 'created_at' => $old]);
        $settlement = CareHourSettlement::create(['care_booking_id' => $funding->care_booking_id,
            'care_hour_booking_funding_id' => $funding->id, 'status' => 'payout_pending', 'approved_at' => $old,
            'caregiver_net_cents' => 6000, 'paid_cents' => 0, 'created_at' => $old, 'last_error' => 'private-provider-error']);
        $this->operation($funding, ['kind' => 'shortfall', 'status' => 'captured', 'captured_cents' => 3100, 'refunded_cents' => 0, 'created_at' => $old]);
        $this->operation($funding, ['kind' => 'shortfall', 'status' => 'checkout', 'created_at' => $old]);
        $this->operation($funding, ['kind' => 'refund', 'status' => 'unknown', 'created_at' => $old]);
        DB::table('care_hour_purchase_notices')->insert(['care_hour_purchase_id' => $purchase->id, 'kind' => 'receipt',
            'recipient' => 'private-buyer@example.test', 'dedupe_key' => 'private-notice', 'processing_at' => now(),
            'attempts' => 999, 'last_error' => 'private-provider-error', 'created_at' => $old, 'updated_at' => now()]);
        $this->webhook('failed', $old);
        $this->visitNotice('failed', $old);
        $purchase->touch();
        $settlement->touch();
        $before = $this->snapshot();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->assertSame(1, Artisan::call('care-hours:health', ['--json' => true]));
        $output = Artisan::output();
        $result = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('attention', $result['status']);
        $this->assertSame(['enabled' => false, 'sales_enabled' => false, 'gifts_enabled' => false], $result['flags']);
        foreach ($result['checks'] as $key => $check) {
            $this->assertGreaterThan(0, $check['overdue_count'], $key.' must detect persisted unresolved work.');
        }
        foreach (['private-buyer', 'private-gift-code', 'private-provider-error', 'cs_private', 'pi_private'] as $private) {
            $this->assertStringNotContainsString($private, $output);
        }
        foreach ($queries as $query) {
            $this->assertMatchesRegularExpression('/^\s*(select|pragma)\b/i', $query, 'Health command must perform only reads.');
        }
        $this->assertSame($before, $this->snapshot());
        Notification::assertNothingSent();
    }

    public function test_grace_period_uses_original_timestamp_and_threshold_is_configurable(): void
    {
        $purchase = $this->purchase(['status' => 'paid', 'paid_at' => now()->subMinutes(29)]);
        $service = app(CareHourHealthService::class);
        $this->assertSame('healthy', $service->inspect()['status']);
        $this->travel(2)->minutes();
        $purchase->touch();
        $this->assertSame('attention', $service->inspect()['status']);
        $this->assertSame('healthy', $service->inspect(60)['status']);
    }

    public function test_a_paid_gift_without_a_ready_code_is_fulfillment_backlog(): void
    {
        $purchase = $this->purchase(['status' => 'paid', 'paid_at' => now()->subHour()]);
        $gift = CareHourGift::create(['care_hour_purchase_id' => $purchase->id]);
        $this->assertSame(1, app(CareHourHealthService::class)->inspect()['checks']['purchase_fulfillment']['overdue_count']);
        $gift->update(['ready_at' => now()]);
        $this->assertSame(1, app(CareHourHealthService::class)->inspect()['checks']['purchase_fulfillment']['overdue_count']);
    }

    public function test_schedule_change_releases_and_external_transfer_reversals_remain_visible(): void
    {
        $funding = $this->funding();
        $release = $this->operation($funding, ['kind' => 'shortfall', 'status' => 'authorized', 'created_at' => now()->subDay(),
            'metadata' => ['release_requested' => true]]);
        $transfer = $this->operation($funding, ['kind' => 'transfer', 'status' => 'succeeded', 'created_at' => now()->subDay(),
            'metadata' => ['provider_reversal_attention' => true]]);
        $result = app(CareHourHealthService::class)->inspect();
        $this->assertSame(1, $result['checks']['payment_releases']['overdue_count']);
        $this->assertSame(1, $result['checks']['payment_operations']['overdue_count']);
        $release->update(['status' => 'cancelled']);
        $transfer->update(['metadata' => ['provider_reversal_attention' => false]]);
        $this->assertSame('healthy', app(CareHourHealthService::class)->inspect()['status']);
    }

    public function test_partial_schema_returns_unknown_without_querying_missing_fields(): void
    {
        Schema::rename('care_hour_gifts', 'health_test_hidden_gifts');
        try {
            $this->assertSame(2, Artisan::call('care-hours:health', ['--json' => true]));
            $result = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('unknown', $result['status']);
            $this->assertContains('care_hour_gifts', $result['schema']['missing']);
            $this->assertSame([], $result['checks']);
        } finally {
            Schema::rename('health_test_hidden_gifts', 'care_hour_gifts');
        }
    }

    public function test_partial_column_schema_returns_unknown_without_assuming_empty_backlog(): void
    {
        Schema::table('care_hour_purchases', fn ($table) => $table->renameColumn('processing_fee_cents', 'health_test_hidden_fee'));
        try {
            $this->assertSame(2, Artisan::call('care-hours:health', ['--json' => true]));
            $result = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
            $this->assertContains('care_hour_purchases.processing_fee_cents', $result['schema']['missing']);
            $this->assertSame([], $result['checks']);
        } finally {
            Schema::table('care_hour_purchases', fn ($table) => $table->renameColumn('health_test_hidden_fee', 'processing_fee_cents'));
        }
    }

    public function test_missing_request_preferences_are_unknown_even_when_sales_are_disabled(): void
    {
        Schema::rename('care_hour_request_preferences', 'health_test_hidden_preferences');
        try {
            $this->assertSame(2, Artisan::call('care-hours:health', ['--json' => true]));
            $result = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('unknown', $result['status']);
            $this->assertContains('care_hour_request_preferences', $result['schema']['missing']);
            $this->assertSame([], $result['checks']);
            Notification::assertNothingSent();
        } finally {
            Schema::rename('health_test_hidden_preferences', 'care_hour_request_preferences');
        }
    }

    public function test_incomplete_request_preferences_cannot_report_healthy(): void
    {
        Schema::table('care_hour_request_preferences', fn ($table) => $table->renameColumn('enabled', 'health_test_hidden_enabled'));
        try {
            $this->assertSame(2, Artisan::call('care-hours:health', ['--json' => true]));
            $result = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('unknown', $result['status']);
            $this->assertContains('care_hour_request_preferences.enabled', $result['schema']['missing']);
            $this->assertSame([], $result['checks']);
            Notification::assertNothingSent();
        } finally {
            Schema::table('care_hour_request_preferences', fn ($table) => $table->renameColumn('health_test_hidden_enabled', 'enabled'));
        }
    }

    public function test_paid_label_cannot_hide_an_underfunded_caregiver_settlement(): void
    {
        $funding = $this->funding();
        $settlement = CareHourSettlement::create(['care_booking_id' => $funding->care_booking_id,
            'care_hour_booking_funding_id' => $funding->id, 'status' => 'paid', 'approved_at' => now()->subHour(),
            'caregiver_net_cents' => 6000, 'paid_cents' => 5999]);
        $this->assertSame(1, app(CareHourHealthService::class)->inspect()['checks']['settlements']['overdue_count']);
        $settlement->update(['paid_cents' => 6000]);
        $this->assertSame('healthy', app(CareHourHealthService::class)->inspect()['status']);
    }

    public function test_shared_webhook_backlog_includes_failed_and_processing_events_without_reading_payloads(): void
    {
        foreach (['received', 'processing', 'failed', 'processed'] as $status) {
            $this->webhook($status, now()->subHour());
        }
        $this->webhook('received', now()->subMinutes(5));
        $this->assertSame(1, Artisan::call('care-hours:health', ['--json' => true]));
        $result = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(4, $result['checks']['shared_stripe_webhook_inbox']['count']);
        $this->assertSame(3, $result['checks']['shared_stripe_webhook_inbox']['overdue_count']);
        $this->assertStringContainsString('card and prepaid', $result['checks']['shared_stripe_webhook_inbox']['age_basis']);
        $this->assertStringNotContainsString('undecryptable-private-payload', Artisan::output());
        DB::table('stripe_webhook_events')->update(['status' => 'processed']);
        $this->assertSame('healthy', app(CareHourHealthService::class)->inspect()['status']);
    }

    public function test_visit_notice_health_includes_failed_sent_at_rows_but_honors_suppression_and_event_scope(): void
    {
        foreach (['queued', 'pending', 'failed', 'suppressed', 'sent'] as $status) {
            $this->visitNotice($status, now()->subHour());
        }
        $this->visitNotice('queued', now()->subMinutes(5));
        $this->visitNotice('failed', now()->subHour(), ['event_key' => 'unrelated_card_event']);
        $this->visitNotice('enabled_stub_pending_provider', now()->subHour(), ['channel' => 'sms']);
        $result = app(CareHourHealthService::class)->inspect();
        $this->assertSame(4, $result['checks']['visit_notices']['count']);
        $this->assertSame(3, $result['checks']['visit_notices']['overdue_count']);
        DB::table('marketplace_notification_deliveries')->where('event_key', 'care_hours_payment_attention')->update(['status' => 'sent']);
        $this->assertSame('healthy', app(CareHourHealthService::class)->inspect()['status']);
        Notification::assertNothingSent();
    }

    public function test_prepaid_settlement_and_payout_notice_backlogs_exclude_ordinary_card_payouts(): void
    {
        $this->visitNotice('failed', now()->subHour(), ['event_key' => 'care_hours_visit_settled']);
        $this->visitNotice('queued', now()->subHour(), ['event_key' => 'payout_transferred',
            'payload' => json_encode(['care_hour_settlement_id' => 123])]);
        $this->visitNotice('failed', now()->subHour(), ['event_key' => 'payout_transferred',
            'payload' => json_encode(['care_booking_id' => 456])]);
        $this->visitNotice('suppressed', now()->subHour(), ['event_key' => 'care_hours_visit_settled']);
        $result = app(CareHourHealthService::class)->inspect();
        $this->assertSame('attention', $result['status']);
        $this->assertSame(2, $result['checks']['visit_notices']['count']);
        $this->assertSame(2, $result['checks']['visit_notices']['overdue_count']);
        Notification::assertNothingSent();
    }

    public function test_shared_webhook_schema_is_required_to_avoid_false_health_before_purchase_is_recorded(): void
    {
        Schema::rename('stripe_webhook_events', 'health_test_hidden_webhooks');
        try {
            $this->assertSame(2, Artisan::call('care-hours:health', ['--json' => true]));
            $result = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
            $this->assertContains('stripe_webhook_events', $result['schema']['missing']);
            $this->assertSame([], $result['checks']);
        } finally {
            Schema::rename('health_test_hidden_webhooks', 'stripe_webhook_events');
        }
    }

    public function test_absent_schema_cannot_report_healthy_even_with_feature_disabled(): void
    {
        Schema::shouldReceive('hasTable')->andReturn(false);
        $this->assertSame(2, Artisan::call('care-hours:health', ['--json' => true]));
        $result = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('unknown', $result['status']);
        $this->assertSame('incomplete', $result['schema']['status']);
    }

    public function test_database_errors_are_unknown_and_do_not_expose_error_details(): void
    {
        $health = Mockery::mock(CareHourHealthService::class);
        $health->shouldReceive('inspect')->once()->andThrow(new RuntimeException('private database user/password'));
        $this->app->instance(CareHourHealthService::class, $health);
        $this->assertSame(2, Artisan::call('care-hours:health', ['--json' => true]));
        $this->assertSame('inspection_failed', json_decode(Artisan::output(), true)['reason']);
        $this->assertStringNotContainsString('password', Artisan::output());
    }

    public function test_invalid_thresholds_return_machine_readable_unknown_without_running_checks(): void
    {
        $health = Mockery::mock(CareHourHealthService::class);
        $health->shouldNotReceive('inspect');
        $this->app->instance(CareHourHealthService::class, $health);
        foreach ([['--max-age-minutes' => 'junk'], ['--max-age-minutes' => '0'], ['--checkout-age-minutes' => '30']] as $options) {
            $this->assertSame(2, Artisan::call('care-hours:health', $options + ['--json' => true]));
            $this->assertSame('invalid_threshold', json_decode(Artisan::output(), true)['reason']);
        }
    }

    private function purchase(array $attributes = []): CareHourPurchase
    {
        return CareHourPurchase::create(array_replace([
            'public_id' => (string) Str::uuid(), 'request_key' => Str::random(64), 'buyer_email' => 'private-buyer@example.test',
            'buyer_name' => 'Private Buyer', 'paid_minutes' => 600, 'bonus_minutes' => 0, 'total_minutes' => 600,
            'amount_cents' => 31000, 'processing_fee_cents' => 929, 'currency' => 'usd', 'status' => 'pending',
            'terms_version' => 'test', 'quote' => [], 'terms_accepted_at' => now(), 'success_url' => 'https://example.test/return',
            'cancel_url' => 'https://example.test/cancel', 'stripe_checkout_session_id' => 'cs_private_'.Str::random(12),
            'created_at' => now(), 'updated_at' => now(),
        ], $attributes));
    }

    private function funding(array $attributes = []): CareHourBookingFunding
    {
        $owner = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $family = app(FamilyAccountContext::class)->account($owner);
        $account = CareHourAccount::create(['family_account_id' => $family->id, 'display_name' => 'Private Recipient', 'currency' => 'usd']);
        $request = CareRequest::create(['family_user_id' => $owner->id, 'title' => 'Care', 'status' => 'filled',
            'address_line1' => '123 Test St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601', 'request_type' => 'one_time',
            'requested_start_at' => now()->addWeek(), 'requested_end_at' => now()->addWeek()->addHours(2)]);
        $application = CareRequestApplication::create(['care_request_id' => $request->id, 'caregiver_user_id' => $caregiver->id, 'status' => 'hired', 'proposed_rate' => 30]);
        $booking = CareBooking::create(['care_request_id' => $request->id, 'care_request_application_id' => $application->id,
            'family_user_id' => $owner->id, 'caregiver_user_id' => $caregiver->id, 'status' => 'scheduled',
            'scheduled_start_at' => $request->requested_start_at, 'scheduled_end_at' => $request->requested_end_at, 'expected_minutes' => 120]);

        return CareHourBookingFunding::create(array_replace(['care_booking_id' => $booking->id, 'care_hour_account_id' => $account->id,
            'status' => 'ready', 'reserved_minutes' => 120, 'committed_at' => now()->subDays(2), 'created_at' => now()->subDays(2)], $attributes));
    }

    private function operation(CareHourBookingFunding $funding, array $attributes): CareHourPaymentOperation
    {
        return CareHourPaymentOperation::create(array_replace(['care_hour_booking_funding_id' => $funding->id,
            'public_id' => (string) Str::uuid(), 'operation_key' => Str::random(64), 'kind' => 'shortfall', 'status' => 'pending',
            'amount_cents' => 3100, 'currency' => 'usd', 'created_at' => now()], $attributes));
    }

    private function snapshot(): array
    {
        return collect(['care_hour_purchases', 'care_hour_gifts', 'care_hour_settlements', 'care_hour_payment_operations',
            'care_hour_purchase_notices', 'care_hour_booking_fundings', 'care_hour_lots', 'care_hour_ledger_entries',
            'stripe_webhook_events', 'marketplace_notification_deliveries'])
            ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
    }

    private function webhook(string $status, mixed $createdAt): void
    {
        // Deliberately not encrypted: health must aggregate without loading or decrypting event bodies.
        DB::table('stripe_webhook_events')->insert(['stripe_event_id' => 'evt_'.Str::random(20), 'type' => 'payment_intent.succeeded',
            'status' => $status, 'payload' => 'undecryptable-private-payload', 'attempts' => 99,
            'created_at' => $createdAt, 'updated_at' => now()]);
    }

    private function visitNotice(string $status, mixed $createdAt, array $attributes = []): void
    {
        DB::table('marketplace_notification_deliveries')->insert(array_replace(['user_id' => User::factory()->create()->id,
            'event_key' => 'care_hours_payment_attention', 'channel' => 'email', 'status' => $status,
            'payload' => json_encode(['email' => 'private-buyer@example.test', 'provider_error' => 'private-provider-error']),
            'sent_at' => $status === 'failed' ? now() : null, 'created_at' => $createdAt, 'updated_at' => now()], $attributes));
    }
}
