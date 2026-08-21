<?php

namespace Tests\Feature\AiSupport;

use App\Models\AiSupportGuidedTask;
use App\Models\AiSupportMessageAction;
use App\Models\CareBooking;
use App\Models\CareBookingChangeRequest;
use App\Models\CarePlan;
use App\Models\CareRecipientProfile;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\CareRequestMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\AiSupport\AiSupportControlService;
use App\Services\AiSupport\AiSupportGuidedTaskService;
use App\Services\AiSupport\AiSupportPilotGrantService;
use App\Services\AiSupport\AiSupportRuntimeService;
use App\Services\AiSupport\FamilyGuidedAssistanceService;
use App\Services\AiSupport\NavigationTargetRegistry;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class FamilyGuidedAssistanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_supported_family_status_intents_are_deterministic_and_do_not_capture_request_creation(): void
    {
        $service = app(FamilyGuidedAssistanceService::class);

        $this->assertSame(FamilyGuidedAssistanceService::INTENT_OVERVIEW, $service->intentFor('What needs my attention?'));
        $this->assertSame(FamilyGuidedAssistanceService::INTENT_REQUESTS, $service->intentFor('Did any caregivers respond to my request?'));
        $this->assertSame(FamilyGuidedAssistanceService::INTENT_REQUESTS, $service->intentFor('Do I have any caregivers waiting for me to review or hire?'));
        $this->assertSame(FamilyGuidedAssistanceService::INTENT_VISITS, $service->intentFor('When is my next visit?'));
        $this->assertSame(FamilyGuidedAssistanceService::INTENT_VISITS, $service->intentFor('Can I accept the caregiver change request?'));
        $this->assertSame(FamilyGuidedAssistanceService::INTENT_TIMESHEETS, $service->intentFor('I need to review submitted hours'));
        $this->assertSame(FamilyGuidedAssistanceService::INTENT_PAYMENT_ATTENTION, $service->intentFor('Why did the care payment fail?'));
        $this->assertSame(FamilyGuidedAssistanceService::INTENT_PROFILES, $service->intentFor('Is the care receiver profile complete?'));
        $this->assertSame(FamilyGuidedAssistanceService::INTENT_MESSAGES, $service->intentFor('Do I have unread messages?'));
        $this->assertSame(FamilyGuidedAssistanceService::INTENT_MESSAGES, $service->intentFor('Can I message my caregiver?'));
        $this->assertSame(FamilyGuidedAssistanceService::INTENT_PAYMENT_ATTENTION, $service->intentFor('What does this payment error message mean?'));
        $this->assertSame(FamilyGuidedAssistanceService::INTENT_PAYMENT_ATTENTION, $service->intentFor('Why did my latest payment fail, and what should I do next?'));
        $this->assertSame(FamilyGuidedAssistanceService::INTENT_TIMESHEETS, $service->intentFor('Please show me the latest hours the caregiver submitted and anything I need to review.'));
        $this->assertSame(FamilyGuidedAssistanceService::INTENT_TIMESHEETS, $service->intentFor('What hours did my caregiver submit?'));
        $this->assertSame(FamilyGuidedAssistanceService::INTENT_HISTORY, $service->intentFor('Show my past visit receipts'));
        $this->assertSame(FamilyGuidedAssistanceService::INTENT_REQUESTS, $service->intentFor('What is the status of my care request?'));
        $this->assertSame(FamilyGuidedAssistanceService::INTENT_REGULAR_CARE, $service->intentFor('When is my next regular care visit?'));
        $this->assertSame(FamilyGuidedAssistanceService::INTENT_REGULAR_CARE, $service->intentFor('Show me my regular care plan.'));
        $this->assertNull($service->intentFor('I want to create a one-time care request.'));
        $this->assertNull($service->intentFor('I want to create a regular care request.'));
    }

    public function test_attention_overview_reads_authoritative_account_data_and_offers_exact_actions_without_model_call(): void
    {
        [, $family] = $this->eligibleFamily();
        $account = app(FamilyAccountContext::class)->account($family);
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Jamie Care']);
        $request = $this->request($family, ['title' => 'Monday companionship']);
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
            'body' => 'Authorized test message',
        ]);
        CareRecipientProfile::query()->create([
            'family_account_id' => $account->id,
            'legacy_family_user_id' => $family->id,
            'created_by_user_id' => $family->id,
            'updated_by_user_id' => $family->id,
            'status' => CareRecipientProfile::STATUS_DRAFT,
            'preferred_name' => 'Pat',
        ]);
        $ticket = $this->automatedTicket($family, 'What needs my attention?');
        Http::fake();

        app(AiSupportRuntimeService::class)->respond($family, $ticket, $ticket->description);

        Http::assertNothingSent();
        $message = $ticket->publicMessages()->reorder()->latest()->firstOrFail();
        $this->assertStringContainsString('I found 4 items that need attention', $message->body);
        $this->assertStringContainsString('Add a payment method to the Family account', $message->body);
        $this->assertStringContainsString('caregiver is waiting for your review', $message->body);
        $this->assertStringContainsString("Finish Pat's care receiver profile", $message->body);
        $this->assertStringContainsString('message from Jamie Care is unread', $message->body);
        $this->assertSame(4, AiSupportGuidedTask::query()->count());
        $this->assertEqualsCanonicalizing(
            ['family.billing.payment_method', 'family.request.applicants', 'family.care_profile.review', 'family.message'],
            AiSupportGuidedTask::query()->pluck('navigation_target_id')->all(),
        );
        $profileTask = AiSupportGuidedTask::query()->where('navigation_target_id', 'family.care_profile.review')->sole();
        $this->assertSame(
            route('family.care-profiles.edit', ['careRecipientProfile' => $profileTask->resource_id, 'step' => 5]),
            app(NavigationTargetRegistry::class)->urlFor($family, $profileTask->navigation_target_id, [
                'resource_type' => $profileTask->resource_type,
                'resource_id' => $profileTask->resource_id,
            ]),
        );
        $this->assertDatabaseHas('ai_support_interaction_events', [
            'event_type' => 'family_account_status_read',
            'result_code' => FamilyGuidedAssistanceService::INTENT_OVERVIEW,
        ]);
        $this->assertDatabaseMissing('ai_support_interaction_events', ['event_type' => 'model_turn_completed']);
    }

    public function test_submitted_hours_guide_uses_authorized_resource_route_and_rejects_another_family_record(): void
    {
        [, $family] = $this->eligibleFamily();
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Alex Care']);
        $request = $this->request($family, [
            'title' => 'Saturday visit',
            'status' => CareRequest::STATUS_FILLED,
        ]);
        $booking = $this->booking($family, $caregiver, $request, [
            'status' => CareBooking::STATUS_COMPLETED,
            'completed_at' => now()->subHour(),
            'timesheet_submitted_at' => now()->subMinutes(50),
            'worked_minutes' => 135,
        ]);
        $ticket = $this->automatedTicket($family, 'Please help me review the submitted hours.');
        Http::fake();

        app(AiSupportRuntimeService::class)->respond($family, $ticket, $ticket->description);

        Http::assertNothingSent();
        $task = AiSupportGuidedTask::query()->sole();
        $action = AiSupportMessageAction::query()->where('action_type', AiSupportMessageAction::TYPE_GUIDED_TASK)->sole();
        $this->assertSame('family.request.timesheet', $task->navigation_target_id);
        $this->assertSame('care_request', $task->resource_type);
        $this->assertSame($request->id, (int) $task->resource_id);
        $this->assertStringContainsString('submitted hours', strtolower($ticket->publicMessages()->reorder()->latest()->firstOrFail()->body));

        $url = app(AiSupportGuidedTaskService::class)->startFromAction($family, $ticket, $action->id);
        $this->assertSame(route('family.requests.show', ['careRequest' => $request->id, 'tab' => 'shift']), $url);
        $this->assertSame(AiSupportGuidedTask::STATE_NAVIGATING, $task->fresh()->state);

        [, $otherFamily] = $this->eligibleFamily();
        $this->assertFalse(app(NavigationTargetRegistry::class)->allowedFor(
            $otherFamily,
            'family.request.timesheet',
            ['resource_type' => 'care_request', 'resource_id' => $request->id],
        ));
        $this->assertSame(135, $booking->fresh()->worked_minutes);
        $this->assertNull($booking->fresh()->family_confirmed_at);
    }

    public function test_production_state_phrases_ignore_past_visits_list_all_waiting_hours_and_answer_no_applicants(): void
    {
        [, $family] = $this->eligibleFamily();
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Current Caregiver']);
        $pastRequest = $this->request($family, [
            'title' => 'Old reviewed visit',
            'status' => CareRequest::STATUS_FILLED,
            'requested_start_at' => now()->subMonths(5),
            'requested_end_at' => now()->subMonths(5)->addHours(2),
        ]);
        $this->booking($family, $caregiver, $pastRequest, [
            'status' => CareBooking::STATUS_REVIEWED,
            'scheduled_start_at' => now()->subMonths(5),
            'scheduled_end_at' => now()->subMonths(5)->addHours(2),
            'timesheet_submitted_at' => now()->subMonths(5),
            'family_confirmed_at' => now()->subMonths(5),
        ]);

        $visitTicket = $this->automatedTicket($family, 'When is my next scheduled visit and who is the caregiver?');
        app(AiSupportRuntimeService::class)->respond($family, $visitTicket, $visitTicket->description);
        $this->assertStringContainsString(
            'did not find a current or upcoming visit',
            $visitTicket->publicMessages()->latest()->firstOrFail()->body,
        );

        foreach (['Monday hours', 'Tuesday hours'] as $index => $title) {
            $request = $this->request($family, ['title' => $title, 'status' => CareRequest::STATUS_FILLED]);
            $this->booking($family, $caregiver, $request, [
                'status' => CareBooking::STATUS_COMPLETED,
                'scheduled_start_at' => now()->subDays($index + 1),
                'scheduled_end_at' => now()->subDays($index + 1)->addHours(2),
                'completed_at' => now()->subDays($index + 1)->addHours(2),
                'timesheet_submitted_at' => now()->subDays($index + 1)->addHours(3),
                'worked_minutes' => 120,
            ]);
        }

        $hoursTicket = $this->automatedTicket($family, 'Which submitted hours are waiting for me to review right now?');
        app(AiSupportRuntimeService::class)->respond($family, $hoursTicket, $hoursTicket->description);
        $hoursBody = $hoursTicket->publicMessages()->latest()->firstOrFail()->body;
        $this->assertStringContainsString('2 submitted-hours review items waiting', $hoursBody);
        $this->assertStringContainsString('Monday hours', $hoursBody);
        $this->assertStringContainsString('Tuesday hours', $hoursBody);
        $this->assertStringNotContainsString('Old reviewed visit', $hoursBody);

        $applicantTicket = $this->automatedTicket($family, 'Do I have any caregivers waiting for me to review or hire?');
        app(AiSupportRuntimeService::class)->respond($family, $applicantTicket, $applicantTicket->description);
        $this->assertSame(
            'No caregivers are waiting for you to review or hire right now.',
            $applicantTicket->publicMessages()->latest()->firstOrFail()->body,
        );
        Http::assertNothingSent();
    }

    public function test_caregiver_visit_change_is_reported_as_an_attention_item_and_guides_to_the_exact_decision(): void
    {
        [, $family] = $this->eligibleFamily();
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Morgan Care']);
        $request = $this->request($family, [
            'title' => 'Wednesday visit',
            'status' => CareRequest::STATUS_FILLED,
        ]);
        $booking = $this->booking($family, $caregiver, $request);
        CareBookingChangeRequest::query()->create([
            'care_booking_id' => $booking->id,
            'requester_user_id' => $caregiver->id,
            'type' => CareBookingChangeRequest::TYPE_RESCHEDULE,
            'status' => CareBookingChangeRequest::STATUS_PENDING,
            'reason' => 'Schedule conflict',
            'proposed_start_at' => now()->addDays(2),
            'proposed_end_at' => now()->addDays(2)->addHours(2),
        ]);
        $ticket = $this->automatedTicket($family, 'Are there any current visit issues?');
        Http::fake();

        app(AiSupportRuntimeService::class)->respond($family, $ticket, $ticket->description);

        Http::assertNothingSent();
        $task = AiSupportGuidedTask::query()->sole();
        $this->assertSame('family.request.visit_issue', $task->navigation_target_id);
        $this->assertSame($request->id, (int) $task->resource_id);
        $this->assertStringContainsString('Caregiver proposed a new visit time', $ticket->publicMessages()->reorder()->latest()->firstOrFail()->body);
        $this->assertDatabaseHas('care_booking_change_requests', [
            'care_booking_id' => $booking->id,
            'status' => CareBookingChangeRequest::STATUS_PENDING,
        ]);
        $this->actingAs($family)
            ->get(route('family.requests.show', ['careRequest' => $request->id, 'tab' => 'shift']))
            ->assertOk()
            ->assertSee('data-ai-target="family.request.visit_issue"', false);
    }

    public function test_profile_messages_history_and_empty_visit_answers_remain_read_only_and_guided(): void
    {
        [, $family] = $this->eligibleFamily();
        Http::fake();

        $cases = [
            ['Is the care receiver profile complete?', 'family.care_profile.create', 'There is no active care receiver profile'],
            ['Do I have unread messages?', 'family.messages', 'do not have any caregiver conversations'],
            ['Show my care history and receipts', 'family.care_history', 'does not have a completed visit'],
            ['When is my next visit?', 'family.care_requests', 'did not find a current or upcoming visit'],
        ];

        foreach ($cases as [$question, $target, $copy]) {
            $ticket = $this->automatedTicket($family, $question);
            app(AiSupportRuntimeService::class)->respond($family, $ticket, $question);
            $this->assertSame($target, AiSupportGuidedTask::query()->where('support_ticket_id', $ticket->id)->sole()->navigation_target_id);
            $this->assertStringContainsString($copy, $ticket->publicMessages()->reorder()->latest()->firstOrFail()->body);
        }

        Http::assertNothingSent();
        $this->assertDatabaseCount('care_requests', 0);
        $this->assertDatabaseCount('care_bookings', 0);
        $this->assertDatabaseCount('care_recipient_profiles', 0);
        $this->assertDatabaseMissing('ai_support_interaction_events', ['event_type' => 'model_turn_completed']);
    }

    public function test_empty_regular_care_answer_guides_to_the_regular_care_page(): void
    {
        [, $family] = $this->eligibleFamily();
        Http::fake();

        $ticket = $this->automatedTicket($family, 'Show me my regular care plan.');
        app(AiSupportRuntimeService::class)->respond($family, $ticket, $ticket->description);

        $task = AiSupportGuidedTask::query()->where('support_ticket_id', $ticket->id)->sole();
        $this->assertSame('family.regular_care', $task->navigation_target_id);
        $this->assertStringContainsString('do not have a regular care plan yet', $ticket->publicMessages()->reorder()->latest()->firstOrFail()->body);
        $this->assertSame(
            route('family.care.index'),
            app(NavigationTargetRegistry::class)->urlFor($family, $task->navigation_target_id),
        );

        $this->actingAs($family)
            ->get(route('family.care.index'))
            ->assertOk()
            ->assertSee('data-ai-target="family.regular_care"', false);
        Http::assertNothingSent();
    }

    public function test_regular_care_attention_guides_to_the_plan_instead_of_its_system_request(): void
    {
        [, $family] = $this->eligibleFamily();
        $account = app(FamilyAccountContext::class)->account($family);
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Taylor Care']);
        $plan = CarePlan::query()->create([
            'family_account_id' => $account->id,
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
        $systemRequest = $this->request($family, [
            'care_plan_id' => $plan->id,
            'is_system_generated' => true,
            'status' => CareRequest::STATUS_FILLED,
            'request_type' => CareRequest::TYPE_RECURRING,
        ]);
        $this->booking($family, $caregiver, $systemRequest, [
            'care_plan_id' => $plan->id,
            'status' => CareBooking::STATUS_IN_PROGRESS,
            'started_at' => now()->subMinutes(20),
        ]);
        $ticket = $this->automatedTicket($family, 'What needs my attention?');
        Http::fake();

        app(AiSupportRuntimeService::class)->respond($family, $ticket, $ticket->description);

        Http::assertNothingSent();
        $task = AiSupportGuidedTask::query()
            ->where('navigation_target_id', 'family.regular_care.attention')
            ->sole();
        $this->assertSame('care_plan', $task->resource_type);
        $this->assertSame($plan->id, (int) $task->resource_id);
        $this->assertSame(
            route('family.care.show', $plan),
            app(NavigationTargetRegistry::class)->urlFor($family, $task->navigation_target_id, [
                'resource_type' => $task->resource_type,
                'resource_id' => $task->resource_id,
            ]),
        );
        $this->actingAs($family)->get(route('family.care.show', $plan))
            ->assertOk()
            ->assertSee('data-ai-target="family.regular_care.attention"', false);
    }

    public function test_batch_two_pages_expose_only_registered_semantic_targets(): void
    {
        [, $family] = $this->eligibleFamily();
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $request = $this->request($family, ['title' => 'Applicant target']);
        CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_APPLIED,
        ]);

        $this->actingAs($family)->get(route('family.requests.index'))
            ->assertOk()
            ->assertSee('data-ai-target="family.care_requests"', false);
        $this->actingAs($family)->get(route('family.care-profiles.index'))
            ->assertOk()
            ->assertSee('data-ai-target="family.care_profiles"', false);
        $this->actingAs($family)->get(route('family.care-profiles.create'))
            ->assertOk()
            ->assertSee('data-ai-target="family.care_profile.editor"', false);
        $this->actingAs($family)->get(route('messages.index'))
            ->assertOk()
            ->assertSee('data-ai-target="family.messages.inbox"', false);
        $this->actingAs($family)->get(route('family.care.history'))
            ->assertOk()
            ->assertSee('data-ai-target="family.care_history"', false);
        $this->actingAs($family)->get(route('family.requests.show', ['careRequest' => $request->id, 'tab' => 'applicants']))
            ->assertOk()
            ->assertSee('data-ai-target="family.request.applicants"', false);

        $visitRequest = $this->request($family, [
            'title' => 'Timesheet target',
            'status' => CareRequest::STATUS_FILLED,
        ]);
        $this->booking($family, $caregiver, $visitRequest, [
            'status' => CareBooking::STATUS_COMPLETED,
            'completed_at' => now()->subHour(),
            'timesheet_submitted_at' => now()->subMinutes(45),
            'worked_minutes' => 90,
        ]);
        $this->actingAs($family)->get(route('family.requests.show', ['careRequest' => $visitRequest->id, 'tab' => 'shift']))
            ->assertOk()
            ->assertSee('data-ai-target="family.request.visit"', false)
            ->assertSee('data-ai-target="family.request.timesheet"', false);
    }

    /** @return array{User,User} */
    private function eligibleFamily(): array
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
            $controls->set($admin, $key, true, 'Open Family guided assistance test capability');
        }
        $controls->set($admin, 'human_only', false, 'Permit Family guided assistance test conversation');

        $family = User::factory()->create(['role' => 'family']);
        app(FamilyAccountContext::class)->account($family);
        app(AiSupportPilotGrantService::class)->grant(
            $admin,
            $family,
            'family_support_v1',
            CarbonImmutable::now(),
            CarbonImmutable::now()->addDays(14),
            'Exact-user Family guided assistance test',
            (string) Str::uuid(),
        );

        return [$admin, $family];
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

    private function automatedTicket(User $family, string $description): SupportTicket
    {
        return SupportTicket::query()->create([
            'family_account_id' => app(FamilyAccountContext::class)->account($family)->id,
            'family_visibility' => 'opener_only',
            'opener_user_id' => $family->id,
            'source' => SupportTicket::SOURCE_CHAT_WIDGET,
            'responder_mode' => SupportTicket::RESPONDER_MODE_AUTOMATED,
            'category' => 'general',
            'status' => SupportTicket::STATUS_OPEN,
            'priority' => 'normal',
            'subject' => 'Family guided assistance test',
            'description' => $description,
            'initial_client_message_id' => (string) Str::uuid(),
        ]);
    }
}
