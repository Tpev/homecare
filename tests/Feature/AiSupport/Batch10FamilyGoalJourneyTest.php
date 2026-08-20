<?php

namespace Tests\Feature\AiSupport;

use App\Livewire\Support\ChatWidget;
use App\Models\AiSupportGoalJourney;
use App\Models\AiSupportMessageAction;
use App\Models\AiSupportRequestDraft;
use App\Models\CareRequest;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\AiSupport\AiSupportControlService;
use App\Services\AiSupport\AiSupportHandoffService;
use App\Services\AiSupport\AiSupportPilotGrantService;
use App\Services\AiSupport\AiSupportRequestDraftService;
use App\Services\AiSupport\AiSupportRuntimeService;
use App\Services\AiSupport\FamilyCareTypeDecisionService;
use App\Services\AiSupport\FamilyGoalJourneyCatalog;
use App\Services\AiSupport\FamilyGoalJourneyService;
use App\Services\AiSupport\FamilyIntentCatalog;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class Batch10FamilyGoalJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_frozen_care_type_corpus_passes_without_a_provider(): void
    {
        $decisions = app(FamilyCareTypeDecisionService::class);
        $cases = $decisions->evaluationCases();

        $this->assertCount(48, $cases);
        foreach ($cases as $case) {
            $actual = $decisions->decide($case['message'], (bool) $case['care_context']);
            $this->assertSame(
                $case['expected'],
                $actual['path'] ?? 'unrelated',
                $case['id'].': '.$case['message'],
            );
        }
    }

    public function test_vague_need_starts_persistent_goal_and_asks_only_the_required_question(): void
    {
        [, $family] = $this->eligibleFamily();
        $ticket = $this->ticket($family, 'My mother needs some help.');
        Http::fake();

        app(AiSupportRuntimeService::class)->respond($family, $ticket, $ticket->description);

        Http::assertNothingSent();
        $journey = AiSupportGoalJourney::query()->sole();
        $this->assertSame('care_request', $journey->journey_type);
        $this->assertSame(AiSupportGoalJourney::STATE_AWAITING_CHOICE, $journey->state);
        $this->assertSame('Is this help for one specific date, or will it repeat every week?', $ticket->publicMessages()->latest()->firstOrFail()->body);
        $action = AiSupportMessageAction::query()->sole();
        $this->assertSame(['one_time', 'recurring', 'unsure', 'human'], collect($action->payload['choices'])->pluck('id')->all());
    }

    public function test_clear_regular_need_requires_explicit_choice_then_starts_the_matching_draft(): void
    {
        [, $family] = $this->eligibleFamily();
        $ticket = $this->ticket($family, 'We need help every Monday and Wednesday.');
        Http::fake();
        app(AiSupportRuntimeService::class)->respond($family, $ticket, $ticket->description);
        $action = AiSupportMessageAction::query()->sole();

        $this->assertSame('recurring', data_get($action->payload, 'recommended_path'));
        $this->assertSame('Continue with regular care', data_get($action->payload, 'choices.0.label'));
        $result = app(FamilyGoalJourneyService::class)->chooseCarePath($family, $ticket, $action->id, 'recurring');

        $this->assertSame('selected', $result['result']);
        $this->assertSame($ticket->description, $result['continue_message']);
        $draft = AiSupportRequestDraft::query()->sole();
        $this->assertSame(CareRequest::TYPE_RECURRING, $draft->request_type);
        $this->assertSame('explicit_user_selection', data_get($draft->payload, '_provenance.request_type'));
        $this->assertSame('collect_request_details', AiSupportGoalJourney::query()->sole()->step_key);
    }

    public function test_irregular_dates_start_the_first_one_time_request_and_preserve_the_remainder(): void
    {
        [, $family] = $this->eligibleFamily();
        $message = 'I need someone this Tuesday and then next Saturday.';
        $ticket = $this->ticket($family, $message);
        app(AiSupportRuntimeService::class)->respond($family, $ticket, $message);
        $action = AiSupportMessageAction::query()->sole();

        $this->assertSame('Start the first one-time request', data_get($action->payload, 'choices.0.label'));
        app(FamilyGoalJourneyService::class)->chooseCarePath($family, $ticket, $action->id, 'one_time');

        $journey = AiSupportGoalJourney::query()->sole();
        $this->assertSame(['next saturday'], array_map('strtolower', (array) data_get($journey->context, 'remaining_irregular_dates')));
        $this->assertSame(CareRequest::TYPE_ONE_TIME, AiSupportRequestDraft::query()->sole()->request_type);
    }

    public function test_natural_language_type_change_clears_only_incompatible_schedule_fields(): void
    {
        [, $family] = $this->eligibleFamily();
        $ticket = $this->ticket($family, 'Help me create regular care.');
        $journeys = app(FamilyGoalJourneyService::class);
        app(AiSupportRuntimeService::class)->respond($family, $ticket, $ticket->description);
        $action = AiSupportMessageAction::query()->sole();
        $journeys->chooseCarePath($family, $ticket, $action->id, CareRequest::TYPE_RECURRING);
        $draft = AiSupportRequestDraft::query()->sole();
        $draft = app(AiSupportRequestDraftService::class)->applyPatch($family, $ticket, [
            'patch_fields' => ['recipient_full_name', 'address_line1', 'city', 'state', 'zip', 'recurring_days', 'recurring_schedule', 'recurring_starts_on'],
            'recipient_full_name' => 'Mary',
            'address_line1' => '10 Oak Street',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
            'recurring_days' => [1],
            'recurring_schedule' => [['day' => 1, 'start_time' => '09:00', 'duration_minutes' => 120]],
            'recurring_starts_on' => now('America/New_York')->next('Monday')->toDateString(),
        ], $draft->version);

        $handled = $journeys->handleEarly($family, $ticket, 'Actually, it is just this Sunday.');

        $this->assertTrue($handled);
        $payload = (array) $draft->fresh()->payload;
        $this->assertSame(CareRequest::TYPE_ONE_TIME, $draft->fresh()->request_type);
        $this->assertSame('Mary', $payload['recipient_full_name']);
        $this->assertSame('10 Oak Street', $payload['address_line1']);
        $this->assertArrayNotHasKey('recurring_days', $payload);
        $this->assertArrayNotHasKey('recurring_schedule', $payload);
        $this->assertSame('explicit_user_type_change', data_get($payload, '_provenance.request_type'));
    }

    public function test_journey_restores_after_refresh_and_chat_renders_plain_goal_progress(): void
    {
        [, $family] = $this->eligibleFamily();
        $ticket = $this->ticket($family, 'I need one visit next Tuesday.');
        app(AiSupportRuntimeService::class)->respond($family, $ticket, $ticket->description);
        $journeyId = AiSupportGoalJourney::query()->sole()->id;

        $restored = app(FamilyGoalJourneyService::class)->activeFor($family->fresh(), $ticket->fresh());
        $this->assertSame($journeyId, $restored?->id);

        Livewire::actingAs($family)
            ->test(ChatWidget::class)
            ->assertSee('Choose care and create a request')
            ->assertSee('Step 1 of 4')
            ->assertSee('Next: choose one-time or regular care');
    }

    public function test_different_goal_never_silently_merges_and_user_can_keep_the_current_goal(): void
    {
        [, $family] = $this->eligibleFamily();
        $ticket = $this->ticket($family, 'I need one visit next Tuesday.');
        app(AiSupportRuntimeService::class)->respond($family, $ticket, $ticket->description);
        $active = AiSupportGoalJourney::query()->sole();
        $differentGoal = collect(app(FamilyIntentCatalog::class)->records())
            ->first(fn (array $record): bool => $record['domain'] === 'communications');
        $this->assertNotNull($differentGoal);

        $blocked = app(FamilyGoalJourneyService::class)->coordinateIntent(
            $family,
            $ticket,
            $differentGoal,
            'Open my messages.',
        );

        $this->assertTrue($blocked);
        $this->assertSame(AiSupportGoalJourney::STATE_AWAITING_CHOICE, $active->fresh()->state);
        $choice = AiSupportMessageAction::query()->where('action_type', AiSupportMessageAction::TYPE_JOURNEY_CHOICES)->sole();
        $this->assertSame(['continue_current', 'start_new', 'unsure', 'human'], collect($choice->payload['choices'])->pluck('id')->all());
        app(FamilyGoalJourneyService::class)->chooseJourney($family, $ticket, $choice->id, 'continue_current');
        $this->assertSame('care_request', $active->fresh()->journey_type);
        $this->assertSame(AiSupportGoalJourney::STATE_ACTIVE, $active->fresh()->state);
        $this->assertDatabaseCount('ai_support_goal_journeys', 1);
    }

    public function test_profile_and_payment_steps_are_detours_that_keep_the_care_request_goal(): void
    {
        [, $family] = $this->eligibleFamily();
        $ticket = $this->ticket($family, 'I need one visit next Tuesday.');
        app(AiSupportRuntimeService::class)->respond($family, $ticket, $ticket->description);
        $journey = AiSupportGoalJourney::query()->sole();
        app(FamilyGoalJourneyService::class)->chooseCarePath(
            $family,
            $ticket,
            AiSupportMessageAction::query()->sole()->id,
            'one_time',
        );
        $paymentMethod = app(FamilyIntentCatalog::class)->find('FAM-PAY-004');

        $blocked = app(FamilyGoalJourneyService::class)->coordinateIntent($family, $ticket, $paymentMethod, 'I need to add a payment method.');

        $this->assertFalse($blocked);
        $this->assertSame('care_request', $journey->fresh()->journey_type);
        $this->assertSame('payment_method', data_get($journey->fresh()->context, 'detour_type'));
        $this->assertDatabaseCount('ai_support_goal_journeys', 1);

        app(FamilyGoalJourneyService::class)->syncAfterVerifiedStep($family, $ticket, 'payment_method_verified');
        $this->assertSame('care_request', $journey->fresh()->journey_type);
        $this->assertNull(data_get($journey->fresh()->context, 'detour_type'));
        $this->assertSame('collect_request_details', $journey->fresh()->step_key);
    }

    public function test_human_transfer_preserves_the_goal_and_deliberate_return_resumes_it(): void
    {
        [$admin, $family] = $this->eligibleFamily();
        $ticket = $this->ticket($family, 'I need one visit next Tuesday.');
        app(AiSupportRuntimeService::class)->respond($family, $ticket, $ticket->description);
        $journey = AiSupportGoalJourney::query()->sole();

        app(AiSupportHandoffService::class)->transfer($family, $ticket, 'user_requested');
        $this->assertSame(AiSupportGoalJourney::STATE_TRANSFERRED, $journey->fresh()->state);
        $this->assertStringContainsString(
            'Active goal: care request',
            $ticket->messages()
                ->where('kind', SupportTicketMessage::KIND_INTERNAL_NOTE)
                ->latest()
                ->firstOrFail()
                ->body,
        );

        app(AiSupportHandoffService::class)->returnToAutomation($admin, $ticket->fresh(), 'Resume the exact pilot journey');
        $this->assertSame(AiSupportGoalJourney::STATE_ACTIVE, $journey->fresh()->state);
        $this->assertSame('care_request', app(FamilyGoalJourneyService::class)->activeFor($family, $ticket->fresh())?->journey_type);
    }

    public function test_cross_account_actor_cannot_restore_or_choose_another_family_journey(): void
    {
        [, $family] = $this->eligibleFamily();
        $other = User::factory()->create(['role' => 'family']);
        app(FamilyAccountContext::class)->account($other);
        $ticket = $this->ticket($family, 'I need one visit next Tuesday.');
        app(AiSupportRuntimeService::class)->respond($family, $ticket, $ticket->description);
        $action = AiSupportMessageAction::query()->sole();

        $this->expectException(AuthorizationException::class);
        app(FamilyGoalJourneyService::class)->chooseCarePath($other, $ticket, $action->id, 'one_time');
    }

    public function test_cancelling_a_goal_invalidates_open_actions_without_changing_domain_records(): void
    {
        [, $family] = $this->eligibleFamily();
        $ticket = $this->ticket($family, 'I need one visit next Tuesday.');
        app(AiSupportRuntimeService::class)->respond($family, $ticket, $ticket->description);

        app(FamilyGoalJourneyService::class)->cancelActive($family, $ticket);

        $this->assertSame(AiSupportGoalJourney::STATE_CANCELLED, AiSupportGoalJourney::query()->sole()->state);
        $this->assertNotNull(AiSupportMessageAction::query()->sole()->invalidated_at);
        $this->assertDatabaseCount('care_requests', 0);
        $this->assertDatabaseCount('ai_support_request_drafts', 0);
    }

    public function test_care_choice_is_single_use_and_cannot_replay_into_a_second_draft(): void
    {
        [, $family] = $this->eligibleFamily();
        $ticket = $this->ticket($family, 'I need one visit next Tuesday.');
        app(AiSupportRuntimeService::class)->respond($family, $ticket, $ticket->description);
        $action = AiSupportMessageAction::query()->sole();
        $journeys = app(FamilyGoalJourneyService::class);

        $journeys->chooseCarePath($family, $ticket, $action->id, 'one_time');
        try {
            $journeys->chooseCarePath($family, $ticket, $action->id, 'one_time');
            $this->fail('A consumed care choice must not be replayable.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseCount('ai_support_request_drafts', 1);
        $this->assertNotNull($action->fresh()->consumed_at);
    }

    public function test_expired_goal_content_and_open_choices_are_removed_by_retention(): void
    {
        [, $family] = $this->eligibleFamily();
        $ticket = $this->ticket($family, 'My mother needs some help.');
        app(AiSupportRuntimeService::class)->respond($family, $ticket, $ticket->description);
        $journey = AiSupportGoalJourney::query()->sole();
        $action = AiSupportMessageAction::query()->sole();

        $this->travel(8)->days();
        $this->artisan('ai-support:apply-retention', ['--execute' => true])->assertSuccessful();

        $this->assertSame(AiSupportGoalJourney::STATE_EXPIRED, $journey->fresh()->state);
        $this->assertNull($journey->fresh()->context);
        $this->assertNull($action->fresh()->payload);
        $this->assertSame('journey_expired', $action->fresh()->invalidation_reason);
    }

    public function test_catalog_contains_exactly_the_ten_approved_family_journeys(): void
    {
        $catalog = app(FamilyGoalJourneyCatalog::class);
        $this->assertSame(FamilyGoalJourneyCatalog::VERSION, 'family-goal-journeys-v1');
        $this->assertSame([
            'care_request', 'care_profile', 'payment_method', 'payment_failure', 'applicant_hiring',
            'visit_hours', 'regular_care', 'history_rebooking', 'messages_notifications', 'human_help',
        ], array_keys($catalog->all()));
    }

    private function eligibleFamily(): array
    {
        config([
            'ai_support.runtime_available' => true,
            'ai_support.provider_enabled' => true,
            'services.openai.api_key' => 'test-key',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $family = User::factory()->create(['role' => 'family']);
        app(FamilyAccountContext::class)->account($family);
        $controls = app(AiSupportControlService::class);
        foreach ([
            'master_enabled', 'user_visible_enabled', 'role.family', 'capability.support_answers_v1',
            'capability.semantic_navigation_v1', 'capability.family_context_v1', 'capability.care_intake_v1',
            'capability.care_request_draft_v1', 'capability.care_request_recap_v1',
        ] as $key) {
            $controls->set($admin, $key, true, 'Batch 10 exact-user journey test');
        }
        $controls->set($admin, 'human_only', false, 'Batch 10 exact-user journey test');
        app(AiSupportPilotGrantService::class)->grant(
            $admin,
            $family,
            'family_support_v1',
            CarbonImmutable::now(),
            CarbonImmutable::now()->addDays(14),
            'Batch 10 exact-user journey test',
            (string) Str::uuid(),
        );

        return [$admin, $family];
    }

    private function ticket(User $family, string $description): SupportTicket
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
            'subject' => 'Batch 10 journey test',
            'description' => $description,
            'initial_client_message_id' => (string) Str::uuid(),
        ]);
    }
}
