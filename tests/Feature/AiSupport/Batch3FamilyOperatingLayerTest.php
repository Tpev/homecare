<?php

namespace Tests\Feature\AiSupport;

use App\Livewire\Admin\AiSupport\Overview;
use App\Livewire\Family\CareProfileEditor;
use App\Livewire\Support\ChatWidget;
use App\Models\AiSupportGuidedTask;
use App\Models\AiSupportMessageAction;
use App\Models\AiSupportPreparation;
use App\Models\CareBooking;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\KnowledgeBaseEntry;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\AiSupport\AiSupportCompletionVerifierRegistry;
use App\Services\AiSupport\AiSupportControlService;
use App\Services\AiSupport\AiSupportGuidedTaskService;
use App\Services\AiSupport\AiSupportPilotGrantService;
use App\Services\AiSupport\AiSupportPreparationContractRegistry;
use App\Services\AiSupport\AiSupportPreparationService;
use App\Services\AiSupport\AiSupportRuntimeService;
use App\Services\AiSupport\FamilyAssistantHomeService;
use App\Services\AiSupport\FamilyIntentCatalog;
use App\Services\AiSupport\FamilyIntentEvaluationCatalog;
use App\Services\AiSupport\FamilyIntentResolver;
use App\Services\AiSupport\FamilyOperationsKnowledgeBaseImportService;
use App\Services\AiSupport\InitialKnowledgeBaseImportService;
use App\Services\AiSupport\KnowledgeBaseWorkflowService;
use App\Services\AiSupport\PaymentTimeKnowledgeBaseImportService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class Batch3FamilyOperatingLayerTest extends TestCase
{
    use RefreshDatabase;

    public function test_executable_catalog_contains_every_intent_and_all_wave_one_mappings(): void
    {
        $catalog = app(FamilyIntentCatalog::class);
        $records = $catalog->records();

        $this->assertCount(324, $records);
        $this->assertCount(324, array_unique(array_column($records, 'intent_id')));
        $this->assertSame(324, $catalog->coverageSummary()['kb_mapped']);
        $this->assertSame(
            ['care_profile_v1', 'care_request_reuse_v1', 'caregiver_message_v1', 'submitted_hours_correction_v1', 'support_intake_v1'],
            array_keys(app(AiSupportPreparationContractRegistry::class)->all()),
        );

        foreach ($records as $record) {
            $this->assertCount(3, data_get($record, 'phrases.ordinary'));
            $this->assertNotEmpty(data_get($record, 'phrases.imperfect'));
            $this->assertArrayHasKey('verifier', $record['contracts']);
            $this->assertArrayHasKey('unsupported_behavior', $record['disposition']);
            if (filled($record['contracts']['verifier'])) {
                $this->assertTrue(app(AiSupportCompletionVerifierRegistry::class)->has($record['contracts']['verifier']));
            }
        }
    }

    public function test_all_family_intents_now_have_an_operational_resolution(): void
    {
        $records = collect(app(FamilyIntentCatalog::class)->records());
        $outcomes = ['complete' => 0, 'assisted' => 0, 'human' => 0, 'none' => 0];

        foreach ($records as $record) {
            $stages = (array) data_get($record, 'capability_stages.current', []);
            $outcome = in_array('Execute', $stages, true) && in_array('Verify', $stages, true)
                ? 'complete'
                : (in_array('Human', $stages, true)
                    ? 'human'
                    : (array_intersect(['Read', 'Navigate', 'Guide', 'Prepare'], $stages) !== [] ? 'assisted' : 'none'));
            $outcomes[$outcome]++;
        }

        $this->assertSame([
            'complete' => 74,
            'assisted' => 163,
            'human' => 87,
            'none' => 0,
        ], $outcomes);

        foreach ([
            'FAM-START-001', 'FAM-START-002', 'FAM-START-016', 'FAM-PROFILE-001',
            'FAM-REQUEST-032', 'FAM-REQUEST-033', 'FAM-REQUEST-044', 'FAM-PAY-010',
            'FAM-PAY-028', 'FAM-PAY-029', 'FAM-PAY-030',
        ] as $intentId) {
            $record = $records->firstWhere('intent_id', $intentId);
            $this->assertNotNull($record, $intentId);
            $this->assertContains('Guide', data_get($record, 'capability_stages.current', []), $intentId);
            $this->assertNotEmpty(data_get($record, 'contracts.destinations'), $intentId);
        }
        $this->assertContains('Read', data_get($records->firstWhere('intent_id', 'FAM-REQUEST-032'), 'capability_stages.current'));
        $this->assertContains('Explain', data_get($records->firstWhere('intent_id', 'FAM-PAY-030'), 'capability_stages.current'));
    }

    public function test_the_eleven_closed_intents_answer_and_offer_a_truthful_next_step_without_provider_calls(): void
    {
        [$admin, $family] = $this->eligibleFamily();
        $this->publishCoverageClosureKnowledge($admin);
        $this->request($family);
        Http::fake();

        $cases = [
            ['Understand what LoLo does for Families', 'helps Families arrange non-medical home care', 'family.care_requests'],
            ['Understand what non-medical care means', 'everyday help such as companionship', 'support.center'],
            ['Understand what the AI can and cannot do', 'perform specifically enabled actions only after a clear recap and confirmation', 'support.center'],
            ['Understand what a care-receiver profile is and who sees it', 'profile stores reusable information', 'family.care_profiles'],
            ['Understand whether publication hired a caregiver', 'publication itself does not hire anyone', 'family.request.overview'],
            ['Understand whether publication charged or authorized the card', 'does not charge or authorize the card', 'family.care_requests'],
            ['Ask for a guaranteed caregiver response or response time', 'cannot guarantee', 'support.center'],
            ['Understand whether publishing a request charges the card', 'does not charge or authorize the card', 'family.care_requests'],
            ['Are taxes, tips, mileage, or holiday charges added?', 'I do not add taxes, tips, mileage, holiday charges, or surcharges', 'family.new_care_request'],
            ['What would 2.5 hours cost?', 'Family total is $75.00', 'family.new_care_request'],
            ['Apply a coupon, credit, or promo code', 'does not currently provide a coupon', 'family.care_history'],
        ];

        foreach ($cases as [$message, $expectedAnswer, $expectedTarget]) {
            $ticket = $this->ticket($family, $message);
            app(AiSupportRuntimeService::class)->respond($family, $ticket, $message);

            $answer = $ticket->publicMessages()->reorder()->latest()->firstOrFail()->body;
            $this->assertStringContainsString($expectedAnswer, $answer, $message);
            $this->assertTrue(
                AiSupportGuidedTask::query()
                    ->where('support_ticket_id', $ticket->id)
                    ->where('navigation_target_id', $expectedTarget)
                    ->exists()
                || AiSupportMessageAction::query()
                    ->where('support_ticket_id', $ticket->id)
                    ->get()
                    ->contains(fn (AiSupportMessageAction $action): bool => data_get($action->payload, 'target_id') === $expectedTarget),
                $message.' should offer '.$expectedTarget,
            );
        }

        Http::assertNothingSent();
    }

    public function test_existing_forty_intents_resolve_to_their_stable_ids(): void
    {
        $resolver = app(FamilyIntentResolver::class);
        foreach (app(FamilyIntentEvaluationCatalog::class)->cases() as $case) {
            $resolution = $resolver->resolve((string) $case['phrases'][0]);
            $this->assertSame(FamilyIntentResolver::STATUS_RECOGNIZED, $resolution['status'], $case['intent_id']);
            $this->assertSame($case['intent_id'], $resolution['intent_id'], $case['intent_id']);
        }
    }

    public function test_active_task_understands_recovery_repetition_verification_and_stop_without_provider_calls(): void
    {
        [, $family] = $this->eligibleFamily();
        $ticket = $this->ticket($family, 'Help me add a payment method');
        Http::fake();
        $task = app(AiSupportGuidedTaskService::class)->offerPaymentMethod($family, $ticket, 'FAM-PAY-002');

        app(AiSupportRuntimeService::class)->respond($family, $ticket, "I can't find it");
        app(AiSupportRuntimeService::class)->respond($family, $ticket, 'It did not work');

        $this->assertSame(2, (int) data_get($task->fresh()->payload, 'recovery_count'));
        $this->assertDatabaseHas('ai_support_interaction_events', ['event_type' => 'intent_looped']);
        $this->assertStringContainsString(
            'will not keep repeating',
            $ticket->publicMessages()->reorder()->latest()->firstOrFail()->body,
        );

        app(AiSupportRuntimeService::class)->respond($family, $ticket, 'check again');
        $this->assertStringContainsString(
            'not a ready saved payment method',
            $ticket->publicMessages()->reorder()->latest()->firstOrFail()->body,
        );
        $this->assertNull($task->fresh()->completed_at);

        app(AiSupportRuntimeService::class)->respond($family, $ticket, 'stop');
        $this->assertSame(AiSupportGuidedTask::STATE_CANCELLED, $task->fresh()->state);
        Http::assertNothingSent();
    }

    public function test_generic_verifier_never_turns_arrival_or_user_claim_into_completion(): void
    {
        [, $family] = $this->eligibleFamily();
        $ticket = $this->ticket($family, 'Open my messages');
        $task = app(AiSupportGuidedTaskService::class)->offerFamilyReadResult(
            $family,
            $ticket,
            'I can open your messages.',
            'FAM-COMMS-001',
            'messages',
            [[
                'task_type' => AiSupportGuidedTask::TYPE_FAMILY_MESSAGE,
                'target_id' => 'family.messages',
                'label' => 'Open messages',
                'verifier_id' => 'unavailable_v1',
            ]],
        )->sole();

        app(AiSupportRuntimeService::class)->respond($family, $ticket, 'I did it');

        $this->assertSame(AiSupportGuidedTask::STATE_ARRIVED, $task->fresh()->state);
        $this->assertNull($task->fresh()->completed_at);
        $this->assertStringContainsString(
            'cannot safely verify',
            $ticket->publicMessages()->reorder()->latest()->firstOrFail()->body,
        );
    }

    public function test_all_five_preparation_contracts_are_visible_reversible_and_do_not_mutate_domains(): void
    {
        [, $family] = $this->eligibleFamily();
        $account = app(FamilyAccountContext::class)->account($family);
        $ticket = $this->ticket($family, 'Prepare some details');
        $request = $this->request($family);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
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
            'started_by_user_id' => $family->id,
        ]);
        $service = app(AiSupportPreparationService::class);
        $requestCount = CareRequest::query()->count();

        $profile = $service->prepare($family, $ticket, 'care_profile_v1', ['preferred_name' => 'Maria'], ['intent_id' => 'FAM-PROFILE-003']);
        $service->prepare($family, $ticket, 'care_request_reuse_v1', ['source_request_id' => (string) $request->id, 'recipient_name' => 'Maria'], ['intent_id' => 'FAM-REQUEST-040']);
        $service->prepare($family, $ticket, 'caregiver_message_v1', ['conversation_id' => (string) $conversation->id, 'message' => 'Please arrive at 9 AM.'], [
            'intent_id' => 'FAM-COMMS-003', 'resource_type' => 'conversation', 'resource_id' => $conversation->id,
        ]);
        $service->prepare($family, $ticket, 'submitted_hours_correction_v1', ['care_request_id' => (string) $request->id, 'issue_type' => 'correction', 'reason' => 'The end time should be 11 AM.'], [
            'intent_id' => 'FAM-VISIT-022', 'resource_type' => 'care_request', 'resource_id' => $request->id,
        ]);
        $service->prepare($family, $ticket, 'support_intake_v1', ['category' => 'general', 'subject' => 'Page problem', 'description' => 'The button is not available.'], ['intent_id' => 'FAM-SUPPORT-008']);

        $this->assertDatabaseCount('ai_support_preparations', 5);
        $this->assertSame($requestCount, CareRequest::query()->count());
        $this->assertDatabaseCount('care_request_messages', 0);
        $this->assertDatabaseCount('support_tickets', 1);

        $action = AiSupportMessageAction::query()
            ->where('action_type', AiSupportMessageAction::TYPE_PREPARATION)
            ->where('payload', '!=', null)
            ->get()
            ->first(fn (AiSupportMessageAction $candidate): bool => data_get($candidate->payload, 'preparation_id') === $profile->id);
        $this->assertNotNull($action);
        $this->actingAs($family);
        $service->applyFromAction($family, $ticket, $action->id);
        Livewire::test(CareProfileEditor::class)
            ->assertSet('preferredName', 'Maria')
            ->assertSet('aiPrepared', true);

        $service->cancel($family, $profile->id);
        $this->assertSame(AiSupportPreparation::STATE_CANCELLED, $profile->fresh()->state);
    }

    public function test_ordinary_message_request_creates_editable_preparation_without_sending_or_calling_provider(): void
    {
        [, $family] = $this->eligibleFamily();
        $request = $this->request($family);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id, 'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_APPLIED,
        ]);
        CareRequestConversation::query()->create([
            'care_request_id' => $request->id,
            'family_account_id' => app(FamilyAccountContext::class)->account($family)->id,
            'family_user_id' => $family->id, 'caregiver_user_id' => $caregiver->id,
            'care_request_application_id' => $application->id, 'started_by_user_id' => $family->id,
        ]);
        $text = 'Please draft a message to my caregiver saying I will arrive at nine.';
        $ticket = $this->ticket($family, $text);
        Http::fake();

        app(AiSupportRuntimeService::class)->respond($family, $ticket, $text);

        $this->assertDatabaseHas('ai_support_preparations', ['contract_id' => 'caregiver_message_v1', 'state' => 'ready']);
        $this->assertDatabaseCount('care_request_messages', 0);
        $this->assertDatabaseHas('ai_support_interaction_events', ['event_type' => 'intent_prepared']);
        $this->assertStringContainsString('have not been saved, sent, submitted, approved, or confirmed', $ticket->publicMessages()->reorder()->latest()->firstOrFail()->body);
        Http::assertNothingSent();
    }

    public function test_natural_profile_creation_extracts_only_the_name_and_discard_removes_the_card(): void
    {
        [, $family] = $this->eligibleFamily();
        $text = 'Can you help me create a care-receiver profile for Maria?';
        $ticket = $this->ticket($family, $text);
        Http::fake();

        app(AiSupportRuntimeService::class)->respond($family, $ticket, $text);

        $preparation = AiSupportPreparation::query()->sole();
        $this->assertSame('care_profile_v1', $preparation->contract_id);
        $this->assertSame('Maria', data_get($preparation->payload, 'fields.preferred_name'));
        $this->assertNotSame($text, data_get($preparation->payload, 'fields.preferred_name'));
        $action = AiSupportMessageAction::query()
            ->where('action_type', AiSupportMessageAction::TYPE_PREPARATION)
            ->sole();

        Livewire::actingAs($family)
            ->test(ChatWidget::class)
            ->call('cancelPreparation', $preparation->id)
            ->assertSee('Prepared details discarded. Nothing was saved or sent.');

        $this->assertSame(AiSupportPreparation::STATE_CANCELLED, $preparation->fresh()->state);
        $this->assertNotNull($action->fresh()->invalidated_at);
        $this->assertSame('preparation_cancelled', $action->fresh()->invalidation_reason);
        Http::assertNothingSent();
    }

    public function test_questioning_submitted_hours_prepares_an_editable_correction(): void
    {
        [, $family] = $this->eligibleFamily();
        $request = $this->request($family);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        CareBooking::query()->create([
            'care_request_id' => $request->id,
            'family_account_id' => app(FamilyAccountContext::class)->account($family)->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_COMPLETED,
            'scheduled_start_at' => now()->subHours(3),
            'scheduled_end_at' => now()->subHour(),
            'completed_at' => now()->subHour(),
            'timesheet_submitted_at' => now()->subMinutes(45),
            'worked_minutes' => 120,
        ]);
        $text = 'I need help to question submitted hours because the end time should be 11 AM.';
        $resolution = app(FamilyIntentResolver::class)->resolve($text);
        $this->assertSame(FamilyIntentResolver::STATUS_RECOGNIZED, $resolution['status']);
        $this->assertSame('FAM-VISIT-022', $resolution['intent_id']);
        $ticket = $this->ticket($family, $text);
        Http::fake();

        app(AiSupportRuntimeService::class)->respond($family, $ticket, $text);

        $preparation = AiSupportPreparation::query()->sole();
        $this->assertSame('submitted_hours_correction_v1', $preparation->contract_id);
        $this->assertSame((string) $request->id, data_get($preparation->payload, 'fields.care_request_id'));
        $this->assertSame('correction', data_get($preparation->payload, 'fields.issue_type'));
        $this->assertSame('the end time should be 11 AM.', data_get($preparation->payload, 'fields.reason'));
        $this->assertDatabaseMissing('ai_support_interaction_events', ['event_type' => 'model_turn_completed']);
        Http::assertNothingSent();
    }

    public function test_preparation_rejects_secrets_and_cross_account_resources(): void
    {
        [, $family] = $this->eligibleFamily();
        $ticket = $this->ticket($family, 'Prepare details');
        $service = app(AiSupportPreparationService::class);

        try {
            $service->prepare($family, $ticket, 'support_intake_v1', [
                'category' => 'general', 'description' => 'My card is 4242 4242 4242 4242',
            ]);
            $this->fail('Card data should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('card details', collect($exception->errors())->flatten()->first());
        }

        $other = User::factory()->create(['role' => 'family']);
        app(FamilyAccountContext::class)->account($other);
        $otherRequest = $this->request($other);
        $this->expectException(AuthorizationException::class);
        $service->prepare($family, $ticket, 'submitted_hours_correction_v1', [
            'care_request_id' => (string) $otherRequest->id, 'reason' => 'Wrong time',
        ], ['resource_type' => 'care_request', 'resource_id' => $otherRequest->id]);
    }

    public function test_state_aware_home_never_shows_more_than_three_personalized_suggestions(): void
    {
        [, $family] = $this->eligibleFamily();
        $home = app(FamilyAssistantHomeService::class)->for($family);

        $this->assertLessThanOrEqual(3, count($home['personalized']));
        $this->assertSame([
            'See what needs my attention', 'Create a care request', 'Check my next visit',
            'Payment help', 'Something else', 'Talk to a person',
        ], array_column($home['general'], 'label'));
    }

    public function test_state_aware_start_card_is_visible_only_to_the_exact_pilot_user(): void
    {
        [, $pilot] = $this->eligibleFamily();
        $nonPilot = User::factory()->create(['role' => 'family']);
        app(FamilyAccountContext::class)->account($nonPilot);

        $this->assertFalse(app(AiSupportControlService::class)->enabled('general_release_enabled'));
        Livewire::actingAs($pilot)->test(ChatWidget::class)
            ->assertSee('See what needs my attention')
            ->assertSee('Talk to a person');
        Livewire::actingAs($nonPilot)->test(ChatWidget::class)
            ->assertDontSee('See what needs my attention')
            ->assertDontSee('Create a care request');
    }

    public function test_admin_coverage_surface_lists_catalog_and_compact_outcomes(): void
    {
        [$admin, $family] = $this->eligibleFamily();
        $ticket = $this->ticket($family, 'Unknown example');
        app(\App\Services\AiSupport\AiSupportEventRecorder::class)->record($ticket, 'intent_unmatched', [
            'capability_id' => 'support_answers_v1', 'result_code' => 'unmatched',
        ], $family);
        app(\App\Services\AiSupport\AiSupportEventRecorder::class)->record($ticket, 'transferred_to_human', [
            'capability_id' => 'support_answers_v1', 'result_code' => 'user_requested',
        ], $family);

        Livewire::actingAs($admin)->test(Overview::class)
            ->assertSee('Family intent coverage')
            ->assertSee('324')
            ->assertSee('324')
            ->assertSee('FAM-START-001')
            ->assertSee('Showing the first 50 of 324 matching intents')
            ->assertSee('Recent Family intent outcomes')
            ->assertSee('Unmatched')
            ->assertViewHas('intentOutcomeCounts', fn ($counts): bool => $counts->get('intent_transferred') === 1);
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
            $controls->set($admin, $key, true, 'Batch 3 operating-layer test');
        }
        $controls->set($admin, 'human_only', false, 'Batch 3 operating-layer test');
        $family = User::factory()->create(['role' => 'family']);
        app(FamilyAccountContext::class)->account($family);
        app(AiSupportPilotGrantService::class)->grant(
            $admin, $family, 'family_support_v1', CarbonImmutable::now(), CarbonImmutable::now()->addDays(14),
            'Exact-user Batch 3 test', (string) Str::uuid(),
        );

        return [$admin, $family];
    }

    private function ticket(User $family, string $description): SupportTicket
    {
        return SupportTicket::query()->create([
            'family_account_id' => app(FamilyAccountContext::class)->account($family)->id,
            'family_visibility' => 'opener_only', 'opener_user_id' => $family->id,
            'source' => SupportTicket::SOURCE_CHAT_WIDGET,
            'responder_mode' => SupportTicket::RESPONDER_MODE_AUTOMATED,
            'category' => 'general', 'status' => SupportTicket::STATUS_OPEN, 'priority' => 'normal',
            'subject' => 'Batch 3 test', 'description' => $description,
            'initial_client_message_id' => (string) Str::uuid(),
        ]);
    }

    private function request(User $family): CareRequest
    {
        return CareRequest::query()->create([
            'family_account_id' => app(FamilyAccountContext::class)->account($family)->id,
            'family_user_id' => $family->id, 'created_by_user_id' => $family->id,
            'is_system_generated' => false, 'title' => 'Test request', 'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addDay(), 'requested_end_at' => now()->addDay()->addHours(2),
            'address_line1' => '123 Main Street', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
        ]);
    }

    private function publishCoverageClosureKnowledge(User $admin): void
    {
        app(InitialKnowledgeBaseImportService::class)->apply($admin);
        $entry = KnowledgeBaseEntry::query()->where('stable_id', 'KB-FAM-004')->firstOrFail();
        $workflow = app(KnowledgeBaseWorkflowService::class);
        $version = $workflow->submitForReview($admin, $entry->workingVersion);
        $version = $workflow->approve($admin, $version);
        $workflow->publish($admin, $version, 'Publish initial Family access fixture.');

        app(FamilyOperationsKnowledgeBaseImportService::class)->publishPackage(
            $admin,
            'Publish the Family intent coverage closure package.',
        );
        app(PaymentTimeKnowledgeBaseImportService::class)->publishPackage(
            $admin,
            'Publish pricing knowledge for Family intent coverage closure.',
        );
    }
}
