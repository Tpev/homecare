<?php

namespace Tests\Feature\Booking;

use App\Models\CareBooking;
use App\Models\CaregiverProfile;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\FamilyAccount;
use App\Models\User;
use App\Services\Ops\SlackOpsNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class HireCharlesForRequest241Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-28 15:30:00', 'America/New_York'));
        Notification::fake();
        Http::preventStrayRequests();
        $this->mock(SlackOpsNotificationService::class, fn ($mock) => $mock->shouldNotReceive('queueCaregiverHired'));
        User::factory()->create(['id' => 431, 'name' => 'John Grady Eberdt', 'email' => 'jeberdt@gmail.com', 'role' => 'family']);
        User::factory()->create(['id' => 17, 'name' => 'Charles Petrini-Poli', 'email' => 'charlespetrinipoli@gmail.com', 'role' => 'caregiver']);
        User::factory()->create(['id' => 900, 'name' => 'Thibaud Peverelli - Admin', 'role' => 'admin']);
        FamilyAccount::query()->forceCreate(['id' => 37, 'owner_user_id' => 431, 'status' => 'active']);
        CaregiverProfile::query()->create(['user_id' => 17, 'status' => 'active', 'platform_hourly_rate' => 30, 'screening_initial_approved_at' => now()]);
        CareRequest::withoutEvents(fn () => CareRequest::query()->forceCreate([
            'id' => 241, 'family_user_id' => 431, 'family_account_id' => 37,
            'title' => 'One-time care support for Transportation', 'status' => 'open', 'request_type' => 'one_time',
            'address_line1' => '123 Test Street', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27609',
            'created_at' => now(),
        ]));
    }

    public function test_preview_is_read_only(): void
    {
        $this->artisan('homecare:hire-charles-request-241')->expectsOutputToContain('"result": "preview"')->assertSuccessful();
        $this->assertDatabaseCount('care_request_applications', 0);
        $this->assertDatabaseCount('care_bookings', 0);
        $this->assertDatabaseHas('care_requests', ['id' => 241, 'status' => 'open']);
        Notification::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_admin_hire_preserves_flexible_timing_and_is_idempotent(): void
    {
        $this->artisan('homecare:hire-charles-request-241', ['--apply' => true, '--admin' => 900])->assertSuccessful();
        $booking = CareBooking::query()->sole();
        $this->assertSame(17, $booking->caregiver_user_id);
        $this->assertSame(431, $booking->family_user_id);
        $this->assertSame(37, $booking->family_account_id);
        $this->assertNull($booking->scheduled_start_at);
        $this->assertNull($booking->scheduled_end_at);
        $this->assertNull($booking->expected_minutes);
        $this->assertNull($booking->family_terms_accepted_at);
        $this->assertNull($booking->caregiver_terms_accepted_at);
        $this->assertNull($booking->started_at);
        $this->assertNull($booking->family_confirmed_at);
        $this->assertNotNull($booking->pricing_version);
        $this->assertSame('hired', CareRequestApplication::query()->sole()->status);
        $this->assertSame(900, CareRequestConversation::query()->sole()->started_by_user_id);
        $this->assertDatabaseHas('care_requests', ['id' => 241, 'status' => 'filled']);
        $this->assertDatabaseHas('care_booking_events', ['care_booking_id' => $booking->id, 'actor_user_id' => 900, 'actor_role' => 'admin', 'event_type' => 'booking_hired_by_admin']);
        $this->assertDatabaseCount('care_booking_payments', 0);
        $this->artisan('homecare:hire-charles-request-241', ['--apply' => true, '--admin' => 900])->expectsOutputToContain('"result": "already_hired"')->assertSuccessful();
        $this->assertDatabaseCount('care_bookings', 1);
        $this->assertDatabaseCount('care_booking_events', 1);
        Notification::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_admin_hire_cannot_assign_new_flexible_work_after_screening_suspension(): void
    {
        CaregiverProfile::where('user_id', 17)->update(['screening_suspended_at' => now()]);

        $this->artisan('homecare:hire-charles-request-241', ['--apply' => true, '--admin' => 900])->assertFailed();
        $this->assertDatabaseCount('care_bookings', 0);
        $this->assertDatabaseCount('care_request_applications', 0);
        $this->assertDatabaseHas('care_requests', ['id' => 241, 'status' => 'open']);
    }

    public function test_non_admin_and_changed_pipeline_cannot_apply(): void
    {
        $this->artisan('homecare:hire-charles-request-241', ['--apply' => true, '--admin' => 431])->assertFailed();
        CareRequestApplication::query()->create(['care_request_id' => 241, 'caregiver_user_id' => 17, 'status' => 'withdrawn', 'proposed_rate' => 30]);
        $this->artisan('homecare:hire-charles-request-241', ['--apply' => true, '--admin' => 900])->assertFailed();
        $this->assertDatabaseCount('care_bookings', 0);
        $this->assertDatabaseHas('care_requests', ['id' => 241, 'status' => 'open']);
    }

    public function test_changed_identity_is_rejected(): void
    {
        User::findOrFail(17)->forceFill(['email' => 'another-person@example.test'])->saveQuietly();
        $this->artisan('homecare:hire-charles-request-241', ['--apply' => true, '--admin' => 900])->assertFailed();
        $this->assertDatabaseCount('care_bookings', 0);
        $this->assertDatabaseCount('care_request_applications', 0);
    }

    public function test_another_application_does_not_block_hiring_charles_and_is_preserved(): void
    {
        $other = User::factory()->create(['role' => 'caregiver']);
        $application = CareRequestApplication::query()->create([
            'care_request_id' => 241, 'caregiver_user_id' => $other->id, 'status' => 'applied',
            'proposed_rate' => 27, 'cover_note' => 'Already working today.',
        ]);
        $this->artisan('homecare:hire-charles-request-241')->assertSuccessful();
        $this->assertSame('applied', $application->fresh()->status);
        $this->assertDatabaseCount('care_bookings', 0);

        $this->artisan('homecare:hire-charles-request-241', ['--apply' => true, '--admin' => 900])->assertSuccessful();
        $this->assertSame('not_selected', $application->fresh()->status);
        $this->assertSame('Already working today.', $application->fresh()->cover_note);
        $this->assertDatabaseCount('care_request_applications', 2);
        $this->assertDatabaseHas('care_request_applications', ['care_request_id' => 241, 'caregiver_user_id' => 17, 'status' => 'hired']);
        $this->assertSame(17, CareBooking::query()->sole()->caregiver_user_id);
        $this->assertDatabaseCount('care_booking_payments', 0);
        Notification::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_charles_existing_application_and_conversation_are_reused(): void
    {
        $application = CareRequestApplication::query()->create([
            'care_request_id' => 241, 'caregiver_user_id' => 17, 'status' => 'shortlisted',
            'proposed_rate' => 27, 'cover_note' => 'Original caregiver application.',
        ]);
        $conversation = CareRequestConversation::findOrCreateForApplication($application->load('careRequest'), 431);
        $this->artisan('homecare:hire-charles-request-241', ['--apply' => true, '--admin' => 900])->assertSuccessful();
        $this->assertSame($application->id, CareBooking::query()->sole()->care_request_application_id);
        $this->assertSame('hired', $application->fresh()->status);
        $this->assertSame('27.00', $application->fresh()->proposed_rate);
        $this->assertSame('Original caregiver application.', $application->fresh()->cover_note);
        $this->assertSame($conversation->id, CareRequestConversation::query()->sole()->id);
        $this->assertSame(431, $conversation->fresh()->started_by_user_id);
        $this->assertDatabaseCount('care_request_applications', 1);
        Notification::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_a_preexisting_hired_application_cannot_be_replaced(): void
    {
        $other = User::factory()->create(['role' => 'caregiver']);
        CareRequestApplication::query()->create(['care_request_id' => 241, 'caregiver_user_id' => $other->id, 'status' => 'hired', 'proposed_rate' => 27]);
        $this->artisan('homecare:hire-charles-request-241', ['--apply' => true, '--admin' => 900])->assertFailed();
        $this->assertDatabaseCount('care_bookings', 0);
        $this->assertDatabaseCount('care_request_applications', 1);
        $this->assertDatabaseHas('care_request_applications', ['caregiver_user_id' => $other->id, 'status' => 'hired']);
    }

    public function test_another_caregivers_booking_cannot_be_replaced(): void
    {
        $other = User::factory()->create(['role' => 'caregiver']);
        $booking = CareBooking::query()->create(['care_request_id' => 241, 'family_user_id' => 431,
            'family_account_id' => 37, 'caregiver_user_id' => $other->id, 'status' => 'scheduled']);
        $this->artisan('homecare:hire-charles-request-241', ['--apply' => true, '--admin' => 900])->assertFailed();
        $this->assertSame($other->id, $booking->fresh()->caregiver_user_id);
        $this->assertDatabaseCount('care_bookings', 1);
        $this->assertDatabaseCount('care_request_applications', 0);
    }
}
