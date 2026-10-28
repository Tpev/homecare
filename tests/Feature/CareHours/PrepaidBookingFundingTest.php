<?php

namespace Tests\Feature\CareHours;

use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CareHourAccount;
use App\Models\CareHourPaymentOperation;
use App\Models\CareHourPurchase;
use App\Models\CareRecipient;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\User;
use App\Services\CareHours\CareHourIdentityService;
use App\Services\CareHours\CareHourLedgerService;
use App\Services\CareHours\PrepaidBookingFundingService;
use App\Services\CareHours\PrepaidBookingSettlementService;
use App\Services\Payments\BookingPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PrepaidBookingFundingTest extends TestCase
{
    use RefreshDatabase;

    public function test_positive_balance_does_not_select_prepaid_and_existing_card_record_blocks_selection(): void
    {
        [$booking, $account, $owner] = $this->fixture();
        $service = app(PrepaidBookingFundingService::class);
        $this->assertFalse($service->isPrepaid($booking));
        CareBookingPayment::create(['care_booking_id' => $booking->id, 'family_user_id' => $owner->id,
            'caregiver_user_id' => $booking->caregiver_user_id, 'status' => 'authorization_failed', 'currency' => 'usd']);
        $this->expectException(ValidationException::class);
        $service->selectForBooking($booking, $account, $owner);
    }

    public function test_full_coverage_needs_no_card_and_cancel_restores_every_minute(): void
    {
        [$booking, $account, $owner] = $this->fixture();
        $service = app(PrepaidBookingFundingService::class);
        $service->selectForBooking($booking, $account, $owner);
        config(['care_hours.enabled' => false]);
        $result = app(BookingPaymentService::class)->authorizeForBooking($booking);
        $this->assertSame('ready', $result->status);
        $this->assertTrue($service->readiness($booking)['ready']);
        $this->assertDatabaseCount('care_booking_payments', 0);
        $booking->update(['status' => 'cancelled']);
        app(BookingPaymentService::class)->cancelForBooking($booking);
        $this->assertSame(300, $account->balance()['available_minutes']);
        $this->assertSame(0, $account->balance()['reserved_minutes']);
    }

    public function test_actual_minutes_consume_once_and_downward_correction_restores_original_lots(): void
    {
        [$booking, $account, $owner] = $this->fixture();
        $funding = app(PrepaidBookingFundingService::class);
        $funding->selectForBooking($booking, $account, $owner);
        $funding->prepare($booking);
        $this->assertSame(0, $account->balance()['used_minutes']);
        $booking->update(['status' => 'completed', 'worked_minutes' => 90, 'completed_at' => now()]);
        app(PrepaidBookingSettlementService::class)->settle($booking);
        app(PrepaidBookingSettlementService::class)->settle($booking);
        $this->assertSame(90, $account->balance()['used_minutes']);
        $booking->update(['worked_minutes' => 60]);
        $settlement = app(PrepaidBookingSettlementService::class)->settle($booking);
        $this->assertSame(60, $settlement->prepaid_minutes);
        $this->assertSame(240, $account->balance()['available_minutes']);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_mixed_booking_keeps_funded_split_when_another_gift_arrives(): void
    {
        [$booking, $account, $owner] = $this->fixture(60);
        $funding = app(PrepaidBookingFundingService::class);
        $record = $funding->selectForBooking($booking, $account, $owner);
        $record = $funding->prepare($booking);
        $this->assertSame(3100, $record->shortfall_cents);
        $op = $this->authorization($record->id, 3100);
        $funding->completeShortfall($op->public_id, ['status' => 'requires_capture', 'currency' => 'usd', 'amount_cents' => 3100,
            'authorized_cents' => 3100, 'payment_intent_id' => 'pi_care_hours_test', 'authorization_expires_at' => now()->addDays(6)]);
        $this->credit($account, 300);
        $prepared = $funding->prepare($booking);
        $this->assertSame(60, $prepared->reserved_minutes);
        $this->assertSame(3100, $prepared->shortfall_cents);
        $this->assertSame(300, $account->balance()['available_minutes']);
        $preview = $funding->approvalPreview($booking, 90);
        $this->assertSame(60, $preview['prepaid_minutes']);
        $this->assertSame(1550, $preview['card_cents']);
    }

    public function test_zero_approved_minutes_has_zero_earnings_and_no_cash_due(): void
    {
        [$booking, $account, $owner] = $this->fixture();
        $service = app(PrepaidBookingFundingService::class);
        $service->selectForBooking($booking, $account, $owner);
        $service->prepare($booking);
        $booking->update(['status' => 'completed', 'worked_minutes' => 0]);
        $settlement = app(PrepaidBookingSettlementService::class)->settle($booking);
        $this->assertSame(0, $settlement->caregiver_gross_cents);
        $this->assertSame(0, $settlement->prepaid_minutes);
        $this->assertSame(300, $account->balance()['available_minutes']);
    }

    public function test_held_package_blocks_prepaid_readiness(): void
    {
        [$booking, $account, $owner, $purchase] = $this->fixture();
        $service = app(PrepaidBookingFundingService::class);
        $service->selectForBooking($booking, $account, $owner);
        $service->prepare($booking);
        app(CareHourLedgerService::class)->holdPurchase($purchase, 'test');
        $this->assertFalse($service->readiness($booking)['ready']);
    }

    public function test_late_captured_shortfall_after_cancellation_is_refunded_once(): void
    {
        [$booking, $account, $owner] = $this->fixture(60);
        $service = app(PrepaidBookingFundingService::class);
        $record = $service->selectForBooking($booking, $account, $owner);
        $service->prepare($booking);
        $op = $this->authorization($record->id, 3100);
        $booking->update(['status' => 'cancelled']);
        $service->release($booking);
        $service->completeShortfall($op->public_id, ['status' => 'succeeded', 'currency' => 'usd', 'amount_cents' => 3100,
            'authorized_cents' => 0, 'amount_received' => 3100, 'payment_intent_id' => 'pi_late_care_hours_test']);
        $service->reconcileReleased($record->id);
        $service->reconcileReleased($record->id);
        $this->assertSame(3100, $op->fresh()->refunded_cents);
        $this->assertSame(60, $account->balance()['available_minutes']);
        $this->assertSame(1, CareHourPaymentOperation::where('kind', 'refund')->count());
    }

    public function test_overrun_pays_collected_portion_and_keeps_uncollected_approved_earnings(): void
    {
        [$booking, $account, $owner] = $this->fixture(120);
        \App\Models\CaregiverProfile::create(['user_id' => $booking->caregiver_user_id, 'stripe_connect_account_id' => 'acct_test',
            'stripe_charges_enabled' => true, 'stripe_payouts_enabled' => true]);
        $service = app(PrepaidBookingFundingService::class);
        $service->selectForBooking($booking, $account, $owner);
        $service->prepare($booking);
        $booking->update(['status' => 'completed', 'worked_minutes' => 180]);
        $settler = app(PrepaidBookingSettlementService::class);
        $settlement = $settler->settle($booking);
        $settlement = $settler->reconcile($settlement);
        $this->assertSame(3100, $settlement->amount_due_cents);
        $this->assertSame(5400, $settlement->paid_cents);
        $this->assertSame(8100, $settlement->caregiver_gross_cents);
        $this->assertSame('partially_settled', $settlement->status);
        $settler->reconcile($settlement);
        $this->assertSame(1, CareHourPaymentOperation::where('kind', 'transfer')->count());
    }

    public function test_delayed_package_fee_unblocks_payout_with_actual_fee_and_correction_reverses_delta(): void
    {
        [$booking, $account, $owner, $purchase] = $this->fixture(300);
        \App\Models\CaregiverProfile::create(['user_id' => $booking->caregiver_user_id, 'stripe_connect_account_id' => 'acct_test',
            'stripe_charges_enabled' => true, 'stripe_payouts_enabled' => true]);
        $purchase->update(['processing_fee_cents' => null, 'stripe_charge_id' => 'ch_package_test']);
        \App\Models\CareHourLot::where('care_hour_purchase_id', $purchase->id)->update(['processing_fee_cents' => null]);
        $service = app(PrepaidBookingFundingService::class);
        $service->selectForBooking($booking, $account, $owner);
        $service->prepare($booking);
        $booking->update(['status' => 'completed', 'worked_minutes' => 120]);
        $settler = app(PrepaidBookingSettlementService::class);
        $settlement = $settler->reconcile($settler->settle($booking));
        $this->assertSame('fee_pending', $settlement->status);
        app(CareHourLedgerService::class)->updatePurchaseFee($purchase, 500);
        $settlement = $settler->reconcile($settlement);
        $this->assertSame(200, $settlement->package_fee_cents);
        $this->assertSame(5200, $settlement->paid_cents);
        $booking->update(['worked_minutes' => 60]);
        $settlement = $settler->reconcile($settler->settle($booking));
        $this->assertSame(2600, $settlement->paid_cents);
        $this->assertSame(1, CareHourPaymentOperation::where('kind', 'reversal')->count());
        app(CareHourLedgerService::class)->updatePurchaseFee($purchase, 600);
        $this->assertSame('reconciliation_pending', $settlement->fresh()->status);
        $settlement = $settler->reconcile($settlement->fresh());
        $this->assertSame(120, $settlement->package_fee_cents);
        $this->assertSame(2580, $settlement->paid_cents);
        $this->assertSame(2, CareHourPaymentOperation::where('kind', 'reversal')->count());
        app(CareHourLedgerService::class)->holdPurchase($purchase, 'Review');
        $held = $settler->reconcile($settlement->fresh());
        $this->assertSame('funding_on_hold', $held->status);
        $this->assertSame(2580, $held->paid_cents);
        app(CareHourLedgerService::class)->releasePurchaseHold($purchase, 'Resolved');
        $restored = $settler->reconcile($held->fresh());
        $this->assertSame('paid', $restored->status);
        $this->assertSame(1, CareHourPaymentOperation::where('kind', 'transfer')->count());
    }

    public function test_plan_selection_reserves_only_due_window_and_disabling_preserves_prepared_visits(): void
    {
        [$booking, $account, $owner] = $this->fixture(300);
        $plan = \App\Models\CarePlan::create(['family_user_id' => $owner->id, 'caregiver_user_id' => $booking->caregiver_user_id,
            'source_care_request_id' => $booking->care_request_id, 'status' => 'active', 'title' => 'Care',
            'schedule_days' => [now()->addDay()->dayOfWeek], 'schedule_start_time' => '09:00', 'schedule_end_time' => '11:00',
            'starts_on' => now()->addDay()->toDateString(), 'hourly_rate' => 30]);
        $booking->update(['care_plan_id' => $plan->id]);
        $funding = app(PrepaidBookingFundingService::class);
        $funding->selectForPlan($plan, $account, $owner);
        $this->assertSame('selected', $funding->forBooking($booking)->status);
        $this->assertSame(0, $account->balance()['reserved_minutes']);
        $window = app(\App\Services\RegularCare\CarePlanPaymentWindowService::class);
        $window->preparePlan($plan);
        $this->assertSame(120, $account->balance()['reserved_minutes']);
        $funding->disablePlan($plan, $owner);
        $this->assertTrue($funding->isPrepaid($booking));
        $this->assertSame(120, $account->balance()['reserved_minutes']);
    }

    public function test_future_unprepared_plan_visit_can_return_to_default_card_mode(): void
    {
        [$booking, $account, $owner] = $this->fixture(300);
        $booking->update(['scheduled_start_at' => now()->addDays(5), 'scheduled_end_at' => now()->addDays(5)->addHours(2)]);
        $plan = \App\Models\CarePlan::create(['family_user_id' => $owner->id, 'caregiver_user_id' => $booking->caregiver_user_id,
            'source_care_request_id' => $booking->care_request_id, 'status' => 'active', 'title' => 'Care',
            'schedule_days' => [now()->addDays(5)->dayOfWeek], 'schedule_start_time' => '09:00', 'schedule_end_time' => '11:00',
            'starts_on' => now()->addDays(5)->toDateString(), 'hourly_rate' => 30]);
        $booking->update(['care_plan_id' => $plan->id]);
        $funding = app(PrepaidBookingFundingService::class);
        $funding->selectForPlan($plan, $account, $owner);
        app(\App\Services\RegularCare\CarePlanPaymentWindowService::class)->preparePlan($plan);
        $this->assertSame(0, $account->balance()['reserved_minutes']);
        $funding->disablePlan($plan, $owner);
        $this->assertFalse($funding->isPrepaid($booking));
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_checkin_guard_changes_only_explicitly_selected_bookings(): void
    {
        [$booking, $account, $owner] = $this->fixture(60);
        $booking->update(['caregiver_terms_accepted_at' => now()]);
        $policy = app(\App\Services\RegularCare\CareBookingCheckInPolicy::class);
        $this->assertTrue($policy->evaluate($booking)['allowed']);
        $funding = app(PrepaidBookingFundingService::class);
        $funding->selectForBooking($booking, $account, $owner);
        $funding->prepare($booking);
        $this->assertFalse($policy->evaluate($booking)['allowed']);
        $this->credit($account, 60);
        app(BookingPaymentService::class)->retryAuthorizationForBooking($booking);
        $this->assertTrue($policy->evaluate($booking)['allowed']);
    }

    public function test_connected_account_or_live_refund_cannot_mutate_test_platform_operation(): void
    {
        [$booking, $account, $owner] = $this->fixture(60);
        $record = app(PrepaidBookingFundingService::class)->selectForBooking($booking, $account, $owner);
        $payment = $this->authorization($record->id, 3100);
        $payment->update(['status' => 'captured', 'captured_cents' => 3100, 'stripe_charge_id' => 'ch_guard', 'stripe_payment_intent_id' => 'pi_guard']);
        $service = app(PrepaidBookingSettlementService::class);
        foreach ([[false, 'acct_other'], [true, null]] as [$live, $connected]) {
            try {
                $service->handleChargeRefundEvent(['id' => 'ch_guard', 'payment_intent' => 'pi_guard', 'currency' => 'usd', 'amount_refunded' => 3100], $live, $connected);
                $this->fail('Provider scope should be rejected.');
            } catch (\App\Exceptions\Payments\PaymentException) {
                $this->assertSame(0, $payment->fresh()->refunded_cents);
            }
        }
        $this->assertFalse($service->handleChargeRefundEvent(['id' => 'ch_unrelated'], true, 'acct_other'));
    }

    public function test_auto_approval_consumes_actual_prepaid_minutes_without_a_card_record(): void
    {
        [$booking, $account, $owner] = $this->fixture();
        $funding = app(PrepaidBookingFundingService::class);
        $funding->selectForBooking($booking, $account, $owner);
        $funding->prepare($booking);
        $booking->update(['status' => 'completed', 'worked_minutes' => 90, 'timesheet_submitted_at' => now()->subHours(25), 'completed_at' => now()->subHours(25)]);
        $this->artisan('homecare:auto-approve-timesheets')->assertSuccessful();
        $this->assertNotNull($booking->fresh()->family_confirmed_at);
        $this->assertSame(90, $account->balance()['used_minutes']);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_existing_source_booking_adopts_explicit_plan_preference_without_card_preparation(): void
    {
        [$booking, $account, $owner] = $this->fixture();
        $plan = \App\Models\CarePlan::create(['family_user_id' => $owner->id, 'caregiver_user_id' => $booking->caregiver_user_id,
            'source_care_request_id' => $booking->care_request_id, 'status' => 'active', 'title' => 'Care',
            'schedule_days' => [now()->addDay()->dayOfWeek], 'schedule_start_time' => '09:00', 'schedule_end_time' => '11:00',
            'starts_on' => now()->addDay()->toDateString(), 'hourly_rate' => 30]);
        $funding = app(PrepaidBookingFundingService::class);
        $funding->selectForPlan($plan, $account, $owner);
        $this->assertFalse($funding->isPrepaid($booking));
        $adopted = app(\App\Services\RegularCare\CarePlanOccurrenceService::class)->attachSourceRequestAsFirstVisit($plan, $booking->careRequest, $booking->application);
        $this->assertSame($booking->id, $adopted->id);
        $this->assertSame($account->id, $funding->forBooking($adopted)->care_hour_account_id);
        $this->assertDatabaseCount('care_booking_payments', 0);
        $this->assertSame(0, $account->balance()['reserved_minutes']);
    }

    public function test_longer_change_can_authorize_only_extra_cash_without_changing_original_visit_before_acceptance(): void
    {
        [$booking, $account, $owner] = $this->fixture(120);
        $funding = app(PrepaidBookingFundingService::class);
        $record = $funding->selectForBooking($booking, $account, $owner);
        $funding->prepare($booking);
        $originalEnd = $booking->scheduled_end_at->toIso8601String();
        $change = \App\Models\CareBookingChangeRequest::create(['care_booking_id' => $booking->id, 'requester_user_id' => $booking->caregiver_user_id,
            'type' => 'reschedule', 'status' => 'pending', 'reason' => 'One more hour',
            'proposed_start_at' => $booking->scheduled_start_at, 'proposed_end_at' => $booking->scheduled_end_at->copy()->addHour()]);
        $service = app(\App\Services\CareHours\CareHourScheduleChangeFundingService::class);
        $this->assertSame(3100, $service->quote($change, $owner)['extra_cents']);
        $checkout = $service->createCheckout($change, $owner, 'http://localhost/success', 'http://localhost/cancel');
        $service->refreshCoverage($change, $owner);
        $operation = CareHourPaymentOperation::where('public_id', $checkout['operation_id'])->firstOrFail();
        $this->assertSame('authorized', $operation->status);
        $this->assertSame($originalEnd, $booking->fresh()->scheduled_end_at->toIso8601String());
        $this->assertSame(120, $account->balance()['reserved_minutes']);
        $this->assertSame(0, $funding->protectedCardCents($record));
        $this->assertTrue($service->quote($change, $owner)['ready']);
        \Illuminate\Support\Facades\DB::transaction(function () use ($change, $booking, $funding) {
            $change->update(['status' => 'accepted']);
            $booking->update(['scheduled_end_at' => $change->proposed_end_at]);
            $funding->protectScheduleChange($booking);
        });
        $this->assertSame(3100, $funding->protectedCardCents($record));
        $this->assertTrue($funding->readiness($booking)['ready']);
        $booking->update(['status' => 'completed', 'worked_minutes' => 180]);
        $settler = app(PrepaidBookingSettlementService::class);
        $settlement = $settler->reconcile($settler->settle($booking));
        $this->assertSame(0, $settlement->amount_due_cents);
        $this->assertSame(3100, $operation->fresh()->captured_cents);
    }

    public function test_rejected_change_late_payment_is_refunded_without_releasing_original_hours(): void
    {
        [$booking, $account, $owner] = $this->fixture(120);
        $funding = app(PrepaidBookingFundingService::class);
        $record = $funding->selectForBooking($booking, $account, $owner);
        $funding->prepare($booking);
        $change = \App\Models\CareBookingChangeRequest::create(['care_booking_id' => $booking->id, 'requester_user_id' => $booking->caregiver_user_id,
            'type' => 'reschedule', 'status' => 'pending', 'reason' => 'One more hour',
            'proposed_start_at' => $booking->scheduled_start_at, 'proposed_end_at' => $booking->scheduled_end_at->copy()->addHour()]);
        $service = app(\App\Services\CareHours\CareHourScheduleChangeFundingService::class);
        $checkout = $service->createCheckout($change, $owner, 'http://localhost/success', 'http://localhost/cancel');
        $change->update(['status' => 'rejected']);
        $funding->completeShortfall($checkout['operation_id'], ['status' => 'succeeded', 'currency' => 'usd', 'amount_cents' => 3100,
            'amount_received' => 3100, 'authorized_cents' => 0, 'payment_intent_id' => 'pi_rejected_change']);
        $operation = CareHourPaymentOperation::where('public_id', $checkout['operation_id'])->firstOrFail();
        $this->assertSame(3100, $operation->refunded_cents);
        $this->assertSame(120, $account->balance()['reserved_minutes']);
        $this->assertSame('ready', $record->fresh()->status);
        $this->assertTrue($funding->readiness($booking)['ready']);
        $this->assertSame(0, $funding->protectedCardCents($record));
    }

    public function test_approved_shorter_visit_accepts_partial_capture_callback_replays(): void
    {
        [$booking, $account, $owner] = $this->fixture(60);
        $funding = app(PrepaidBookingFundingService::class);
        $record = $funding->selectForBooking($booking, $account, $owner);
        $funding->prepare($booking);
        $op = $this->authorization($record->id, 3100);
        $funding->completeShortfall($op->public_id, ['status' => 'requires_capture', 'currency' => 'usd', 'amount_cents' => 3100,
            'authorized_cents' => 3100, 'payment_intent_id' => 'pi_partial_visit', 'authorization_expires_at' => now()->addDays(6)]);
        $booking->update(['status' => 'completed', 'worked_minutes' => 90]);
        $settler = app(PrepaidBookingSettlementService::class);
        $settler->reconcile($settler->settle($booking));
        $this->assertSame(1550, $op->fresh()->captured_cents);
        $callback = ['status' => 'succeeded', 'currency' => 'usd', 'amount_cents' => 3100, 'amount_received' => 1550,
            'authorized_cents' => 0, 'payment_intent_id' => 'pi_partial_visit'];
        $funding->completeShortfall($op->public_id, $callback);
        $funding->completeShortfall($op->public_id, $callback);
        $this->assertSame(1550, $op->fresh()->captured_cents);
        $this->assertSame(60, $account->balance()['used_minutes']);
        $this->assertSame(1, CareHourPaymentOperation::where('kind', 'capture')->count());
    }

    public function test_recovery_releases_unaccepted_extension_when_original_care_finishes(): void
    {
        [$booking, $account, $owner] = $this->fixture(120);
        $funding = app(PrepaidBookingFundingService::class);
        $funding->selectForBooking($booking, $account, $owner);
        $funding->prepare($booking);
        $change = \App\Models\CareBookingChangeRequest::create(['care_booking_id' => $booking->id, 'requester_user_id' => $booking->caregiver_user_id,
            'type' => 'reschedule', 'status' => 'pending', 'reason' => 'One more hour',
            'proposed_start_at' => $booking->scheduled_start_at, 'proposed_end_at' => $booking->scheduled_end_at->copy()->addHour()]);
        $service = app(\App\Services\CareHours\CareHourScheduleChangeFundingService::class);
        $checkout = $service->createCheckout($change, $owner, 'http://localhost/success', 'http://localhost/cancel');
        $service->refreshCoverage($change, $owner);
        $booking->update(['status' => 'completed', 'worked_minutes' => 120]);
        $settler = app(PrepaidBookingSettlementService::class);
        $settler->reconcile($settler->settle($booking));
        $op = CareHourPaymentOperation::where('public_id', $checkout['operation_id'])->firstOrFail();
        $this->assertSame('authorized', $op->status);
        $this->artisan('care-hours:reconcile-bookings')->assertSuccessful();
        $this->assertSame('cancelled', $op->fresh()->status);
        $this->assertSame(0, $op->fresh()->captured_cents);
        $this->assertSame(120, $account->balance()['used_minutes']);
    }

    public function test_fees_exceeding_earned_care_remain_for_finance_review_and_never_mark_paid(): void
    {
        [$booking, $account, $owner, $purchase] = $this->fixture(120);
        app(CareHourLedgerService::class)->updatePurchaseFee($purchase, 6000);
        $funding = app(PrepaidBookingFundingService::class);
        $funding->selectForBooking($booking, $account, $owner);
        $funding->prepare($booking);
        $booking->update(['status' => 'completed', 'worked_minutes' => 120]);
        $settler = app(PrepaidBookingSettlementService::class);
        $settlement = $settler->reconcile($settler->settle($booking));
        $this->assertSame('payment_attention', $settlement->status);
        $this->assertNull($settlement->payable_cents);
        $this->assertNull($settlement->paid_at);
        $this->assertSame(0, CareHourPaymentOperation::where('kind', 'transfer')->count());
    }

    public function test_correction_to_no_delivered_care_restores_hours_cash_and_payout_without_caregiver_refund_fees(): void
    {
        config(['services.stripe.bypass_processing_fee_fixed_cents' => 100]);
        [$booking, $account, $owner] = $this->fixture(60);
        \App\Models\CaregiverProfile::create(['user_id' => $booking->caregiver_user_id, 'stripe_connect_account_id' => 'acct_test',
            'stripe_charges_enabled' => true, 'stripe_payouts_enabled' => true]);
        $funding = app(PrepaidBookingFundingService::class);
        $record = $funding->selectForBooking($booking, $account, $owner);
        $funding->prepare($booking);
        $op = $this->authorization($record->id, 3100);
        $funding->completeShortfall($op->public_id, ['status' => 'requires_capture', 'currency' => 'usd', 'amount_cents' => 3100,
            'authorized_cents' => 3100, 'payment_intent_id' => 'pi_zero_correction', 'authorization_expires_at' => now()->addDays(6)]);
        $booking->update(['status' => 'completed', 'worked_minutes' => 120]);
        $settler = app(PrepaidBookingSettlementService::class);
        $initial = $settler->reconcile($settler->settle($booking));
        $this->assertSame(5300, $initial->paid_cents);
        $booking->update(['worked_minutes' => 0]);
        $corrected = $settler->reconcile($settler->settle($booking));
        $this->assertSame(0, $corrected->paid_cents);
        $this->assertSame(0, $corrected->caregiver_net_cents);
        $this->assertSame(0, $corrected->card_fee_cents);
        $this->assertSame('paid', $corrected->status);
        $this->assertSame(3100, $op->fresh()->refunded_cents);
        $this->assertSame(60, $account->balance()['available_minutes']);
        $this->assertSame(0, $account->balance()['used_minutes']);
    }

    private function authorization(int $fundingId, int $amount): CareHourPaymentOperation
    {
        return CareHourPaymentOperation::create(['public_id' => (string) Str::uuid(), 'operation_key' => (string) Str::uuid(),
            'care_hour_booking_funding_id' => $fundingId, 'kind' => 'shortfall', 'status' => 'checkout', 'amount_cents' => $amount, 'currency' => 'usd']);
    }

    private function fixture(int $minutes = 300): array
    {
        config(['care_hours.enabled' => true]);
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

        return [$booking, $account, $owner, $this->credit($account, $minutes)];
    }

    private function credit(CareHourAccount $account, int $minutes): CareHourPurchase
    {
        $purchase = CareHourPurchase::create(['public_id' => (string) Str::uuid(), 'request_key' => (string) Str::uuid(),
            'care_hour_account_id' => $account->id, 'family_account_id' => $account->family_account_id,
            'buyer_user_id' => $account->familyAccount->owner_user_id, 'buyer_email' => 'buyer@example.test', 'buyer_name' => 'Family',
            'paid_minutes' => $minutes, 'bonus_minutes' => 0, 'total_minutes' => $minutes, 'amount_cents' => (int) round($minutes * 3100 / 60),
            'currency' => 'usd', 'status' => 'paid', 'processing_fee_cents' => 0, 'terms_version' => 'test', 'quote' => [],
            'terms_accepted_at' => now(), 'success_url' => '/care-hours', 'cancel_url' => '/care-hours']);
        app(CareHourLedgerService::class)->issuePurchase($purchase);

        return $purchase;
    }
}
