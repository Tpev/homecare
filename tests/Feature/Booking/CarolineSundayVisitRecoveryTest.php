<?php

namespace Tests\Feature\Booking;

use App\Console\Commands\RecoverCarolineSundayVisit;
use App\Models\CareBooking;
use App\Models\CareBookingEvent;
use App\Models\CareBookingTimeCorrection;
use App\Models\CaregiverProfile;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestInvitation;
use App\Models\FamilyAccount;
use App\Models\SupportTicket;
use App\Models\SupportTicketActivity;
use App\Models\User;
use App\Services\Booking\CareBookingTimeCorrectionService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\Ops\SlackOpsNotificationService;
use App\Support\FamilyActionInboxBuilder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CarolineSundayVisitRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-15 09:00:00', 'America/New_York'));
        config()->set('marketplace.time_corrections.enabled', true);
        config()->set('services.stripe.bypass', true);
        Notification::fake();
        Http::preventStrayRequests();
    }

    public function test_preview_changes_no_records_and_shows_the_correct_family_and_hours(): void
    {
        $this->scenario();
        $before = $this->sourceState();

        $this->assertSame(0, Artisan::call('homecare:recover-caroline-sunday-visit'));
        $output = Artisan::output();
        $this->assertStringContainsString('"result": "preview"', $output);
        $this->assertStringContainsString('jeberdt@gmail.com', $output);
        $this->assertStringContainsString('"worked_minutes": 90', $output);
        $this->assertStringContainsString('"target_charge_cents": 4650', $output);

        $this->assertSame($before, $this->sourceState());
        $this->assertDatabaseCount('care_bookings', 0);
        $this->assertDatabaseCount('care_booking_time_corrections', 0);
        $this->assertDatabaseCount('care_booking_payments', 0);
        Notification::assertNothingSent();
    }

    public function test_apply_restores_only_this_visit_with_admin_attribution_and_pending_family_review(): void
    {
        [$family, $caregiver, $admin, $otherFamily] = $this->scenario();
        $unrelated = CareRequest::findOrFail(102)->getAttributes();

        $this->artisan('homecare:recover-caroline-sunday-visit', ['--apply' => true, '--admin' => $admin->id])->assertSuccessful();

        $booking = CareBooking::query()->sole();
        $correction = CareBookingTimeCorrection::query()->sole();
        $this->assertSame(173, $booking->care_request_id);
        $this->assertSame(196, $booking->care_request_application_id);
        $this->assertSame(431, $booking->family_user_id);
        $this->assertSame(256, $booking->caregiver_user_id);
        $this->assertNull($booking->started_at);
        $this->assertNull($booking->completed_at);
        $this->assertNull($booking->family_terms_accepted_at);
        $this->assertNull($booking->caregiver_terms_accepted_at);
        $this->assertNull($booking->family_confirmed_at);
        $this->assertNull($booking->timesheet_submitted_at);
        $this->assertSame(CareBookingTimeCorrection::STATUS_PENDING_FAMILY, $correction->status);
        $this->assertSame(90, $correction->proposed_worked_minutes);
        $this->assertSame('2026-09-13 11:00:00', $correction->proposed_started_at->toDateTimeString());
        $this->assertSame('2026-09-13 12:30:00', $correction->proposed_completed_at->toDateTimeString());
        $this->assertSame(256, $correction->requester_user_id);
        $this->assertSame(59, $correction->support_ticket_id);
        $this->assertSame(RecoverCarolineSundayVisit::CLIENT_REQUEST_ID, $correction->client_request_id);
        $this->assertDatabaseHas('support_tickets', ['id' => 59, 'care_request_id' => 173, 'care_booking_id' => $booking->id, 'family_account_id' => 37, 'counterparty_user_id' => 431, 'status' => 'open']);
        $this->assertDatabaseHas('care_requests', ['id' => 173, 'status' => 'filled']);
        $this->assertDatabaseHas('care_request_applications', ['id' => 196, 'status' => 'hired']);
        $this->assertSame($unrelated, CareRequest::findOrFail(102)->getAttributes());
        $this->assertDatabaseCount('care_booking_payments', 0);
        Notification::assertNothingSent();
        Http::assertNothingSent();

        $audit = SupportTicketActivity::query()->where('action', 'missing_visit_recovered')->sole();
        $this->assertSame($admin->id, $audit->actor_user_id);
        $this->assertSame(102, data_get($audit->metadata, 'before.ticket.care_request_id'));
        $this->assertSame(32, data_get($audit->metadata, 'before.ticket.family_account_id'));
        $this->assertDatabaseHas('care_booking_events', ['event_type' => 'time_correction_reported_by_support', 'actor_user_id' => $admin->id, 'actor_role' => 'admin']);
        $this->assertSame(0, CareBookingEvent::query()->where('actor_role', 'caregiver')->count());

        $inbox = app(FamilyActionInboxBuilder::class);
        $this->assertContains('time_correction', $inbox->buildForAccount(app(FamilyAccountContext::class)->account($family))->pluck('type')->all());
        $this->assertNotContains('time_correction', $inbox->buildForAccount(app(FamilyAccountContext::class)->account($otherFamily))->pluck('type')->all());
        $this->assertFalse(SupportTicket::query()->visibleTo($otherFamily)->whereKey(59)->exists());
        $this->artisan('homecare:auto-approve-timesheets')->assertSuccessful();
        $this->assertDatabaseCount('care_booking_payments', 0);

        $this->artisan('homecare:recover-caroline-sunday-visit', ['--apply' => true, '--admin' => $admin->id])
            ->expectsOutputToContain('"result": "already_recovered"')->assertSuccessful();
        $this->assertDatabaseCount('care_bookings', 1);
        $this->assertDatabaseCount('care_booking_time_corrections', 1);
        $this->assertSame(1, SupportTicketActivity::query()->where('action', 'missing_visit_recovered')->count());

        $applied = app(CareBookingTimeCorrectionService::class)->approve($correction->fresh(), $family);
        $this->assertSame(CareBookingTimeCorrection::STATUS_APPLIED, $applied->status);
        $this->assertSame(90, $booking->fresh()->worked_minutes);
        $this->assertNotNull($booking->fresh()->family_confirmed_at);
        $this->assertDatabaseCount('care_booking_payments', 1);
    }

    public function test_apply_requires_a_real_admin(): void
    {
        [$family] = $this->scenario();
        $before = $this->sourceState();

        $this->artisan('homecare:recover-caroline-sunday-visit', ['--apply' => true, '--admin' => $family->id])->assertFailed();

        $this->assertSame($before, $this->sourceState());
        $this->assertDatabaseCount('care_bookings', 0);
    }

    public function test_existing_booking_is_never_overwritten(): void
    {
        [, , $admin] = $this->scenario();
        $booking = CareBooking::query()->create(['care_request_id' => 173, 'care_request_application_id' => 196, 'family_account_id' => 37, 'family_user_id' => 431, 'caregiver_user_id' => 256, 'status' => 'scheduled']);
        $before = $this->sourceState();

        $this->artisan('homecare:recover-caroline-sunday-visit', ['--apply' => true, '--admin' => $admin->id])->assertFailed();

        $this->assertSame($before, $this->sourceState());
        $this->assertDatabaseCount('care_bookings', 1);
        $this->assertSame($booking->id, CareBooking::query()->sole()->id);
        $this->assertDatabaseCount('care_booking_time_corrections', 0);
    }

    public function test_wrong_family_identity_and_changed_ticket_are_rejected(): void
    {
        [$family, , $admin] = $this->scenario();
        $family->forceFill(['email' => 'wrong-family@example.test'])->saveQuietly();
        $this->artisan('homecare:recover-caroline-sunday-visit', ['--apply' => true, '--admin' => $admin->id])->assertFailed();
        $family->forceFill(['email' => 'jeberdt@gmail.com'])->saveQuietly();
        SupportTicket::findOrFail(59)->forceFill(['care_request_id' => 173])->saveQuietly();
        $this->artisan('homecare:recover-caroline-sunday-visit', ['--apply' => true, '--admin' => $admin->id])->assertFailed();
        $this->assertDatabaseCount('care_bookings', 0);
        $this->assertDatabaseCount('care_booking_time_corrections', 0);
    }

    public function test_recorded_care_overlapping_the_report_blocks_recovery(): void
    {
        [, , $admin] = $this->scenario();
        CareBooking::query()->create([
            'care_request_id' => 102, 'family_account_id' => 32, 'family_user_id' => 406,
            'caregiver_user_id' => 256, 'status' => CareBooking::STATUS_COMPLETED,
            'started_at' => '2026-09-13 12:00:00', 'completed_at' => '2026-09-13 13:00:00',
        ]);
        $before = $this->sourceState();

        $this->artisan('homecare:recover-caroline-sunday-visit', ['--apply' => true, '--admin' => $admin->id])
            ->expectsOutputToContain('These hours overlap care recorded or reported for another visit.')->assertFailed();

        $this->assertSame($before, $this->sourceState());
        $this->assertDatabaseCount('care_bookings', 1);
        $this->assertDatabaseCount('care_booking_time_corrections', 0);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_a_failed_audit_rolls_back_the_booking_and_all_source_changes(): void
    {
        [, , $admin] = $this->scenario();
        $before = $this->sourceState();
        SupportTicketActivity::creating(function (SupportTicketActivity $activity): void {
            if ($activity->action === 'missing_visit_recovered') {
                $this->assertDatabaseHas('care_bookings', ['care_request_id' => 173]);
                $this->assertDatabaseHas('support_tickets', ['id' => 59, 'care_request_id' => 173]);
                $this->assertDatabaseCount('care_booking_time_corrections', 1);
                throw ValidationException::withMessages(['recovery' => 'Simulated audit failure.']);
            }
        });

        $this->artisan('homecare:recover-caroline-sunday-visit', ['--apply' => true, '--admin' => $admin->id])
            ->expectsOutputToContain('Simulated audit failure.')->assertFailed();

        $this->assertSame($before, $this->sourceState());
        $this->assertDatabaseCount('care_bookings', 0);
        $this->assertDatabaseCount('care_booking_time_corrections', 0);
        $this->assertDatabaseCount('care_booking_payments', 0);
        $this->assertDatabaseCount('care_booking_events', 0);
        $this->assertSame(0, SupportTicketActivity::query()->where('action', 'missing_visit_recovered')->count());
        Notification::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_support_submission_cannot_be_attributed_to_a_non_admin(): void
    {
        [$family, $caregiver] = $this->scenario();
        $booking = new CareBooking(['caregiver_user_id' => $caregiver->id]);

        $this->expectException(AuthorizationException::class);
        app(CareBookingTimeCorrectionService::class)->submit($booking, $caregiver, [], (string) Str::uuid(), supportAdmin: $family);
    }

    private function sourceState(): array
    {
        return [
            CareRequest::findOrFail(173)->getAttributes(),
            CareRequestApplication::findOrFail(196)->getAttributes(),
            SupportTicket::findOrFail(59)->getAttributes(),
        ];
    }

    private function scenario(): array
    {
        $family = User::factory()->create(['id' => 431, 'role' => 'family', 'name' => 'John Grady Eberdt', 'email' => 'jeberdt@gmail.com']);
        $otherFamily = User::factory()->create(['id' => 406, 'role' => 'family', 'name' => 'John Murray']);
        $caregiver = User::factory()->create(['id' => 256, 'role' => 'caregiver', 'name' => 'Caroline Petrini-Poli']);
        $admin = User::factory()->create(['role' => 'admin']);
        foreach ([[37, $family], [32, $otherFamily]] as [$id, $owner]) {
            FamilyAccount::query()->forceCreate(['id' => $id, 'owner_user_id' => $owner->id, 'status' => 'active']);
            app(FamilyAccountContext::class)->account($owner);
        }
        CaregiverProfile::query()->create(['user_id' => 256, 'status' => 'active', 'platform_hourly_rate' => 30, 'stripe_connect_account_id' => 'acct_recovery_test', 'stripe_charges_enabled' => true, 'stripe_payouts_enabled' => true, 'stripe_connect_onboarding_completed_at' => now()]);
        $address = ['address_line1' => '123 Test Street', 'city' => 'Durham', 'state' => 'NC', 'zip' => '27703'];
        CareRequest::query()->forceCreate([...$address, 'id' => 102, 'family_user_id' => 406, 'family_account_id' => 32, 'title' => 'Other John recurring care', 'status' => 'open', 'request_type' => 'recurring']);
        CareRequest::query()->forceCreate([...$address, 'id' => 173, 'family_user_id' => 431, 'family_account_id' => 37, 'title' => 'John Eberdt Sunday visit', 'status' => 'cancelled', 'request_type' => 'one_time', 'requested_start_at' => '2026-09-13 10:00:00', 'requested_end_at' => '2026-09-13 12:00:00']);
        CareRequestApplication::query()->forceCreate(['id' => 196, 'care_request_id' => 173, 'caregiver_user_id' => 256, 'status' => 'not_selected', 'proposed_rate' => 30]);
        CareRequestInvitation::query()->forceCreate(['id' => 81, 'care_request_id' => 173, 'care_request_application_id' => 196, 'family_user_id' => 431, 'family_account_id' => 37, 'caregiver_user_id' => 256, 'status' => 'accepted']);
        SupportTicket::query()->forceCreate(['id' => 59, 'opener_user_id' => 256, 'care_request_id' => 102, 'family_account_id' => 32, 'family_visibility' => 'shared_care', 'status' => 'open', 'subject' => 'Need to log hours from Sunday', 'description' => 'I worked from 11am to 12:30pm but could not start my shift']);
        Notification::fake();
        $this->mock(SlackOpsNotificationService::class, fn ($mock) => $mock->shouldNotReceive('queueCaregiverHired'));

        return [$family, $caregiver, $admin, $otherFamily];
    }
}
