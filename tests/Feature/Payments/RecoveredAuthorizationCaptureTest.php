<?php

namespace Tests\Feature\Payments;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareBooking;
use App\Models\CareBookingChangeRequest;
use App\Models\CareBookingPayment;
use App\Models\CareBookingPaymentAttempt;
use App\Models\CareBookingPaymentOperation;
use App\Models\CaregiverProfile;
use App\Models\CareRequest;
use App\Models\FamilyAccount;
use App\Models\User;
use App\Services\Payments\BookingPaymentV2Service;
use App\Services\Payments\StripeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RecoveredAuthorizationCaptureTest extends TestCase
{
    use RefreshDatabase;

    public function test_recovery_capture_uses_a_stable_key_per_intent_without_reusing_the_canceled_intent_key(): void
    {
        [$booking, $payment] = $this->scenario('pi_canceled_original', false);
        $keys = [];
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldReceive('capturePaymentIntent')->times(4)
            ->andReturnUsing(function (string $intentId, int $amount, string $key) use (&$keys): array {
                $this->assertSame(37252, $amount);
                $keys[] = $key;
                throw new PaymentException('Capture response unavailable.');
            });
        $stripe->shouldReceive('retrievePaymentIntentFinancials')->times(3)
            ->andReturnUsing(fn (string $intentId): array => [
                'id' => $intentId, 'status' => 'requires_capture', 'amount_received' => 0,
            ]);
        $this->app->instance(StripeClient::class, $stripe);

        $this->assertCaptureFails($booking, $payment);
        foreach (['pi_recovery_a', 'pi_recovery_a', 'pi_recovery_b'] as $intentId) {
            $payment->update([
                'stripe_payment_intent_id' => $intentId,
                'metadata' => $this->receipt($intentId),
            ]);
            $this->assertCaptureFails($booking, $payment);
        }

        $this->assertSame('payment-v2:'.$payment->id.':capture-primary:37252', $keys[0]);
        $this->assertNotSame($keys[0], $keys[1]);
        $this->assertSame($keys[1], $keys[2]);
        $this->assertNotSame($keys[2], $keys[3]);
        $this->assertDatabaseCount('care_booking_payment_operations', 0);
    }

    public function test_an_uncertain_capture_response_is_reconciled_once_without_capturing_again(): void
    {
        [$booking, $payment] = $this->scenario();
        $originalBooking = $booking->getAttributes();
        $attempt = CareBookingPaymentAttempt::query()->create([
            'care_booking_payment_id' => $payment->id,
            'care_booking_id' => $booking->id,
            'revision_key' => 'oct3-recovery',
            'authorization_key' => hash('sha256', 'oct3-recovery'),
            'stripe_payment_intent_id' => 'pi_recovered',
            'amount_cents' => 37252,
            'currency' => 'usd',
            'status' => CareBookingPayment::STATUS_AUTHORIZED,
        ]);
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldReceive('retrievePaymentIntentFinancials')->with('pi_recovered', 37252)
            ->andReturn(
                ['id' => 'pi_recovered', 'status' => 'requires_capture', 'amount_received' => 0],
                $this->financials(),
            );
        $stripe->shouldReceive('capturePaymentIntent')->once()
            ->with('pi_recovered', 37252, 'payment-v2:'.$payment->id.':capture-primary-recovery-pi_recovered:37252')
            ->andThrow(new PaymentException('Capture succeeded but its response was lost.'));
        $stripe->shouldReceive('createTransferForCharge')->once()->with(
            'acct_recovered', 'ch_recovered', 'LOLO_'.$booking->financial_reference, 31335, 'usd',
            Mockery::type('array'), Mockery::type('string'),
        )->andReturn(['id' => 'tr_recovered']);
        $this->app->instance(StripeClient::class, $stripe);

        $this->assertCaptureFails($booking, $payment);
        $this->assertDatabaseCount('care_booking_payment_operations', 0);
        $service = app(BookingPaymentV2Service::class);
        $service->capture($booking, $payment);
        $result = $service->capture($booking, $payment);

        $this->assertSame(37252, (int) $result->amount_captured_cents);
        $this->assertSame(32445, (int) $result->caregiver_gross_amount_cents);
        $this->assertSame(1110, (int) $result->stripe_processing_fee_cents);
        $this->assertSame(31335, (int) $result->caregiver_amount_cents);
        $this->assertSame(CareBookingPayment::STATUS_TRANSFERRED, $result->status);
        foreach ([CareBookingPaymentOperation::TYPE_CHARGE, CareBookingPaymentOperation::TYPE_PROCESSING_FEE,
            CareBookingPaymentOperation::TYPE_EARNING, CareBookingPaymentOperation::TYPE_TRANSFER] as $type) {
            $this->assertSame(1, $payment->operations()->where('type', $type)->count(), $type);
        }
        $this->assertDatabaseCount('caregiver_payout_items', 1);
        $this->assertSame(CareBookingPayment::STATUS_CAPTURED, $attempt->fresh()->status);
        $this->assertSame($originalBooking, $booking->fresh()->getAttributes());
    }

    public function test_an_already_succeeded_recovery_is_recorded_without_a_capture_request(): void
    {
        [$booking, $payment] = $this->scenario();
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldReceive('retrievePaymentIntentFinancials')->with('pi_recovered', 37252)
            ->andReturn($this->financials());
        $stripe->shouldNotReceive('capturePaymentIntent');
        $stripe->shouldReceive('createTransferForCharge')->once()->andReturn(['id' => 'tr_recovered']);
        $this->app->instance(StripeClient::class, $stripe);

        app(BookingPaymentV2Service::class)->capture($booking, $payment);
        app(BookingPaymentV2Service::class)->capture($booking, $payment);

        $this->assertSame(1, $payment->operations()->where('type', CareBookingPaymentOperation::TYPE_CHARGE)->count());
        $this->assertSame(37252, (int) $payment->fresh()->amount_captured_cents);
    }

    public function test_a_webhook_before_the_capture_response_pays_the_recorded_721_minutes_once(): void
    {
        [$booking, $payment] = $this->scenario();
        $originalBooking = $booking->getAttributes();
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldNotReceive('capturePaymentIntent');
        $stripe->shouldReceive('retrievePaymentIntentFinancials')->with('pi_recovered', 37252)
            ->andReturn($this->financials());
        $stripe->shouldReceive('createTransferForCharge')->once()->with(
            'acct_recovered', 'ch_recovered', 'LOLO_'.$booking->financial_reference, 31335, 'usd',
            Mockery::type('array'), Mockery::type('string'),
        )->andReturn(['id' => 'tr_recovered']);
        $this->app->instance(StripeClient::class, $stripe);
        $service = app(BookingPaymentV2Service::class);

        $service->recordSucceededPaymentIntent($payment, $this->financials());
        $result = $service->capture($booking, $payment);

        $this->assertSame(32445, (int) $result->caregiver_gross_amount_cents);
        $this->assertSame(31335, (int) $result->caregiver_amount_cents);
        $this->assertSame(37252, (int) $result->amount_captured_cents);
        $this->assertSame(1, $payment->operations()->where('type', CareBookingPaymentOperation::TYPE_CHARGE)->count());
        $this->assertSame(1, $payment->operations()->where('type', CareBookingPaymentOperation::TYPE_TRANSFER)->count());
        $this->assertSame($originalBooking, $booking->fresh()->getAttributes());
    }

    public function test_a_new_dispute_blocks_a_late_webhook_from_transferring_recovery_earnings(): void
    {
        [$booking, $payment] = $this->scenario();
        $booking->update(['dispute_status' => 'open', 'dispute_opened_at' => now()]);
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldNotReceive('capturePaymentIntent');
        $stripe->shouldNotReceive('createTransferForCharge');
        $this->app->instance(StripeClient::class, $stripe);

        try {
            app(BookingPaymentV2Service::class)->recordSucceededPaymentIntent($payment, $this->financials());
            $this->fail('Expected the new dispute to stop the recovery transfer.');
        } catch (PaymentException $exception) {
            $this->assertStringContainsString('visit changed', $exception->userMessage);
        }

        $this->assertSame(1, $payment->operations()->where('type', CareBookingPaymentOperation::TYPE_CHARGE)->count());
        $this->assertSame(0, $payment->operations()->where('type', CareBookingPaymentOperation::TYPE_TRANSFER)->count());
        $this->assertDatabaseCount('caregiver_payout_items', 0);
    }

    public function test_an_authorization_received_before_provider_verification_cannot_capture_or_transfer(): void
    {
        [$booking, $payment] = $this->scenario();
        $receipt = $this->receipt('pi_recovered');
        $receipt['oct3_payment_recovery']['state'] = 'authorization_received';
        $payment->update(['metadata' => $receipt]);
        $this->app->instance(StripeClient::class, Mockery::mock(StripeClient::class));

        $this->assertCaptureFails($booking, $payment);
        $this->assertDatabaseCount('care_booking_payment_operations', 0);
        try {
            app(BookingPaymentV2Service::class)->recordSucceededPaymentIntent($payment, $this->financials());
            $this->fail('Expected unverified authorization to stop a webhook transfer.');
        } catch (PaymentException $exception) {
            $this->assertStringContainsString('provider verification', $exception->userMessage);
        }

        $this->assertSame(1, $payment->operations()->where('type', CareBookingPaymentOperation::TYPE_CHARGE)->count());
        $this->assertSame(0, $payment->operations()->where('type', CareBookingPaymentOperation::TYPE_TRANSFER)->count());
        $this->assertDatabaseCount('caregiver_payout_items', 0);
    }

    public function test_a_new_change_request_prevents_capture_even_with_the_original_recorded_hours(): void
    {
        [$booking, $payment] = $this->scenario();
        $booking->changeRequests()->create([
            'requester_user_id' => 335, 'type' => CareBookingChangeRequest::TYPE_CANCEL,
            'status' => CareBookingChangeRequest::STATUS_PENDING, 'reason' => 'Please review this visit.',
        ]);
        $this->app->instance(StripeClient::class, Mockery::mock(StripeClient::class));

        $this->assertCaptureFails($booking, $payment);

        $this->assertDatabaseCount('care_booking_payment_operations', 0);
    }

    public function test_changed_recorded_times_prevent_capture_before_any_provider_call(): void
    {
        [$booking, $payment] = $this->scenario();
        $booking->update(['completed_at' => '2026-10-04 07:12:42']);
        $this->app->instance(StripeClient::class, Mockery::mock(StripeClient::class));

        $this->assertCaptureFails($booking, $payment);

        $this->assertDatabaseCount('care_booking_payment_operations', 0);
    }

    public function test_a_completed_recovery_receipt_does_not_block_later_normal_settlement(): void
    {
        [$booking, $payment] = $this->scenario();
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldReceive('retrievePaymentIntentFinancials')->with('pi_recovered', 37252)
            ->andReturn($this->financials());
        $stripe->shouldReceive('createTransferForCharge')->once()->andReturn(['id' => 'tr_recovered']);
        $stripe->shouldNotReceive('capturePaymentIntent');
        $this->app->instance(StripeClient::class, $stripe);
        $service = app(BookingPaymentV2Service::class);
        $service->capture($booking, $payment);
        $receipt = $this->receipt('pi_recovered');
        $receipt['oct3_payment_recovery']['state'] = 'complete';
        $payment->refresh()->update(['metadata' => $receipt]);
        $booking->update(['status' => CareBooking::STATUS_REVIEWED, 'reviewed_at' => now()]);

        $result = $service->finalizeFeesAndTransfers($payment);

        $this->assertSame(CareBookingPayment::STATUS_TRANSFERRED, $result->status);
        $this->assertSame(37252, (int) $result->amount_captured_cents);
        $this->assertSame(1, $payment->operations()->where('type', CareBookingPaymentOperation::TYPE_TRANSFER)->count());
    }

    #[DataProvider('unconfirmedFinancials')]
    public function test_unconfirmed_or_mismatched_recovery_financials_cannot_capture_or_write_a_charge(array $overrides): void
    {
        [$booking, $payment] = $this->scenario();
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldReceive('retrievePaymentIntentFinancials')->once()->with('pi_recovered', 37252)
            ->andReturn(array_replace($this->financials(), $overrides));
        $stripe->shouldNotReceive('capturePaymentIntent');
        $stripe->shouldNotReceive('createTransferForCharge');
        $this->app->instance(StripeClient::class, $stripe);

        $this->assertCaptureFails($booking, $payment);

        $this->assertDatabaseCount('care_booking_payment_operations', 0);
        $this->assertNull($payment->fresh()->amount_captured_cents);
    }

    public static function unconfirmedFinancials(): array
    {
        return [
            'canceled' => [['status' => 'canceled', 'amount_received' => 0]],
            'customer action required' => [['status' => 'requires_action', 'amount_received' => 0]],
            'wrong amount' => [['amount_received' => 37251]],
            'wrong intent' => [['id' => 'pi_some_other_visit']],
            'missing charge' => [['latest_charge_id' => null]],
            'partial amount while capturable' => [['status' => 'requires_capture', 'amount_received' => 1]],
        ];
    }

    public function test_a_recovery_receipt_for_another_intent_does_not_change_ordinary_capture_behavior(): void
    {
        [$booking, $payment] = $this->scenario();
        $payment->update(['metadata' => $this->receipt('pi_another_intent')]);
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldNotReceive('retrievePaymentIntentFinancials');
        $stripe->shouldReceive('capturePaymentIntent')->once()
            ->with('pi_recovered', 37252, 'payment-v2:'.$payment->id.':capture-primary:37252')
            ->andThrow(new PaymentException('Ordinary capture attempted.'));
        $this->app->instance(StripeClient::class, $stripe);

        $this->assertCaptureFails($booking, $payment);
    }

    public function test_an_ordinary_v2_capture_keeps_its_existing_key_and_settlement_flow(): void
    {
        [$booking, $payment] = $this->scenario('pi_recovered', false);
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldReceive('capturePaymentIntent')->once()->ordered()
            ->with('pi_recovered', 37252, 'payment-v2:'.$payment->id.':capture-primary:37252')
            ->andReturn($this->financials());
        $stripe->shouldReceive('retrievePaymentIntentFinancials')->once()->ordered()
            ->with('pi_recovered', 37252)->andReturn($this->financials());
        $stripe->shouldReceive('createTransferForCharge')->once()->ordered()->andReturn(['id' => 'tr_recovered']);
        $this->app->instance(StripeClient::class, $stripe);

        $result = app(BookingPaymentV2Service::class)->capture($booking, $payment);

        $this->assertSame(CareBookingPayment::STATUS_TRANSFERRED, $result->status);
        $this->assertSame(37252, (int) $result->amount_captured_cents);
        $this->assertSame(31335, (int) $result->caregiver_amount_cents);
    }

    private function assertCaptureFails(CareBooking $booking, CareBookingPayment $payment): void
    {
        try {
            app(BookingPaymentV2Service::class)->capture($booking, $payment);
            $this->fail('Expected capture to stop for review.');
        } catch (PaymentException $exception) {
            $this->assertNotSame('', $exception->userMessage);
        }
    }

    private function receipt(string $intentId): array
    {
        return ['oct3_payment_recovery' => [
            'new_payment_intent_id' => $intentId,
            'target_charge_cents' => 37252,
            'state' => 'authorized',
        ]];
    }

    private function financials(): array
    {
        return [
            'id' => 'pi_recovered', 'status' => 'succeeded', 'amount_received' => 37252,
            'latest_charge_id' => 'ch_recovered', 'balance_transaction_id' => 'txn_recovered',
            'processing_fee_cents' => 1110, 'fee_finalized' => true,
        ];
    }

    private function scenario(string $intentId = 'pi_recovered', bool $recovered = true): array
    {
        config()->set('services.stripe.secret', null);
        config()->set('app.timezone', 'America/New_York');
        $family = User::factory()->create(['id' => 335, 'role' => 'family']);
        $caregiver = User::factory()->create(['id' => 349, 'role' => 'caregiver']);
        $account = new FamilyAccount;
        $account->forceFill(['id' => 3, 'owner_user_id' => $family->id, 'status' => FamilyAccount::STATUS_ACTIVE])->save();
        CaregiverProfile::query()->create([
            'user_id' => $caregiver->id, 'status' => 'active', 'platform_hourly_rate' => 27,
            'stripe_connect_account_id' => 'acct_recovered',
            'stripe_charges_enabled' => true, 'stripe_payouts_enabled' => true,
        ]);
        $request = new CareRequest;
        $request->forceFill([
            'id' => 227, 'family_account_id' => 3,
            'family_user_id' => $family->id, 'title' => 'Recorded overnight visit',
            'status' => CareRequest::STATUS_FILLED, 'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => '2026-10-03 19:00:00', 'requested_end_at' => '2026-10-04 07:00:00',
            'address_line1' => '123 Test St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
        ])->save();
        $booking = new CareBooking;
        $booking->forceFill([
            'id' => 239, 'family_account_id' => 3,
            'care_request_id' => $request->id, 'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id, 'status' => CareBooking::STATUS_COMPLETED,
            'pricing_version' => '2026-08-v2', 'family_care_rate_cents' => 3000,
            'family_processing_fee_rate_cents' => 100, 'caregiver_gross_rate_cents' => 2700,
            'caregiver_fee_policy' => 'successful_charge_balance_transaction',
            'scheduled_start_at' => $request->requested_start_at, 'scheduled_end_at' => $request->requested_end_at,
            'started_at' => '2026-10-03 19:09:52', 'completed_at' => '2026-10-04 07:11:42',
            'timesheet_submitted_at' => '2026-10-04 07:11:42',
            'expected_minutes' => 720, 'worked_minutes' => 721, 'dispute_status' => 'none',
            'check_in_source' => 'manual', 'check_out_source' => 'manual',
        ])->save();
        $payment = new CareBookingPayment;
        $payment->forceFill([
            'id' => 120, 'family_account_id' => 3, 'worked_minutes' => 721,
            'care_booking_id' => $booking->id, 'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id, 'financial_reference' => $booking->financial_reference,
            'pricing_version' => $booking->pricing_version, 'status' => CareBookingPayment::STATUS_AUTHORIZED,
            'currency' => 'usd', 'stripe_payment_intent_id' => $intentId,
            'stripe_transfer_group' => 'LOLO_'.$booking->financial_reference,
            'amount_authorized_cents' => 37252, 'metadata' => $recovered ? $this->receipt($intentId) : [],
        ])->save();

        return [$booking->fresh(), $payment];
    }
}
