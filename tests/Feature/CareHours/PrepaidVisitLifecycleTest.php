<?php

namespace Tests\Feature\CareHours;

use App\Livewire\Caregiver\ApplyToCareRequest;
use App\Livewire\Family\ManageCareRequest;
use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CareBookingPaymentOperation;
use App\Models\CaregiverPayoutItem;
use App\Models\CaregiverProfile;
use App\Models\CareHourPaymentOperation;
use App\Models\CareHourPurchase;
use App\Models\CareHourSettlement;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\User;
use App\Services\CareHours\CareHourIdentityService;
use App\Services\CareHours\CareHourLedgerService;
use App\Services\Earnings\CaregiverEarningsService;
use App\Services\Payments\StripeClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class PrepaidVisitLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Exercise real commit callbacks without a wrapping transaction or touching a persisted database.
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->artisan('migrate:fresh')->assertSuccessful();
    }

    public function test_two_hour_visit_uses_actual_care_and_pays_once_through_existing_actions(): void
    {
        [$family, $caregiver, $request, $application, $account] = $this->scenario();
        $this->expectSourceTransfer(5232);

        $this->assertSame(660, $account->balance()['available_minutes']);
        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('reviewHire', $application->id)->set('careHourAccountId', $account->id)
            ->call('confirmReviewedHire')->assertHasNoErrors();
        $booking = $request->booking()->firstOrFail();
        $this->assertSame(120, $account->balance()['reserved_minutes']);
        $this->assertSame(540, $account->balance()['available_minutes']);

        $this->travelTo($request->requested_start_at);
        $visit = Livewire::actingAs($caregiver)->test(ApplyToCareRequest::class, ['careRequest' => $request->id])
            ->call('acceptBookingAgreement')->set('checkInNote', 'Arrived for the scheduled visit.')
            ->call('startBooking')->assertHasNoErrors();
        $this->assertSame(CareBooking::STATUS_IN_PROGRESS, $booking->fresh()->status);

        $this->travelTo($request->requested_start_at->copy()->addMinutes(120));
        $visit->set('checkOutNote', 'Completed two hours of companionship.')->call('completeBooking')->assertHasNoErrors();
        $booking->refresh();
        $this->assertSame(CareBooking::STATUS_COMPLETED, $booking->status);
        $this->assertSame(120, $booking->worked_minutes);
        $this->assertNotNull($booking->timesheet_submitted_at);
        $this->assertNull($booking->family_confirmed_at);
        $this->assertSame(0, $account->balance()['used_minutes']);
        $this->assertDatabaseCount('care_hour_settlements', 0);
        $this->assertDatabaseCount('caregiver_payout_items', 0);

        $approval = Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('reviewCompletion')->assertSee('Approve hours')
            ->set('confirmationNote', 'The two-hour timesheet is correct.')
            ->call('confirmReviewedCompletion')->assertHasNoErrors();
        $this->assertNotNull($booking->fresh()->family_confirmed_at);
        $this->assertSame(540, $account->balance()['available_minutes']);
        $this->assertSame(0, $account->balance()['reserved_minutes']);
        $this->assertSame(120, $account->balance()['used_minutes']);

        $settlement = CareHourSettlement::where('care_booking_id', $booking->id)->sole();
        $this->assertSame('paid', $settlement->status);
        $this->assertSame(120, (int) $settlement->prepaid_minutes);
        $this->assertSame(0, (int) $settlement->card_minutes);
        $this->assertSame(5400, (int) $settlement->caregiver_gross_cents);
        $this->assertSame(168, (int) $settlement->package_fee_cents);
        $this->assertSame(5232, (int) $settlement->caregiver_net_cents);
        $this->assertSame(0, (int) $settlement->amount_due_cents);
        $item = CaregiverPayoutItem::where('care_booking_id', $booking->id)->sole();
        $this->assertSame(CaregiverPayoutItem::STATUS_PAID, $item->status);
        $this->assertSame('54.00', $item->gross_amount);
        $this->assertSame('1.68', $item->processing_fee_amount);
        $this->assertSame('52.32', $item->amount);
        $this->assertSame('paid', $item->payout->status);
        $this->assertNull($item->care_booking_payment_id);
        $earnings = app(CaregiverEarningsService::class)->forCaregiver($caregiver);
        $this->assertSame(54.0, $earnings['summary']['today_gross']);
        $this->assertSame(52.32, $earnings['summary']['paid_this_month']);
        $this->assertSame('paid', $earnings['shiftItems'][0]['status_key']);

        $approval->call('reviewCompletion')->call('confirmReviewedCompletion')->assertHasNoErrors();
        $this->assertSame(1, CareHourPaymentOperation::where('kind', 'transfer')->count());
        $this->assertSame(120, $account->balance()['used_minutes']);
        $this->assertDatabaseCount('care_booking_payments', 0);
        $this->assertNull($family->fresh()->stripe_customer_id);
    }

    public function test_family_ending_a_paused_prepaid_visit_uses_elapsed_care_and_restores_unused_hours(): void
    {
        [$family, $caregiver, $request, $application, $account] = $this->scenario();
        $this->expectSourceTransfer(2616);
        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('reviewHire', $application->id)->set('careHourAccountId', $account->id)
            ->call('confirmReviewedHire')->assertHasNoErrors();
        $booking = $request->booking()->firstOrFail();
        $this->travelTo($request->requested_start_at);
        $visit = Livewire::actingAs($caregiver)->test(ApplyToCareRequest::class, ['careRequest' => $request->id])
            ->call('acceptBookingAgreement')->call('startBooking')->assertHasNoErrors();
        $this->travelTo($request->requested_start_at->copy()->addMinutes(60));
        $visit->call('pauseBooking')->assertHasNoErrors();
        $this->travelTo($request->requested_start_at->copy()->addMinutes(75));
        $familyVisit = Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('reviewCompletion')->call('confirmReviewedCompletion')->assertHasNoErrors();

        $booking->refresh();
        $this->assertSame(60, $booking->worked_minutes);
        $this->assertSame(900, $booking->total_paused_seconds);
        $this->assertNull($booking->paused_at);
        $this->assertSame(0, $account->balance()['used_minutes']);
        $familyVisit->call('reviewCompletion')->call('confirmReviewedCompletion')->assertHasNoErrors();
        $this->assertSame(600, $account->balance()['available_minutes']);
        $this->assertSame(60, $account->balance()['used_minutes']);
        $this->assertSame(0, $account->balance()['reserved_minutes']);
        $settlement = CareHourSettlement::where('care_booking_id', $booking->id)->sole();
        $this->assertSame('paid', $settlement->status);
        $this->assertSame(2700, (int) $settlement->caregiver_gross_cents);
        $this->assertSame(84, (int) $settlement->package_fee_cents);
        $this->assertSame(2616, (int) $settlement->caregiver_net_cents);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_explicit_card_booking_still_authorizes_captures_and_pays_with_positive_care_hours(): void
    {
        [$family, $caregiver, $request, $application, $account] = $this->scenario();
        config(['marketplace.pricing_v2.enabled' => true,
            'services.stripe.bypass_processing_fee_percent' => 0,
            'services.stripe.bypass_processing_fee_fixed_cents' => 0]);
        $originalBalance = $account->balance();

        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->set('careHourAccountId', null)->call('reviewHire', $application->id)->assertSet('careHourAccountId', null)
            ->call('confirmReviewedHire')->assertHasNoErrors();
        $booking = $request->booking()->firstOrFail();
        $payment = $booking->payment()->sole();
        $this->assertSame(CareBookingPayment::STATUS_AUTHORIZED, $payment->status);
        $this->assertGreaterThanOrEqual(6200, (int) $payment->amount_authorized_cents);
        $this->assertNotNull($payment->stripe_payment_intent_id);
        $this->assertDatabaseCount('care_hour_booking_fundings', 0);
        $this->assertSame($originalBalance, $account->balance());

        $this->travelTo($request->requested_start_at);
        $visit = Livewire::actingAs($caregiver)->test(ApplyToCareRequest::class, ['careRequest' => $request->id])
            ->call('acceptBookingAgreement')->call('startBooking')->assertHasNoErrors();
        $this->assertSame(CareBooking::STATUS_IN_PROGRESS, $booking->fresh()->status);
        $this->travelTo($request->requested_start_at->copy()->addMinutes(120));
        $visit->call('completeBooking')->assertHasNoErrors();
        $this->assertSame(120, $booking->fresh()->worked_minutes);
        $this->assertSame(CareBookingPayment::STATUS_AUTHORIZED, $payment->fresh()->status);

        $approval = Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('reviewCompletion')->assertSee('Approve hours and pay $62.00')
            ->call('confirmReviewedCompletion')->assertHasNoErrors();
        $payment->refresh();
        $this->assertSame(CareBookingPayment::STATUS_TRANSFERRED, $payment->status);
        $this->assertSame(6200, (int) $payment->amount_captured_cents);
        $this->assertSame(5400, (int) $payment->caregiver_gross_amount_cents);
        $this->assertSame(0, (int) $payment->stripe_processing_fee_cents);
        $this->assertSame(5400, (int) $payment->caregiver_amount_cents);
        $this->assertNotNull($booking->fresh()->family_confirmed_at);
        $item = $booking->payoutItem()->sole();
        $this->assertSame(CaregiverPayoutItem::STATUS_PAID, $item->status);
        $this->assertSame($payment->id, $item->care_booking_payment_id);
        $this->assertSame('54.00', $item->gross_amount);
        $this->assertSame('54.00', $item->amount);
        $this->assertSame(1, $payment->operations()->where('type', CareBookingPaymentOperation::TYPE_CHARGE)->count());
        $this->assertSame(1, $payment->operations()->where('type', CareBookingPaymentOperation::TYPE_TRANSFER)->count());

        $approval->call('reviewCompletion')->call('confirmReviewedCompletion')->assertHasNoErrors();
        $this->assertSame(1, $payment->operations()->where('type', CareBookingPaymentOperation::TYPE_CHARGE)->count());
        $this->assertSame(1, $payment->operations()->where('type', CareBookingPaymentOperation::TYPE_TRANSFER)->count());
        $this->assertSame($originalBalance, $account->balance());
        $this->assertDatabaseCount('care_hour_booking_fundings', 0);
        $this->assertDatabaseCount('care_hour_settlements', 0);
        $this->assertDatabaseCount('care_hour_payment_operations', 0);
    }

    private function expectSourceTransfer(int $amountCents): void
    {
        $this->partialMock(StripeClient::class, function ($mock) use ($amountCents) {
            $mock->shouldReceive('createPrepaidTransferForCharge')->once()
                ->with('acct_care_hours_lifecycle', 'ch_care_hours_lifecycle',
                    $amountCents, 'usd', Mockery::type('array'), Mockery::type('string'))
                ->andReturn(['id' => 'tr_care_hours_lifecycle']);
        });
    }

    private function scenario(): array
    {
        $this->travelTo(Carbon::parse('2026-10-21 12:00:00'));
        config(['care_hours.enabled' => true, 'services.stripe.bypass' => true]);
        Notification::fake();
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        CaregiverProfile::create(['user_id' => $caregiver->id, 'status' => 'active',
            'screening_initial_approved_at' => now(), 'identity_verified_at' => now(),
            'identity_verification_status' => 'approved', 'bio' => str_repeat('Experienced caregiver. ', 4),
            'platform_hourly_rate' => 27, 'years_experience' => 5, 'service_area_zip' => '27601',
            'service_radius_miles' => 10, 'insurance_status' => CaregiverProfile::INSURANCE_NO,
            'stripe_connect_account_id' => 'acct_care_hours_lifecycle',
            'stripe_charges_enabled' => true, 'stripe_payouts_enabled' => true]);
        $request = CareRequest::create(['family_user_id' => $family->id, 'title' => 'Morning companionship',
            'status' => CareRequest::STATUS_OPEN, 'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addDay()->setTime(9, 0), 'requested_end_at' => now()->addDay()->setTime(11, 0),
            'address_line1' => '123 Main St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601']);
        $recipient = $request->recipient()->create(['full_name' => 'Margaret', 'relationship_to_family' => 'Mother']);
        $account = app(CareHourIdentityService::class)->enroll($recipient, $family);
        // Authoritative paid-purchase fixture: 10 paid hours plus 1 welcome hour and a finalized $9.29 fee.
        $purchase = CareHourPurchase::create(['public_id' => (string) Str::uuid(), 'request_key' => (string) Str::uuid(),
            'care_hour_account_id' => $account->id, 'family_account_id' => $account->family_account_id,
            'buyer_user_id' => $family->id, 'buyer_email' => $family->email, 'buyer_name' => $family->name,
            'paid_minutes' => 600, 'bonus_minutes' => 60, 'total_minutes' => 660, 'amount_cents' => 31000,
            'currency' => 'usd', 'status' => 'paid', 'processing_fee_cents' => 929,
            'stripe_charge_id' => 'ch_care_hours_lifecycle', 'terms_version' => 'test', 'quote' => [],
            'terms_accepted_at' => now(), 'success_url' => '/care-hours', 'cancel_url' => '/care-hours']);
        app(CareHourLedgerService::class)->issuePurchase($purchase);
        $application = CareRequestApplication::create(['care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id, 'status' => CareRequestApplication::STATUS_APPLIED,
            'proposed_rate' => 27, 'cover_note' => str_repeat('Reliable care. ', 10)]);

        return [$family, $caregiver, $request, $application, $account];
    }
}
