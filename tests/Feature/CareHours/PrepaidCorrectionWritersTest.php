<?php

namespace Tests\Feature\CareHours;

use App\Models\CareBooking;
use App\Models\CareBookingCorrection;
use App\Models\CareBookingTimeCorrection;
use App\Models\CaregiverProfile;
use App\Models\CareHourPurchase;
use App\Models\CareHourSettlement;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Booking\BookingCorrectionService;
use App\Services\Booking\CareBookingTimeCorrectionService;
use App\Services\Booking\CaregiverCancellationService;
use App\Services\CareHours\CareHourIdentityService;
use App\Services\CareHours\CareHourLedgerService;
use App\Services\CareHours\PrepaidBookingFundingService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrepaidCorrectionWritersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Approval owns commit callbacks; exercise them without a wrapping test transaction.
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->artisan('migrate:fresh')->assertSuccessful();
    }

    public function test_family_time_correction_consumes_approved_hours_without_card_authorization(): void
    {
        [$booking, $account, $family, $caregiver] = $this->scenario();
        $service = app(CareBookingTimeCorrectionService::class);
        $proposal = $service->submit($booking, $caregiver, $this->timeInput($booking, 90), (string) Str::uuid());
        $this->assertSame(0, (int) data_get($proposal->financial_preview, 'target_charge_cents'));
        $this->assertSame(120, $account->balance()['reserved_minutes']);

        $result = $service->approve($proposal, $family);

        $this->assertSame(CareBookingTimeCorrection::STATUS_APPLIED, $result->status);
        $this->assertSame(90, $booking->fresh()->worked_minutes);
        $this->assertSame(90, $account->balance()['used_minutes']);
        $this->assertSame(210, $account->balance()['available_minutes']);
        $this->assertSame(0, $account->balance()['reserved_minutes']);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_corrected_overrun_stays_pending_until_its_separate_payment_is_covered(): void
    {
        [$booking, $account, $family, $caregiver] = $this->scenario(120);
        $service = app(CareBookingTimeCorrectionService::class);
        $proposal = $service->submit($booking, $caregiver, $this->timeInput($booking, 180), (string) Str::uuid());
        $this->assertSame(3100, (int) data_get($proposal->financial_preview, 'target_charge_cents'));

        $result = $service->approve($proposal, $family);

        $this->assertSame(CareBookingTimeCorrection::STATUS_PAYMENT_ACTION_REQUIRED, $result->status);
        $this->assertNull($result->finalized_at);
        $settlement = CareHourSettlement::where('care_booking_id', $booking->id)->sole();
        $this->assertSame(3100, (int) $settlement->amount_due_cents);
        $this->assertSame(120, $account->balance()['used_minutes']);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_admin_correction_and_repeat_use_the_prepaid_ledger_without_a_cash_charge(): void
    {
        [$booking, $account, $family, $caregiver] = $this->scenario();
        $admin = User::factory()->create(['role' => 'admin']);
        $ticket = SupportTicket::create(['opener_user_id' => $caregiver->id,
            'care_request_id' => $booking->care_request_id, 'care_booking_id' => $booking->id,
            'subject' => 'Correct visit time', 'description' => 'Both parties confirmed the visit time.',
            'category' => 'billing', 'priority' => 'normal', 'status' => SupportTicket::STATUS_OPEN]);
        $changes = ['action' => CareBookingCorrection::ACTION_COMPLETE_AND_BILL,
            'started_at' => $booking->scheduled_start_at->toDateTimeString(),
            'completed_at' => $booking->scheduled_start_at->copy()->addMinutes(90)->toDateTimeString(),
            'break_minutes' => 0, 'reason' => 'Family and caregiver confirmed the corrected ninety-minute visit.',
            'family_approved' => true];
        $service = app(BookingCorrectionService::class);
        $key = (string) Str::uuid();
        $preview = $service->preview($booking, $changes);
        $this->assertSame(0, $preview['target_charge_cents']);

        $result = $service->apply($ticket, $admin, $changes, $key);
        $repeat = $service->apply($ticket, $admin, $changes, $key);

        $this->assertSame($result->id, $repeat->id);
        $this->assertSame(CareBookingCorrection::STATUS_SUCCEEDED, $result->status);
        $this->assertSame(90, $account->balance()['used_minutes']);
        $this->assertSame(210, $account->balance()['available_minutes']);
        $this->assertDatabaseCount('care_hour_settlements', 1);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_caregiver_cancellation_reopens_request_and_returns_all_reserved_hours_once(): void
    {
        [$booking, $account, $family, $caregiver] = $this->scenario(300, false);
        $service = app(CaregiverCancellationService::class);
        $service->cancel($booking, $caregiver, 'I am unavailable for this visit.');
        $service->cancel($booking->fresh(), $caregiver, 'I am unavailable for this visit.');

        $this->assertSame(CareBooking::STATUS_CANCELLED, $booking->fresh()->status);
        $this->assertSame(CareRequest::STATUS_OPEN, $booking->careRequest->fresh()->status);
        $this->assertSame(CareRequestApplication::STATUS_WITHDRAWN, $booking->application->fresh()->status);
        $this->assertSame(300, $account->balance()['available_minutes']);
        $this->assertSame(0, $account->balance()['reserved_minutes']);
        $this->assertSame(0, $account->balance()['used_minutes']);
        $this->assertDatabaseCount('care_hour_settlements', 0);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    private function timeInput(CareBooking $booking, int $minutes): array
    {
        return ['reason_code' => CareBookingTimeCorrection::REASON_FORGOT_BOTH,
            'started_at' => $booking->scheduled_start_at->format('Y-m-d\TH:i'),
            'completed_at' => $booking->scheduled_start_at->copy()->addMinutes($minutes)->format('Y-m-d\TH:i'),
            'break_minutes' => 0, 'explanation' => 'I provided care but forgot to use the visit timer.', 'confirmed' => true];
    }

    private function scenario(int $minutes = 300, bool $past = true): array
    {
        config(['care_hours.enabled' => true, 'services.stripe.bypass' => true, 'marketplace.time_corrections.enabled' => true]);
        Notification::fake();
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        CaregiverProfile::create(['user_id' => $caregiver->id, 'platform_hourly_rate' => 27,
            'stripe_connect_account_id' => 'acct_writer_fixture', 'stripe_charges_enabled' => true, 'stripe_payouts_enabled' => true]);
        $day = $past ? now()->subDay() : now()->addDay();
        $request = CareRequest::create(['family_user_id' => $family->id, 'title' => 'Robert care visit',
            'status' => CareRequest::STATUS_FILLED, 'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => $day->copy()->setTime(9, 0), 'requested_end_at' => $day->copy()->setTime(11, 0),
            'address_line1' => '123 Test Street', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601']);
        $recipient = $request->recipient()->create(['full_name' => 'Robert Bennett', 'relationship_to_family' => 'Father']);
        $account = app(CareHourIdentityService::class)->enroll($recipient, $family);
        $purchase = CareHourPurchase::create(['public_id' => (string) Str::uuid(), 'request_key' => (string) Str::uuid(),
            'care_hour_account_id' => $account->id, 'family_account_id' => $account->family_account_id,
            'buyer_user_id' => $family->id, 'buyer_email' => $family->email, 'buyer_name' => $family->name,
            'paid_minutes' => $minutes, 'bonus_minutes' => 0, 'total_minutes' => $minutes,
            'amount_cents' => (int) round($minutes * 3100 / 60), 'currency' => 'usd', 'status' => 'paid',
            'processing_fee_cents' => 0, 'stripe_charge_id' => 'ch_writer_fixture', 'terms_version' => 'test',
            'quote' => [], 'terms_accepted_at' => now(), 'success_url' => '/care-hours', 'cancel_url' => '/care-hours']);
        app(CareHourLedgerService::class)->issuePurchase($purchase);
        $application = CareRequestApplication::create(['care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id, 'status' => CareRequestApplication::STATUS_HIRED, 'proposed_rate' => 27]);
        $booking = CareBooking::create(['care_request_id' => $request->id, 'care_request_application_id' => $application->id,
            'family_user_id' => $family->id, 'caregiver_user_id' => $caregiver->id, 'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => $request->requested_start_at, 'scheduled_end_at' => $request->requested_end_at, 'expected_minutes' => 120]);
        $funding = app(PrepaidBookingFundingService::class);
        $funding->selectForBooking($booking, $account, $family);
        $funding->prepare($booking);

        return [$booking, $account, $family, $caregiver];
    }
}
