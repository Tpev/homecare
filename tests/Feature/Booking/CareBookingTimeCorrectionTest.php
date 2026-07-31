<?php

namespace Tests\Feature\Booking;

use App\Livewire\Admin\SupportTicketShow;
use App\Livewire\Caregiver\ApplyToCareRequest;
use App\Livewire\Caregiver\ShiftsIndex;
use App\Livewire\Family\ManageCareRequest;
use App\Livewire\Family\RegularCareShow;
use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CareBookingTimeCorrection;
use App\Models\CaregiverProfile;
use App\Models\CarePlan;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Booking\CareBookingTimeCorrectionService;
use App\Services\Payments\BookingPaymentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CareBookingTimeCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('marketplace.time_corrections.enabled', true);
        config()->set('services.stripe.bypass', true);
        Notification::fake();
    }

    public function test_caregiver_submission_is_immutable_and_does_not_change_visit_or_payment(): void
    {
        [$family, $caregiver, $booking] = $this->scenario();
        $booking->forceFill([
            'check_in_lat' => 35.994,
            'check_in_lng' => -78.898,
            'check_in_accuracy_meters' => 12,
            'check_in_source' => 'browser',
        ])->save();
        $before = $booking->fresh()->only(['status', 'started_at', 'completed_at', 'worked_minutes', 'family_confirmed_at']);

        $correction = $this->submit($booking, $caregiver);

        $this->assertSame(CareBookingTimeCorrection::STATUS_PENDING_FAMILY, $correction->status);
        $this->assertSame($before, $booking->fresh()->only(array_keys($before)));
        $this->assertDatabaseMissing('care_booking_payments', ['care_booking_id' => $booking->id]);
        $this->assertSame(35.994, (float) data_get($correction->original_snapshot, 'booking.check_in_lat'));
        $this->assertSame('browser', data_get($correction->original_snapshot, 'booking.check_in_source'));

        $this->expectException(\LogicException::class);
        $correction->forceFill(['explanation' => 'Changed after submission'])->save();
    }

    public function test_disabled_flag_preserves_existing_support_path_and_rejects_new_workflow(): void
    {
        [$family, $caregiver, $booking, $request] = $this->scenario();
        config()->set('marketplace.time_corrections.enabled', false);

        Livewire::actingAs($caregiver)
            ->test(ApplyToCareRequest::class, ['careRequest' => $request->id])
            ->assertDontSee('Fix visit time');

        $this->expectException(ValidationException::class);
        $this->submit($booking, $caregiver);
    }

    public function test_only_assigned_caregiver_and_owning_family_can_act(): void
    {
        [$family, $caregiver, $booking] = $this->scenario();
        $otherCaregiver = User::factory()->create(['role' => 'caregiver']);

        try {
            $this->submit($booking, $otherCaregiver);
            $this->fail('Expected caregiver authorization failure.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('care_booking_time_corrections', 0);
        }

        $correction = $this->submit($booking, $caregiver);
        $otherFamily = User::factory()->create(['role' => 'family']);

        $this->expectException(AuthorizationException::class);
        app(CareBookingTimeCorrectionService::class)->approve($correction, $otherFamily);
    }

    public function test_forgotten_start_end_and_whole_visit_reasons_work_across_eligible_visit_states(): void
    {
        $cases = [
            [CareBookingTimeCorrection::REASON_FORGOT_START, CareBooking::STATUS_IN_PROGRESS],
            [CareBookingTimeCorrection::REASON_FORGOT_END, CareBooking::STATUS_PAUSED],
            [CareBookingTimeCorrection::REASON_FORGOT_BOTH, CareBooking::STATUS_COMPLETED],
        ];

        foreach ($cases as [$reason, $status]) {
            [$family, $caregiver, $booking] = $this->scenario();
            $booking->forceFill([
                'status' => $status,
                'started_at' => $status === CareBooking::STATUS_COMPLETED ? $booking->scheduled_start_at : null,
                'completed_at' => $status === CareBooking::STATUS_COMPLETED ? $booking->scheduled_end_at : null,
                'worked_minutes' => $status === CareBooking::STATUS_COMPLETED ? 180 : null,
            ])->save();
            $input = $this->input($booking);
            $input['reason_code'] = $reason;

            $correction = app(CareBookingTimeCorrectionService::class)->submit(
                $booking->fresh(),
                $caregiver,
                $input,
                (string) Str::uuid(),
            );

            $this->assertSame(CareBookingTimeCorrection::STATUS_PENDING_FAMILY, $correction->status);
            $this->assertSame($reason, $correction->reason_code);
            $this->assertSame($status, data_get($correction->original_snapshot, 'booking.status'));
            $this->assertNull($booking->fresh()->family_confirmed_at);
        }
    }

    public function test_family_can_request_changes_and_caregiver_revision_supersedes_original(): void
    {
        [$family, $caregiver, $booking] = $this->scenario();
        $service = app(CareBookingTimeCorrectionService::class);
        $first = $this->submit($booking, $caregiver);

        $service->requestChanges($first, $family, 'Please use the actual 9:15 arrival time.');
        $revisedInput = $this->input($booking);
        $revisedInput['started_at'] = $booking->scheduled_start_at->copy()->addMinutes(15)->format('Y-m-d\TH:i');
        $revisedInput['explanation'] = 'I checked my notes and arrived at 9:15 AM.';
        $second = $service->submit($booking, $caregiver, $revisedInput, (string) Str::uuid(), $first->id);

        $this->assertSame(2, $second->version);
        $this->assertSame($first->id, $second->supersedes_id);
        $this->assertSame(CareBookingTimeCorrection::STATUS_SUPERSEDED, $first->fresh()->status);
        $this->assertSame(CareBookingTimeCorrection::STATUS_PENDING_FAMILY, $second->status);
        $this->assertSame('Please use the actual 9:15 arrival time.', $first->fresh()->family_response_note);
    }

    public function test_submission_is_idempotent_and_only_one_active_chain_is_allowed(): void
    {
        [$family, $caregiver, $booking] = $this->scenario();
        $service = app(CareBookingTimeCorrectionService::class);
        $clientId = (string) Str::uuid();
        $first = $service->submit($booking, $caregiver, $this->input($booking), $clientId);
        $same = $service->submit($booking, $caregiver, $this->input($booking), $clientId);

        $this->assertSame($first->id, $same->id);
        $this->assertDatabaseCount('care_booking_time_corrections', 1);

        try {
            $service->submit($booking, $caregiver, $this->input($booking), (string) Str::uuid());
            $this->fail('Expected a second active chain to be rejected.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('care_booking_time_corrections', 1);
        }
    }

    public function test_family_approval_applies_time_preserves_gps_and_captures_once(): void
    {
        [$family, $caregiver, $booking] = $this->scenario();
        app(BookingPaymentService::class)->authorizeForBooking($booking);
        $booking->forceFill([
            'check_in_lat' => 35.994,
            'check_in_lng' => -78.898,
            'check_out_lat' => 35.995,
            'check_out_lng' => -78.897,
        ])->save();
        $correction = $this->submit($booking, $caregiver);

        $applied = app(CareBookingTimeCorrectionService::class)->approve($correction, $family);

        $this->assertSame(CareBookingTimeCorrection::STATUS_APPLIED, $applied->status);
        $booking->refresh();
        $this->assertSame(CareBooking::STATUS_COMPLETED, $booking->status);
        $this->assertSame(180, $booking->worked_minutes);
        $this->assertSame('family_approved_manual', $booking->check_in_source);
        $this->assertSame('family_approved_manual', $booking->check_out_source);
        $this->assertSame(35.994, (float) $booking->check_in_lat);
        $this->assertSame(-78.897, (float) $booking->check_out_lng);
        $this->assertNotNull($booking->family_confirmed_at);
        $this->assertContains($booking->payment->status, [
            CareBookingPayment::STATUS_CAPTURED,
            CareBookingPayment::STATUS_TRANSFERRED,
            CareBookingPayment::STATUS_TRANSFER_FAILED,
        ]);
        $this->assertDatabaseHas('care_booking_corrections', [
            'time_correction_request_id' => $correction->id,
            'source' => 'family_approved_time',
            'requester_user_id' => $caregiver->id,
            'approved_by_user_id' => $family->id,
            'status' => 'succeeded',
        ]);

        try {
            app(CareBookingTimeCorrectionService::class)->approve($correction->fresh(), $family);
            $this->fail('Expected repeat approval to be rejected.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('care_booking_corrections', 1);
        }
    }

    public function test_payment_action_keeps_approval_without_caregiver_resubmission(): void
    {
        [$family, $caregiver, $booking] = $this->scenario();
        config()->set('services.stripe.bypass', false);
        config()->set('services.stripe.secret', '');
        $correction = $this->submit($booking, $caregiver);

        $result = app(CareBookingTimeCorrectionService::class)->approve($correction, $family);

        $this->assertSame(CareBookingTimeCorrection::STATUS_PAYMENT_ACTION_REQUIRED, $result->status);
        $this->assertNotNull($result->approved_at);
        $this->assertSame($family->id, $result->approved_by_user_id);
        $this->assertDatabaseCount('care_booking_time_corrections', 1);
        $this->assertSame(CareBooking::STATUS_COMPLETED, $booking->fresh()->status);
    }

    public function test_expired_authorization_is_safely_reauthorized_after_family_approval(): void
    {
        [$family, $caregiver, $booking] = $this->scenario();
        $payment = app(BookingPaymentService::class)->authorizeForBooking($booking);
        $payment->forceFill(['authorization_expires_at' => now()->subMinute()])->save();
        $correction = $this->submit($booking->fresh(), $caregiver);

        $result = app(CareBookingTimeCorrectionService::class)->approve($correction, $family);

        $this->assertSame(CareBookingTimeCorrection::STATUS_APPLIED, $result->status);
        $this->assertContains($booking->fresh()->payment->status, [
            CareBookingPayment::STATUS_CAPTURED,
            CareBookingPayment::STATUS_TRANSFERRED,
            CareBookingPayment::STATUS_TRANSFER_FAILED,
        ]);
        $this->assertDatabaseHas('care_booking_corrections', [
            'time_correction_request_id' => $correction->id,
            'status' => 'succeeded',
        ]);
    }

    public function test_settled_payment_routes_family_agreement_to_exactly_one_admin_ticket(): void
    {
        [$family, $caregiver, $booking] = $this->scenario();
        app(BookingPaymentService::class)->authorizeForBooking($booking);
        $booking->forceFill([
            'status' => CareBooking::STATUS_COMPLETED,
            'started_at' => $booking->scheduled_start_at,
            'completed_at' => $booking->scheduled_end_at,
            'worked_minutes' => 180,
            'timesheet_submitted_at' => now()->subHour(),
            'family_confirmed_at' => now()->subHour(),
        ])->save();
        app(BookingPaymentService::class)->captureForBooking($booking->fresh());
        $beforeStartedAt = $booking->fresh()->started_at;
        $input = $this->input($booking);
        $input['started_at'] = $booking->scheduled_start_at->copy()->addMinutes(10)->format('Y-m-d\TH:i');
        $input['explanation'] = 'The family and I found that the recorded start was ten minutes early.';
        $correction = app(CareBookingTimeCorrectionService::class)->submit($booking->fresh(), $caregiver, $input, (string) Str::uuid());

        $result = app(CareBookingTimeCorrectionService::class)->approve($correction, $family);
        $again = app(CareBookingTimeCorrectionService::class)->escalate($result, $family, 'Please review the settled payment.');

        $this->assertContains($result->status, [
            CareBookingTimeCorrection::STATUS_APPROVED_ADMIN_REQUIRED,
            CareBookingTimeCorrection::STATUS_ESCALATED,
        ]);
        $this->assertSame($beforeStartedAt?->toIso8601String(), $booking->fresh()->started_at?->toIso8601String());
        $this->assertSame($result->support_ticket_id, $again->support_ticket_id);
        $this->assertDatabaseCount('support_tickets', 1);
        $this->assertDatabaseHas('support_tickets', [
            'id' => $result->support_ticket_id,
            'category' => 'time_correction',
            'priority' => 'normal',
        ]);
    }

    public function test_invalid_future_excessive_and_overlapping_times_are_rejected(): void
    {
        [$family, $caregiver, $booking] = $this->scenario();
        $service = app(CareBookingTimeCorrectionService::class);

        foreach ([
            ['started_at' => now()->addHour()->format('Y-m-d\TH:i'), 'completed_at' => now()->addHours(2)->format('Y-m-d\TH:i')],
            ['started_at' => $booking->scheduled_start_at->format('Y-m-d\TH:i'), 'completed_at' => $booking->scheduled_start_at->copy()->addHours(20)->format('Y-m-d\TH:i')],
            ['started_at' => $booking->scheduled_end_at->format('Y-m-d\TH:i'), 'completed_at' => $booking->scheduled_start_at->format('Y-m-d\TH:i')],
        ] as $changes) {
            try {
                $service->preview($booking, array_merge($this->input($booking), $changes));
                $this->fail('Expected invalid time validation.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }

        $otherRequest = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Overlapping visit',
            'status' => CareRequest::STATUS_FILLED,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => $booking->scheduled_start_at->copy()->addHour(),
            'requested_end_at' => $booking->scheduled_end_at->copy()->addHour(),
            'address_line1' => '456 Overlap Street',
            'city' => 'Durham', 'state' => 'NC', 'zip' => '27703',
        ]);
        $otherApplication = CareRequestApplication::query()->create([
            'care_request_id' => $otherRequest->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_HIRED,
            'proposed_rate' => 30,
        ]);
        CareBooking::query()->create([
            'care_request_id' => $otherRequest->id,
            'care_request_application_id' => $otherApplication->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => $otherRequest->requested_start_at,
            'scheduled_end_at' => $otherRequest->requested_end_at,
        ]);

        $this->expectException(ValidationException::class);
        $service->preview($booking, $this->input($booking));
    }

    public function test_admin_can_finish_a_family_approved_settled_correction_with_linked_audit(): void
    {
        [$family, $caregiver, $booking] = $this->scenario();
        $admin = User::factory()->create(['role' => 'admin']);
        app(BookingPaymentService::class)->authorizeForBooking($booking);
        $booking->forceFill([
            'status' => CareBooking::STATUS_COMPLETED,
            'started_at' => $booking->scheduled_start_at,
            'completed_at' => $booking->scheduled_end_at,
            'worked_minutes' => 180,
            'timesheet_submitted_at' => now()->subHour(),
            'family_confirmed_at' => now()->subHour(),
        ])->save();
        app(BookingPaymentService::class)->captureForBooking($booking->fresh());
        $input = $this->input($booking);
        $input['completed_at'] = $booking->scheduled_end_at->copy()->subMinutes(15)->format('Y-m-d\TH:i');
        $input['explanation'] = 'We agreed that care ended fifteen minutes before the scheduled end.';
        $correction = app(CareBookingTimeCorrectionService::class)->submit($booking->fresh(), $caregiver, $input, (string) Str::uuid());
        $correction = app(CareBookingTimeCorrectionService::class)->approve($correction, $family);
        $ticket = SupportTicket::query()->findOrFail($correction->support_ticket_id);

        Livewire::actingAs($admin)
            ->test(SupportTicketShow::class, ['ticket' => $ticket])
            ->assertSee('Caregiver + family collaboration')
            ->assertSet('correctionFamilyApproved', true)
            ->set('correctionImpactConfirmed', true)
            ->call('applyVisitCorrection')
            ->assertHasNoErrors();

        $this->assertSame(CareBookingTimeCorrection::STATUS_APPLIED, $correction->fresh()->status);
        $this->assertDatabaseHas('care_booking_corrections', [
            'time_correction_request_id' => $correction->id,
            'source' => 'admin_time_correction',
            'requester_user_id' => $caregiver->id,
            'approved_by_user_id' => $family->id,
            'actor_admin_user_id' => $admin->id,
            'status' => 'succeeded',
        ]);
        $this->assertSame(SupportTicket::STATUS_RESOLVED, $ticket->fresh()->status);
    }

    public function test_active_correction_blocks_timesheet_auto_approval(): void
    {
        [$family, $caregiver, $booking] = $this->scenario();
        $booking->forceFill([
            'status' => CareBooking::STATUS_COMPLETED,
            'started_at' => now()->subDays(2)->setTime(9, 0),
            'completed_at' => now()->subDays(2)->setTime(12, 0),
            'worked_minutes' => 180,
            'timesheet_submitted_at' => now()->subHours(25),
        ])->save();
        $this->submit($booking->fresh(), $caregiver);

        $this->artisan('homecare:auto-approve-timesheets')->assertSuccessful();

        $this->assertNull($booking->fresh()->family_confirmed_at);
        $this->assertDatabaseCount('care_booking_corrections', 0);
    }

    public function test_overnight_and_dst_times_use_the_booking_timezone_safely(): void
    {
        config()->set('app.timezone', 'America/New_York');
        Carbon::setTestNow(Carbon::parse('2026-11-03 12:00:00', 'America/New_York'));
        [$family, $caregiver, $booking] = $this->scenario();
        $booking->forceFill([
            'scheduled_start_at' => Carbon::parse('2026-11-01 00:30:00', 'America/New_York'),
            'scheduled_end_at' => Carbon::parse('2026-11-01 02:30:00', 'America/New_York'),
        ])->save();

        $fallBack = app(CareBookingTimeCorrectionService::class)->preview($booking->fresh(), [
            'started_at' => '2026-11-01T00:30',
            'completed_at' => '2026-11-01T02:30',
            'break_minutes' => 0,
        ]);
        $this->assertSame(180, (int) $fallBack['worked_minutes']);

        $booking->forceFill([
            'scheduled_start_at' => Carbon::parse('2026-11-01 23:00:00', 'America/New_York'),
            'scheduled_end_at' => Carbon::parse('2026-11-02 02:00:00', 'America/New_York'),
        ])->save();
        $overnight = app(CareBookingTimeCorrectionService::class)->preview($booking->fresh(), [
            'started_at' => '2026-11-01T23:00',
            'completed_at' => '2026-11-02T02:00',
            'break_minutes' => 30,
        ]);
        $this->assertSame(150, (int) $overnight['worked_minutes']);

        Carbon::setTestNow(Carbon::parse('2026-03-09 12:00:00', 'America/New_York'));
        $booking->forceFill([
            'scheduled_start_at' => Carbon::parse('2026-03-08 01:00:00', 'America/New_York'),
            'scheduled_end_at' => Carbon::parse('2026-03-08 04:00:00', 'America/New_York'),
        ])->save();
        try {
            app(CareBookingTimeCorrectionService::class)->preview($booking->fresh(), [
                'started_at' => '2026-03-08T02:30',
                'completed_at' => '2026-03-08T04:00',
                'break_minutes' => 0,
            ]);
            $this->fail('Expected nonexistent DST time to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('timeCorrectionStartedAt', $exception->errors());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_regular_care_correction_changes_only_one_occurrence_and_shows_plan_banner(): void
    {
        [$family, $caregiver, $booking] = $this->scenario();
        [$plan, $future] = $this->attachPlan($family, $caregiver, $booking);
        app(BookingPaymentService::class)->authorizeForBooking($booking);
        $futureBefore = [
            'status' => $future->status,
            'scheduled_start_at' => $future->scheduled_start_at?->toIso8601String(),
            'scheduled_end_at' => $future->scheduled_end_at?->toIso8601String(),
            'plan_schedule_version' => $future->plan_schedule_version,
        ];
        $correction = $this->submit($booking->fresh(), $caregiver);

        Livewire::actingAs($family)
            ->test(RegularCareShow::class, ['carePlan' => $plan->id])
            ->assertSee('regular-care visit')
            ->assertSee('Review corrected hours');

        app(CareBookingTimeCorrectionService::class)->approve($correction, $family);

        $future->refresh();
        $this->assertSame($futureBefore, [
            'status' => $future->status,
            'scheduled_start_at' => $future->scheduled_start_at?->toIso8601String(),
            'scheduled_end_at' => $future->scheduled_end_at?->toIso8601String(),
            'plan_schedule_version' => $future->plan_schedule_version,
        ]);
        $this->assertSame(1, $plan->fresh()->schedule_version);
        $this->assertSame(CarePlan::STATUS_ACTIVE, $plan->fresh()->status);
    }

    public function test_extra_care_plan_visit_changes_only_that_occurrence(): void
    {
        [$family, $caregiver, $booking] = $this->scenario();
        [$plan, $future] = $this->attachPlan($family, $caregiver, $booking);
        $booking->forceFill([
            'plan_visit_kind' => 'extra',
            'occurrence_key' => 'regular-care:'.$plan->id.':extra:past:09:00',
        ])->save();
        app(BookingPaymentService::class)->authorizeForBooking($booking);
        $futureBefore = $future->only(['status', 'plan_visit_kind', 'plan_schedule_version', 'occurrence_key']);

        $correction = $this->submit($booking->fresh(), $caregiver);
        app(CareBookingTimeCorrectionService::class)->approve($correction, $family);

        $this->assertSame(CareBookingTimeCorrection::STATUS_APPLIED, $correction->fresh()->status);
        $this->assertSame('extra', $booking->fresh()->plan_visit_kind);
        $this->assertSame($futureBefore, $future->fresh()->only(array_keys($futureBefore)));
        $this->assertSame(1, $plan->fresh()->schedule_version);
        $this->assertSame(CarePlan::STATUS_ACTIVE, $plan->fresh()->status);
    }

    public function test_scheduler_reminds_then_escalates_idempotently(): void
    {
        [$family, $caregiver, $booking] = $this->scenario();
        $correction = $this->submit($booking, $caregiver);
        DB::table('care_booking_time_corrections')->where('id', $correction->id)->update(['submitted_at' => now()->subHours(13)]);

        $this->artisan('homecare:process-time-corrections')->assertSuccessful();
        $this->assertNotNull($correction->fresh()->first_reminded_at);

        DB::table('care_booking_time_corrections')->where('id', $correction->id)->update(['submitted_at' => now()->subHours(49)]);
        $this->artisan('homecare:process-time-corrections')->assertSuccessful();
        $this->artisan('homecare:process-time-corrections')->assertSuccessful();

        $this->assertSame(CareBookingTimeCorrection::STATUS_ESCALATED, $correction->fresh()->status);
        $this->assertDatabaseCount('support_tickets', 1);
    }

    public function test_scheduler_escalates_a_stalled_approved_finalization_after_safe_retries(): void
    {
        [$family, $caregiver, $booking] = $this->scenario();
        $correction = $this->submit($booking, $caregiver);
        DB::table('care_booking_time_corrections')->where('id', $correction->id)->update([
            'status' => CareBookingTimeCorrection::STATUS_APPROVED_PROCESSING,
            'approved_by_user_id' => $family->id,
            'approved_at' => now()->subHour(),
            'processing_started_at' => now()->subMinutes(20),
            'processing_attempts' => 3,
            'last_error' => 'Previous automatic attempt failed.',
            'updated_at' => now(),
        ]);

        $this->artisan('homecare:process-time-corrections')->assertSuccessful();
        $this->artisan('homecare:process-time-corrections')->assertSuccessful();

        $this->assertSame(CareBookingTimeCorrection::STATUS_ESCALATED, $correction->fresh()->status);
        $this->assertNotNull($correction->fresh()->support_ticket_id);
        $this->assertDatabaseCount('support_tickets', 1);
    }

    public function test_caregiver_and_family_livewire_experiences_are_responsive_workflow_entry_points(): void
    {
        [$family, $caregiver, $booking, $request] = $this->scenario();

        Livewire::actingAs($caregiver)
            ->test(ApplyToCareRequest::class, ['careRequest' => $request->id])
            ->assertSee('I provided care — add my hours')
            ->call('openTimeCorrection')
            ->assertSet('showTimeCorrectionPanel', true)
            ->assertSee('Fix visit time')
            ->assertSee('Family approval required');

        $correction = $this->submit($booking, $caregiver);
        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->set('activeTab', 'shift')
            ->assertSee('asked you to review visit time')
            ->assertSee('Scheduled')
            ->assertSee('App recorded')
            ->assertSee('Requested')
            ->assertSee('Approve 3 hrs and pay');

        Livewire::actingAs($caregiver)
            ->test(ShiftsIndex::class)
            ->assertSee($correction->statusLabel());
    }

    /** @return array{User,User,CareBooking,CareRequest} */
    private function scenario(): array
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'active',
            'platform_hourly_rate' => 30,
            'stripe_connect_account_id' => 'acct_time_correction',
            'stripe_charges_enabled' => true,
            'stripe_payouts_enabled' => true,
            'stripe_connect_onboarding_completed_at' => now(),
        ]);
        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Barbara care visit',
            'status' => CareRequest::STATUS_FILLED,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->subDay()->setTime(9, 0),
            'requested_end_at' => now()->subDay()->setTime(12, 0),
            'address_line1' => '123 Test Street',
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
        $booking = CareBooking::query()->create([
            'care_request_id' => $request->id,
            'care_request_application_id' => $application->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => $request->requested_start_at,
            'scheduled_end_at' => $request->requested_end_at,
            'expected_minutes' => 180,
            'family_terms_accepted_at' => now()->subDays(2),
            'caregiver_terms_accepted_at' => now()->subDays(2),
        ]);

        return [$family, $caregiver, $booking, $request];
    }

    /** @return array<string, mixed> */
    private function input(CareBooking $booking): array
    {
        return [
            'reason_code' => CareBookingTimeCorrection::REASON_FORGOT_BOTH,
            'started_at' => $booking->scheduled_start_at->format('Y-m-d\TH:i'),
            'completed_at' => $booking->scheduled_end_at->format('Y-m-d\TH:i'),
            'break_minutes' => 0,
            'explanation' => 'I provided the scheduled care but forgot to use the visit timer.',
            'confirmed' => true,
        ];
    }

    private function submit(CareBooking $booking, User $caregiver): CareBookingTimeCorrection
    {
        return app(CareBookingTimeCorrectionService::class)->submit(
            $booking,
            $caregiver,
            $this->input($booking),
            (string) Str::uuid(),
        );
    }

    /** @return array{CarePlan,CareBooking} */
    private function attachPlan(User $family, User $caregiver, CareBooking $booking): array
    {
        $plan = CarePlan::query()->create([
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'source_care_request_id' => $booking->care_request_id,
            'source_care_booking_id' => $booking->id,
            'status' => CarePlan::STATUS_ACTIVE,
            'title' => 'Barbara regular care',
            'schedule_days' => [strtolower(now()->format('l'))],
            'schedule_start_time' => '09:00:00',
            'schedule_end_time' => '12:00:00',
            'starts_on' => now()->subMonth()->toDateString(),
            'timezone' => config('app.timezone'),
            'hourly_rate' => 30,
            'payment_status' => CarePlan::PAYMENT_AUTHORIZED,
            'schedule_version' => 1,
        ]);
        $booking->update([
            'care_plan_id' => $plan->id,
            'occurrence_key' => 'regular-care:'.$plan->id.':regular:past:09:00',
            'plan_visit_kind' => 'regular',
            'plan_schedule_version' => 1,
        ]);
        $booking->careRequest()->update(['care_plan_id' => $plan->id]);

        $futureRequest = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'care_plan_id' => $plan->id,
            'title' => 'Future Barbara regular visit',
            'status' => CareRequest::STATUS_FILLED,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addWeek()->setTime(9, 0),
            'requested_end_at' => now()->addWeek()->setTime(12, 0),
            'address_line1' => '123 Test Street',
            'city' => 'Durham', 'state' => 'NC', 'zip' => '27703',
        ]);
        $futureApplication = CareRequestApplication::query()->create([
            'care_request_id' => $futureRequest->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_HIRED,
            'proposed_rate' => 30,
        ]);
        $future = CareBooking::query()->create([
            'care_request_id' => $futureRequest->id,
            'care_request_application_id' => $futureApplication->id,
            'care_plan_id' => $plan->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => $futureRequest->requested_start_at,
            'scheduled_end_at' => $futureRequest->requested_end_at,
            'plan_visit_kind' => 'regular',
            'plan_schedule_version' => 1,
            'occurrence_key' => 'regular-care:'.$plan->id.':regular:future:09:00',
        ]);
        $plan->forceFill(['next_booking_id' => $future->id])->save();

        return [$plan->fresh(), $future];
    }
}
