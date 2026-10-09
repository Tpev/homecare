<?php

namespace Tests\Feature\Booking;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CareBookingPaymentOperation;
use App\Models\CaregiverProfile;
use App\Models\CareRecipient;
use App\Models\CareRequest;
use App\Models\CareTask;
use App\Models\FamilyAccount;
use App\Models\User;
use App\Services\Ops\SlackOpsNotificationService;
use App\Services\Payments\StripeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CreateJohnCharlesOctoberVisitsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-09 12:00:00', 'America/New_York'));
        config()->set([
            'app.timezone' => 'UTC',
            'services.stripe.bypass' => true,
            'services.stripe.currency' => 'usd',
            'marketplace.pricing_v2.enabled' => true,
            'marketplace.pricing_v2.family_care_hourly_cents' => 3000,
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
            'stripe_connect_account_id' => 'acct_script_test',
            'stripe_charges_enabled' => true, 'stripe_payouts_enabled' => true,
        ]);
        CareRequest::withoutEvents(fn () => CareRequest::query()->forceCreate([
            'id' => 241, 'family_user_id' => 431, 'family_account_id' => 37,
            'title' => 'One-time care support for Transportation', 'status' => 'filled', 'request_type' => 'one_time',
            'address_line1' => '123 Test Street', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27609',
            'scope_of_work' => 'Transport to appointments',
        ]));
        CareRecipient::create(['care_request_id' => 241, 'full_name' => 'John Grady Eberdt', 'recipient_is_requester' => true, 'relationship_to_family' => 'Self']);
        $task = CareTask::firstOrCreate(['name' => 'Transportation']);
        CareRequest::findOrFail(241)->tasks()->sync([$task->id => ['task_note' => 'Template task note']]);
    }

    public function test_creation_preserves_eastern_dates_in_utc_and_is_idempotent_without_payment(): void
    {
        $this->actingAs(User::findOrFail(17));
        $this->artisan('homecare:create-john-charles-october-visits')->assertSuccessful();
        $firstIds = CareBooking::orderBy('id')->pluck('id')->all();
        $this->artisan('homecare:create-john-charles-october-visits')->assertSuccessful();
        $this->assertSame($firstIds, CareBooking::orderBy('id')->pluck('id')->all());
        $this->assertDatabaseCount('care_bookings', 2);
        $this->assertDatabaseCount('care_requests', 3);
        $this->assertDatabaseCount('care_request_applications', 2);
        $this->assertDatabaseCount('care_request_conversations', 2);
        $this->assertDatabaseCount('care_booking_events', 2);
        $this->assertDatabaseCount('care_booking_payments', 0);
        foreach (CareBooking::orderBy('id')->get() as $index => $booking) {
            $date = ['2026-10-02', '2026-10-05'][$index];
            $this->assertSame($date.' 20:30:00', $booking->scheduled_start_at->format('Y-m-d H:i:s'));
            $this->assertSame($date.' 21:30:00', $booking->completed_at->format('Y-m-d H:i:s'));
            $this->assertSame(60, (int) $booking->worked_minutes);
            $this->assertSame('completed', $booking->status);
            $this->assertNull($booking->family_terms_accepted_at);
            $this->assertNull($booking->caregiver_terms_accepted_at);
            $this->assertNull($booking->family_confirmed_at);
            $this->assertNull($booking->timesheet_submitted_at);
            $this->assertSame('John Grady Eberdt', $booking->careRequest->recipient->full_name);
            $this->assertSame('Template task note', $booking->careRequest->tasks->sole()->pivot->task_note);
            $this->assertSame(1, $booking->taskChecks()->count());
        }
        $this->assertSame(17, auth()->id());
        Notification::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_charge_and_rerun_create_only_two_payment_and_charge_receipts(): void
    {
        $this->artisan('homecare:create-john-charles-october-visits', ['--charge' => true])->assertSuccessful();
        $operationIds = CareBookingPaymentOperation::orderBy('id')->pluck('id')->all();
        $this->artisan('homecare:create-john-charles-october-visits', ['--charge' => true])->assertSuccessful();
        $this->assertDatabaseCount('care_bookings', 2);
        $this->assertDatabaseCount('care_booking_payments', 2);
        $this->assertSame($operationIds, CareBookingPaymentOperation::orderBy('id')->pluck('id')->all());
        $this->assertSame(2, CareBookingPaymentOperation::where('type', 'charge')->count());
        foreach (CareBookingPayment::get() as $payment) {
            $this->assertSame(3100, $payment->amount_captured_cents);
            $this->assertSame(900, $payment->initiated_by_user_id);
            $this->assertSame(0, $payment->overage_pending_cents);
            $this->assertStringStartsWith('pi_bypass_', $payment->stripe_payment_intent_id);
        }
        Notification::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_second_date_request_overlap_rolls_back_first_date_creation(): void
    {
        CareRequest::withoutEvents(fn () => CareRequest::create([
            'family_user_id' => 431, 'family_account_id' => 37, 'title' => 'Existing', 'request_type' => 'one_time',
            'address_line1' => '123 Test Street', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27609',
            'status' => 'open', 'requested_start_at' => '2026-10-05 20:00:00', 'requested_end_at' => '2026-10-05 21:00:00',
        ]));
        $this->artisan('homecare:create-john-charles-october-visits')
            ->expectsOutputToContain('already has a request overlapping 2026-10-05')->assertFailed();
        $this->assertDatabaseCount('care_bookings', 0);
        $this->assertDatabaseCount('care_requests', 2);
        $this->assertDatabaseCount('care_request_applications', 0);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_caregiver_overlap_with_another_family_blocks_creation(): void
    {
        $other = User::factory()->create(['role' => 'family']);
        $request = CareRequest::withoutEvents(fn () => CareRequest::create(['family_user_id' => $other->id, 'title' => 'Existing', 'request_type' => 'one_time', 'status' => 'filled',
            'address_line1' => '123 Test Street', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27609']));
        CareBooking::create(['care_request_id' => $request->id, 'family_user_id' => $other->id, 'caregiver_user_id' => 17,
            'status' => 'completed', 'started_at' => '2026-10-02 20:45:00', 'completed_at' => '2026-10-02 22:00:00']);
        $this->artisan('homecare:create-john-charles-october-visits')
            ->expectsOutputToContain('overlapping booking on 2026-10-02')->assertFailed();
        $this->assertDatabaseCount('care_bookings', 1);
        $this->assertDatabaseCount('care_requests', 2);
    }

    public function test_changed_identity_is_rejected_without_inserting_records(): void
    {
        User::findOrFail(431)->forceFill(['email' => 'wrong@example.test'])->saveQuietly();
        $this->artisan('homecare:create-john-charles-october-visits')
            ->expectsOutputToContain('John #431 does not match.')->assertFailed();
        $this->assertDatabaseCount('care_bookings', 0);
        $this->assertNull(auth()->user());
    }

    public function test_payment_failure_preserves_both_bookings_and_restores_actor(): void
    {
        $this->actingAs(User::findOrFail(17));
        $this->mock(StripeClient::class, fn ($mock) => $mock->shouldReceive('ensureFamilyCustomer')->once()->andThrow(new PaymentException('Test payment declined.')));
        $this->artisan('homecare:create-john-charles-october-visits', ['--charge' => true])
            ->expectsOutputToContain('Test payment declined.')->assertFailed();
        $this->assertDatabaseCount('care_bookings', 2);
        $this->assertDatabaseCount('care_requests', 3);
        $this->assertDatabaseCount('care_booking_payments', 0);
        $this->assertSame(17, auth()->id());
    }
}
