<?php

namespace Tests\Feature\CareHours;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareBooking;
use App\Models\CaregiverProfile;
use App\Models\CareHourPaymentOperation;
use App\Models\CareHourPurchase;
use App\Models\CareRecipient;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\User;
use App\Services\CareHours\CareHourIdentityService;
use App\Services\CareHours\CareHourLedgerService;
use App\Services\CareHours\CareHourPaymentGateway;
use App\Services\CareHours\CareHourWebhookService;
use App\Services\CareHours\PrepaidBookingFundingService;
use App\Services\CareHours\PrepaidBookingSettlementService;
use App\Services\Payments\StripeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class PrepaidShortfallProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authoritative_cancellation_revokes_ready_authorization_without_releasing_reserved_hours(): void
    {
        [$booking, $funding, $payment, $account] = $this->fixture();
        $service = app(PrepaidBookingFundingService::class);
        $this->assertTrue($service->readiness($booking)['ready']);
        $result = $this->cancellation($payment);
        $gateway = Mockery::mock(CareHourPaymentGateway::class);
        $gateway->shouldReceive('financials')->twice()->with($payment->stripe_payment_intent_id)->andReturn($result);
        app()->instance(CareHourPaymentGateway::class, $gateway);
        for ($i = 0; $i < 2; $i++) {
            $this->assertTrue(app(CareHourWebhookService::class)->handle('payment_intent.canceled', [
                'id' => $payment->stripe_payment_intent_id, 'metadata' => $result['metadata'],
            ], false));
        }
        $this->assertFalse($service->readiness($booking)['ready']);
        $this->assertSame(0, $service->protectedCardCents($funding));
        $this->assertSame('payment_attention', $funding->fresh()->status);
        $this->assertSame(60, $account->balance()['reserved_minutes']);
        $this->assertSame('cancelled', $payment->fresh()->status);
        // A delayed authorization result must not resurrect the revoked hold.
        $service->completeShortfall($payment->public_id, $this->authorization($payment));
        $this->assertSame('cancelled', $payment->fresh()->status);
        $this->assertFalse($service->readiness($booking)['ready']);
    }

    public function test_checkout_expiry_and_stale_cancellation_do_not_undo_collected_payment(): void
    {
        [$booking, $funding, $payment] = $this->fixture();
        $service = app(PrepaidBookingFundingService::class);
        $service->failShortfall($payment->public_id);
        $this->assertSame('authorized', $payment->fresh()->status);
        $payment->update(['status' => 'captured', 'captured_cents' => 3100]);
        $service->cancelShortfall($payment->public_id, $this->cancellation($payment));
        $this->assertSame('captured', $payment->fresh()->status);
        $this->assertSame(3100, $service->protectedCardCents($funding));
    }

    public function test_cancellation_rejects_a_different_payment_intent(): void
    {
        [, , $payment] = $this->fixture();
        $result = $this->cancellation($payment);
        $result['payment_intent_id'] = 'pi_unrelated';
        try {
            app(PrepaidBookingFundingService::class)->cancelShortfall($payment->public_id, $result);
            $this->fail('Different payment must be rejected.');
        } catch (PaymentException) {
            $this->assertSame('authorized', $payment->fresh()->status);
        }
    }

    public function test_expired_checkout_cannot_reopen_released_funding(): void
    {
        [$booking, $funding, $payment] = $this->fixture();
        $payment->update(['status' => 'checkout', 'stripe_payment_intent_id' => null,
            'metadata' => ['funding_revision' => $funding->revision]]);
        $service = app(PrepaidBookingFundingService::class);
        $service->release($booking);
        $service->failShortfall($payment->public_id);
        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame('released', $funding->fresh()->status);
    }

    public function test_expired_checkout_with_wrong_environment_cannot_release_hours(): void
    {
        [, $funding, $payment, $account] = $this->fixture();
        $payment->update(['status' => 'checkout', 'stripe_payment_intent_id' => null, 'stripe_checkout_session_id' => 'cs_owned']);
        try {
            app(CareHourWebhookService::class)->handle('checkout.session.expired', [
                'id' => 'cs_owned', 'metadata' => ['care_hour_domain' => 'shortfall', 'care_hour_operation_id' => $payment->public_id],
            ], true);
            $this->fail('Live-mode expiry must not mutate a test payment.');
        } catch (PaymentException) {
            $this->assertSame('checkout', $payment->fresh()->status);
            $this->assertSame(60, $account->balance()['reserved_minutes']);
        }
    }

    public function test_open_dispute_holds_readiness_and_payout_without_reversing_paid_earnings(): void
    {
        [$booking, $funding, $payment, $account, $settlement] = $this->paidFixture();
        $before = $settlement->paid_cents;
        $this->assertTrue($this->dispute($payment, 'needs_response'));
        $this->assertFalse(app(PrepaidBookingFundingService::class)->readiness($booking)['ready']);
        $this->assertSame(0, app(PrepaidBookingFundingService::class)->protectedCardCents($funding));
        $this->assertSame('funding_on_hold', $settlement->fresh()->status);
        $this->assertNull($settlement->fresh()->payable_cents);
        $this->assertNull($settlement->fresh()->paid_at);
        app(PrepaidBookingSettlementService::class)->reconcile($settlement->fresh());
        $this->assertSame($before, $settlement->fresh()->paid_cents);
        $this->assertSame(0, CareHourPaymentOperation::where('kind', 'reversal')->count());
        $this->assertSame(60, $account->balance()['used_minutes']);
    }

    public function test_won_dispute_restores_coverage_without_duplicate_payout(): void
    {
        [, $funding, $payment, , $settlement] = $this->paidFixture();
        $count = CareHourPaymentOperation::where('kind', 'transfer')->count();
        $this->dispute($payment, 'needs_response');
        $this->dispute($payment, 'won');
        $this->dispute($payment, 'won');
        $this->assertSame(3100, app(PrepaidBookingFundingService::class)->protectedCardCents($funding));
        $this->assertSame('paid', $settlement->fresh()->status);
        $this->assertSame($count, CareHourPaymentOperation::where('kind', 'transfer')->count());
        $this->assertSame(0, CareHourPaymentOperation::where('kind', 'reversal')->count());
        $this->assertSame(1, CareHourPaymentOperation::where('kind', 'dispute')->count());
    }

    public function test_lost_dispute_reverses_only_card_sourced_transfer_once_and_never_recharges(): void
    {
        [$booking, $funding, $payment, $account, $settlement] = $this->paidFixture();
        $source = $settlement->operations()->where('kind', 'transfer')->where('source_charge_id', $payment->stripe_charge_id)->firstOrFail();
        $paid = $settlement->paid_cents;
        $transfers = CareHourPaymentOperation::where('kind', 'transfer')->count();
        $this->dispute($payment, 'lost');
        $this->dispute($payment, 'lost');
        app(PrepaidBookingSettlementService::class)->reconcile($settlement->fresh());
        $this->assertSame($paid - $source->amount_cents, $settlement->fresh()->paid_cents);
        $this->assertSame(1, CareHourPaymentOperation::where('kind', 'reversal')->count());
        $this->assertSame($source->stripe_transfer_id, CareHourPaymentOperation::where('kind', 'reversal')->sole()->stripe_transfer_id);
        $this->assertSame($transfers, CareHourPaymentOperation::where('kind', 'transfer')->count());
        $this->assertSame(0, CareHourPaymentOperation::where('kind', 'refund')->count());
        $this->assertSame(60, $account->balance()['used_minutes']);
        $this->assertSame(0, $settlement->fresh()->amount_due_cents);
        $this->expectException(PaymentException::class);
        app(PrepaidBookingFundingService::class)->createShortfallCheckout($booking, $account->familyAccount->owner, '/ok', '/cancel');
    }

    public function test_partial_dispute_reverses_proportional_card_earnings_and_preserves_prepaid_source(): void
    {
        [, , $payment, , $settlement] = $this->paidFixture();
        $source = $settlement->operations()->where('kind', 'transfer')->where('source_charge_id', $payment->stripe_charge_id)->firstOrFail();
        $this->dispute($payment, 'lost', 1550);
        $reversal = CareHourPaymentOperation::where('kind', 'reversal')->sole();
        $this->assertSame((int) round($source->amount_cents / 2), $reversal->amount_cents);
        $this->assertSame($source->stripe_transfer_id, $reversal->stripe_transfer_id);
    }

    public function test_failed_dispute_reversal_is_retried_without_a_second_operation(): void
    {
        [, , $payment, , $settlement] = $this->paidFixture();
        $stripe = Mockery::mock(StripeClient::class)->makePartial();
        $stripe->shouldReceive('createTransferReversal')->once()->andThrow(new PaymentException('Temporary provider failure.'));
        app()->instance(StripeClient::class, $stripe);
        try {
            $this->dispute($payment, 'lost');
            $this->fail('Provider failure must remain retryable.');
        } catch (PaymentException) {
            $operation = CareHourPaymentOperation::where('kind', 'reversal')->sole();
            $this->assertSame('unknown', $operation->status);
        }
        app()->forgetInstance(StripeClient::class);
        app(PrepaidBookingSettlementService::class)->reconcile($settlement->fresh());
        $this->assertSame(1, CareHourPaymentOperation::where('kind', 'reversal')->count());
        $this->assertSame('succeeded', $operation->fresh()->status);
        $this->assertSame('funding_on_hold', $settlement->fresh()->status);
    }

    public function test_reopened_visit_still_recovers_a_lost_card_dispute_reversal_without_new_payout(): void
    {
        [$booking, , $payment, , $settlement] = $this->paidFixture();
        $booking->update(['status' => CareBooking::STATUS_IN_PROGRESS]);
        $transfers = $settlement->operations()->where('kind', 'transfer')->count();
        $stripe = Mockery::mock(StripeClient::class)->makePartial();
        $stripe->shouldReceive('createTransferReversal')->once()->andThrow(new PaymentException('Temporary provider failure.'));
        app()->instance(StripeClient::class, $stripe);
        try {
            $this->dispute($payment, 'lost');
            $this->fail('The initial reversal must remain unresolved.');
        } catch (PaymentException) {
            $reversal = CareHourPaymentOperation::where('kind', 'reversal')->sole();
            $this->assertSame('unknown', $reversal->status);
        }
        app()->forgetInstance(StripeClient::class);

        $this->artisan('care-hours:reconcile-bookings')->assertSuccessful();
        $this->artisan('care-hours:reconcile-bookings')->assertSuccessful();

        $this->assertSame('succeeded', $reversal->fresh()->status);
        $this->assertSame(1, CareHourPaymentOperation::where('kind', 'reversal')->count());
        $this->assertSame($transfers, $settlement->operations()->where('kind', 'transfer')->count());
        $this->assertSame('funding_on_hold', $settlement->fresh()->status);
        $this->assertNull($settlement->fresh()->payable_cents);
    }

    public function test_replayed_pending_or_lost_event_cannot_replace_won_outcome(): void
    {
        [, $funding, $payment] = $this->paidFixture();
        $this->dispute($payment, 'won');
        $this->dispute($payment, 'under_review');
        $this->dispute($payment, 'lost');
        $this->assertSame('won', CareHourPaymentOperation::where('kind', 'dispute')->sole()->status);
        $this->assertFalse(app(PrepaidBookingFundingService::class)->hasDisputedShortfall($funding));
        $this->assertSame(0, CareHourPaymentOperation::where('kind', 'reversal')->count());
    }

    public function test_disputed_payment_cannot_be_refunded_again(): void
    {
        [, , $payment] = $this->paidFixture();
        $this->dispute($payment, 'lost');
        $this->expectException(PaymentException::class);
        app(PrepaidBookingSettlementService::class)->refundUnused($payment->fresh());
    }

    public function test_late_win_after_reversal_keeps_manual_recovery_hold(): void
    {
        [, $funding, $payment, , $settlement] = $this->paidFixture();
        $this->dispute($payment, 'lost');
        $paid = $settlement->fresh()->paid_cents;
        $transfers = CareHourPaymentOperation::where('kind', 'transfer')->count();
        $this->dispute($payment, 'won');
        app(PrepaidBookingSettlementService::class)->reconcile($settlement->fresh());
        $this->assertTrue(app(PrepaidBookingFundingService::class)->hasDisputedShortfall($funding));
        $this->assertSame('funding_on_hold', $settlement->fresh()->status);
        $this->assertSame($paid, $settlement->fresh()->paid_cents);
        $this->assertSame($transfers, CareHourPaymentOperation::where('kind', 'transfer')->count());
    }

    public function test_delayed_open_event_uses_current_provider_outcome_and_can_find_by_intent(): void
    {
        [, $funding, $payment] = $this->fixture();
        $payment->update(['status' => 'captured', 'captured_cents' => 3100, 'stripe_charge_id' => null]);
        $event = $this->disputeObject($payment, 'under_review');
        $event['charge'] = 'ch_early_link';
        $latest = array_merge($event, ['status' => 'won', 'livemode' => false]);
        config(['services.stripe.bypass' => false]);
        $gateway = Mockery::mock(CareHourPaymentGateway::class);
        $gateway->shouldReceive('retrieveDispute')->once()->with('dp_shortfall_test')->andReturn($latest);
        app()->instance(CareHourPaymentGateway::class, $gateway);
        $this->assertTrue(app(CareHourWebhookService::class)->handle('charge.dispute.updated', $event, false));
        $this->assertSame('won', CareHourPaymentOperation::where('kind', 'dispute')->sole()->status);
        $this->assertSame('ch_early_link', $payment->fresh()->stripe_charge_id);
        $this->assertSame(3100, app(PrepaidBookingFundingService::class)->protectedCardCents($funding));
    }

    public function test_unknown_legacy_disputes_continue_to_original_handler(): void
    {
        $this->assertFalse(app(CareHourWebhookService::class)->handle('charge.dispute.closed', [
            'id' => 'dp_legacy', 'charge' => 'ch_legacy', 'payment_intent' => 'pi_legacy', 'status' => 'lost', 'amount' => 3100, 'currency' => 'usd',
        ], false));
        $this->assertSame(0, CareHourPaymentOperation::where('kind', 'dispute')->count());
    }

    public function test_connected_account_dispute_cannot_mutate_platform_payment(): void
    {
        [, , $payment] = $this->paidFixture();
        try {
            app(CareHourWebhookService::class)->handle('charge.dispute.closed', $this->disputeObject($payment, 'lost'), false, 'acct_other');
            $this->fail('Scope mismatch must reject the event.');
        } catch (PaymentException) {
            $this->assertSame(0, CareHourPaymentOperation::where('kind', 'dispute')->count());
            $this->assertSame(0, CareHourPaymentOperation::where('kind', 'reversal')->count());
        }
    }

    private function fixture(): array
    {
        config(['care_hours.enabled' => true, 'services.stripe.bypass' => true]);
        $owner = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $request = CareRequest::create(['family_user_id' => $owner->id, 'title' => 'Care', 'status' => 'filled',
            'address_line1' => '123 Test St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601', 'request_type' => 'one_time',
            'requested_start_at' => now()->addDay(), 'requested_end_at' => now()->addDay()->addHours(2)]);
        $recipient = CareRecipient::create(['care_request_id' => $request->id, 'full_name' => 'Margaret', 'relationship_to_family' => 'Mother']);
        $account = app(CareHourIdentityService::class)->enroll($recipient, $owner);
        $application = CareRequestApplication::create(['care_request_id' => $request->id, 'caregiver_user_id' => $caregiver->id, 'status' => 'hired', 'proposed_rate' => 27]);
        $booking = CareBooking::create(['care_request_id' => $request->id, 'care_request_application_id' => $application->id,
            'family_user_id' => $owner->id, 'caregiver_user_id' => $caregiver->id, 'status' => 'scheduled',
            'scheduled_start_at' => $request->requested_start_at, 'scheduled_end_at' => $request->requested_end_at, 'expected_minutes' => 120]);
        $purchase = CareHourPurchase::create(['public_id' => (string) Str::uuid(), 'request_key' => (string) Str::uuid(),
            'care_hour_account_id' => $account->id, 'family_account_id' => $account->family_account_id,
            'buyer_user_id' => $owner->id, 'buyer_email' => 'buyer@example.test', 'buyer_name' => 'Family',
            'paid_minutes' => 60, 'bonus_minutes' => 0, 'total_minutes' => 60, 'amount_cents' => 3100, 'stripe_charge_id' => 'ch_package_test',
            'currency' => 'usd', 'status' => 'paid', 'processing_fee_cents' => 0, 'terms_version' => 'test', 'quote' => [],
            'terms_accepted_at' => now(), 'success_url' => '/care-hours', 'cancel_url' => '/care-hours']);
        app(CareHourLedgerService::class)->issuePurchase($purchase);
        $service = app(PrepaidBookingFundingService::class);
        $service->selectForBooking($booking, $account, $owner);
        $funding = $service->prepare($booking);
        $payment = CareHourPaymentOperation::create(['public_id' => (string) Str::uuid(), 'operation_key' => (string) Str::uuid(),
            'care_hour_booking_funding_id' => $funding->id, 'kind' => 'shortfall', 'status' => 'checkout', 'amount_cents' => 3100, 'currency' => 'usd']);
        $service->completeShortfall($payment->public_id, $this->authorization($payment));

        return [$booking, $funding->fresh(), $payment->fresh(), $account];
    }

    private function paidFixture(): array
    {
        [$booking, $funding, $payment, $account] = $this->fixture();
        CaregiverProfile::create(['user_id' => $booking->caregiver_user_id, 'stripe_connect_account_id' => 'acct_test',
            'stripe_charges_enabled' => true, 'stripe_payouts_enabled' => true]);
        $booking->update(['status' => 'completed', 'worked_minutes' => 120, 'completed_at' => now()]);
        $service = app(PrepaidBookingSettlementService::class);
        $settlement = $service->reconcile($service->settle($booking));
        $this->assertSame('paid', $settlement->status);

        return [$booking, $funding->fresh(), $payment->fresh(), $account, $settlement];
    }

    private function authorization(CareHourPaymentOperation $payment): array
    {
        return ['status' => 'requires_capture', 'currency' => 'usd', 'amount_cents' => 3100, 'authorized_cents' => 3100,
            'payment_intent_id' => 'pi_shortfall_test', 'authorization_expires_at' => now()->addDays(6), 'livemode' => false,
            'metadata' => ['care_hour_domain' => 'shortfall', 'care_hour_operation_id' => $payment->public_id]];
    }

    private function cancellation(CareHourPaymentOperation $payment): array
    {
        return array_merge($this->authorization($payment), ['status' => 'canceled', 'authorized_cents' => 0]);
    }

    private function dispute(CareHourPaymentOperation $payment, string $state, int $amount = 3100): bool
    {
        return app(CareHourWebhookService::class)->handle('charge.dispute.updated', $this->disputeObject($payment, $state, $amount), false);
    }

    private function disputeObject(CareHourPaymentOperation $payment, string $state, int $amount = 3100): array
    {
        return ['id' => 'dp_shortfall_test', 'charge' => $payment->stripe_charge_id,
            'payment_intent' => $payment->stripe_payment_intent_id, 'status' => $state, 'amount' => $amount, 'currency' => 'usd'];
    }
}
