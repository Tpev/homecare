<?php

namespace Tests\Feature\Booking;

use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\User;
use App\Services\Payments\BookingPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AutoApproveTimesheetsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_auto_approves_timesheet_after_24_hours(): void
    {
        [$booking, $payment] = $this->createCompletedBooking([
            'timesheet_submitted_at' => now()->subHours(25),
        ]);

        $this->mock(BookingPaymentService::class, function ($mock) use ($booking, $payment): void {
            $mock->shouldReceive('captureForBooking')
                ->once()
                ->with(Mockery::on(fn (CareBooking $capturedBooking): bool => $capturedBooking->id === $booking->id))
                ->andReturn($payment);
        });

        $this->artisan('homecare:auto-approve-timesheets')
            ->assertExitCode(0);

        $this->assertNotNull($booking->fresh()?->family_confirmed_at);
        $this->assertDatabaseHas('care_booking_events', [
            'care_booking_id' => $booking->id,
            'actor_role' => 'system',
            'event_type' => 'timesheet_auto_confirmed_after_24h',
        ]);
    }

    public function test_command_skips_disputed_timesheets(): void
    {
        [$booking] = $this->createCompletedBooking([
            'timesheet_submitted_at' => now()->subHours(25),
            'dispute_opened_at' => now()->subHour(),
            'dispute_status' => 'open',
        ]);

        $this->mock(BookingPaymentService::class, function ($mock): void {
            $mock->shouldNotReceive('captureForBooking');
        });

        $this->artisan('homecare:auto-approve-timesheets')
            ->assertExitCode(0);

        $this->assertNull($booking->fresh()?->family_confirmed_at);
    }

    /**
     * @param  array<string, mixed>  $bookingOverrides
     * @return array{0: CareBooking, 1: CareBookingPayment}
     */
    private function createCompletedBooking(array $bookingOverrides = []): array
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);

        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Auto approval test request',
            'status' => CareRequest::STATUS_FILLED,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->subDays(2)->setTime(10, 0),
            'requested_end_at' => now()->subDays(2)->setTime(12, 0),
            'address_line1' => '123 Test St',
            'city' => 'Durham',
            'state' => 'NC',
            'zip' => '27703',
        ]);

        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_HIRED,
            'proposed_rate' => 30,
        ]);

        $booking = CareBooking::query()->create(array_merge([
            'care_request_id' => $request->id,
            'care_request_application_id' => $application->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_COMPLETED,
            'scheduled_start_at' => now()->subDays(2)->setTime(10, 0),
            'scheduled_end_at' => now()->subDays(2)->setTime(12, 0),
            'completed_at' => now()->subDays(2)->setTime(12, 0),
            'timesheet_submitted_at' => now()->subHours(25),
            'worked_minutes' => 120,
            'dispute_status' => 'none',
        ], $bookingOverrides));

        $payment = CareBookingPayment::query()->create([
            'care_booking_id' => $booking->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBookingPayment::STATUS_AUTHORIZED,
            'currency' => 'usd',
            'stripe_customer_id' => 'cus_test',
            'stripe_payment_method_id' => 'pm_test',
            'stripe_payment_intent_id' => 'pi_test_'.$booking->id,
            'amount_authorized_cents' => 7200,
            'authorized_at' => now()->subDays(2),
        ]);

        return [$booking, $payment];
    }
}
