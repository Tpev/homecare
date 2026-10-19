<?php

namespace Tests\Feature\Payments;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareBooking;
use App\Models\CareBookingEvent;
use App\Models\CareBookingPayment;
use App\Models\CareBookingPaymentAttempt;
use App\Models\CaregiverProfile;
use App\Models\CareRequest;
use App\Models\FamilyAccount;
use App\Models\FamilyAccountMember;
use App\Models\User;
use App\Services\Payments\BookingPaymentV2Service;
use App\Services\Payments\Oct3PaymentRecoveryService;
use App\Services\Payments\StripeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class Oct3PaymentRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private const OLD_INTENT = 'pi_3UK1bF6ewFB76RHF0L3C0G6p';

    private const NEW_INTENT = 'pi_oct3_recovery_test';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.timezone', 'America/New_York');
        config()->set('services.stripe.bypass', false);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-19 13:08:09', 'America/New_York'));
        Notification::fake();
        $this->seedVisit();
    }

    public function test_preview_verifies_exact_quote_without_writes_or_provider_calls(): void
    {
        $this->instance(StripeClient::class, Mockery::mock(StripeClient::class));
        $before = $this->databaseSnapshot();

        $preview = app(Oct3PaymentRecoveryService::class)->preview();

        $this->assertSame(239, $preview['booking_id']);
        $this->assertSame(120, $preview['payment_id']);
        $this->assertSame(721, $preview['worked_minutes']);
        $this->assertSame(37252, $preview['target_charge_cents']);
        $this->assertSame(32445, $preview['caregiver_gross_cents']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $preview['expected_state']);
        $this->assertSame($before, $this->databaseSnapshot());
    }

    public function test_command_defaults_to_read_only_preview_without_admin_or_provider_access(): void
    {
        $this->instance(StripeClient::class, Mockery::mock(StripeClient::class));
        $before = $this->databaseSnapshot();

        $this->artisan('homecare:recover-oct3-payment')
            ->expectsOutputToContain('"target_charge_cents": 37252')
            ->expectsOutputToContain('READ-ONLY PREVIEW')
            ->assertSuccessful();

        $this->assertSame($before, $this->databaseSnapshot());
    }

    public function test_command_apply_without_valid_admin_stops_before_provider_access(): void
    {
        $this->instance(StripeClient::class, Mockery::mock(StripeClient::class));
        $before = $this->databaseSnapshot();

        $this->artisan('homecare:recover-oct3-payment', ['--apply' => true])
            ->assertFailed();

        $this->assertSame($before, $this->databaseSnapshot());
    }

    public function test_recovery_charges_exact_amount_and_preserves_all_recorded_visit_fields(): void
    {
        $stripe = $this->mockProvider();
        $key = null;
        $stripe->shouldReceive('createManualAuthorization')->once()
            ->withArgs(function ($booking, $customer, $method, $amount, $currency, $idempotencyKey) use (&$key) {
                $key = $idempotencyKey;

                return $booking->id === 239 && $customer === 'cus_UzbLWUlGKpPlYP'
                    && $method === 'pm_1U5lyG6ewFB76RHFVkgytmCg' && $amount === 37252
                    && $currency === 'usd' && filled($idempotencyKey);
            })->andReturn($this->authorization());
        $this->mockSuccessfulCapture();
        $before = (array) DB::table('care_bookings')->find(239);
        $service = app(Oct3PaymentRecoveryService::class);
        $hash = $service->preview()['expected_state'];
        $requestId = (string) Str::uuid();

        $service->apply($this->admin, $hash, $requestId, 'Reviewed October 3 visit and expired payment.', 'auto-approval', null, true);

        $after = (array) DB::table('care_bookings')->find(239);
        $allowed = ['family_confirmed_at', 'family_confirmed_by_user_id', 'updated_at'];
        $this->assertSame(Arr::except($before, $allowed), Arr::except($after, $allowed));
        $this->assertNotNull($after['family_confirmed_at']);
        $this->assertNull($after['family_confirmed_by_user_id']);
        $this->assertDatabaseHas('care_booking_payments', [
            'id' => 120, 'stripe_payment_intent_id' => self::NEW_INTENT,
            'amount_authorized_cents' => 37252, 'amount_captured_cents' => 37252,
        ]);
        $this->assertDatabaseHas('care_booking_payment_attempts', [
            'care_booking_payment_id' => 120, 'stripe_payment_intent_id' => self::NEW_INTENT,
            'amount_cents' => 37252,
        ]);
        $this->assertSame($requestId, data_get(CareBookingPayment::findOrFail(120)->metadata, 'oct3_payment_recovery.request_id'));
        $this->assertNotEmpty($key);

        // Repeating the reviewed request must not authorize or capture twice.
        $service->apply($this->admin, $hash, $requestId, 'Reviewed October 3 visit and expired payment.', 'auto-approval', null, true);
        $this->assertDatabaseCount('care_booking_payment_attempts', 2);
        Notification::assertNothingSent();
    }

    public function test_stale_preview_is_rejected_before_provider_access(): void
    {
        $this->instance(StripeClient::class, Mockery::mock(StripeClient::class));
        $service = app(Oct3PaymentRecoveryService::class);
        $hash = $service->preview()['expected_state'];
        DB::table('care_bookings')->where('id', 239)->update(['updated_at' => now()]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The preview is stale.');
        $service->apply($this->admin, $hash, (string) Str::uuid(), 'Reviewed visit.', 'auto-approval', null, true);
    }

    public function test_real_payment_pipeline_preserves_721_minutes_and_records_exact_charge_and_earnings(): void
    {
        $stripe = $this->mockProvider();
        $stripe->shouldReceive('createManualAuthorization')->once()->andReturn($this->authorization());
        $financials = [
            'id' => self::NEW_INTENT, 'status' => 'succeeded', 'amount_received' => 37252,
            'latest_charge_id' => 'ch_oct3_recovery', 'balance_transaction_id' => 'txn_oct3_recovery',
            'fee_finalized' => true, 'processing_fee_cents' => 1110,
        ];
        $stripe->shouldReceive('capturePaymentIntent')->once()
            ->withArgs(fn ($intent, $amount, $key) => $intent === self::NEW_INTENT && $amount === 37252
                && filled($key) && $key !== 'payment-v2:120:capture-primary:37252')
            ->andReturn($financials);
        $stripe->shouldReceive('retrievePaymentIntentFinancials')->with(self::NEW_INTENT, 37252)
            ->andReturn(['id' => self::NEW_INTENT, 'status' => 'requires_capture', 'amount_received' => 0], $financials);
        $stripe->shouldReceive('createTransferForCharge')->once()
            ->withArgs(fn ($account, $charge, $group, $amount, $currency, $metadata, $key) => $account === 'acct_1UIUxkGE0jQOqi7z' && $charge === 'ch_oct3_recovery'
                && $amount === 31335 && $currency === 'usd' && filled($key))
            ->andReturn(['id' => 'tr_oct3_recovery']);
        $before = (array) DB::table('care_bookings')->find(239);
        $service = app(Oct3PaymentRecoveryService::class);
        $hash = $service->preview()['expected_state'];
        $requestId = (string) Str::uuid();

        $service->apply($this->admin, $hash, $requestId, 'Reviewed October 3 timesheet and Stripe history.', 'auto-approval', null, true);

        $this->assertDatabaseHas('care_booking_payments', [
            'id' => 120, 'status' => 'transferred', 'worked_minutes' => 721,
            'amount_captured_cents' => 37252, 'family_care_amount_cents' => 36050,
            'family_processing_fee_cents' => 1202, 'caregiver_gross_amount_cents' => 32445,
            'stripe_processing_fee_cents' => 1110, 'caregiver_amount_cents' => 31335,
            'stripe_transfer_id' => 'tr_oct3_recovery',
        ]);
        $this->assertDatabaseHas('care_booking_payment_operations', [
            'care_booking_payment_id' => 120, 'type' => 'charge', 'status' => 'succeeded',
            'amount_cents' => 37252, 'stripe_parent_object_id' => self::NEW_INTENT,
        ]);
        $this->assertDatabaseHas('caregiver_payout_items', [
            'care_booking_id' => 239, 'care_booking_payment_id' => 120, 'status' => 'paid', 'amount' => 313.35,
        ]);
        $this->assertSame(Arr::except($before, ['family_confirmed_at', 'family_confirmed_by_user_id', 'updated_at']),
            Arr::except((array) DB::table('care_bookings')->find(239), ['family_confirmed_at', 'family_confirmed_by_user_id', 'updated_at']));

        $service->apply($this->admin, $hash, $requestId, 'Reviewed October 3 timesheet and Stripe history.', 'auto-approval', null, true);
        $this->assertSame(1, DB::table('care_booking_payment_operations')->where('type', 'charge')->count());
        $this->assertSame(1, DB::table('care_booking_payment_operations')->where('type', 'transfer')->count());
        Notification::assertNothingSent();
    }

    #[DataProvider('invalidVisitStates')]
    public function test_unsafe_visit_or_payment_state_blocks_recovery(string $table, array $changes): void
    {
        $this->instance(StripeClient::class, Mockery::mock(StripeClient::class));
        DB::table($table)->where('id', $table === 'care_bookings' ? 239 : 120)->update($changes);

        $this->expectException(ValidationException::class);
        app(Oct3PaymentRecoveryService::class)->preview();
    }

    public static function invalidVisitStates(): array
    {
        return [
            'different minutes' => ['care_bookings', ['worked_minutes' => 722]],
            'rounded timestamps' => ['care_bookings', ['started_at' => '2026-10-03 19:09:00']],
            'different pricing' => ['care_bookings', ['family_care_rate_cents' => 3100]],
            'dispute' => ['care_bookings', ['dispute_status' => 'open']],
            'missing timesheet' => ['care_bookings', ['timesheet_submitted_at' => null]],
            'missing terms' => ['care_bookings', ['caregiver_terms_accepted_at' => null]],
            'captured already' => ['care_booking_payments', ['amount_captured_cents' => 37252]],
            'prepaid hold' => ['care_booking_payments', ['metadata' => '{"prepaid_visit_recovery":{"state":"held"}}']],
        ];
    }

    public function test_pending_time_correction_blocks_recovery(): void
    {
        $this->instance(StripeClient::class, Mockery::mock(StripeClient::class));
        DB::table('care_booking_time_corrections')->insert([
            'client_request_id' => (string) Str::uuid(), 'care_booking_id' => 239,
            'requester_user_id' => 349, 'family_user_id' => 335, 'version' => 1,
            'status' => 'pending_family_review', 'reason_code' => 'incorrect_time',
            'explanation' => 'Requested different hours.', 'proposed_started_at' => '2026-10-03 19:00:00',
            'proposed_completed_at' => '2026-10-04 07:00:00', 'proposed_worked_minutes' => 720,
            'original_snapshot' => '{}', 'financial_preview' => '{}', 'submitted_at' => now(),
        ]);

        $this->expectException(ValidationException::class);
        app(Oct3PaymentRecoveryService::class)->preview();
    }

    public function test_provider_confirmation_is_required_before_any_provider_call(): void
    {
        $this->instance(StripeClient::class, Mockery::mock(StripeClient::class));
        $service = app(Oct3PaymentRecoveryService::class);
        $hash = $service->preview()['expected_state'];

        $this->expectException(ValidationException::class);
        $service->apply($this->admin, $hash, (string) Str::uuid(), 'Reviewed visit.');
    }

    public function test_service_requires_an_explicit_approval_basis(): void
    {
        $this->instance(StripeClient::class, Mockery::mock(StripeClient::class));
        $service = app(Oct3PaymentRecoveryService::class);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Choose an explicit approval basis');
        $service->apply($this->admin, $service->preview()['expected_state'], (string) Str::uuid(),
            'Reviewed October 3 visit.', providerVerified: true);
    }

    #[DataProvider('invalidProviderIdentities')]
    public function test_both_provider_intents_must_match_live_usd_customer_identity(string $stage, array $changes): void
    {
        $stripe = $this->mockProvider('requires_capture', $stage === 'old' ? $changes : [], $stage === 'new' ? $changes : []);
        $v2 = Mockery::mock(BookingPaymentV2Service::class);
        if ($stage === 'new') {
            $stripe->shouldReceive('createManualAuthorization')->once()->andReturn($this->authorization());
            $v2->shouldReceive('recordAuthorization')->once();
        }
        $this->instance(BookingPaymentV2Service::class, $v2);
        $service = app(Oct3PaymentRecoveryService::class);
        try {
            $service->apply($this->admin, $service->preview()['expected_state'], (string) Str::uuid(),
                'Reviewed October 3 visit.', 'auto-approval', null, true);
            $this->fail('Provider identity mismatch must stop recovery.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Stripe', $exception->getMessage());
            $this->assertNull(CareBooking::findOrFail(239)->family_confirmed_at);
            $this->assertNull(CareBookingPayment::findOrFail(120)->captured_at);
            $this->assertDatabaseCount('caregiver_payout_items', 0);
            $this->assertDatabaseCount('care_booking_payment_attempts', $stage === 'new' ? 2 : 1);
        }
    }

    public static function invalidProviderIdentities(): array
    {
        return [
            'old wrong customer' => ['old', ['customer_id' => 'cus_unrelated']],
            'old wrong currency' => ['old', ['currency' => 'eur']],
            'old test mode' => ['old', ['livemode' => false]],
            'new wrong customer' => ['new', ['customer_id' => 'cus_unrelated']],
            'new wrong currency' => ['new', ['currency' => 'eur']],
            'new test mode' => ['new', ['livemode' => false]],
        ];
    }

    public function test_family_approval_records_the_active_family_member_after_capture(): void
    {
        $stripe = $this->mockProvider();
        $stripe->shouldReceive('createManualAuthorization')->once()->andReturn($this->authorization());
        $this->mockSuccessfulCapture();
        $service = app(Oct3PaymentRecoveryService::class);

        $service->apply($this->admin, $service->preview()['expected_state'], (string) Str::uuid(),
            'Mary approved the reviewed 721 minute timesheet.', 'family', 378, true);

        $this->assertSame(378, CareBooking::findOrFail(239)->family_confirmed_by_user_id);
    }

    public function test_family_approval_cannot_be_attributed_to_an_unrelated_family_account(): void
    {
        $outsider = User::factory()->create(['role' => 'family']);
        $this->instance(StripeClient::class, Mockery::mock(StripeClient::class));
        $service = app(Oct3PaymentRecoveryService::class);

        $this->expectException(ValidationException::class);
        $service->apply($this->admin, $service->preview()['expected_state'], (string) Str::uuid(),
            'Reviewed the family approval.', 'family', $outsider->id, true);
    }

    public function test_auto_approval_cannot_skip_the_existing_twenty_four_hour_window(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-04 12:00:00', 'America/New_York'));
        $this->instance(StripeClient::class, Mockery::mock(StripeClient::class));
        $service = app(Oct3PaymentRecoveryService::class);
        $preview = $service->preview();
        $this->assertFalse($preview['auto_approval_eligible']);

        $this->expectException(ValidationException::class);
        $service->apply($this->admin, $preview['expected_state'], (string) Str::uuid(), 'Reviewed October 3 visit.', 'auto-approval', null, true);
    }

    public function test_failed_capture_preserves_timesheet_and_does_not_record_approval(): void
    {
        $stripe = $this->mockProvider();
        $stripe->shouldReceive('createManualAuthorization')->once()->andReturn($this->authorization());
        $v2 = Mockery::mock(BookingPaymentV2Service::class);
        $v2->shouldReceive('recordAuthorization')->once();
        $v2->shouldReceive('capture')->once()->andThrow(new PaymentException('Capture result is unknown.'));
        $this->instance(BookingPaymentV2Service::class, $v2);
        $service = app(Oct3PaymentRecoveryService::class);
        $before = DB::table('care_bookings')->find(239);

        try {
            $service->apply($this->admin, $service->preview()['expected_state'], (string) Str::uuid(), 'Reviewed October 3 visit.', 'auto-approval', null, true);
            $this->fail('Expected capture failure.');
        } catch (PaymentException) {
            $this->assertEquals($before, DB::table('care_bookings')->find(239));
            $this->assertSame('capture_unknown', data_get(CareBookingPayment::findOrFail(120)->metadata, 'oct3_payment_recovery.state'));
        }
    }

    public function test_old_intent_must_be_uncaptured_and_canceled_before_new_authorization(): void
    {
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldReceive('retrievePaymentIntent')->with(self::OLD_INTENT)->andReturn([
            'id' => self::OLD_INTENT, 'status' => 'succeeded', 'amount_received' => 37252,
            'customer_id' => 'cus_UzbLWUlGKpPlYP', 'currency' => 'usd', 'livemode' => true,
        ]);
        $this->instance(StripeClient::class, $stripe);
        $service = app(Oct3PaymentRecoveryService::class);

        $this->expectException(ValidationException::class);
        $service->apply($this->admin, $service->preview()['expected_state'], (string) Str::uuid(), 'Reviewed visit.', 'auto-approval', null, true);
    }

    #[DataProvider('actionRequiredStatuses')]
    public function test_decline_or_authentication_requirement_does_not_capture_or_approve(string $status): void
    {
        $stripe = $this->mockProvider($status);
        $stripe->shouldReceive('createManualAuthorization')->once()->andReturn($this->authorization($status));
        $v2 = Mockery::mock(BookingPaymentV2Service::class);
        $v2->shouldReceive('recordAuthorization')->once();
        $this->instance(BookingPaymentV2Service::class, $v2);
        $service = app(Oct3PaymentRecoveryService::class);

        try {
            $service->apply($this->admin, $service->preview()['expected_state'], (string) Str::uuid(), 'Reviewed visit.', 'auto-approval', null, true);
        } catch (PaymentException|ValidationException $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }

        $this->assertNull(CareBooking::findOrFail(239)->family_confirmed_at);
        $this->assertNull(CareBookingPayment::findOrFail(120)->captured_at);
        $this->assertDatabaseCount('caregiver_payout_items', 0);
    }

    public static function actionRequiredStatuses(): array
    {
        return [['requires_payment_method'], ['requires_action']];
    }

    public function test_unknown_authorization_retries_same_key_and_rejects_another_request_id(): void
    {
        $stripe = $this->mockProvider();
        $keys = [];
        $stripe->shouldReceive('createManualAuthorization')->twice()->andReturnUsing(function (...$args) use (&$keys) {
            $keys[] = $args[5];
            if (count($keys) === 1) {
                throw new PaymentException('Provider response was lost.');
            }

            return $this->authorization();
        });
        $this->mockSuccessfulCapture();
        $service = app(Oct3PaymentRecoveryService::class);
        $hash = $service->preview()['expected_state'];
        $requestId = (string) Str::uuid();
        try {
            $service->apply($this->admin, $hash, $requestId, 'Reviewed visit.', 'auto-approval', null, true);
            $this->fail('Expected the unknown provider result to stop processing.');
        } catch (PaymentException) {
            $this->assertNull(CareBooking::findOrFail(239)->family_confirmed_at);
        }
        $anotherAdmin = User::factory()->create(['role' => 'admin']);
        foreach ([[$this->admin, (string) Str::uuid(), 'Reviewed visit.'],
            [$this->admin, $requestId, 'Different operational reason.'],
            [$anotherAdmin, $requestId, 'Reviewed visit.']] as [$admin, $retryId, $reason]) {
            try {
                $service->apply($admin, $hash, $retryId, $reason, 'auto-approval', null, true);
                $this->fail('Changed request, reason or administrator must not replace an ambiguous authorization.');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString('exactly its original request', $exception->getMessage());
                $this->assertCount(1, $keys);
            }
        }

        $service->apply($this->admin, $hash, $requestId, 'Reviewed visit.', 'auto-approval', null, true);
        $this->assertCount(2, $keys);
        $this->assertSame($keys[0], $keys[1]);
    }

    public function test_unknown_authorization_older_than_twenty_three_hours_requires_manual_reconciliation(): void
    {
        $stripe = $this->mockProvider();
        $stripe->shouldReceive('createManualAuthorization')->once()->andThrow(new PaymentException('Provider response was lost.'));
        $service = app(Oct3PaymentRecoveryService::class);
        $hash = $service->preview()['expected_state'];
        $requestId = (string) Str::uuid();
        try {
            $service->apply($this->admin, $hash, $requestId, 'Reviewed visit.', 'auto-approval', null, true);
            $this->fail('Expected ambiguous authorization failure.');
        } catch (PaymentException) {
            $this->assertNull(CareBooking::findOrFail(239)->family_confirmed_at);
        }
        $this->travel(24)->hours();

        $this->expectException(ValidationException::class);
        $service->apply($this->admin, $hash, $requestId, 'Reviewed visit.', 'auto-approval', null, true);
    }

    private function mockProvider(string $newStatus = 'requires_capture', array $oldChanges = [], array $newChanges = []): \Mockery\MockInterface
    {
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldReceive('retrievePaymentIntent')->with(self::OLD_INTENT)->andReturn(array_replace([
            'id' => self::OLD_INTENT, 'status' => 'canceled', 'amount' => 44640,
            'amount_received' => 0, 'amount_capturable' => 0, 'currency' => 'usd',
            'customer' => 'cus_UzbLWUlGKpPlYP', 'customer_id' => 'cus_UzbLWUlGKpPlYP',
            'livemode' => true, 'cancellation_reason' => 'automatic',
            'metadata' => ['care_booking_id' => '239'],
        ], $oldChanges));
        $stripe->shouldReceive('retrievePaymentIntent')->with(self::NEW_INTENT)->andReturn(array_replace([
            'id' => self::NEW_INTENT, 'status' => $newStatus, 'amount' => 37252,
            'amount_received' => 0, 'amount_capturable' => 37252, 'currency' => 'usd',
            'customer' => 'cus_UzbLWUlGKpPlYP', 'customer_id' => 'cus_UzbLWUlGKpPlYP',
            'livemode' => true, 'payment_method' => 'pm_1U5lyG6ewFB76RHFVkgytmCg',
            'metadata' => ['care_booking_id' => '239'],
        ], $newChanges));
        $this->instance(StripeClient::class, $stripe);

        return $stripe;
    }

    private function mockSuccessfulCapture(): void
    {
        $v2 = Mockery::mock(BookingPaymentV2Service::class);
        $v2->shouldReceive('recordAuthorization')->once();
        $v2->shouldReceive('capture')->once()->andReturnUsing(function (CareBooking $booking, CareBookingPayment $payment) {
            $this->assertSame(721, $booking->worked_minutes);
            $this->assertNull($booking->family_confirmed_at);
            $this->assertSame(self::NEW_INTENT, $payment->stripe_payment_intent_id);
            $payment->forceFill([
                'status' => CareBookingPayment::STATUS_CAPTURED, 'amount_captured_cents' => 37252,
                'stripe_primary_charge_id' => 'ch_oct3_recovery', 'captured_at' => now(), 'worked_minutes' => 721,
            ])->save();

            return $payment;
        });
        $this->instance(BookingPaymentV2Service::class, $v2);
    }

    private function authorization(string $status = 'requires_capture'): array
    {
        return ['payment_intent_id' => self::NEW_INTENT, 'status' => $status, 'amount' => 37252,
            'authorization_expires_at' => now()->addDays(6), 'failure_message' => 'Card needs action.'];
    }

    private function databaseSnapshot(): array
    {
        return collect(['care_bookings', 'care_booking_payments', 'care_booking_payment_attempts',
            'care_booking_events', 'care_booking_payment_operations', 'caregiver_payout_items'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
    }

    private function seedVisit(): void
    {
        $this->admin = User::factory()->create(['id' => 1, 'role' => 'admin']);
        User::factory()->create(['id' => 335, 'role' => 'family', 'stripe_customer_id' => 'cus_UzbLWUlGKpPlYP']);
        User::factory()->create(['id' => 349, 'role' => 'caregiver']);
        User::factory()->create(['id' => 378, 'role' => 'family']);
        FamilyAccount::forceCreate(['id' => 3, 'owner_user_id' => 335, 'status' => 'active', 'stripe_customer_id' => 'cus_UzbLWUlGKpPlYP']);
        foreach ([335 => 'owner', 378 => 'member'] as $id => $access) {
            FamilyAccountMember::create(['family_account_id' => 3, 'user_id' => $id, 'access_level' => $access, 'status' => 'active']);
        }
        CaregiverProfile::create(['user_id' => 349, 'status' => 'active', 'platform_hourly_rate' => 27,
            'stripe_connect_account_id' => 'acct_1UIUxkGE0jQOqi7z', 'stripe_charges_enabled' => true,
            'stripe_payouts_enabled' => true, 'stripe_connect_onboarding_completed_at' => now()]);
        CareRequest::forceCreate(['id' => 227, 'family_account_id' => 3, 'family_user_id' => 335,
            'title' => 'October 3 care', 'status' => CareRequest::STATUS_FILLED, 'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => '2026-10-03 19:00:00', 'requested_end_at' => '2026-10-04 07:00:00',
            'address_line1' => '123 Test Street', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601']);
        CareBooking::forceCreate(['id' => 239, 'care_request_id' => 227, 'family_account_id' => 3,
            'family_user_id' => 335, 'caregiver_user_id' => 349, 'status' => 'completed',
            'scheduled_start_at' => '2026-10-03 19:00:00', 'scheduled_end_at' => '2026-10-04 07:00:00',
            'started_at' => '2026-10-03 19:09:52', 'completed_at' => '2026-10-04 07:11:42',
            'timesheet_submitted_at' => '2026-10-04 07:11:42', 'expected_minutes' => 720, 'worked_minutes' => 721,
            'total_paused_seconds' => 0, 'check_in_source' => 'manual', 'check_out_source' => 'manual',
            'dispute_status' => 'none', 'no_show_flag' => false, 'late_cancel_flag' => false,
            'pricing_version' => '2026-08-v2', 'family_care_rate_cents' => 3000,
            'family_processing_fee_rate_cents' => 100, 'caregiver_gross_rate_cents' => 2700,
            'caregiver_fee_policy' => 'successful_charge_balance_transaction',
            'pricing_snapshotted_at' => '2026-09-26 15:49:32', 'family_terms_accepted_at' => '2026-09-26 15:49:32',
            'caregiver_terms_accepted_at' => '2026-09-26 16:40:59',
            'created_at' => '2026-09-26 15:49:32', 'updated_at' => '2026-10-04 07:11:42']);
        foreach ([['booking_hired', 378, 'family', '2026-09-26 15:49:32'],
            ['agreement_accepted_by_caregiver', 349, 'caregiver', '2026-09-26 16:40:59'],
            ['checked_in_by_caregiver', 349, 'caregiver', '2026-10-03 19:09:52'],
            ['checked_out_by_caregiver', 349, 'caregiver', '2026-10-04 07:11:42']] as [$type, $actor, $role, $at]) {
            CareBookingEvent::create(['care_booking_id' => 239, 'actor_user_id' => $actor,
                'actor_role' => $role, 'event_type' => $type, 'happened_at' => $at]);
        }
        CareBookingPayment::forceCreate(['id' => 120, 'care_booking_id' => 239, 'family_account_id' => 3,
            'pricing_version' => '2026-08-v2',
            'family_user_id' => 335, 'caregiver_user_id' => 349, 'status' => 'cancelled', 'currency' => 'usd',
            'stripe_customer_id' => 'cus_UzbLWUlGKpPlYP', 'stripe_payment_method_id' => 'pm_1U5lyG6ewFB76RHFVkgytmCg',
            'stripe_payment_intent_id' => self::OLD_INTENT, 'amount_authorized_cents' => 44640,
            'amount_refunded_cents' => 0, 'authorized_at' => '2026-09-26 15:49:38',
            'failed_at' => '2026-10-03 15:49:38', 'last_error' => 'Stripe payment failed.',
            'created_at' => '2026-09-26 15:49:32', 'updated_at' => '2026-10-03 15:49:38']);
        CareBookingPaymentAttempt::create(['care_booking_payment_id' => 120, 'care_booking_id' => 239,
            'family_account_id' => 3, 'purpose' => 'booking_authorization', 'revision_key' => 'legacy:payment:120',
            'authorization_key' => hash('sha256', 'legacy'), 'stripe_payment_intent_id' => self::OLD_INTENT,
            'amount_cents' => 44640, 'status' => 'cancelled', 'is_active' => false,
            'authorized_at' => '2026-09-26 15:49:38', 'canceled_at' => '2026-10-03 15:49:38']);
    }
}
