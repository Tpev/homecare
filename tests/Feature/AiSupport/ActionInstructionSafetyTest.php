<?php

namespace Tests\Feature\AiSupport;

use App\Models\AiSupportMessageAction;
use App\Models\CareBooking;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\AiSupport\AiSupportControlService;
use App\Services\AiSupport\AiSupportPilotGrantService;
use App\Services\AiSupport\AiSupportRequestDraftService;
use App\Services\AiSupport\AiSupportRuntimeService;
use App\Services\AiSupport\FamilyActionIntentGuard;
use App\Services\AiSupport\FamilyCareOperationsActionService;
use App\Services\AiSupport\FamilyGoalJourneyService;
use App\Services\AiSupport\FamilyIntentResolver;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ActionInstructionSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_restrictions_override_action_keywords_and_keep_the_requested_read(): void
    {
        foreach ([
            "Don't hire anyone. Just show me applicants." => 'FAM-MATCH-013',
            'Don’t hire anyone; only show the applications.' => 'FAM-MATCH-013',
            'Do not approve hours. Just show submitted hours.' => 'FAM-VISIT-018',
            'Never cancel my recurring care; show my next visit.' => 'FAM-VISIT-001',
            'Do not do anything. Just show me applicants.' => 'FAM-MATCH-013',
            'I am not ready to hire. Show me applicants.' => 'FAM-MATCH-013',
            'Just show me the applicants without hiring anyone.' => 'FAM-MATCH-013',
        ] as $message => $expected) {
            $result = app(FamilyIntentResolver::class)->resolve($message);
            $this->assertSame($expected, $result['intent_id'], $message);
            $this->assertNotSame('catalog_lexical', $result['source']);
        }

        foreach (["Don't hire anyone.", 'No hiring yet.', 'Do not send the caregiver a message.',
            "Don't post my request yet.", "Don't confirm this yet.", 'Do not proceed.', 'Wait.', 'No, not yet.', 'Please wait.', 'Wait, not yet.'] as $message) {
            $this->assertNull(app(FamilyIntentResolver::class)->resolve($message)['intent_id'], $message);
        }
    }

    public function test_affirmative_commands_and_narrow_scope_corrections_still_resolve(): void
    {
        foreach ([
            'Hire Taylor for this request.' => 'FAM-MATCH-020',
            'Approve the submitted hours.' => 'FAM-VISIT-020',
            "Cancel Monday's visit, not the whole plan." => 'FAM-VISIT-007',
            'Not this caregiver.' => 'FAM-MATCH-016',
            'The caregiver did not show.' => 'FAM-VISIT-014',
        ] as $message => $expected) {
            $this->assertNull(app(FamilyActionIntentGuard::class)->inspect($message), $message);
            $this->assertSame($expected, app(FamilyIntentResolver::class)->resolve($message)['intent_id'], $message);
        }
    }

    public function test_full_runtime_shows_applicants_without_a_hire_card_even_with_a_single_applicant(): void
    {
        $family = $this->family();
        $request = $this->careRequest($family);
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Taylor Safety']);
        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id, 'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_APPLIED, 'proposed_rate' => 30,
        ]);
        foreach ([
            "Don't hire anyone. Just show me applicants.",
            'Don’t hire anyone; just show who applied.',
            'Just show the applicants without hiring anyone.',
        ] as $message) {
            $ticket = $this->ticket($family);
            app(FamilyGoalJourneyService::class)->coordinateIntent($family, $ticket,
                app(\App\Services\AiSupport\FamilyIntentCatalog::class)->find('FAM-MATCH-020'), 'Hire Taylor Safety.');
            $this->assertSame('applicant_hiring', app(FamilyGoalJourneyService::class)->activeFor($family, $ticket)?->journey_type);
            app(AiSupportRuntimeService::class)->respond($family, $ticket, $message);

            $this->assertSame(SupportTicket::RESPONDER_MODE_AUTOMATED, $ticket->fresh()->responder_mode);
            $this->assertNull(app(FamilyGoalJourneyService::class)->activeFor($family, $ticket));
            $this->assertFalse($ticket->aiMessageActions()->where('action_type', AiSupportMessageAction::TYPE_DOMAIN_RECAP)->exists());
            $this->assertStringContainsString('Taylor Safety', $ticket->messages()->pluck('body')->implode(' '));
        }
        $this->assertSame(CareRequest::STATUS_OPEN, $request->fresh()->status);
        $this->assertSame(CareRequestApplication::STATUS_APPLIED, $application->fresh()->status);
        $this->assertDatabaseCount('care_bookings', 0);
        Http::assertNothingSent();
    }

    public function test_restriction_precedes_unfinished_draft_and_revokes_old_confirmations(): void
    {
        $family = $this->family();
        $ticket = $this->ticket($family);
        $draft = app(AiSupportRequestDraftService::class)->start($family, $ticket, CareRequest::TYPE_ONE_TIME);
        $payload = $draft->payload;
        $version = $draft->version;
        $message = SupportTicketMessage::query()->create([
            'support_ticket_id' => $ticket->id, 'kind' => SupportTicketMessage::KIND_PUBLIC,
            'responder_type' => SupportTicketMessage::RESPONDER_AUTOMATED, 'body' => 'Old confirmation',
            'client_message_id' => (string) Str::uuid(),
        ]);
        $action = AiSupportMessageAction::query()->create([
            'id' => (string) Str::uuid(), 'support_ticket_message_id' => $message->id,
            'support_ticket_id' => $ticket->id, 'actor_user_id' => $family->id,
            'action_type' => AiSupportMessageAction::TYPE_RECAP, 'payload' => ['can_confirm' => true],
            'expires_at' => now()->addMinutes(30),
        ]);

        app(AiSupportRuntimeService::class)->respond($family, $ticket, 'Do not publish or change anything. Just show me applicants.');

        $this->assertSame($payload, $draft->fresh()->payload);
        $this->assertSame($version, $draft->fresh()->version);
        $this->assertSame('user_requested_no_action', $action->fresh()->invalidation_reason);
        $this->assertFalse($action->fresh()->isActive());
        $this->assertDatabaseCount('care_requests', 0);
        Http::assertNothingSent();
    }

    public function test_how_to_questions_do_not_prepare_financial_actions(): void
    {
        $family = $this->family();
        $this->careRequest($family);
        foreach (['How do I hire a caregiver?', 'How can I approve the submitted hours?', 'How do I publish a care request?'] as $question) {
            $ticket = $this->ticket($family);
            app(AiSupportRuntimeService::class)->respond($family, $ticket, $question);
            $this->assertSame(SupportTicket::RESPONDER_MODE_AUTOMATED, $ticket->fresh()->responder_mode);
            $this->assertFalse($ticket->aiMessageActions()->whereIn('action_type', [
                AiSupportMessageAction::TYPE_DOMAIN_RECAP, AiSupportMessageAction::TYPE_RECAP,
                AiSupportMessageAction::TYPE_PREPARATION,
            ])->exists(), $question);
            $this->assertTrue($ticket->messages()->exists(), $question);
        }
        $this->assertSame(0, CareBooking::query()->count());
        Http::assertNothingSent();
    }

    public function test_policy_question_does_not_parse_its_weekday_into_an_active_recurring_draft(): void
    {
        $family = $this->family();
        $ticket = $this->ticket($family);
        $runtime = app(AiSupportRuntimeService::class);
        $runtime->respond($family, $ticket, 'I need care every week.');
        $choice = $ticket->aiMessageActions()->where('action_type', AiSupportMessageAction::TYPE_PATH_CHOICES)->latest('created_at')->firstOrFail();
        app(FamilyGoalJourneyService::class)->chooseCarePath($family, $ticket, $choice->id, CareRequest::TYPE_RECURRING);
        $draft = \App\Models\AiSupportRequestDraft::query()->where('support_ticket_id', $ticket->id)->sole();
        $payload = $draft->payload;
        $version = $draft->version;

        $runtime->respond($family, $ticket, 'What happens if I cancel Monday’s visit?');

        $this->assertSame($payload, $draft->fresh()->payload);
        $this->assertSame($version, $draft->fresh()->version);
        $this->assertStringContainsString('cancel', mb_strtolower($ticket->publicMessages()->reorder()->latest('id')->firstOrFail()->body));
        $this->assertNotNull(app(FamilyGoalJourneyService::class)->activeFor($family, $ticket));
        $this->assertDatabaseCount('care_requests', 0);
        Http::assertNothingSent();
    }

    public function test_model_completion_guard_distinguishes_claims_from_conditions_and_negative_statements(): void
    {
        $method = new \ReflectionMethod(AiSupportRuntimeService::class, 'unsafeSuccessOrHeldClaim');
        $runtime = app(AiSupportRuntimeService::class);
        foreach (['Your card has been charged.', 'Good news, your card has been charged.', 'Your request was published.', 'We have approved the hours.', 'Your caregiver has now been hired.'] as $message) {
            $this->assertTrue($method->invoke($runtime, $message), $message);
        }
        foreach (['If your card has been charged, open the receipt.', 'Your card has not been charged.', 'Has my card been charged?', 'Your card has been charged?', 'You can approve the hours after checking them.'] as $message) {
            $this->assertFalse($method->invoke($runtime, $message), $message);
        }
    }

    private function family(): User
    {
        Http::preventStrayRequests();
        config(['ai_support.runtime_available' => true, 'ai_support.provider_enabled' => true, 'services.openai.api_key' => 'test-key']);
        $admin = User::factory()->create(['role' => 'admin']);
        $family = User::factory()->create(['role' => 'family']);
        app(FamilyAccountContext::class)->account($family);
        $controls = app(AiSupportControlService::class);
        foreach (['master_enabled', 'user_visible_enabled', 'role.family', 'capability.support_answers_v1',
            'capability.semantic_navigation_v1', 'capability.family_context_v1', 'capability.care_intake_v1',
            'capability.care_request_draft_v1', 'capability.care_request_recap_v1',
            'capability.'.FamilyCareOperationsActionService::CAPABILITY] as $key) {
            $controls->set($admin, $key, true, 'Isolated action instruction regression');
        }
        $controls->set($admin, 'human_only', false, 'Isolated action instruction regression');
        app(AiSupportPilotGrantService::class)->grant($admin, $family, 'family_support_v1',
            CarbonImmutable::now(), CarbonImmutable::now()->addDays(14), 'Isolated regression', (string) Str::uuid());

        return $family;
    }

    private function ticket(User $family): SupportTicket
    {
        return SupportTicket::query()->create([
            'family_account_id' => app(FamilyAccountContext::class)->account($family)->id,
            'family_visibility' => 'opener_only', 'opener_user_id' => $family->id,
            'source' => SupportTicket::SOURCE_CHAT_WIDGET, 'responder_mode' => SupportTicket::RESPONDER_MODE_AUTOMATED,
            'category' => 'general', 'status' => SupportTicket::STATUS_OPEN, 'priority' => 'normal',
            'subject' => 'Instruction safety', 'description' => 'Local regression',
            'initial_client_message_id' => (string) Str::uuid(),
        ]);
    }

    private function careRequest(User $family): CareRequest
    {
        return CareRequest::query()->create([
            'family_account_id' => app(FamilyAccountContext::class)->account($family)->id,
            'family_user_id' => $family->id, 'created_by_user_id' => $family->id,
            'is_system_generated' => false, 'title' => 'Morning help', 'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_ONE_TIME, 'requested_start_at' => now()->addDays(2)->setTime(9, 0),
            'requested_end_at' => now()->addDays(2)->setTime(11, 0), 'preferred_response_hours' => 12,
            'address_line1' => '123 Main Street', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
        ]);
    }
}
