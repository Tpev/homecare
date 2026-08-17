<?php

namespace Tests\Feature\AiSupport;

use App\Models\AiSupportGuidedTask;
use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CareBookingTimeCorrection;
use App\Models\CarePlan;
use App\Models\CareRecipientProfile;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\CareRequestMessage;
use App\Models\CompletedExtraVisitRequest;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\AiSupport\AiSupportControlService;
use App\Services\AiSupport\AiSupportPilotGrantService;
use App\Services\AiSupport\AiSupportRuntimeService;
use App\Services\AiSupport\NavigationTargetRegistry;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class FamilyGuidedAssistanceStateMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_and_applicant_states_return_authorized_exact_destinations(): void
    {
        $family = $this->eligibleFamily();
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Applicant Care']);
        $request = $this->request($family, ['title' => 'Morning companionship']);
        Http::fake();

        [$ticket, $task, $message] = $this->respond($family, 'What is the status of my request?');
        $this->assertSame('family.request.overview', $task->navigation_target_id);
        $this->assertSame($request->id, (int) $task->resource_id);
        $this->assertStringContainsString('Morning companionship', $message);

        CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_APPLIED,
        ]);
        [, $applicantTask, $applicantMessage] = $this->respond($family, 'Did any caregivers apply to my request?');
        $this->assertSame('family.request.applicants', $applicantTask->navigation_target_id);
        $this->assertSame($request->id, (int) $applicantTask->resource_id);
        $this->assertStringContainsString('waiting for your review', $applicantMessage);

        $otherFamily = $this->eligibleFamily();
        $this->assertFalse(app(NavigationTargetRegistry::class)->allowedFor(
            $otherFamily,
            $applicantTask->navigation_target_id,
            ['resource_type' => $applicantTask->resource_type, 'resource_id' => $applicantTask->resource_id],
        ));
        $this->assertSame(CareRequest::STATUS_OPEN, $request->fresh()->status);
        $this->assertSame(SupportTicket::RESPONDER_MODE_AUTOMATED, $ticket->fresh()->responder_mode);
        Http::assertNothingSent();
        $this->assertDatabaseMissing('ai_support_interaction_events', ['event_type' => 'model_turn_completed']);
    }

    public function test_scheduled_live_and_submitted_hours_states_remain_read_only(): void
    {
        $family = $this->eligibleFamily();
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Jordan Care']);
        $request = $this->request($family, ['title' => 'Friday visit', 'status' => CareRequest::STATUS_FILLED]);
        $booking = $this->booking($family, $caregiver, $request);
        Http::fake();

        [, $scheduledTask, $scheduledMessage] = $this->respond($family, 'When is my next visit?');
        $this->assertSame('family.request.visit', $scheduledTask->navigation_target_id);
        $this->assertStringContainsString('Jordan Care has a scheduled visit', $scheduledMessage);

        $booking->forceFill(['status' => CareBooking::STATUS_IN_PROGRESS, 'started_at' => now()->subMinutes(10)])->save();
        [, $liveTask, $liveMessage] = $this->respond($family, 'What is the current visit status?');
        $this->assertSame('family.request.visit', $liveTask->navigation_target_id);
        $this->assertStringContainsString('Care is happening now', $liveMessage);

        $booking->forceFill([
            'status' => CareBooking::STATUS_COMPLETED,
            'completed_at' => now(),
            'timesheet_submitted_at' => now(),
            'worked_minutes' => 105,
        ])->save();
        [, $hoursTask, $hoursMessage] = $this->respond($family, 'Show the caregiver submitted hours.');
        $this->assertSame('family.request.timesheet', $hoursTask->navigation_target_id);
        $this->assertStringContainsString('visit hours', $hoursMessage);
        $this->assertSame(105, $booking->fresh()->worked_minutes);
        $this->assertNull($booking->fresh()->family_confirmed_at);
        Http::assertNothingSent();
        $this->assertDatabaseMissing('ai_support_interaction_events', ['event_type' => 'model_turn_completed']);
    }

    public function test_care_payment_failure_is_read_without_exposing_provider_details_or_mutating_payment(): void
    {
        $family = $this->eligibleFamily();
        $account = app(FamilyAccountContext::class)->account($family);
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Robin Care']);
        $request = $this->request($family, ['title' => 'Payment recovery visit', 'status' => CareRequest::STATUS_FILLED]);
        $booking = $this->booking($family, $caregiver, $request);
        $payment = CareBookingPayment::query()->create([
            'care_booking_id' => $booking->id,
            'family_account_id' => $account->id,
            'family_user_id' => $family->id,
            'initiated_by_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBookingPayment::STATUS_FAILED,
            'currency' => 'usd',
            'amount_authorized_cents' => 0,
            'amount_captured_cents' => 0,
            'amount_refunded_cents' => 0,
            'amount_overage_cents' => 0,
            'overage_pending_cents' => 0,
            'platform_fee_cents' => 0,
            'caregiver_amount_cents' => 0,
            'last_error' => 'provider_internal_decline_code_do_not_echo',
        ]);
        Http::fake();

        [, $task, $message] = $this->respond($family, 'Why did my care payment fail?');
        $this->assertSame('family.request.payment_attention', $task->navigation_target_id);
        $this->assertSame('care_request', $task->resource_type);
        $this->assertSame($request->id, (int) $task->resource_id);
        $this->assertStringContainsString('care payment', $message);
        $this->assertStringNotContainsString('provider_internal_decline_code_do_not_echo', $message);
        $this->assertSame(CareBookingPayment::STATUS_FAILED, $payment->fresh()->status);
        $this->assertSame('provider_internal_decline_code_do_not_echo', $payment->fresh()->last_error);
        Http::assertNothingSent();
        $this->assertDatabaseMissing('ai_support_interaction_events', ['event_type' => 'model_turn_completed']);
    }

    public function test_profile_message_and_history_positive_states_are_read_only_and_exactly_guided(): void
    {
        $family = $this->eligibleFamily();
        $account = app(FamilyAccountContext::class)->account($family);
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Casey Care']);
        $profile = CareRecipientProfile::query()->create([
            'family_account_id' => $account->id,
            'legacy_family_user_id' => $family->id,
            'created_by_user_id' => $family->id,
            'updated_by_user_id' => $family->id,
            'status' => CareRecipientProfile::STATUS_READY,
            'preferred_name' => 'Sam',
        ]);
        $request = $this->request($family, ['title' => 'History and messages', 'status' => CareRequest::STATUS_FILLED]);
        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_APPLIED,
        ]);
        $conversation = CareRequestConversation::query()->create([
            'care_request_id' => $request->id,
            'family_account_id' => $account->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'care_request_application_id' => $application->id,
            'started_by_user_id' => $caregiver->id,
            'last_message_at' => now(),
            'last_message_sender_id' => $caregiver->id,
        ]);
        CareRequestMessage::query()->create([
            'care_request_conversation_id' => $conversation->id,
            'sender_user_id' => $caregiver->id,
            'body' => 'Frozen evaluation message',
        ]);
        $booking = $this->booking($family, $caregiver, $request, [
            'status' => CareBooking::STATUS_COMPLETED,
            'completed_at' => now()->subHour(),
            'timesheet_submitted_at' => now()->subMinutes(50),
            'worked_minutes' => 120,
            'family_confirmed_at' => now()->subMinutes(40),
        ]);
        Http::fake();

        [, $profileTask, $profileMessage] = $this->respond($family, 'Is the care receiver profile ready?');
        $this->assertSame('family.care_profiles', $profileTask->navigation_target_id);
        $this->assertStringContainsString('ready to use', $profileMessage);

        [, $messageTask, $message] = $this->respond($family, 'Show my unread caregiver message.');
        $this->assertSame('family.message', $messageTask->navigation_target_id);
        $this->assertSame($conversation->id, (int) $messageTask->resource_id);
        $this->assertStringContainsString('unread conversation', $message);

        [, $historyTask, $historyMessage] = $this->respond($family, 'Show my past visits.');
        $this->assertSame('family.care_history', $historyTask->navigation_target_id);
        $this->assertStringContainsString('1 completed visit', $historyMessage);
        $this->assertSame(CareRecipientProfile::STATUS_READY, $profile->fresh()->status);
        $this->assertSame(CareBooking::STATUS_COMPLETED, $booking->fresh()->status);
        $this->assertDatabaseCount('care_request_messages', 1);
        Http::assertNothingSent();
        $this->assertDatabaseMissing('ai_support_interaction_events', ['event_type' => 'model_turn_completed']);
    }

    public function test_time_correction_and_regular_care_attention_use_their_authorized_resources(): void
    {
        $family = $this->eligibleFamily();
        $account = app(FamilyAccountContext::class)->account($family);
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Taylor Care']);
        $request = $this->request($family, ['title' => 'Corrected visit', 'status' => CareRequest::STATUS_FILLED]);
        $booking = $this->booking($family, $caregiver, $request, [
            'status' => CareBooking::STATUS_COMPLETED,
            'completed_at' => now()->subDay(),
            'timesheet_submitted_at' => now()->subHours(20),
            'worked_minutes' => 90,
        ]);
        $correction = CareBookingTimeCorrection::query()->create([
            'client_request_id' => (string) Str::uuid(),
            'care_booking_id' => $booking->id,
            'requester_user_id' => $caregiver->id,
            'family_account_id' => $account->id,
            'family_user_id' => $family->id,
            'version' => 1,
            'status' => CareBookingTimeCorrection::STATUS_PAYMENT_ACTION_REQUIRED,
            'reason_code' => CareBookingTimeCorrection::REASON_FORGOT_END,
            'explanation' => 'Frozen evaluation correction',
            'proposed_started_at' => now()->subDay()->setTime(9, 0),
            'proposed_completed_at' => now()->subDay()->setTime(11, 0),
            'proposed_break_minutes' => 0,
            'proposed_worked_minutes' => 120,
            'original_snapshot' => [],
            'financial_preview' => ['target_charge_cents' => 6000],
            'submitted_at' => now()->subHours(19),
            'payment_action_required_at' => now()->subHour(),
        ]);

        $regularFamily = $this->eligibleFamily();
        $regularAccount = app(FamilyAccountContext::class)->account($regularFamily);
        $regularCaregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Regular Caregiver']);
        $plan = $this->plan($regularFamily, $regularCaregiver);
        $systemRequest = $this->request($regularFamily, [
            'title' => 'Regular morning care',
            'care_plan_id' => $plan->id,
            'is_system_generated' => true,
            'status' => CareRequest::STATUS_FILLED,
            'request_type' => CareRequest::TYPE_RECURRING,
        ]);
        $this->booking($regularFamily, $regularCaregiver, $systemRequest, ['care_plan_id' => $plan->id]);
        $extra = CompletedExtraVisitRequest::query()->create([
            'client_request_id' => (string) Str::uuid(),
            'care_plan_id' => $plan->id,
            'family_account_id' => $regularAccount->id,
            'family_user_id' => $regularFamily->id,
            'caregiver_user_id' => $regularCaregiver->id,
            'version' => 1,
            'status' => CompletedExtraVisitRequest::STATUS_PAYMENT_ACTION_REQUIRED,
            'reason_code' => CompletedExtraVisitRequest::REASON_FAMILY_REQUESTED,
            'explanation' => 'Frozen evaluation extra visit',
            'timezone' => 'America/New_York',
            'proposed_started_at' => now()->subDays(2)->setTime(10, 0),
            'proposed_completed_at' => now()->subDays(2)->setTime(12, 0),
            'proposed_break_minutes' => 0,
            'proposed_worked_minutes' => 120,
            'financial_preview' => ['total_charge_cents' => 6000],
            'submitted_at' => now()->subDay(),
            'payment_action_required_at' => now()->subHours(2),
        ]);
        Http::fake();

        [, $correctionTask] = $this->respond($family, 'Open the time correction payment step.');
        $this->assertSame('family.request.payment_attention', $correctionTask->navigation_target_id);
        $this->assertSame($request->id, (int) $correctionTask->resource_id);

        [, $regularVisitTask] = $this->respond($regularFamily, 'When is my next regular care visit?');
        $this->assertSame('family.regular_care.attention', $regularVisitTask->navigation_target_id);
        $this->assertSame($plan->id, (int) $regularVisitTask->resource_id);

        [, $extraTask, $extraMessage] = $this->respond($regularFamily, 'Show the completed extra visit report.');
        $this->assertSame('family.regular_care.attention', $extraTask->navigation_target_id);
        $this->assertSame($plan->id, (int) $extraTask->resource_id);
        $this->assertStringContainsString('reported visit', $extraMessage);
        $this->assertSame(CareBookingTimeCorrection::STATUS_PAYMENT_ACTION_REQUIRED, $correction->fresh()->status);
        $this->assertSame(CompletedExtraVisitRequest::STATUS_PAYMENT_ACTION_REQUIRED, $extra->fresh()->status);
        Http::assertNothingSent();
        $this->assertDatabaseMissing('ai_support_interaction_events', ['event_type' => 'model_turn_completed']);
    }

    /** @return array{SupportTicket,AiSupportGuidedTask,string} */
    private function respond(User $family, string $question): array
    {
        $ticket = SupportTicket::query()->create([
            'family_account_id' => app(FamilyAccountContext::class)->account($family)->id,
            'family_visibility' => 'opener_only',
            'opener_user_id' => $family->id,
            'source' => SupportTicket::SOURCE_CHAT_WIDGET,
            'responder_mode' => SupportTicket::RESPONDER_MODE_AUTOMATED,
            'category' => 'general',
            'status' => SupportTicket::STATUS_OPEN,
            'priority' => 'normal',
            'subject' => 'Family intent state evaluation',
            'description' => $question,
            'initial_client_message_id' => (string) Str::uuid(),
        ]);

        app(AiSupportRuntimeService::class)->respond($family, $ticket, $question);
        $task = AiSupportGuidedTask::query()->where('support_ticket_id', $ticket->id)->sole();
        $message = $ticket->publicMessages()->reorder()->latest()->firstOrFail()->body;

        return [$ticket, $task, $message];
    }

    private function eligibleFamily(): User
    {
        config([
            'ai_support.runtime_available' => true,
            'ai_support.provider_enabled' => true,
            'services.openai.api_key' => 'test-key',
            'services.stripe.bypass' => true,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $controls = app(AiSupportControlService::class);
        foreach ([
            'master_enabled', 'user_visible_enabled', 'role.family',
            'capability.support_answers_v1', 'capability.semantic_navigation_v1',
            'capability.family_context_v1',
        ] as $key) {
            $controls->set($admin, $key, true, 'Open Family intent state evaluation capability');
        }
        $controls->set($admin, 'human_only', false, 'Permit Family intent state evaluation');

        $family = User::factory()->create(['role' => 'family']);
        app(FamilyAccountContext::class)->account($family);
        app(AiSupportPilotGrantService::class)->grant(
            $admin,
            $family,
            'family_support_v1',
            CarbonImmutable::now(),
            CarbonImmutable::now()->addDays(14),
            'Exact-user Family intent state evaluation',
            (string) Str::uuid(),
        );

        return $family;
    }

    private function request(User $family, array $attributes = []): CareRequest
    {
        $account = app(FamilyAccountContext::class)->account($family);

        return CareRequest::query()->create([
            'family_account_id' => $account->id,
            'family_user_id' => $family->id,
            'created_by_user_id' => $family->id,
            'is_system_generated' => false,
            'title' => 'Care request',
            'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addDay(),
            'requested_end_at' => now()->addDay()->addHours(2),
            'address_line1' => '123 Main Street',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
            ...$attributes,
        ]);
    }

    private function booking(User $family, User $caregiver, CareRequest $request, array $attributes = []): CareBooking
    {
        return CareBooking::query()->create([
            'care_request_id' => $request->id,
            'family_account_id' => app(FamilyAccountContext::class)->account($family)->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => now()->addDay(),
            'scheduled_end_at' => now()->addDay()->addHours(2),
            ...$attributes,
        ]);
    }

    private function plan(User $family, User $caregiver): CarePlan
    {
        return CarePlan::query()->create([
            'family_account_id' => app(FamilyAccountContext::class)->account($family)->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CarePlan::STATUS_ACTIVE,
            'title' => 'Regular morning care',
            'recipient_snapshot' => ['full_name' => 'Mary'],
            'address_snapshot' => ['address_line1' => '123 Main Street', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601'],
            'task_snapshot' => [],
            'schedule_days' => [1],
            'schedule_start_time' => '09:00',
            'schedule_end_time' => '11:00',
            'starts_on' => now()->subWeek()->toDateString(),
            'timezone' => 'America/New_York',
            'hourly_rate' => 30,
            'payment_status' => CarePlan::PAYMENT_AUTHORIZED,
        ]);
    }
}
