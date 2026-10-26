<?php

namespace Tests\Feature\Booking;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CareBookingPaymentOperation;
use App\Models\CaregiverProfile;
use App\Models\CareRecipient;
use App\Models\CareRequest;
use App\Models\FamilyAccount;
use App\Models\User;
use App\Services\Ops\SlackOpsNotificationService;
use App\Services\Payments\StripeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CreateJohnCharlesOctober20VisitTest extends TestCase
{
    use RefreshDatabase;

    private const COMMAND = 'homecare:create-john-charles-oct20-visit';

    private const APPLY = ['--apply' => true, '--expected-charge' => 3100];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-26 12:00:00', 'America/New_York'));
        config()->set([
            'app.timezone' => 'UTC', 'services.stripe.bypass' => true, 'services.stripe.currency' => 'usd',
            'marketplace.pricing_v2.enabled' => true, 'marketplace.pricing_v2.family_care_hourly_cents' => 3000,
            'marketplace.pricing_v2.family_processing_fee_hourly_cents' => 100,
            'marketplace.pricing_v2.caregiver_gross_hourly_cents' => 2700,
        ]);
        date_default_timezone_set('UTC');
        Http::preventStrayRequests();
        Notification::fake();
        $this->mock(SlackOpsNotificationService::class, function ($mock): void {
            $mock->shouldNotReceive('queueCaregiverHired');
            $mock->shouldNotReceive('queueCareRequestCreated');
        });
        User::factory()->create(['id' => 431, 'name' => 'John Grady Eberdt', 'email' => 'jeberdt@gmail.com', 'role' => 'family']);
        User::factory()->create(['id' => 17, 'name' => 'Charles Petrini-Poli', 'email' => 'charlespetrinipoli@gmail.com', 'role' => 'caregiver']);
        User::factory()->create(['id' => 900, 'name' => 'Thibaud Peverelli - Admin', 'role' => 'admin']);
        FamilyAccount::query()->forceCreate(['id' => 37, 'owner_user_id' => 431, 'status' => 'active']);
        CaregiverProfile::query()->create([
            'user_id' => 17, 'status' => 'active', 'platform_hourly_rate' => 30,
            'stripe_connect_account_id' => 'acct_oct20_test', 'stripe_charges_enabled' => true, 'stripe_payouts_enabled' => true,
        ]);
        CareRequest::withoutEvents(fn () => CareRequest::query()->forceCreate([
            'id' => 241, 'family_user_id' => 431, 'family_account_id' => 37,
            'title' => 'One-time care support for Transportation', 'status' => 'filled', 'request_type' => 'one_time',
            'address_line1' => '123 Test Street', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27609',
        ]));
        CareRecipient::create(['care_request_id' => 241, 'full_name' => 'John Grady Eberdt', 'recipient_is_requester' => true, 'relationship_to_family' => 'Self']);
    }

    public function test_preview_does_not_create_a_visit_or_contact_stripe(): void
    {
        $this->artisan(self::COMMAND)->expectsOutputToContain('"charge_cents": 3100')->assertSuccessful();
        $this->assertDatabaseCount('care_requests', 1);
        $this->assertDatabaseCount('care_bookings', 0);
        $this->assertDatabaseCount('care_booking_payments', 0);
        $this->assertDatabaseCount('care_booking_events', 0);
        Http::assertNothingSent();
        Notification::assertNothingSent();
    }

    public function test_apply_completes_the_raleigh_hour_and_settlement_once(): void
    {
        $this->actingAs(User::findOrFail(17));
        $this->artisan(self::COMMAND, self::APPLY)->assertSuccessful();
        $operations = CareBookingPaymentOperation::orderBy('id')->pluck('id')->all();
        $this->artisan(self::COMMAND, self::APPLY)->assertSuccessful();
        $this->assertSame($operations, CareBookingPaymentOperation::orderBy('id')->pluck('id')->all());
        $this->assertDatabaseCount('care_requests', 2);
        $this->assertDatabaseCount('care_bookings', 1);
        $this->assertDatabaseCount('care_booking_payments', 1);
        $this->assertSame(1, CareBookingPaymentOperation::where('type', 'charge')->count());
        $booking = CareBooking::sole();
        $this->assertSame('2026-10-20 15:30:00', $booking->started_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-20 16:30:00', $booking->completed_at->format('Y-m-d H:i:s'));
        $this->assertTrue($booking->scheduled_start_at->equalTo($booking->started_at));
        $this->assertTrue($booking->scheduled_end_at->equalTo($booking->completed_at));
        $this->assertSame(60, (int) $booking->worked_minutes);
        $this->assertSame('completed', $booking->status);
        $this->assertNotNull($booking->family_confirmed_at);
        $this->assertNull($booking->family_confirmed_by_user_id);
        $this->assertNull($booking->family_terms_accepted_at);
        $this->assertNull($booking->caregiver_terms_accepted_at);
        $this->assertNull($booking->timesheet_submitted_at);
        $this->assertSame(1, $booking->events()->where('event_type', 'historical_visit_completed_and_paid_by_admin')->where('actor_user_id', 900)->count());
        $payment = CareBookingPayment::sole();
        $this->assertSame(3100, $payment->amount_captured_cents);
        $this->assertSame(900, $payment->initiated_by_user_id);
        $this->assertSame('transferred', $payment->status);
        $this->assertNotNull($payment->stripe_transfer_id);
        $this->assertSame('finalized', $payment->fee_finalization_status);
        $this->assertSame(17, auth()->id());
        Notification::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_wrong_expected_amount_does_not_create_or_charge(): void
    {
        $this->artisan(self::COMMAND, ['--apply' => true, '--expected-charge' => 3000])
            ->expectsOutputToContain('does not match current pricing')->assertFailed();
        $this->assertDatabaseCount('care_requests', 1);
        $this->assertDatabaseCount('care_bookings', 0);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_conflicting_visit_is_rejected(): void
    {
        CareBooking::create(['care_request_id' => 241, 'family_user_id' => 431, 'family_account_id' => 37,
            'caregiver_user_id' => 17, 'status' => 'completed',
            'started_at' => '2026-10-20 15:45:00', 'completed_at' => '2026-10-20 16:45:00']);
        $this->artisan(self::COMMAND, self::APPLY)->expectsOutputToContain('overlapping booking')->assertFailed();
        $this->assertDatabaseCount('care_bookings', 1);
        $this->assertDatabaseCount('care_requests', 1);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_changed_identity_is_rejected(): void
    {
        User::findOrFail(17)->forceFill(['name' => 'Someone Else'])->saveQuietly();
        $this->artisan(self::COMMAND, self::APPLY)->expectsOutputToContain('Charles #17 does not match')->assertFailed();
        $this->assertDatabaseCount('care_bookings', 0);
        $this->assertNull(auth()->user());
    }

    public function test_authorization_failure_keeps_one_unapproved_booking_for_retry(): void
    {
        $this->mock(StripeClient::class, fn ($mock) => $mock->shouldReceive('ensureFamilyCustomer')->once()->andThrow(new PaymentException('Test card declined.')));
        $this->artisan(self::COMMAND, self::APPLY)->expectsOutputToContain('Test card declined.')->assertFailed();
        $this->assertDatabaseCount('care_bookings', 1);
        $this->assertNull(CareBooking::sole()->family_confirmed_at);
        $this->assertDatabaseCount('care_booking_payments', 0);
        $this->app->forgetInstance(StripeClient::class);
        $this->app->forgetInstance(\App\Services\Payments\BookingPaymentService::class);
        $this->app->forgetInstance(\App\Services\Payments\BookingPaymentV2Service::class);
        $this->artisan(self::COMMAND, self::APPLY)->assertSuccessful();
        $this->assertDatabaseCount('care_bookings', 1);
        $this->assertSame(3100, CareBookingPayment::sole()->amount_captured_cents);
    }

    public function test_production_refuses_simulated_payments(): void
    {
        $this->app->instance('env', 'production');
        $this->artisan(self::COMMAND, self::APPLY)->expectsOutputToContain('Stripe bypass is enabled')->assertFailed();
        $this->assertDatabaseCount('care_bookings', 0);
    }

    public function test_pending_caregiver_transfer_can_resume_without_charging_again(): void
    {
        CaregiverProfile::where('user_id', 17)->update(['stripe_payouts_enabled' => false]);
        $this->artisan(self::COMMAND, self::APPLY)->expectsOutputToContain('caregiver settlement is pending')->assertFailed();
        $payment = CareBookingPayment::sole();
        $this->assertSame(3100, $payment->amount_captured_cents);
        $this->assertNull($payment->stripe_transfer_id);
        $intent = $payment->stripe_payment_intent_id;
        $chargeIds = CareBookingPaymentOperation::where('type', 'charge')->pluck('id')->all();
        CaregiverProfile::where('user_id', 17)->update(['stripe_payouts_enabled' => true]);
        $this->artisan(self::COMMAND, self::APPLY)->assertSuccessful();
        $this->assertSame($intent, CareBookingPayment::sole()->stripe_payment_intent_id);
        $this->assertSame($chargeIds, CareBookingPaymentOperation::where('type', 'charge')->pluck('id')->all());
        $this->assertSame('transferred', CareBookingPayment::sole()->status);
        $this->assertDatabaseCount('care_bookings', 1);
        Notification::assertNothingSent();
    }

    public function test_another_running_invocation_blocks_writes(): void
    {
        $lock = \Illuminate\Support\Facades\Cache::lock('john-charles-2026-10-20-1130', 600);
        $this->assertTrue($lock->get());
        try {
            $this->artisan(self::COMMAND, self::APPLY)->expectsOutputToContain('already being processed')->assertFailed();
            $this->assertDatabaseCount('care_bookings', 0);
        } finally {
            $lock->release();
        }
    }
}
