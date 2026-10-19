<?php

namespace Tests\Feature\Payments;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareBooking;
use App\Models\CareBookingChangeRequest;
use App\Models\CareBookingEvent;
use App\Models\CareBookingPayment;
use App\Models\CareBookingPaymentAttempt;
use App\Models\CaregiverProfile;
use App\Models\CareRequest;
use App\Models\FamilyAccount;
use App\Models\FamilyAccountMember;
use App\Models\User;
use App\Services\Payments\BookingPaymentService;
use App\Services\Payments\BookingPaymentV2Service;
use App\Services\Payments\Oct17PaymentRecoveryService;
use App\Services\Payments\StripeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class Oct17PaymentRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private const OLD_INTENT = 'pi_3UKiSN6ewFB76RHF0KKlFOqg';

    private const INTENT = 'pi_3URf2A6ewFB76RHF0YL59lIw';

    private User $admin;

    private bool $providerCaptured = false;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.timezone', 'America/New_York');
        config()->set('services.stripe.bypass', false);
        $this->travelTo(Carbon::parse('2026-10-19 13:08:09', 'America/New_York'));
        Notification::fake();
        $this->seedVisits();
    }

    public function test_preview_and_default_command_do_not_write_or_call_stripe(): void
    {
        $this->instance(StripeClient::class, Mockery::mock(StripeClient::class));
        $before = $this->databaseSnapshot();

        $preview = app(Oct17PaymentRecoveryService::class)->preview();

        $this->assertSame(244, $preview['source_booking_id']);
        $this->assertSame(311, $preview['booking_id']);
        $this->assertSame(175, $preview['payment_id']);
        $this->assertSame(697, $preview['worked_minutes']);
        $this->assertSame(36012, $preview['target_charge_cents']);
        $this->assertSame(31365, $preview['caregiver_gross_cents']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $preview['expected_state']);
        $this->artisan('homecare:recover-oct17-payment')->expectsOutputToContain('READ-ONLY')->assertSuccessful();
        $this->assertSame($before, $this->databaseSnapshot());
    }

    public function test_apply_command_requires_an_administrator_before_any_provider_access(): void
    {
        $this->instance(StripeClient::class, Mockery::mock(StripeClient::class));
        $before = $this->databaseSnapshot();

        $this->artisan('homecare:recover-oct17-payment', ['--apply' => true])->assertFailed();

        $this->assertSame($before, $this->databaseSnapshot());
    }

    public function test_real_stripe_payload_normalization_preserves_capturable_amount_and_billing_identity(): void
    {
        $intent = \Stripe\PaymentIntent::constructFrom([
            'id' => self::INTENT, 'status' => 'requires_capture', 'amount' => 42780,
            'amount_capturable' => 42780, 'amount_received' => 0, 'currency' => 'usd', 'livemode' => true,
            'customer' => ['id' => 'cus_UzbLWUlGKpPlYP', 'object' => 'customer'],
        ]);

        $payload = (new \ReflectionMethod(StripeClient::class, 'paymentIntentPayload'))->invoke(new StripeClient, $intent);

        $this->assertSame(42780, $payload['amount_capturable']);
        $this->assertSame(42780, $payload['amount']);
        $this->assertSame(0, $payload['amount_received']);
        $this->assertSame('cus_UzbLWUlGKpPlYP', $payload['customer_id']);
        $this->assertSame('usd', $payload['currency']);
        $this->assertTrue($payload['livemode']);
    }

    public function test_reassignment_preserves_the_evidence_and_bills_exactly_once_through_the_real_pipeline(): void
    {
        $stripe = $this->provider();
        $this->successfulPipeline($stripe);
        $sourceBefore = (array) DB::table('care_bookings')->find(244);
        $sourceEventsBefore = DB::table('care_booking_events')->where('care_booking_id', 244)->orderBy('id')->get()->toArray();
        $targetBefore = (array) DB::table('care_bookings')->find(311);
        $service = app(Oct17PaymentRecoveryService::class);
        $hash = $service->preview()['expected_state'];
        $requestId = (string) Str::uuid();

        $service->apply($this->admin, $hash, $requestId, 'Confirmed October 9 was canceled; the 697 minutes belong to October 17.', true, true);
        app(BookingPaymentService::class)->captureForBooking(CareBooking::findOrFail(311), false);
        app(BookingPaymentV2Service::class)->finalizeFeesAndTransfers(CareBookingPayment::findOrFail(175));

        $source = (array) DB::table('care_bookings')->find(244);
        $this->assertSame('cancelled', $source['status']);
        $this->assertSame(Arr::except($sourceBefore, ['status', 'cancelled_at', 'cancelled_by_user_id', 'cancellation_reason', 'updated_at']),
            Arr::except($source, ['status', 'cancelled_at', 'cancelled_by_user_id', 'cancellation_reason', 'updated_at']));
        $this->assertEquals($sourceEventsBefore, DB::table('care_booking_events')->whereIn('id', array_column($sourceEventsBefore, 'id'))->orderBy('id')->get()->toArray());
        $target = (array) DB::table('care_bookings')->find(311);
        foreach (['started_at', 'completed_at', 'timesheet_submitted_at', 'worked_minutes', 'check_in_source', 'check_out_source', 'total_paused_seconds'] as $field) {
            $this->assertSame($sourceBefore[$field], $target[$field], $field);
        }
        $this->assertSame('completed', $target['status']);
        $this->assertSame($targetBefore['scheduled_start_at'], $target['scheduled_start_at']);
        $this->assertSame($targetBefore['scheduled_end_at'], $target['scheduled_end_at']);
        $this->assertSame($targetBefore['family_terms_accepted_at'], $target['family_terms_accepted_at']);
        $this->assertNull($target['caregiver_terms_accepted_at'], 'The admin must not fabricate caregiver acceptance of the target booking.');
        $this->assertNotNull($target['family_confirmed_at']);
        $this->assertNull($target['family_confirmed_by_user_id']);
        $this->assertSame(0, DB::table('care_booking_events')->where('care_booking_id', 311)->where('event_type', 'agreement_accepted_by_caregiver')->count());
        foreach ([6, 7] as $changeId) {
            $this->assertDatabaseHas('care_booking_change_requests', [
                'id' => $changeId, 'care_booking_id' => 244, 'status' => 'accepted', 'resolved_by_user_id' => $this->admin->id,
            ]);
            $this->assertNotEmpty(DB::table('care_booking_change_requests')->find($changeId)->resolution_note);
        }
        $this->assertDatabaseHas('care_booking_payments', [
            'id' => 175, 'status' => 'transferred', 'worked_minutes' => 697, 'amount_captured_cents' => 36012,
            'family_care_amount_cents' => 34850, 'family_processing_fee_cents' => 1162,
            'caregiver_gross_amount_cents' => 31365, 'stripe_processing_fee_cents' => 1074,
            'caregiver_amount_cents' => 30291, 'stripe_transfer_id' => 'tr_oct17_recovery',
        ]);
        $this->assertDatabaseHas('caregiver_payout_items', [
            'care_booking_id' => 311, 'care_booking_payment_id' => 175, 'status' => 'paid', 'amount' => 302.91,
        ]);
        $sourcePayment = CareBookingPayment::findOrFail(126);
        $this->assertNull($sourcePayment->amount_captured_cents);
        $this->assertNull($sourcePayment->stripe_transfer_id);
        $this->assertNotEmpty(data_get($sourcePayment->metadata, 'oct17_visit_reassignment'));
        $this->assertDatabaseMissing('caregiver_payout_items', ['care_booking_id' => 244]);

        $service->apply($this->admin, $hash, $requestId, 'Confirmed October 9 was canceled; the 697 minutes belong to October 17.', true, true);
        $this->assertSame(1, DB::table('care_booking_payment_operations')->where('type', 'charge')->count());
        $this->assertSame(1, DB::table('care_booking_payment_operations')->where('type', 'transfer')->count());
        $this->assertDatabaseCount('care_booking_payment_attempts', 2);
        Notification::assertNothingSent();
    }

    #[DataProvider('invalidDatabaseStates')]
    public function test_changed_visit_or_payment_evidence_blocks_preview(string $table, int $id, array $changes): void
    {
        $this->instance(StripeClient::class, Mockery::mock(StripeClient::class));
        DB::table($table)->where('id', $id)->update($changes);

        $this->expectException(ValidationException::class);
        app(Oct17PaymentRecoveryService::class)->preview();
    }

    public static function invalidDatabaseStates(): array
    {
        return [
            'source different caregiver' => ['care_bookings', 244, ['caregiver_user_id' => 378]],
            'source hours changed' => ['care_bookings', 244, ['worked_minutes' => 698]],
            'source rounded start' => ['care_bookings', 244, ['started_at' => '2026-10-17 19:30:00']],
            'source new dispute' => ['care_bookings', 244, ['dispute_status' => 'open']],
            'target new dispute' => ['care_bookings', 311, ['dispute_status' => 'open']],
            'target already worked' => ['care_bookings', 311, ['started_at' => '2026-10-17 19:30:56']],
            'target different pricing' => ['care_bookings', 311, ['family_care_rate_cents' => 3100]],
            'source refund' => ['care_booking_payments', 126, ['amount_refunded_cents' => 100]],
            'target refund' => ['care_booking_payments', 175, ['amount_refunded_cents' => 100]],
            'target existing capture' => ['care_booking_payments', 175, ['amount_captured_cents' => 36012]],
            'source prepaid hold' => ['care_booking_payments', 126, ['metadata' => '{"prepaid_visit_recovery":{"state":"held"}}']],
            'source authorization in progress' => ['care_booking_payments', 126, ['metadata' => '{"authorization_in_progress_at":"2026-10-19T13:00:00-04:00"}']],
            'target pending authorization' => ['care_booking_payments', 175, ['metadata' => '{"pending_authorization":{"amount_cents":42780}}']],
            'cancellation no longer pending' => ['care_booking_change_requests', 6, ['status' => 'rejected']],
        ];
    }

    public function test_stale_preview_stops_before_stripe(): void
    {
        $this->instance(StripeClient::class, Mockery::mock(StripeClient::class));
        $service = app(Oct17PaymentRecoveryService::class);
        $hash = $service->preview()['expected_state'];
        DB::table('care_bookings')->where('id', 244)->update(['updated_at' => now()]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('stale');
        $service->apply($this->admin, $hash, (string) Str::uuid(), 'Confirmed October 9 cancellation and October 17 hours.', true, true);
    }

    #[DataProvider('missingAttestations')]
    public function test_apply_requires_both_operator_attestations(bool $providerVerified, bool $assignmentConfirmed): void
    {
        $this->instance(StripeClient::class, Mockery::mock(StripeClient::class));
        $service = app(Oct17PaymentRecoveryService::class);
        $hash = $service->preview()['expected_state'];
        $before = $this->databaseSnapshot();
        try {
            $service->apply($this->admin, $hash, (string) Str::uuid(),
                'Confirmed October 9 cancellation and October 17 hours.', $providerVerified, $assignmentConfirmed);
            $this->fail('Both attestations must be explicit.');
        } catch (ValidationException) {
            $this->assertSame($before, $this->databaseSnapshot());
        }
    }

    public static function missingAttestations(): array
    {
        return [[false, true], [true, false], [false, false]];
    }

    public function test_automatic_approval_requires_the_timesheet_window_to_have_elapsed(): void
    {
        $this->travelTo(Carbon::parse('2026-10-18 12:00:00', 'America/New_York'));
        $this->instance(StripeClient::class, Mockery::mock(StripeClient::class));
        $service = app(Oct17PaymentRecoveryService::class);
        $hash = $service->preview()['expected_state'];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('24-hour');
        $service->apply($this->admin, $hash, (string) Str::uuid(),
            'Confirmed October 9 cancellation and October 17 hours.', true, true);
    }

    public function test_an_additional_authorization_attempt_requires_separate_review(): void
    {
        $this->instance(StripeClient::class, Mockery::mock(StripeClient::class));
        CareBookingPaymentAttempt::create([
            'care_booking_id' => 311, 'care_booking_payment_id' => 175, 'family_account_id' => 3,
            'purpose' => 'booking_authorization', 'revision_key' => 'unexpected',
            'authorization_key' => hash('sha256', 'unexpected'), 'stripe_payment_intent_id' => 'pi_unreviewed',
            'amount_cents' => 42780, 'status' => 'authorized', 'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);
        app(Oct17PaymentRecoveryService::class)->preview();
    }

    public function test_an_additional_change_request_requires_separate_review(): void
    {
        $this->instance(StripeClient::class, Mockery::mock(StripeClient::class));
        CareBookingChangeRequest::create(['care_booking_id' => 311, 'requester_user_id' => 378,
            'type' => 'cancel', 'status' => 'pending', 'reason' => 'A different pending change.']);

        $this->expectException(ValidationException::class);
        app(Oct17PaymentRecoveryService::class)->preview();
    }

    public function test_time_correction_blocks_reassignment_and_payment(): void
    {
        $this->instance(StripeClient::class, Mockery::mock(StripeClient::class));
        DB::table('care_booking_time_corrections')->insert([
            'client_request_id' => (string) Str::uuid(), 'care_booking_id' => 244,
            'requester_user_id' => 349, 'family_user_id' => 335, 'version' => 1,
            'status' => 'pending_family_review', 'reason_code' => 'incorrect_time',
            'explanation' => 'Requested different hours.', 'proposed_started_at' => '2026-10-17 19:30:00',
            'proposed_completed_at' => '2026-10-18 07:00:00', 'proposed_worked_minutes' => 690,
            'original_snapshot' => '{}', 'financial_preview' => '{}', 'submitted_at' => now(),
        ]);

        $this->expectException(ValidationException::class);
        app(Oct17PaymentRecoveryService::class)->preview();
    }

    #[DataProvider('invalidProviderStates')]
    public function test_live_provider_must_confirm_uncaptured_matching_authorizations(string $which, array $overrides): void
    {
        $this->provider($which === 'old' ? $overrides : [], $which === 'target' ? $overrides : []);
        $service = app(Oct17PaymentRecoveryService::class);
        $hash = $service->preview()['expected_state'];
        try {
            $service->apply($this->admin, $hash, (string) Str::uuid(),
                'Confirmed October 9 cancellation and October 17 hours.', true, true);
            $this->fail('Unsafe live provider state must stop recovery.');
        } catch (ValidationException|PaymentException) {
            $this->assertDatabaseMissing('care_booking_payment_operations', ['type' => 'charge']);
            $this->assertDatabaseCount('caregiver_payout_items', 0);
            $this->assertNull(CareBooking::findOrFail(311)->family_confirmed_at);
        }
    }

    public static function invalidProviderStates(): array
    {
        return [
            'old already paid' => ['old', ['status' => 'succeeded', 'amount_received' => 36012]],
            'old wrong customer' => ['old', ['customer_id' => 'cus_other']],
            'target canceled' => ['target', ['status' => 'canceled', 'amount_capturable' => 0]],
            'target insufficient authorization' => ['target', ['amount_capturable' => 36011]],
            'target wrong amount' => ['target', ['amount' => 42781]],
            'target wrong customer' => ['target', ['customer_id' => 'cus_other']],
            'target wrong currency' => ['target', ['currency' => 'eur']],
            'target test mode' => ['target', ['livemode' => false]],
            'unreserved target already paid' => ['target', ['status' => 'succeeded', 'amount_received' => 36012]],
        ];
    }

    public function test_unknown_capture_resumes_existing_intent_without_a_second_charge(): void
    {
        $stripe = $this->provider();
        $this->successfulPipeline($stripe, true);
        $service = app(Oct17PaymentRecoveryService::class);
        $hash = $service->preview()['expected_state'];
        $requestId = (string) Str::uuid();
        try {
            $service->apply($this->admin, $hash, $requestId, 'Confirmed October 9 cancellation and October 17 hours.', true, true);
            $this->fail('Expected an unknown capture result to stop processing.');
        } catch (PaymentException) {
            $this->assertNull(CareBooking::findOrFail(311)->family_confirmed_at);
        }

        // MySQL normalizes JSON object key order. Recovery must compare evidence values,
        // while preserving list order and scalar types, rather than PHP insertion order.
        $payment = CareBookingPayment::findOrFail(175);
        $payment->forceFill(['metadata' => $this->canonicalizeJsonObjects($payment->metadata)])->save();

        foreach ([[(string) Str::uuid(), 'Confirmed October 9 cancellation and October 17 hours.'],
            [$requestId, 'A different operational approval reason.']] as [$retryId, $reason]) {
            try {
                $service->apply($this->admin, $hash, $retryId, $reason, true, true);
                $this->fail('An uncertain recovery must keep its original request and approval evidence.');
            } catch (ValidationException) {
                $this->assertNull(CareBooking::findOrFail(311)->family_confirmed_at);
            }
        }

        $service->apply($this->admin, $hash, $requestId, 'Confirmed October 9 cancellation and October 17 hours.', true, true);

        $this->assertSame(1, DB::table('care_booking_payment_operations')->where('type', 'charge')->count());
        $this->assertSame(1, DB::table('care_booking_payment_operations')->where('type', 'transfer')->count());
        $this->assertDatabaseHas('care_booking_payments', ['id' => 175, 'amount_captured_cents' => 36012, 'status' => 'transferred']);
        $this->assertDatabaseMissing('caregiver_payout_items', ['care_booking_id' => 244]);
    }

    public function test_reassigned_source_visit_cannot_be_captured_again(): void
    {
        $stripe = $this->provider();
        $this->successfulPipeline($stripe);
        $service = app(Oct17PaymentRecoveryService::class);
        $service->apply($this->admin, $service->preview()['expected_state'], (string) Str::uuid(),
            'Confirmed October 9 cancellation and October 17 hours.', true, true);

        $this->expectException(PaymentException::class);
        app(BookingPaymentV2Service::class)->capture(CareBooking::findOrFail(244), CareBookingPayment::findOrFail(126));
    }

    public function test_reserved_recovery_blocks_normal_payment_paths_even_for_stale_callers(): void
    {
        $staleSource = CareBooking::with('payment')->findOrFail(244);
        $staleTarget = CareBooking::with('payment')->findOrFail(311);
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldReceive('retrievePaymentIntent')->once()->with(self::OLD_INTENT)
            ->andThrow(new PaymentException('Provider temporarily unavailable.'));
        $this->instance(StripeClient::class, $stripe);
        $service = app(Oct17PaymentRecoveryService::class);
        try {
            $service->apply($this->admin, $service->preview()['expected_state'], (string) Str::uuid(),
                'Confirmed October 9 cancellation and October 17 hours.', true, true);
            $this->fail('Expected unavailable provider to stop the reserved recovery.');
        } catch (PaymentException) {
            $this->assertSame('reserved', data_get(CareBookingPayment::findOrFail(175)->metadata, 'oct17_payment_recovery.state'));
        }

        foreach ([$staleSource, $staleTarget] as $booking) {
            $this->assertNull(data_get($booking->payment->metadata, 'oct17_visit_reassignment'));
            $this->assertNull(data_get($booking->payment->metadata, 'oct17_payment_recovery'));
            foreach (['authorizeForBooking', 'captureForBooking'] as $method) {
                try {
                    app(BookingPaymentService::class)->$method($booking);
                    $this->fail('A stale normal payment caller must respect the new recovery reservation.');
                } catch (PaymentException $exception) {
                    $this->assertNotEmpty($exception->userMessage);
                }
            }
            try {
                app(BookingPaymentV2Service::class)->capture($booking, $booking->payment);
                $this->fail('V2 must refresh the stale payment before permitting capture.');
            } catch (PaymentException $exception) {
                $this->assertNotEmpty($exception->userMessage);
            }
        }
        $this->assertDatabaseMissing('care_booking_payment_operations', ['type' => 'charge']);
        $this->assertDatabaseCount('caregiver_payout_items', 0);
    }

    public function test_webhook_before_capture_response_uses_the_697_minute_snapshot_once(): void
    {
        $stripe = $this->provider();
        $this->successfulPipeline($stripe, webhookFirst: true);
        $service = app(Oct17PaymentRecoveryService::class);

        $service->apply($this->admin, $service->preview()['expected_state'], (string) Str::uuid(),
            'Confirmed October 9 cancellation and October 17 hours.', true, true);

        $this->assertDatabaseHas('care_booking_payments', [
            'id' => 175, 'worked_minutes' => 697, 'caregiver_gross_amount_cents' => 31365,
            'amount_captured_cents' => 36012, 'caregiver_amount_cents' => 30291,
        ]);
        $this->assertSame(1, DB::table('care_booking_payment_operations')->where('type', 'charge')->count());
        $this->assertSame(1, DB::table('care_booking_payment_operations')->where('type', 'transfer')->count());
        $this->assertDatabaseMissing('caregiver_payout_items', ['care_booking_id' => 244]);
    }

    public function test_unexpected_captured_amount_after_reconciliation_blocks_v2_before_stripe(): void
    {
        $stripe = $this->provider();
        $stripe->shouldReceive('capturePaymentIntent')->once()->andThrow(new PaymentException('Capture response is unknown.'));
        $service = app(Oct17PaymentRecoveryService::class);
        try {
            $service->apply($this->admin, $service->preview()['expected_state'], (string) Str::uuid(),
                'Confirmed October 9 cancellation and October 17 hours.', true, true);
            $this->fail('Expected an uncertain capture after the visit was reconciled.');
        } catch (PaymentException) {
            $this->assertSame('capture_unknown', data_get(CareBookingPayment::findOrFail(175)->metadata, 'oct17_payment_recovery.state'));
            $this->assertSame('completed', CareBooking::findOrFail(311)->status);
        }
        DB::table('care_booking_payments')->where('id', 175)->update(['amount_captured_cents' => 36013]);
        $this->instance(StripeClient::class, Mockery::mock(StripeClient::class));

        try {
            app(BookingPaymentV2Service::class)->capture(CareBooking::findOrFail(311), CareBookingPayment::findOrFail(175));
            $this->fail('A captured amount outside the reviewed amount must stop before Stripe.');
        } catch (PaymentException) {
            $this->assertDatabaseMissing('care_booking_payment_operations', ['type' => 'charge']);
            $this->assertDatabaseCount('caregiver_payout_items', 0);
            $this->assertNull(CareBooking::findOrFail(311)->family_confirmed_at);
        }
    }

    private function provider(array $oldOverrides = [], array $targetOverrides = []): \Mockery\MockInterface
    {
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldReceive('retrievePaymentIntent')->with(self::OLD_INTENT)->andReturn(array_replace([
            'id' => self::OLD_INTENT, 'status' => 'canceled', 'amount' => 44640,
            'amount_received' => 0, 'amount_capturable' => 0, 'currency' => 'usd',
            'customer_id' => 'cus_UzbLWUlGKpPlYP', 'livemode' => true,
        ], $oldOverrides));
        $stripe->shouldReceive('retrievePaymentIntent')->with(self::INTENT)->andReturnUsing(fn () => array_replace([
            'id' => self::INTENT, 'status' => $this->providerCaptured ? 'succeeded' : 'requires_capture',
            'amount' => 42780, 'amount_received' => $this->providerCaptured ? 36012 : 0,
            'amount_capturable' => $this->providerCaptured ? 0 : 42780, 'currency' => 'usd',
            'customer_id' => 'cus_UzbLWUlGKpPlYP', 'livemode' => true,
            'payment_method_id' => 'pm_1U5lyG6ewFB76RHFVkgytmCg',
        ], $this->providerCaptured ? $this->financials() : [], $targetOverrides));
        $this->instance(StripeClient::class, $stripe);

        return $stripe;
    }

    private function successfulPipeline(\Mockery\MockInterface $stripe, bool $unknownCapture = false, bool $webhookFirst = false): void
    {
        $stripe->shouldReceive('retrievePaymentIntentFinancials')->with(self::INTENT, 36012)
            ->andReturnUsing(fn () => $this->providerCaptured ? $this->financials() : [
                'id' => self::INTENT, 'status' => 'requires_capture', 'amount_received' => 0,
            ]);
        $stripe->shouldReceive('capturePaymentIntent')->once()
            ->withArgs(fn ($intent, $amount, $key) => $intent === self::INTENT && $amount === 36012
                && $key === 'payment-v2:175:capture-oct17-'.self::INTENT.'-'
                    .data_get(CareBookingPayment::findOrFail(175)->metadata, 'oct17_payment_recovery.request_id').':36012')
            ->andReturnUsing(function () use ($unknownCapture, $webhookFirst) {
                $this->providerCaptured = true;
                if ($webhookFirst) {
                    $payment = CareBookingPayment::findOrFail(175);
                    $this->assertSame(697, $payment->worked_minutes);
                    $this->assertSame(31365, $payment->caregiver_gross_amount_cents);
                    app(BookingPaymentV2Service::class)->recordSucceededPaymentIntent($payment, $this->financials());
                }
                if ($unknownCapture) {
                    throw new PaymentException('Capture response was lost after Stripe accepted it.');
                }

                return $this->financials();
            });
        $stripe->shouldReceive('createTransferForCharge')->once()
            ->withArgs(fn ($account, $charge, $group, $amount, $currency, $metadata, $key) => $account === 'acct_1UIUxkGE0jQOqi7z' && $charge === 'ch_oct17_recovery'
                && $amount === 30291 && $currency === 'usd' && filled($key))
            ->andReturn(['id' => 'tr_oct17_recovery']);
    }

    private function financials(): array
    {
        return ['id' => self::INTENT, 'status' => 'succeeded', 'amount_received' => 36012,
            'latest_charge_id' => 'ch_oct17_recovery', 'balance_transaction_id' => 'txn_oct17_recovery',
            'fee_finalized' => true, 'processing_fee_cents' => 1074];
    }

    private function canonicalizeJsonObjects(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalizeJsonObjects($item);
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    private function databaseSnapshot(): array
    {
        return collect(['care_bookings', 'care_booking_payments', 'care_booking_payment_attempts', 'care_booking_events',
            'care_booking_change_requests', 'care_booking_payment_operations', 'caregiver_payout_items'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
    }

    private function seedVisits(): void
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
        foreach ([238 => ['2026-10-09 19:00:00', '2026-10-10 07:00:00'], 317 => ['2026-10-17 19:30:00', '2026-10-18 07:00:00']] as $id => [$start, $end]) {
            CareRequest::forceCreate(['id' => $id, 'family_account_id' => 3, 'family_user_id' => 335,
                'title' => 'Villano visit '.$id, 'status' => CareRequest::STATUS_FILLED, 'request_type' => CareRequest::TYPE_ONE_TIME,
                'requested_start_at' => $start, 'requested_end_at' => $end,
                'address_line1' => '123 Test Street', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601']);
        }
        $common = ['family_account_id' => 3, 'family_user_id' => 335, 'caregiver_user_id' => 349,
            'total_paused_seconds' => 0, 'dispute_status' => 'none', 'no_show_flag' => false, 'late_cancel_flag' => false,
            'pricing_version' => '2026-08-v2', 'family_care_rate_cents' => 3000,
            'family_processing_fee_rate_cents' => 100, 'caregiver_gross_rate_cents' => 2700,
            'caregiver_fee_policy' => 'successful_charge_balance_transaction'];
        CareBooking::forceCreate(array_merge($common, [
            'id' => 244, 'care_request_id' => 238, 'status' => 'completed',
            'scheduled_start_at' => '2026-10-09 19:00:00', 'scheduled_end_at' => '2026-10-10 07:00:00',
            'started_at' => '2026-10-17 19:30:56', 'completed_at' => '2026-10-18 07:08:49',
            'timesheet_submitted_at' => '2026-10-18 07:08:49', 'expected_minutes' => 720, 'worked_minutes' => 697,
            'check_in_source' => 'manual', 'check_out_source' => 'manual',
            'pricing_snapshotted_at' => '2026-09-28 13:35:14', 'family_terms_accepted_at' => '2026-09-28 13:35:14',
            'caregiver_terms_accepted_at' => '2026-09-28 13:54:00',
            'created_at' => '2026-09-28 13:35:14', 'updated_at' => '2026-10-18 07:08:49',
        ]));
        CareBooking::forceCreate(array_merge($common, [
            'id' => 311, 'care_request_id' => 317, 'status' => 'scheduled',
            'scheduled_start_at' => '2026-10-17 19:30:00', 'scheduled_end_at' => '2026-10-18 07:00:00',
            'expected_minutes' => 690, 'pricing_snapshotted_at' => '2026-10-17 17:20:54',
            'family_terms_accepted_at' => '2026-10-17 17:20:54', 'caregiver_terms_accepted_at' => null,
            'created_at' => '2026-10-17 17:20:54', 'updated_at' => '2026-10-17 17:20:54',
        ]));
        foreach ([[645, 244, 'booking_hired', 378, 'family', '2026-09-28 13:35:14'],
            [646, 244, 'agreement_accepted_by_caregiver', 349, 'caregiver', '2026-09-28 13:54:00'],
            [765, 244, 'booking_change_requested', 378, 'family', '2026-10-07 20:24:00'],
            [766, 244, 'booking_change_requested', 378, 'family', '2026-10-07 20:25:00'],
            [824, 311, 'booking_hired', 378, 'family', '2026-10-17 17:20:54'],
            [825, 244, 'checked_in_by_caregiver', 349, 'caregiver', '2026-10-17 19:30:56'],
            [826, 244, 'checked_out_by_caregiver', 349, 'caregiver', '2026-10-18 07:08:49']] as [$id, $booking, $type, $actor, $role, $at]) {
            CareBookingEvent::forceCreate(['id' => $id, 'care_booking_id' => $booking, 'actor_user_id' => $actor,
                'actor_role' => $role, 'event_type' => $type, 'happened_at' => $at]);
        }
        foreach ([6 => 'Did not interpret web interface properly to reflect the current status.', 7 => 'cancel visit'] as $id => $reason) {
            $changedAt = $id === 6 ? '2026-10-07 20:24:39' : '2026-10-07 20:25:07';
            CareBookingChangeRequest::forceCreate(['id' => $id, 'care_booking_id' => 244, 'requester_user_id' => 378,
                'type' => 'cancel', 'status' => 'pending', 'reason' => $reason,
                'created_at' => $changedAt, 'updated_at' => $changedAt]);
        }
        foreach ([[126, 244, 85, self::OLD_INTENT, 'cancelled', 44640, '2026-09-28 13:35:19'],
            [175, 311, 96, self::INTENT, 'authorized', 42780, '2026-10-17 17:20:57']] as [$id, $booking, $attempt, $intent, $status, $amount, $at]) {
            CareBookingPayment::forceCreate(['id' => $id, 'care_booking_id' => $booking, 'family_account_id' => 3,
                'pricing_version' => '2026-08-v2', 'family_user_id' => 335, 'caregiver_user_id' => 349,
                'status' => $status, 'currency' => 'usd', 'stripe_customer_id' => 'cus_UzbLWUlGKpPlYP',
                'stripe_payment_method_id' => 'pm_1U5lyG6ewFB76RHFVkgytmCg', 'stripe_payment_intent_id' => $intent,
                'amount_authorized_cents' => $amount, 'amount_refunded_cents' => 0, 'authorized_at' => $at,
                'failed_at' => $id === 126 ? '2026-10-05 13:35:19' : null,
                'last_error' => $id === 126 ? 'Stripe payment failed.' : null,
                'created_at' => $at, 'updated_at' => $at]);
            CareBookingPaymentAttempt::forceCreate(['id' => $attempt, 'care_booking_payment_id' => $id,
                'care_booking_id' => $booking, 'family_account_id' => 3, 'purpose' => 'booking_authorization',
                'revision_key' => 'legacy:payment:'.$id, 'authorization_key' => hash('sha256', 'legacy:'.$id),
                'stripe_payment_intent_id' => $intent, 'amount_cents' => $amount, 'status' => $status,
                'is_active' => $id === 175, 'authorized_at' => $at,
                'canceled_at' => $id === 126 ? '2026-10-05 13:35:19' : null]);
        }
    }
}
