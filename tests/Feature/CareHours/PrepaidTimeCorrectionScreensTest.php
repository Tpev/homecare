<?php

namespace Tests\Feature\CareHours;

use App\Exceptions\Payments\PaymentException;
use App\Livewire\Family\ManageCareRequest;
use App\Models\CareBooking;
use App\Models\CareBookingTimeCorrection;
use App\Models\CaregiverProfile;
use App\Models\CareHourPurchase;
use App\Models\CareHourSettlement;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\User;
use App\Services\Booking\CareBookingTimeCorrectionService;
use App\Services\CareHours\CareHourIdentityService;
use App\Services\CareHours\CareHourLedgerService;
use App\Services\CareHours\CareHourPaymentGateway;
use App\Services\CareHours\PrepaidBookingFundingService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PrepaidTimeCorrectionScreensTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Real commit callbacks finalize settlement before the UI reads the amount due.
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->artisan('migrate:fresh')->assertSuccessful();
    }

    public function test_prepaid_correction_continues_to_its_checkout_and_provider_return_finishes_the_approved_correction(): void
    {
        [$booking, $account, $family, $correction] = $this->pendingCorrection();
        $return = route('family.care-hours.shortfall.return', $booking);
        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $booking->care_request_id])
            ->assertSee('Corrected visit coverage')->assertSee('From care hours')->assertSee('Remaining payment')->assertSee('$31.00')
            ->call('continueTimeCorrectionPayment', $correction->id)->assertRedirect($return)
            ->assertNotDispatched('confirm-stripe-booking-payment');
        $this->assertDatabaseHas('care_hour_payment_operations', ['kind' => 'shortfall', 'status' => 'checkout', 'amount_cents' => 3100]);
        $this->assertSame(CareBookingTimeCorrection::STATUS_PAYMENT_ACTION_REQUIRED, $correction->fresh()->status);

        $this->actingAs($family)->get($return)->assertRedirect(route('family.requests.show', $booking->care_request_id));

        $this->assertSame(CareBookingTimeCorrection::STATUS_APPLIED, $correction->fresh()->status);
        $this->assertNotNull($correction->fresh()->finalized_at);
        $this->assertSame(0, (int) CareHourSettlement::where('care_booking_id', $booking->id)->sole()->amount_due_cents);
        $this->assertDatabaseHas('care_hour_payment_operations', ['kind' => 'shortfall', 'status' => 'captured', 'captured_cents' => 3100]);
        $this->assertSame(120, $account->balance()['used_minutes']);
        $this->assertDatabaseCount('care_booking_payments', 0);
        $this->assertNull($family->fresh()->stripe_default_payment_method_id);
        $this->assertNull($account->familyAccount->fresh()->stripe_customer_id);

        $this->get($return)->assertRedirect();
        $this->assertSame(120, $account->balance()['used_minutes']);
        $this->assertDatabaseCount('care_hour_settlements', 1);
        $this->assertDatabaseHas('care_hour_payment_operations', ['kind' => 'capture', 'amount_cents' => 3100, 'status' => 'succeeded']);
        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $booking->care_request_id])
            ->assertSee('Nothing to pay for the corrected care.');
    }

    public function test_fully_covered_correction_review_names_the_hours_used_instead_of_asking_to_pay_zero(): void
    {
        [$booking, $account, $family, $correction] = $this->pendingCorrection(false, 90);
        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $booking->care_request_id])
            ->assertSee('Corrected visit coverage')->assertSee('From care hours')->assertSee(\App\Models\CareHourAccount::formatMinutes(90))
            ->assertSee('Nothing to pay for the corrected care.')->assertDontSee('Final amount if approved')
            ->assertSee('Approve '.$correction->durationLabel().' using care hours')
            ->assertDontSee('and pay $0.00')
            ->call('beginTimeCorrectionApproval', $correction->id)
            ->assertSee('Approve '.$correction->durationLabel().' using care hours?');
        $this->assertSame(120, $account->balance()['reserved_minutes']);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_return_without_confirmed_payment_cannot_finalize_correction_and_an_outsider_cannot_resume_it(): void
    {
        [$booking, $account, $family, $correction] = $this->pendingCorrection();
        $return = route('family.care-hours.shortfall.return', $booking);
        $this->actingAs(User::factory()->create(['role' => 'family']))->get($return)->assertNotFound();
        $this->actingAs($family)->get($return)->assertRedirect(route('family.requests.show', $booking->care_request_id));
        $this->assertSame(CareBookingTimeCorrection::STATUS_PAYMENT_ACTION_REQUIRED, $correction->fresh()->status);
        $this->assertNull($correction->fresh()->finalized_at);
        $this->assertSame(3100, (int) CareHourSettlement::where('care_booking_id', $booking->id)->sole()->amount_due_cents);
        $this->assertSame(120, $account->balance()['used_minutes']);
        $this->assertDatabaseMissing('care_hour_payment_operations', ['kind' => 'shortfall']);
        $this->assertDatabaseMissing('care_hour_payment_operations', ['kind' => 'capture']);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_checkout_failure_keeps_the_prepaid_correction_approved_and_shows_only_safe_recovery_copy(): void
    {
        [$booking, $account, $family, $correction] = $this->pendingCorrection();
        $this->partialMock(CareHourPaymentGateway::class, function ($mock): void {
            $mock->shouldReceive('createShortfallCheckout')->once()->andThrow(new PaymentException('Please retry the secure payment shortly.', 'Private provider diagnostic must stay hidden.'));
        });
        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $booking->care_request_id])
            ->call('continueTimeCorrectionPayment', $correction->id)->assertNoRedirect()
            ->assertNotDispatched('confirm-stripe-booking-payment')
            ->assertSee('Please retry the secure payment shortly.')->assertDontSee('Private provider diagnostic');
        $this->assertSame(CareBookingTimeCorrection::STATUS_PAYMENT_ACTION_REQUIRED, $correction->fresh()->status);
        $this->assertSame(120, $account->balance()['used_minutes']);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    private function pendingCorrection(bool $approve = true, int $correctedMinutes = 180): array
    {
        config(['care_hours.enabled' => true, 'care_hours.enrollment_enabled' => true, 'services.stripe.bypass' => true, 'marketplace.time_corrections.enabled' => true]);
        Notification::fake();
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        CaregiverProfile::create(['user_id' => $caregiver->id, 'platform_hourly_rate' => 27,
            'stripe_connect_account_id' => 'acct_correction_ui', 'stripe_charges_enabled' => true, 'stripe_payouts_enabled' => true]);
        $request = CareRequest::create(['family_user_id' => $family->id, 'title' => 'Robert care visit',
            'status' => CareRequest::STATUS_FILLED, 'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->subDay()->setTime(9, 0), 'requested_end_at' => now()->subDay()->setTime(11, 0),
            'address_line1' => '123 Test Street', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601']);
        $recipient = $request->recipient()->create(['full_name' => 'Robert Bennett', 'relationship_to_family' => 'Father']);
        $account = app(CareHourIdentityService::class)->enroll($recipient, $family);
        $purchase = CareHourPurchase::create(['public_id' => (string) Str::uuid(), 'request_key' => (string) Str::uuid(),
            'care_hour_account_id' => $account->id, 'family_account_id' => $account->family_account_id,
            'buyer_user_id' => $family->id, 'buyer_email' => $family->email, 'buyer_name' => $family->name,
            'paid_minutes' => 120, 'bonus_minutes' => 0, 'total_minutes' => 120, 'amount_cents' => 6200,
            'currency' => 'usd', 'status' => 'paid', 'processing_fee_cents' => 0, 'stripe_charge_id' => 'ch_correction_ui',
            'terms_version' => 'test', 'quote' => [], 'terms_accepted_at' => now(), 'success_url' => '/care-hours', 'cancel_url' => '/care-hours']);
        app(CareHourLedgerService::class)->issuePurchase($purchase);
        $application = CareRequestApplication::create(['care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id, 'status' => CareRequestApplication::STATUS_HIRED, 'proposed_rate' => 27]);
        $booking = CareBooking::create(['care_request_id' => $request->id, 'care_request_application_id' => $application->id,
            'family_user_id' => $family->id, 'caregiver_user_id' => $caregiver->id, 'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => $request->requested_start_at, 'scheduled_end_at' => $request->requested_end_at, 'expected_minutes' => 120]);
        $funding = app(PrepaidBookingFundingService::class);
        $funding->selectForBooking($booking, $account, $family);
        $funding->prepare($booking);
        $service = app(CareBookingTimeCorrectionService::class);
        $proposal = $service->submit($booking, $caregiver, [
            'reason_code' => CareBookingTimeCorrection::REASON_FORGOT_BOTH,
            'started_at' => $booking->scheduled_start_at->format('Y-m-d\TH:i'),
            'completed_at' => $booking->scheduled_start_at->copy()->addMinutes($correctedMinutes)->format('Y-m-d\TH:i'),
            'break_minutes' => 0, 'explanation' => 'I provided care but forgot to use the visit timer.', 'confirmed' => true,
        ], (string) Str::uuid());
        $correction = $approve ? $service->approve($proposal, $family) : $proposal;
        if ($approve) {
            $this->assertSame(CareBookingTimeCorrection::STATUS_PAYMENT_ACTION_REQUIRED, $correction->status);
        }

        return [$booking->fresh(), $account, $family, $correction];
    }
}
