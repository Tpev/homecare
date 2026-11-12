<?php

namespace Tests\Feature\Notifications;

use App\Jobs\SendManagedCareOpsNotification;
use App\Jobs\SendSlackOpsNotification;
use App\Models as M;
use App\Services\CustomerCare\ManagedCareService;
use App\Services\Ops\ManagedCareOpsDelivery;
use App\Services\Ops\ManagedCareOpsRecorder;
use App\Services\RegularCare\CarePlanOccurrenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class ManagedCareOpsNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK = 'https://hooks.slack.com/services/T000/B000/testing-secret';

    private M\User $staff;

    private M\User $family;

    private M\User $caregiver;

    private M\CareRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        Queue::fake();
        Http::preventStrayRequests();
        config()->set('services.slack.ops.enabled', false);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-12 10:00:00', 'America/New_York'));
        $this->staff = M\User::factory()->create(['role' => 'sdr', 'name' => 'Care Team Agent']);
        $this->family = M\User::factory()->create(['role' => 'family', 'name' => 'Charlie Test']);
        $this->caregiver = M\User::factory()->create(['role' => 'caregiver', 'name' => 'Summer Test']);
        $this->request = app(ManagedCareService::class)->save($this->staff, $this->family, $this->input());
    }

    public function test_caregiver_messages_have_context_and_exact_cs_link_without_private_contents(): void
    {
        $conversation = M\CareRequestConversation::findOrCreateForRequest($this->request, $this->caregiver->id, $this->caregiver->id);
        $this->enable();
        $this->actingAs($this->caregiver);
        $message = M\CareRequestMessage::create(['care_request_conversation_id' => $conversation->id,
            'sender_user_id' => $this->caregiver->id, 'body' => 'PRIVATE MEDICAL QUESTION AND ACCESS CODE']);
        $event = M\ManagedCareOpsEvent::sole();
        $text = $this->payload($event);
        foreach (['Caregiver message received', 'Charlie Test', 'Summer Test', 'Afternoon care', 'Read the message and reply',
            'conversation='.$conversation->id, '#conversation-'.$conversation->id, '/admin/customer-care/requests/'.$this->request->id] as $value) {
            $this->assertStringContainsString($value, $text);
        }
        $this->assertStringNotContainsString('PRIVATE', $text);
        $this->assertStringNotContainsString('123 Private Street', $text);
        $conversation->update(['staff_last_read_at' => now()]);
        $message->touch();
        $this->assertSame(1, M\ManagedCareOpsEvent::count());
        Queue::assertPushed(SendManagedCareOpsNotification::class, 1);
        Queue::assertNotPushed(SendSlackOpsNotification::class);
        Notification::assertNothingSent();
    }

    public function test_staff_reply_and_internal_note_use_existing_managed_workflows(): void
    {
        $this->application();
        $this->enable();
        $this->actingAs($this->staff);
        app(ManagedCareService::class)->message($this->staff, $this->request, $this->caregiver->id, 'PRIVATE STAFF MESSAGE');
        app(ManagedCareService::class)->note($this->staff, $this->request, 'PRIVATE INTERNAL FOLLOWUP');
        $this->assertSame(['message', 'note'], M\ManagedCareOpsEvent::orderBy('id')->pluck('event_key')->all());
        $text = $this->payloads();
        $this->assertStringContainsString('Care Team Agent', $text);
        $this->assertStringContainsString('Internal note added', $text);
        $this->assertStringNotContainsString('PRIVATE', $text);
        Notification::assertNotSentTo($this->family, \App\Notifications\MarketplaceEventNotification::class);
    }

    public function test_new_applicant_shortlist_hire_and_repeat_saves_are_reported_once(): void
    {
        $this->enable();
        $application = $this->application();
        $this->actingAs($this->staff);
        app(ManagedCareService::class)->application($this->staff, $this->request, $application->id, 'shortlisted');
        $application->refresh()->update(['status' => 'hired']);
        $application->update(['status' => 'hired']);
        $this->assertSame(3, M\ManagedCareOpsEvent::count());
        foreach (['New applicant', 'Applicant shortlisted', 'Caregiver hired', 'Applied → Shortlisted', 'review_hire='.$application->id] as $value) {
            $this->assertStringContainsString($value, $this->payloads());
        }
        Queue::assertNotPushed(SendSlackOpsNotification::class);
    }

    public function test_staff_takeover_archive_restore_and_recipient_only_edits_are_reported(): void
    {
        $this->request->forceFill(['staff_managed' => false])->saveQuietly();
        $this->enable();
        $this->actingAs($this->staff);
        app(ManagedCareService::class)->takeOver($this->staff, $this->request, 'Team will coordinate this request.');
        app(ManagedCareService::class)->save($this->staff, $this->family, $this->input(['recipient_name' => 'PRIVATE RECIPIENT UPDATE']), $this->request->fresh());
        app(ManagedCareService::class)->archive($this->staff, $this->request->fresh(), 'No longer needed by this customer.');
        app(ManagedCareService::class)->restore($this->staff, $this->request->fresh());
        foreach (['Team management started', 'Care details saved', 'Request archived', 'Request restored'] as $value) {
            $this->assertStringContainsString($value, $this->payloads());
        }
        $this->assertStringNotContainsString('PRIVATE RECIPIENT UPDATE', $this->payloads());
    }

    public function test_cancelling_request_reports_bulk_closed_applications_and_invitations(): void
    {
        $application = $this->application();
        $invite = M\CareRequestInvitation::create(['care_request_id' => $this->request->id, 'family_user_id' => $this->family->id,
            'caregiver_user_id' => $this->caregiver->id, 'invited_by_user_id' => $this->staff->id, 'status' => 'pending']);
        $this->enable();
        $this->actingAs($this->staff);
        app(ManagedCareService::class)->withdraw($this->staff, $this->request, 'Customer no longer requires this visit.');
        $this->assertSame('not_selected', $application->fresh()->status);
        $this->assertSame('cancelled', $invite->fresh()->status);
        foreach (['Request withdrawn', 'Applicant not selected', 'Invitation Cancelled'] as $value) {
            $this->assertStringContainsString($value, $this->payloads());
        }
    }

    public function test_automatic_expiration_includes_request_and_invitations(): void
    {
        $this->request->update(['requested_start_at' => now()->subDays(3), 'requested_end_at' => now()->subDays(2)]);
        M\CareRequestInvitation::create(['care_request_id' => $this->request->id, 'family_user_id' => $this->family->id,
            'caregiver_user_id' => $this->caregiver->id, 'status' => 'pending']);
        $this->enable();
        app(\App\Services\CareRequests\CareRequestExpirationService::class)->expireDue();
        $this->assertSame(2, M\ManagedCareOpsEvent::count());
        $this->assertStringContainsString('Request Expired', $this->payloads());
        $this->assertStringContainsString('Automatic system update', $this->payloads());
    }

    public function test_booking_reschedule_visit_lifecycle_and_hours_include_before_after(): void
    {
        $booking = $this->booking();
        $this->enable();
        $this->actingAs($this->staff);
        $booking->update(['scheduled_start_at' => '2026-11-15 11:00:00', 'scheduled_end_at' => '2026-11-15 14:00:00']);
        $this->actingAs($this->caregiver);
        $booking->update(['status' => 'in_progress', 'started_at' => now()]);
        $booking->update(['status' => 'paused', 'paused_at' => now()]);
        $booking->update(['status' => 'in_progress', 'paused_at' => null]);
        $booking->update(['status' => 'completed', 'completed_at' => now(), 'worked_minutes' => 175, 'timesheet_submitted_at' => now()]);
        $this->actingAs($this->staff);
        $booking->update(['family_confirmed_at' => now(), 'family_confirmed_by_user_id' => $this->staff->id]);
        $text = $this->payloads();
        foreach (['Previously:', 'Now:', 'America/New_York', 'Visit rescheduled', 'Caregiver checked in', 'Visit paused',
            'Visit resumed', 'Hours ready for review', 'Visit hours approved', '#'.$booking->id, 'tab=shift'] as $value) {
            $this->assertStringContainsString($value, $text);
        }
        $this->assertSame(6, M\ManagedCareOpsEvent::count());
    }

    public function test_incident_no_show_and_dispute_are_urgent_with_booking_context(): void
    {
        $booking = $this->booking();
        $this->enable();
        $booking->update(['no_show_flag' => true]);
        $booking->update(['dispute_status' => 'open', 'dispute_reason' => 'PRIVATE DISPUTE']);
        M\CareBookingIncident::create(['care_booking_id' => $booking->id, 'reporter_user_id' => $this->caregiver->id,
            'severity' => 'high', 'status' => 'open', 'title' => 'PRIVATE INCIDENT', 'description' => 'PRIVATE MEDICAL DETAILS', 'reported_at' => now()]);
        $text = $this->payloads();
        foreach (['🔴', 'Caregiver no-show reported', 'Visit dispute Open', 'Incident reported', 'tab=support'] as $value) {
            $this->assertStringContainsString($value, $text);
        }
        $this->assertStringNotContainsString('PRIVATE', $text);
        $this->assertSame(3, M\ManagedCareOpsEvent::count());
    }

    public function test_card_payment_statuses_refunds_and_duplicate_provider_updates(): void
    {
        $booking = $this->booking();
        $payment = M\CareBookingPayment::create(['care_booking_id' => $booking->id, 'family_user_id' => $this->family->id,
            'caregiver_user_id' => $this->caregiver->id, 'status' => 'authorized', 'currency' => 'usd']);
        $this->enable();
        $payment->update(['status' => 'failed', 'last_error' => 'PRIVATE STRIPE ERROR']);
        $payment->update(['status' => 'captured', 'amount_captured_cents' => 9300]);
        $payment->update(['status' => 'captured', 'amount_captured_cents' => 9300]);
        $payment->update(['status' => 'refunded', 'amount_refunded_cents' => 9300]);
        $this->assertSame(3, M\ManagedCareOpsEvent::count());
        $text = $this->payloads();
        foreach (['Card payment · Failed', 'Card payment · Captured', 'Card payment · Refunded', 'USD 93.00', '#'.$booking->id] as $value) {
            $this->assertStringContainsString($value, $text);
        }
        $this->assertStringNotContainsString('PRIVATE STRIPE ERROR', $text);
    }

    public function test_recurring_materialization_groups_generated_visits_and_repeat_run_is_quiet(): void
    {
        $plan = $this->plan();
        $this->enable();
        $result = app(CarePlanOccurrenceService::class)->materialize($plan, through: now()->addDays(12));
        $this->assertGreaterThan(1, $result['created']->count());
        $group = M\ManagedCareOpsEvent::where('event_key', 'visits_generated')->sole();
        $text = $this->payload($group);
        foreach ($result['created'] as $visit) {
            $this->assertStringContainsString('Visit #'.$visit->id, $text);
            $this->assertStringContainsString('/admin/customer-care/requests/'.$visit->care_request_id, $text);
        }
        $this->assertSame($this->request->id, $group->care_request_id);
        $this->assertSame(0, M\ManagedCareOpsEvent::whereIn('event_key', ['booking', 'application', 'request'])->count());
        $before = M\ManagedCareOpsEvent::count();
        app(CarePlanOccurrenceService::class)->materialize($plan->fresh(), through: now()->addDays(12));
        $this->assertSame($before, M\ManagedCareOpsEvent::count());
    }

    public function test_slow_generation_starts_new_group_after_earlier_group_was_claimed(): void
    {
        $plan = $this->plan();
        $this->enable();
        app(ManagedCareOpsRecorder::class)->batchOccurrences($plan, function () use ($plan): void {
            $this->booking(['care_plan_id' => $plan->id]);
            M\ManagedCareOpsEvent::where('event_key', 'visits_generated')->update(['status' => 'sent']);
            $child = $this->request->replicate(['staff_request_key']);
            $child->forceFill(['is_system_generated' => true, 'care_plan_id' => $plan->id])->save();
            $this->booking(['care_request_id' => $child->id, 'care_plan_id' => $plan->id, 'scheduled_start_at' => '2026-11-16 10:00:00', 'scheduled_end_at' => '2026-11-16 12:00:00']);
        });
        $this->assertSame(2, M\ManagedCareOpsEvent::where('event_key', 'visits_generated')->count());
    }

    public function test_generated_child_inherits_managed_context_and_links_its_own_booking(): void
    {
        $plan = $this->plan();
        $child = $this->request->replicate(['staff_request_key']);
        $child->forceFill(['staff_managed' => false, 'is_system_generated' => true, 'care_plan_id' => $plan->id])->save();
        $booking = $this->booking(['care_request_id' => $child->id, 'care_plan_id' => $plan->id]);
        $this->enable();
        $booking->update(['status' => 'cancelled']);
        $event = M\ManagedCareOpsEvent::sole();
        $this->assertSame($this->request->id, $event->care_request_id);
        $this->assertSame($booking->id, $event->care_booking_id);
        $this->assertStringContainsString('/admin/customer-care/requests/'.$this->request->id.'?panel=visits&booking='.$booking->id.'#visit-'.$booking->id, $this->payload($event));
        $this->assertStringContainsString('Charlie Test', $this->payload($event));
    }

    public function test_rollback_discards_events_and_dispatches_but_sequential_changes_keep_snapshots(): void
    {
        $this->enable();
        DB::beginTransaction();
        $this->request->update(['status' => 'cancelled']);
        DB::rollBack();
        $this->assertSame(0, M\ManagedCareOpsEvent::count());
        Queue::assertNotPushed(SendManagedCareOpsNotification::class);
        DB::transaction(function (): void {
            $this->request->refresh()->update(['status' => 'draft']);
            $this->request->update(['status' => 'open']);
        });
        $this->assertSame(2, M\ManagedCareOpsEvent::count());
        $this->assertStringContainsString('Open → Draft', $this->payloads());
        $this->assertStringContainsString('Draft → Open', $this->payloads());
        Queue::assertPushed(SendManagedCareOpsNotification::class, 2);
    }

    public function test_unmanaged_and_disabled_records_do_not_create_managed_events(): void
    {
        $application = $this->application();
        $this->assertSame(0, M\ManagedCareOpsEvent::count());
        $this->request->forceFill(['staff_managed' => false])->saveQuietly();
        $this->enable();
        $this->request->update(['title' => 'Family self-service request']);
        $application->update(['status' => 'hired']);
        $this->assertSame(0, M\ManagedCareOpsEvent::count());
        Queue::assertNotPushed(SendManagedCareOpsNotification::class);
        Queue::assertPushed(SendSlackOpsNotification::class, 1);
    }

    public function test_delivery_replay_cannot_post_the_same_success_twice(): void
    {
        $event = $this->event();
        Http::fake([self::WEBHOOK => Http::response('ok')]);
        $delivery = app(ManagedCareOpsDelivery::class);
        $delivery->deliver($event->id);
        $delivery->deliver($event->id);
        $this->assertSame('sent', $event->fresh()->status);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->data() === $event->payload);
    }

    public function test_known_rejection_retries_without_replaying_success(): void
    {
        $event = $this->event();
        Http::fake([self::WEBHOOK => Http::sequence()->push('unavailable', 503)->push('ok')]);
        $delivery = app(ManagedCareOpsDelivery::class);
        try {
            $delivery->deliver($event->id);
            $this->fail('A retryable rejection must reach the queue retry mechanism.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('will retry', $exception->getMessage());
        }
        $this->assertSame('pending', $event->fresh()->status);
        $delivery->deliver($event->id);
        Http::assertSentCount(1);
        $this->travel(11)->seconds();
        $delivery->deliver($event->id);
        $delivery->deliver($event->id);
        $this->assertSame('sent', $event->fresh()->status);
        $this->assertSame(2, $event->fresh()->attempts);
        Http::assertSentCount(2);
    }

    public function test_ambiguous_timeout_is_flagged_without_automatic_duplicate(): void
    {
        $event = $this->event();
        Http::fake(fn () => throw new ConnectionException('private webhook URL must not be logged'));
        app(ManagedCareOpsDelivery::class)->deliver($event->id);
        app(ManagedCareOpsDelivery::class)->deliver($event->id);
        $this->assertSame('uncertain', $event->fresh()->status);
        $this->assertSame(1, $event->fresh()->attempts);
        $this->assertStringNotContainsString('private webhook', $event->fresh()->last_error);
    }

    public function test_permanent_rejection_and_stale_worker_are_not_blindly_replayed(): void
    {
        $event = $this->event();
        Http::fake([self::WEBHOOK => Http::response('invalid_payload', 400)]);
        app(ManagedCareOpsDelivery::class)->deliver($event->id);
        app(ManagedCareOpsDelivery::class)->deliver($event->id);
        $this->assertSame('failed', $event->fresh()->status);
        Http::assertSentCount(1);
        $event->update(['status' => 'sending', 'sending_at' => now()->subMinutes(6)]);
        $this->artisan('ops:managed-care:dispatch')->assertSuccessful();
        $this->assertSame('uncertain', $event->fresh()->status);
    }

    public function test_scheduled_drain_recovers_pending_delivery_and_status_is_read_only(): void
    {
        $event = $this->event();
        Queue::fake();
        $this->artisan('ops:managed-care:dispatch --status')->assertSuccessful();
        Queue::assertNothingPushed();
        $this->artisan('ops:managed-care:dispatch')->assertSuccessful();
        Queue::assertPushed(SendManagedCareOpsNotification::class, fn ($job) => $job->eventId === $event->id);
        Http::assertNothingSent();
    }

    public function test_schedule_change_correction_and_extra_care_reports_include_proposed_times(): void
    {
        $booking = $this->booking();
        $plan = $this->plan();
        $this->enable();
        $this->actingAs($this->caregiver);
        $change = M\CareBookingChangeRequest::create(['care_booking_id' => $booking->id, 'requester_user_id' => $this->caregiver->id,
            'type' => 'reschedule', 'status' => 'pending', 'reason' => 'PRIVATE REASON',
            'proposed_start_at' => '2026-11-15 13:00:00', 'proposed_end_at' => '2026-11-15 15:00:00']);
        $correction = M\CareBookingTimeCorrection::create(['client_request_id' => (string) Str::uuid(), 'care_booking_id' => $booking->id,
            'requester_user_id' => $this->caregiver->id, 'family_user_id' => $this->family->id, 'status' => 'pending_family',
            'reason_code' => 'forgot_start', 'explanation' => 'PRIVATE EXPLANATION', 'proposed_started_at' => '2026-11-15 10:05:00',
            'proposed_completed_at' => '2026-11-15 12:00:00', 'proposed_worked_minutes' => 115, 'original_snapshot' => [],
            'financial_preview' => [], 'submitted_at' => now()]);
        $extra = M\CompletedExtraVisitRequest::create(['client_request_id' => (string) Str::uuid(), 'care_plan_id' => $plan->id,
            'family_user_id' => $this->family->id, 'caregiver_user_id' => $this->caregiver->id, 'status' => 'pending_family',
            'reason_code' => 'family_requested', 'explanation' => 'PRIVATE EXTRA VISIT', 'timezone' => 'America/New_York',
            'proposed_started_at' => '2026-11-16 14:00:00', 'proposed_completed_at' => '2026-11-16 16:00:00',
            'proposed_worked_minutes' => 120, 'financial_preview' => [], 'submitted_at' => now()]);
        $this->actingAs($this->staff);
        $change->update(['status' => 'accepted', 'resolved_by_user_id' => $this->staff->id]);
        $correction->update(['status' => 'applied', 'approved_by_user_id' => $this->staff->id]);
        $extra->update(['status' => 'applied', 'approved_by_user_id' => $this->staff->id]);
        $this->assertSame(6, M\ManagedCareOpsEvent::count());
        $text = $this->payloads();
        foreach (['Reschedule request received', 'Time correction submitted', 'Reported extra care submitted',
            '1:00 PM', '10:05 AM', 'Proposed care minutes: 115', 'Time correction #'.$correction->id, 'Care Team Agent'] as $value) {
            $this->assertStringContainsString($value, $text);
        }
        $this->assertStringNotContainsString('PRIVATE', $text);
    }

    public function test_recurring_schedule_proposal_pause_resume_and_counter_offer_have_clear_dates(): void
    {
        $plan = $this->plan();
        $this->enable();
        $this->actingAs($this->caregiver);
        $proposal = M\CarePlanScheduleChange::create(['care_plan_id' => $plan->id, 'requested_by_user_id' => $this->caregiver->id,
            'type' => 'schedule', 'status' => 'pending', 'effective_on' => '2026-11-20',
            'current_schedule' => ['slots' => [['day' => 1, 'start_time' => '10:00', 'end_time' => '12:00']]],
            'proposed_schedule' => ['slots' => [['day' => 1, 'start_time' => '13:00', 'end_time' => '15:00']]], 'note' => 'PRIVATE NOTE']);
        $proposal->update(['status' => 'accepted']);
        $plan->update(['status' => 'paused', 'pause_starts_on' => '2026-11-20', 'resumes_on' => '2026-11-25']);
        $plan->update(['status' => 'active', 'pause_starts_on' => null, 'resumes_on' => null]);
        $plan->update(['status' => 'countered', 'counter_schedule_days' => [1], 'counter_schedule_start_time' => '14:00', 'counter_schedule_end_time' => '16:00']);
        $this->assertSame(5, M\ManagedCareOpsEvent::count());
        foreach (['Previously:', 'Proposed:', '2026-11-20', 'Recurring care Paused', 'Recurring care Active', 'Caregiver proposed:'] as $value) {
            $this->assertStringContainsString($value, $this->payloads());
        }
        $this->assertStringNotContainsString('PRIVATE', $this->payloads());
    }

    public function test_prepaid_coverage_operations_and_settlement_follow_the_same_booking(): void
    {
        $booking = $this->booking();
        $account = M\CareHourAccount::create(['family_account_id' => $this->request->family_account_id, 'display_name' => 'Family wallet']);
        $this->enable();
        $funding = M\CareHourBookingFunding::create(['care_booking_id' => $booking->id, 'care_hour_account_id' => $account->id,
            'status' => 'ready', 'reserved_minutes' => 120, 'committed_at' => now()]);
        $settlement = M\CareHourSettlement::create(['care_booking_id' => $booking->id, 'care_hour_booking_funding_id' => $funding->id,
            'status' => 'pending', 'worked_minutes' => 120, 'prepaid_minutes' => 120]);
        $operation = M\CareHourPaymentOperation::create(['public_id' => (string) Str::uuid(), 'operation_key' => 'test:transfer',
            'care_hour_booking_funding_id' => $funding->id, 'care_hour_settlement_id' => $settlement->id,
            'kind' => 'transfer', 'status' => 'pending', 'amount_cents' => 5400]);
        $funding->update(['status' => 'payment_attention', 'shortfall_minutes' => 30]);
        $operation->update(['status' => 'failed', 'last_error' => 'PRIVATE PROVIDER ERROR']);
        // The reconciliation service deliberately uses a revision-guarded bulk update.
        DB::transaction(function () use ($settlement): void {
            M\CareHourSettlement::whereKey($settlement->id)->where('revision', $settlement->revision)->update(['status' => 'settled']);
            app(ManagedCareOpsRecorder::class)->recordChanges($settlement, ['status' => 'settled']);
        });
        $this->assertSame(6, M\ManagedCareOpsEvent::count());
        foreach (M\ManagedCareOpsEvent::all() as $event) {
            $this->assertSame($booking->id, $event->care_booking_id);
            $this->assertSame($this->request->id, $event->care_request_id);
        }
        foreach (['Reserved prepaid minutes: 120', 'Minutes needing payment: 0 → 30', 'Prepaid Transfer · Failed', 'Pending → Settled'] as $value) {
            $this->assertStringContainsString($value, $this->payloads());
        }
        $this->assertStringNotContainsString('PRIVATE PROVIDER ERROR', $this->payloads());
    }

    public function test_support_updates_and_internal_replies_keep_private_contents_in_lolo(): void
    {
        $booking = $this->booking();
        $this->enable();
        $ticket = M\SupportTicket::create(['opener_user_id' => $this->caregiver->id, 'care_booking_id' => $booking->id,
            'care_request_id' => $this->request->id, 'category' => 'incident', 'priority' => 'high', 'status' => 'open',
            'subject' => 'PRIVATE SUBJECT', 'description' => 'PRIVATE DESCRIPTION']);
        M\SupportTicketMessage::create(['support_ticket_id' => $ticket->id, 'sender_user_id' => $this->staff->id,
            'kind' => 'internal_note', 'body' => 'PRIVATE INTERNAL NOTE']);
        $ticket->update(['status' => 'resolved']);
        $this->assertSame(3, M\ManagedCareOpsEvent::count());
        foreach (['Support ticket opened', 'Internal support note added', 'Support ticket Resolved', 'Support ticket #'.$ticket->id] as $value) {
            $this->assertStringContainsString($value, $this->payloads());
        }
        $this->assertStringNotContainsString('PRIVATE', $this->payloads());
    }

    public function test_selected_day_agreement_resolves_back_to_managed_family_request(): void
    {
        $arrangement = M\CareArrangement::create(['source_care_request_id' => $this->request->id,
            'family_user_id' => $this->family->id, 'family_account_id' => $this->request->family_account_id,
            'title' => 'Selected-day care', 'schedule_slots' => [['day' => 1, 'start_time' => '10:00', 'end_time' => '12:00']],
            'starts_on' => '2026-11-13', 'timezone' => 'America/New_York']);
        $child = $this->request->replicate(['staff_request_key']);
        $child->forceFill(['staff_managed' => false, 'is_system_generated' => true])->save();
        $plan = $this->plan();
        $plan->update(['source_care_request_id' => $child->id, 'care_arrangement_id' => $arrangement->id]);
        $this->enable();
        $plan->update(['status' => 'ended']);
        $event = M\ManagedCareOpsEvent::sole();
        $this->assertSame($this->request->id, $event->care_request_id);
        $this->assertStringContainsString('Recurring care Ended', $this->payload($event));
        $this->assertStringContainsString('/admin/customer-care/requests/'.$this->request->id.'?panel=visits', $this->payload($event));
    }

    public function test_disabling_delivery_leaves_pending_record_for_later_without_network_calls(): void
    {
        $event = $this->event();
        config()->set('services.slack.ops.enabled', false);
        app(ManagedCareOpsDelivery::class)->deliver($event->id);
        $this->assertSame('pending', $event->fresh()->status);
        $this->assertSame(0, $event->fresh()->attempts);
        Http::assertNothingSent();
    }

    public function test_managed_creation_uses_only_managed_notice_and_replayed_form_is_quiet(): void
    {
        $this->enable();
        $this->actingAs($this->staff);
        $input = $this->input(['status' => 'draft']);
        $request = app(ManagedCareService::class)->save($this->staff, $this->family, $input);
        app(ManagedCareService::class)->save($this->staff, $this->family, $input);
        app(ManagedCareService::class)->save($this->staff, $this->family, array_replace($input, ['status' => 'open']), $request);
        $this->assertSame(2, M\ManagedCareOpsEvent::count());
        $this->assertStringContainsString('Managed request drafted', $this->payloads());
        $this->assertStringContainsString('Request posted', $this->payloads());
        Queue::assertNotPushed(SendSlackOpsNotification::class);
    }

    public function test_conditional_bulk_updates_keep_guards_and_only_record_actual_changes(): void
    {
        $this->enable();
        $recorder = app(ManagedCareOpsRecorder::class);
        $query = fn () => M\CareRequest::whereKey($this->request->id)->where('status', 'open');
        $this->assertSame(1, $recorder->updateQuery($query(), ['status' => 'draft']));
        $this->assertSame(0, $recorder->updateQuery($query(), ['status' => 'cancelled']));
        $this->assertSame('draft', $this->request->fresh()->status);
        $this->assertSame(1, M\ManagedCareOpsEvent::count());
        $this->assertStringContainsString('Open → Draft', $this->payloads());
        DB::beginTransaction();
        $recorder->updateQuery(M\CareRequest::whereKey($this->request->id), ['status' => 'cancelled']);
        DB::rollBack();
        $this->assertSame('draft', $this->request->fresh()->status);
        $this->assertSame(1, M\ManagedCareOpsEvent::count());
    }

    public function test_pre_takeover_visit_link_opens_the_managed_parent_and_cannot_select_another_request(): void
    {
        $plan = $this->plan();
        $child = $this->request->replicate(['staff_request_key']);
        $child->forceFill(['staff_managed' => false, 'is_system_generated' => true, 'care_plan_id' => $plan->id])->save();
        $booking = $this->booking(['care_request_id' => $child->id, 'care_plan_id' => $plan->id]);
        $other = $this->request->replicate(['staff_request_key']);
        $other->save();
        $otherBooking = $this->booking(['care_request_id' => $other->id]);
        $this->enable();
        $booking->update(['status' => 'cancelled']);
        $event = M\ManagedCareOpsEvent::sole();
        $button = collect($event->payload['blocks'])->firstWhere('type', 'actions')['elements'][0];
        $this->actingAs($this->staff)->get($button['url'])->assertOk()->assertSee('id="visit-'.$booking->id.'"', false);
        $this->get(route('customer-care.show', ['careRequest' => $this->request->id, 'panel' => 'visits', 'booking' => $otherBooking->id]))
            ->assertOk()->assertDontSee('id="visit-'.$otherBooking->id.'"', false);
        $this->assertSame(1, M\ManagedCareOpsEvent::count());
    }

    public function test_recurring_event_does_not_misidentify_its_original_one_time_booking(): void
    {
        $this->booking();
        $plan = $this->plan();
        $this->enable();
        $plan->update(['status' => 'paused']);
        $event = M\ManagedCareOpsEvent::sole();
        $this->assertNull($event->care_booking_id);
        $this->assertStringContainsString('panel=visits', $this->payload($event));
        $this->assertStringNotContainsString('tab=shift', $this->payload($event));
    }

    private function enable(): void
    {
        config()->set(['services.slack.ops.enabled' => true, 'services.slack.ops.webhook_url' => self::WEBHOOK]);
    }

    private function event(): M\ManagedCareOpsEvent
    {
        $this->enable();
        $this->request->update(['title' => 'Updated managed request']);

        return M\ManagedCareOpsEvent::sole();
    }

    private function payload(M\ManagedCareOpsEvent $event): string
    {
        return json_encode($event->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function payloads(): string
    {
        return M\ManagedCareOpsEvent::orderBy('id')->get()->map(fn ($event) => $this->payload($event))->implode("\n");
    }

    private function application(): M\CareRequestApplication
    {
        return M\CareRequestApplication::create(['care_request_id' => $this->request->id,
            'caregiver_user_id' => $this->caregiver->id, 'status' => 'applied', 'proposed_rate' => 30]);
    }

    private function booking(array $attributes = []): M\CareBooking
    {
        return M\CareBooking::create($attributes + ['care_request_id' => $this->request->id, 'family_user_id' => $this->family->id,
            'caregiver_user_id' => $this->caregiver->id, 'status' => 'scheduled',
            'scheduled_start_at' => '2026-11-15 10:00:00', 'scheduled_end_at' => '2026-11-15 12:00:00']);
    }

    private function plan(): M\CarePlan
    {
        return M\CarePlan::create(['source_care_request_id' => $this->request->id, 'family_user_id' => $this->family->id,
            'caregiver_user_id' => $this->caregiver->id, 'status' => 'active', 'title' => 'Weekly companionship',
            'schedule_days' => [1, 3, 5], 'schedule_start_time' => '10:00', 'schedule_end_time' => '12:00',
            'starts_on' => '2026-11-13', 'ends_on' => '2026-11-25', 'timezone' => 'America/New_York',
            'hourly_rate' => 30, 'payment_status' => 'authorized']);
    }

    private function input(array $overrides = []): array
    {
        return $overrides + ['request_key' => (string) Str::uuid(), 'title' => 'Afternoon care',
            'scope_of_work' => 'PRIVATE CARE NOTES', 'recipient_name' => 'PRIVATE RECIPIENT',
            'address_line1' => '123 Private Street', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
            'request_type' => 'one_time', 'requested_start_at' => '2026-11-15 10:00:00',
            'requested_end_at' => '2026-11-15 12:00:00', 'status' => 'open',
            'tasks' => [M\CareTask::firstOrCreate(['name' => 'Companionship'])->id]];
    }
}
