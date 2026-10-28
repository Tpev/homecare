<?php

namespace Tests\Feature\CareHours;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareBooking;
use App\Models\CareHourPurchase;
use App\Models\CareRecipient;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\User;
use App\Services\CareHours\CareHourIdentityService;
use App\Services\CareHours\CareHourLedgerService;
use App\Services\CareHours\PrepaidBookingFundingService;
use App\Services\CareHours\PrepaidBookingSettlementService;
use App\Services\CareHours\PrepaidTransferPlanner;
use App\Services\Payments\StripeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrepaidSettlementFreshBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // These fully prepaid visits must never create a card charge.
        $this->mock(StripeClient::class);
        $this->mock(PrepaidTransferPlanner::class)->shouldReceive('pay')->andReturnNull();
    }

    public function test_delayed_callback_keeps_downward_correction_and_does_not_create_a_card_shortfall(): void
    {
        [$booking, $account] = $this->completedVisit(90);
        $service = app(PrepaidBookingSettlementService::class);
        $service->settle($booking);
        $callbackBooking = $booking->fresh();

        $booking->update(['worked_minutes' => 60]);
        $corrected = $service->settle($booking);
        $balance = $account->balance();
        $revision = $corrected->revision;

        $delayed = $service->settle($callbackBooking);

        $this->assertSame(60, (int) $booking->fresh()->worked_minutes);
        $this->assertSame(60, (int) $delayed->worked_minutes);
        $this->assertSame(60, (int) $delayed->prepaid_minutes);
        $this->assertSame(0, (int) $delayed->card_minutes);
        $this->assertSame(0, (int) $delayed->amount_due_cents);
        $this->assertSame($revision, $delayed->revision);
        $this->assertSame($balance, $account->balance());
        $this->assertDatabaseCount('care_hour_payment_operations', 0);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_delayed_callback_keeps_upward_correction_instead_of_restoring_old_hours(): void
    {
        [$booking, $account] = $this->completedVisit(60);
        $service = app(PrepaidBookingSettlementService::class);
        $service->settle($booking);
        $callbackBooking = $booking->fresh();

        $booking->update(['worked_minutes' => 150]);
        $corrected = $service->settle($booking);
        $balance = $account->balance();
        $revision = $corrected->revision;

        $delayed = $service->settle($callbackBooking);

        $this->assertSame(150, (int) $delayed->worked_minutes);
        $this->assertSame((int) $corrected->prepaid_minutes, (int) $delayed->prepaid_minutes);
        $this->assertSame((int) $corrected->card_minutes, (int) $delayed->card_minutes);
        $this->assertSame((int) $corrected->amount_due_cents, (int) $delayed->amount_due_cents);
        $this->assertSame($revision, $delayed->revision);
        $this->assertSame($balance, $account->balance());
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_delayed_callback_rechecks_a_dispute_opened_after_the_booking_was_loaded(): void
    {
        [$booking, $account] = $this->completedVisit(90);
        $callbackBooking = $booking->fresh();
        $booking->update(['dispute_opened_at' => now(), 'dispute_status' => 'open']);
        $balance = $account->balance();

        try {
            app(PrepaidBookingSettlementService::class)->settle($callbackBooking);
            $this->fail('A newly disputed visit must not settle from a stale booking.');
        } catch (PaymentException $error) {
            $this->assertSame('Resolve the visit dispute before settling care hours.', $error->getMessage());
        }

        $this->assertSame($balance, $account->balance());
        $this->assertDatabaseCount('care_hour_settlements', 0);
        $this->assertDatabaseCount('care_hour_payment_operations', 0);
    }

    public function test_delayed_callback_rechecks_that_the_visit_is_still_completed(): void
    {
        [$booking, $account] = $this->completedVisit(90);
        $callbackBooking = $booking->fresh();
        $booking->update(['status' => CareBooking::STATUS_IN_PROGRESS, 'completed_at' => null]);
        $balance = $account->balance();

        try {
            app(PrepaidBookingSettlementService::class)->settle($callbackBooking);
            $this->fail('A reopened visit must not settle from a stale booking.');
        } catch (PaymentException $error) {
            $this->assertSame('Approve completed care before using prepaid hours.', $error->getMessage());
        }

        $this->assertSame($balance, $account->balance());
        $this->assertDatabaseCount('care_hour_settlements', 0);
        $this->assertDatabaseCount('care_hour_payment_operations', 0);
    }

    private function completedVisit(int $workedMinutes): array
    {
        config(['care_hours.enabled' => true]);
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $request = CareRequest::create([
            'family_user_id' => $family->id, 'title' => 'Care', 'status' => 'filled',
            'address_line1' => '123 Test St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
            'request_type' => 'one_time', 'requested_start_at' => now()->addDay(),
            'requested_end_at' => now()->addDay()->addHours(2),
        ]);
        $recipient = CareRecipient::create([
            'care_request_id' => $request->id, 'full_name' => 'Margaret', 'relationship_to_family' => 'Mother',
        ]);
        $account = app(CareHourIdentityService::class)->enroll($recipient, $family);
        $application = CareRequestApplication::create([
            'care_request_id' => $request->id, 'caregiver_user_id' => $caregiver->id,
            'status' => 'hired', 'proposed_rate' => 27,
        ]);
        $booking = CareBooking::create([
            'care_request_id' => $request->id, 'care_request_application_id' => $application->id,
            'family_user_id' => $family->id, 'caregiver_user_id' => $caregiver->id, 'status' => 'scheduled',
            'scheduled_start_at' => $request->requested_start_at, 'scheduled_end_at' => $request->requested_end_at,
            'expected_minutes' => 120,
        ]);
        $purchase = CareHourPurchase::create([
            'public_id' => (string) Str::uuid(), 'request_key' => (string) Str::uuid(),
            'care_hour_account_id' => $account->id, 'family_account_id' => $account->family_account_id,
            'buyer_user_id' => $family->id, 'buyer_email' => 'buyer@example.test', 'buyer_name' => 'Family',
            'paid_minutes' => 300, 'bonus_minutes' => 0, 'total_minutes' => 300, 'amount_cents' => 15500,
            'currency' => 'usd', 'status' => 'paid', 'processing_fee_cents' => 0, 'terms_version' => 'test',
            'quote' => [], 'terms_accepted_at' => now(), 'success_url' => '/care-hours', 'cancel_url' => '/care-hours',
        ]);
        app(CareHourLedgerService::class)->issuePurchase($purchase);
        $funding = app(PrepaidBookingFundingService::class);
        $funding->selectForBooking($booking, $account, $family);
        $funding->prepare($booking);
        $booking->update(['status' => CareBooking::STATUS_COMPLETED, 'worked_minutes' => $workedMinutes, 'completed_at' => now()]);

        return [$booking, $account];
    }
}
