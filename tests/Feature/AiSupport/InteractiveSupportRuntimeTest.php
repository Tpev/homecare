<?php

namespace Tests\Feature\AiSupport;

use App\Models\AiSupportInteractionEvent;
use App\Models\AiSupportMessageAction;
use App\Models\AiSupportRequestDraft;
use App\Models\CareRequest;
use App\Models\CareTask;
use App\Models\FamilyHouseholdProfile;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\AiSupport\AiSupportControlService;
use App\Services\AiSupport\AiSupportHandoffService;
use App\Services\AiSupport\AiSupportPilotGrantService;
use App\Services\AiSupport\AiSupportRecapService;
use App\Services\AiSupport\AiSupportRequestDraftService;
use App\Services\AiSupport\AiSupportRuntimeService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\Support\SupportChatService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InteractiveSupportRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_granted_and_provider_disabled_users_remain_on_human_support_without_model_calls(): void
    {
        Http::fake();
        $family = User::factory()->create(['role' => 'family']);
        $ticket = app(SupportChatService::class)->startConversation(
            $family,
            'Where are my care requests?',
            (string) Str::uuid(),
            'dashboard',
            '/dashboard',
        );

        $this->assertSame(SupportTicket::RESPONDER_MODE_HUMAN_ONLY, $ticket->responder_mode);
        app(AiSupportRuntimeService::class)->respond($family, $ticket, 'Where are my care requests?');
        Http::assertNothingSent();
        $this->assertDatabaseCount('ai_support_interaction_events', 0);
    }

    public function test_daily_model_turn_limit_transfers_without_another_provider_call(): void
    {
        [, $family] = $this->eligibleFamily();
        config(['ai_support.pilot_daily_model_turn_limit' => 1]);
        AiSupportInteractionEvent::query()->create([
            'id' => (string) Str::uuid(),
            'actor_user_id' => $family->id,
            'event_type' => 'model_turn_completed',
            'event_contract_version' => 'support-event-v1',
            'occurred_at' => now(),
            'created_at' => now(),
        ]);
        Http::fake();
        $ticket = $this->automatedTicket($family);

        app(AiSupportRuntimeService::class)->respond($family, $ticket, 'Where are my care requests?');

        Http::assertNothingSent();
        $this->assertTrue($ticket->fresh()->isHumanOnly());
        $this->assertSame('daily_turn_limit', $ticket->fresh()->handoff_reason_code);
    }

    public function test_exact_family_pilot_gets_private_automated_conversation_and_strict_path_choice(): void
    {
        [$admin, $family] = $this->eligibleFamily();
        $other = User::factory()->create(['role' => 'family']);
        $account = app(FamilyAccountContext::class)->account($family);
        $account->memberships()->create([
            'user_id' => $other->id,
            'access_level' => 'member',
            'status' => 'active',
            'joined_at' => now(),
        ]);
        Http::fake(['*/responses' => Http::response($this->providerEnvelope([
            'operation' => 'care_path',
            'message' => 'This sounds like one visit, so one-time care is the best fit.',
            'navigation_target_id' => null,
            'care_path' => 'one_time',
            'clarifying_question' => null,
            'confidence_band' => 'clear',
            'kb_stable_ids' => [],
            'draft_patch' => $this->emptyPatch(),
        ]))]);

        $ticket = app(SupportChatService::class)->startConversation(
            $family,
            'I need someone to help my father for one visit.',
            (string) Str::uuid(),
            'dashboard',
            '/dashboard',
        );
        $this->assertSame(SupportTicket::RESPONDER_MODE_AUTOMATED, $ticket->responder_mode);
        $this->assertSame('opener_only', $ticket->family_visibility);
        $this->assertSame(0, Artisan::call('homecare:verify-family-accounts'));
        $this->assertNull(SupportTicket::query()->visibleTo($other)->find($ticket->id));

        app(AiSupportRuntimeService::class)->respond($family, $ticket, $ticket->description);

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $payload['store'] === false
                && $payload['parallel_tool_calls'] === false
                && data_get($payload, 'text.format.strict') === true
                && data_get($payload, 'text.format.schema.additionalProperties') === false;
        });
        $action = AiSupportMessageAction::query()->sole();
        $this->assertSame(AiSupportMessageAction::TYPE_PATH_CHOICES, $action->action_type);
        $this->assertDatabaseHas('ai_support_interaction_events', ['event_type' => 'model_turn_completed']);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $admin->id]);
    }

    public function test_continuous_coverage_transfers_without_model_or_queue_claims(): void
    {
        [, $family] = $this->eligibleFamily();
        Http::fake();
        $ticket = $this->automatedTicket($family);

        app(AiSupportRuntimeService::class)->respond($family, $ticket, 'We need round-the-clock 24/7 care at home.');

        Http::assertNothingSent();
        $ticket->refresh();
        $this->assertTrue($ticket->isHumanOnly());
        $this->assertSame('continuous_coverage', $ticket->handoff_reason_code);
        $message = $ticket->publicMessages()->latest()->firstOrFail();
        $this->assertSame("I've transferred this conversation to LoLo Support. They'll reply here as soon as they can.", $message->body);
        $this->assertStringNotContainsString('queue', strtolower($message->body));
        $this->assertStringNotContainsString('minutes', strtolower($message->body));
    }

    public function test_emergency_instruction_precedes_transfer_and_never_calls_model(): void
    {
        [, $family] = $this->eligibleFamily();
        Http::fake();
        $ticket = $this->automatedTicket($family);

        app(AiSupportRuntimeService::class)->respond($family, $ticket, 'My father is not breathing and is unconscious.');

        Http::assertNothingSent();
        $messages = $ticket->publicMessages()->oldest()->pluck('body')->all();
        $this->assertCount(2, $messages);
        $this->assertStringContainsString('call 911 now', $messages[0]);
        $this->assertStringContainsString('transferred this conversation', $messages[1]);
        $this->assertTrue($ticket->fresh()->isHumanOnly());
    }

    public function test_one_time_draft_recap_confirmation_and_idempotent_live_publication_match(): void
    {
        [, $family] = $this->eligibleFamily(true);
        $account = app(FamilyAccountContext::class)->account($family);
        FamilyHouseholdProfile::query()->create([
            'family_account_id' => $account->id,
            'family_user_id' => $family->id,
            'address_line1' => '10 Oak Street',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
            'preferred_response_hours' => 12,
        ]);
        $mealTask = CareTask::query()->create(['name' => 'Meal preparation']);
        $companionshipTask = CareTask::query()->create(['name' => 'Companionship']);
        $ticket = $this->automatedTicket($family);
        $drafts = app(AiSupportRequestDraftService::class);

        $draft = $drafts->start($family, $ticket, CareRequest::TYPE_ONE_TIME);
        $start = now('America/New_York')->addDay()->startOfHour()->addHours(3);
        $draft = $drafts->applyPatch($family, $ticket, [
            'patch_fields' => [
                'recipient_is_requester', 'recipient_full_name', 'recipient_relationship',
                'task_ids', 'task_notes', 'requested_start_date', 'requested_start_time', 'duration_minutes',
            ],
            'recipient_is_requester' => false,
            'recipient_full_name' => 'Arthur Example',
            'recipient_relationship' => 'Father',
            'task_ids' => [$mealTask->id, $companionshipTask->id],
            'task_notes' => [
                ['task_id' => $mealTask->id, 'note' => 'Prepare a light lunch.'],
                ['task_id' => $companionshipTask->id, 'note' => 'Bring a deck of cards.'],
            ],
            'requested_start_date' => $start->format('Y-m-d'),
            'requested_start_time' => $start->format('H:i'),
            'duration_minutes' => 180,
        ], $draft->version);

        $this->assertSame('ready_for_recap', $draft->state);
        $action = app(AiSupportRecapService::class)->issue($family, $ticket, $draft);
        $this->assertTrue($action->isActive());
        $this->assertStringEndsWith('Eastern Time', $action->payload['recap']['schedule']);

        $request = app(AiSupportRecapService::class)->confirm($family, $ticket, $action->id);
        $again = app(AiSupportRecapService::class)->confirm($family, $ticket, $action->id);

        $this->assertSame($request->id, $again->id);
        $this->assertDatabaseCount('care_requests', 1);
        $this->assertDatabaseCount('ai_support_confirmed_action_evidence', 1);
        $this->assertDatabaseCount('care_bookings', 0);
        $this->assertSame(CareRequest::STATUS_OPEN, $request->status);
        $this->assertSame('ai_support', $request->origin);
        $this->assertSame($ticket->id, $request->ai_support_ticket_id);
        $this->assertSame('Arthur Example', $request->recipient->full_name);
        $this->assertEqualsCanonicalizing([$mealTask->id, $companionshipTask->id], $request->tasks->pluck('id')->all());
        $this->assertSame(
            "- Companionship: Bring a deck of cards.\n- Meal preparation: Prepare a light lunch.",
            $request->scope_of_work,
        );
        $this->assertEquals(180, $request->requested_start_at->diffInMinutes($request->requested_end_at));
        $this->assertDatabaseHas('funnel_events', [
            'event' => 'care_request_published',
            'entity_id' => $request->id,
        ]);
        $this->assertDatabaseCount('funnel_events', 1);

        $receipt = AiSupportMessageAction::query()
            ->where('support_ticket_id', $ticket->id)
            ->where('action_type', AiSupportMessageAction::TYPE_RECEIPT)
            ->sole();
        $receiptPayload = $receipt->payload;

        app(AiSupportHandoffService::class)->transfer($family, $ticket, 'user_requested');
        $this->assertNull($receipt->fresh()->invalidated_at);
        $this->assertSame($receiptPayload, $receipt->fresh()->payload);

        app(AiSupportControlService::class)->systemStop(
            'capability.support_answers_v1',
            'synthetic_receipt_preservation_stop',
            'Verify a completed receipt survives automatic rollback.',
        );
        $this->assertNull($receipt->fresh()->invalidated_at);
        $this->assertSame($receiptPayload, $receipt->fresh()->payload);
        $this->assertDatabaseHas('care_requests', ['id' => $request->id]);
        $this->assertDatabaseHas('ai_support_confirmed_action_evidence', ['id' => $request->ai_support_action_evidence_id]);
    }

    public function test_stale_tab_expired_confirmation_and_cross_account_draft_access_fail_closed(): void
    {
        [, $family] = $this->eligibleFamily(true);
        $other = User::factory()->create(['role' => 'family']);
        $task = CareTask::query()->create(['name' => 'Meal preparation']);
        $ticket = $this->automatedTicket($family);
        $drafts = app(AiSupportRequestDraftService::class);
        $draft = $drafts->start($family, $ticket, CareRequest::TYPE_ONE_TIME);

        try {
            $drafts->applyPatch($family, $ticket, [
                'patch_fields' => ['task_ids'],
                'task_ids' => [$task->id],
            ], $draft->version - 1);
            $this->fail('A stale draft version must not write.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('draft', $exception->errors());
        }

        try {
            $drafts->applyPatch($other, $ticket, ['patch_fields' => ['task_ids'], 'task_ids' => [$task->id]], $draft->version);
            $this->fail('Another Family account must not access the draft.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }
        $this->assertDatabaseCount('care_requests', 0);
    }

    public function test_human_transfer_blocks_draft_creation_from_a_stale_automated_ticket(): void
    {
        [, $family] = $this->eligibleFamily();
        $ticket = $this->automatedTicket($family);
        $staleAutomatedTicket = $ticket->fresh();

        app(AiSupportHandoffService::class)->transfer($family, $ticket, 'user_requested');

        try {
            app(AiSupportRequestDraftService::class)->start(
                $family,
                $staleAutomatedTicket,
                CareRequest::TYPE_ONE_TIME,
            );
            $this->fail('A stale automated ticket must not create a draft after human transfer.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseCount('ai_support_request_drafts', 0);
        $this->assertDatabaseCount('ai_support_message_actions', 0);
        $this->assertTrue($ticket->fresh()->isHumanOnly());
    }

    public function test_recurring_request_supports_different_daily_schedules_and_aligns_start_date(): void
    {
        [, $family] = $this->eligibleFamily(true, true);
        $account = app(FamilyAccountContext::class)->account($family);
        FamilyHouseholdProfile::query()->create([
            'family_account_id' => $account->id,
            'family_user_id' => $family->id,
            'address_line1' => '22 Pine Road',
            'city' => 'Durham',
            'state' => 'NC',
            'zip' => '27701',
            'preferred_response_hours' => 8,
        ]);
        $task = CareTask::query()->create(['name' => 'Meal preparation']);
        $ticket = $this->automatedTicket($family);
        $drafts = app(AiSupportRequestDraftService::class);
        $draft = $drafts->start($family, $ticket, CareRequest::TYPE_RECURRING);
        $sunday = now('America/New_York')->next('Sunday')->toDateString();

        $draft = $drafts->applyPatch($family, $ticket, [
            'patch_fields' => [
                'recipient_is_requester', 'recipient_full_name', 'recipient_relationship', 'task_ids',
                'recurring_days', 'recurring_schedule', 'recurring_starts_on', 'recurring_ends_on',
            ],
            'recipient_is_requester' => true,
            'recipient_full_name' => $family->name,
            'recipient_relationship' => 'Self',
            'task_ids' => [$task->id],
            'recurring_days' => [1, 3],
            'recurring_schedule' => [
                ['day' => 1, 'start_time' => '09:00', 'duration_minutes' => 120],
                ['day' => 3, 'start_time' => '14:30', 'duration_minutes' => 180],
            ],
            'recurring_starts_on' => $sunday,
            'recurring_ends_on' => null,
        ], $draft->version);

        $this->assertSame('ready_for_recap', $draft->state);
        $this->assertSame(now('America/New_York')->next('Monday')->toDateString(), $draft->payload['recurring_starts_on']);
        $action = app(AiSupportRecapService::class)->issue($family, $ticket, $draft);
        $this->assertNotNull($action->payload['recap']['schedule_adjustment']);
        $request = app(AiSupportRecapService::class)->confirm($family, $ticket, $action->id);

        $this->assertSame(CareRequest::TYPE_RECURRING, $request->request_type);
        $this->assertSame([1, 3], $request->recurring_days);
        $this->assertSame('11:00', $request->recurring_schedule[0]['end_time']);
        $this->assertSame('17:30', $request->recurring_schedule[1]['end_time']);
        $this->assertNull($request->recurring_ends_on);
    }

    public function test_invalid_calendar_dates_duplicate_regular_slots_and_bad_zip_never_become_ready(): void
    {
        [, $family] = $this->eligibleFamily();
        $task = CareTask::query()->create(['name' => 'Companionship']);
        $ticket = $this->automatedTicket($family);
        $drafts = app(AiSupportRequestDraftService::class);
        $draft = $drafts->start($family, $ticket, CareRequest::TYPE_RECURRING);
        $invalidFutureDate = now('America/New_York')->addYear()->format('Y').'-02-30';

        $draft = $drafts->applyPatch($family, $ticket, [
            'patch_fields' => [
                'recipient_is_requester', 'recipient_full_name', 'recipient_relationship',
                'task_ids', 'recurring_days', 'recurring_schedule', 'recurring_starts_on',
                'address_line1', 'city', 'state', 'zip',
            ],
            'recipient_is_requester' => false,
            'recipient_full_name' => 'Arthur Example',
            'recipient_relationship' => 'Father',
            'task_ids' => [$task->id],
            'recurring_days' => [1],
            'recurring_schedule' => [
                ['day' => 1, 'start_time' => '09:00', 'duration_minutes' => 120],
                ['day' => 1, 'start_time' => '13:00', 'duration_minutes' => 120],
            ],
            'recurring_starts_on' => $invalidFutureDate,
            'address_line1' => '10 Oak Street',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27ABC',
        ], $draft->version);

        $codes = collect($drafts->validatePayload($family, $draft)['errors'])->pluck('code');
        $this->assertSame(AiSupportRequestDraft::STATE_COLLECTING, $draft->state);
        $this->assertTrue($codes->contains('incomplete_recurring_schedule'));
        $this->assertTrue($codes->contains('recurring_start_invalid'));
        $this->assertTrue($codes->contains('invalid_zip'));
        $this->assertDatabaseCount('care_requests', 0);
    }

    public function test_human_takeover_invalidates_recap_and_wins_before_publication(): void
    {
        [, $family] = $this->eligibleFamily(true);
        $account = app(FamilyAccountContext::class)->account($family);
        FamilyHouseholdProfile::query()->create([
            'family_account_id' => $account->id, 'family_user_id' => $family->id,
            'address_line1' => '1 Main Street', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
        ]);
        $task = CareTask::query()->create(['name' => 'Companionship']);
        $ticket = $this->automatedTicket($family);
        $drafts = app(AiSupportRequestDraftService::class);
        $draft = $drafts->start($family, $ticket, CareRequest::TYPE_ONE_TIME);
        $start = now('America/New_York')->addDays(2)->setTime(10, 0);
        $draft = $drafts->applyPatch($family, $ticket, [
            'patch_fields' => ['recipient_is_requester', 'recipient_full_name', 'task_ids', 'requested_start_date', 'requested_start_time', 'duration_minutes'],
            'recipient_is_requester' => true, 'recipient_full_name' => $family->name,
            'task_ids' => [$task->id], 'requested_start_date' => $start->toDateString(),
            'requested_start_time' => '10:00', 'duration_minutes' => 120,
        ], $draft->version);
        $action = app(AiSupportRecapService::class)->issue($family, $ticket, $draft);

        app(AiSupportHandoffService::class)->transfer($family, $ticket, 'user_requested');
        $this->assertNotNull($action->fresh()->invalidated_at);
        $this->assertNull($action->fresh()->payload);

        $this->expectException(ValidationException::class);
        try {
            app(AiSupportRecapService::class)->confirm($family, $ticket, $action->id);
        } finally {
            $this->assertDatabaseCount('care_requests', 0);
        }
    }

    public function test_fabricated_success_is_suppressed_transferred_and_automatically_stops_answers(): void
    {
        [, $family] = $this->eligibleFamily();
        Http::fake(['*/responses' => Http::response($this->providerEnvelope([
            'operation' => 'answer',
            'message' => 'I created and published your care request.',
            'navigation_target_id' => null,
            'care_path' => null,
            'clarifying_question' => null,
            'confidence_band' => 'clear',
            'kb_stable_ids' => [],
            'draft_patch' => $this->emptyPatch(),
        ]))]);
        $ticket = $this->automatedTicket($family);

        app(AiSupportRuntimeService::class)->respond($family, $ticket, 'Did you finish it?');

        $this->assertTrue($ticket->fresh()->isHumanOnly());
        $this->assertDatabaseCount('care_requests', 0);
        $this->assertFalse(app(AiSupportControlService::class)->enabled('capability.support_answers_v1'));
        $this->assertDatabaseHas('ai_support_admin_audit_events', [
            'action' => 'automatic_capability_stop',
            'reason_code' => 'unsafe_model_claim',
        ]);
        $this->assertFalse($ticket->publicMessages()->where('body', 'like', '%created and published%')->exists());
    }

    public function test_caregiver_runtime_allows_only_role_approved_navigation_and_never_creates_family_draft(): void
    {
        [, $caregiver] = $this->eligibleCaregiver();
        Http::fake(['*/responses' => Http::response($this->providerEnvelope([
            'operation' => 'navigate',
            'message' => 'I can open your Work Inbox.',
            'navigation_target_id' => 'caregiver.work_inbox',
            'care_path' => null,
            'clarifying_question' => null,
            'confidence_band' => 'clear',
            'kb_stable_ids' => [],
            'draft_patch' => $this->emptyPatch(),
        ]))]);
        $ticket = SupportTicket::query()->create([
            'opener_user_id' => $caregiver->id,
            'source' => SupportTicket::SOURCE_CHAT_WIDGET,
            'responder_mode' => SupportTicket::RESPONDER_MODE_AUTOMATED,
            'category' => 'general', 'status' => SupportTicket::STATUS_OPEN, 'priority' => 'normal',
            'subject' => 'Caregiver navigation', 'description' => 'Open my Work Inbox.',
            'initial_client_message_id' => (string) Str::uuid(),
        ]);

        app(AiSupportRuntimeService::class)->respond($caregiver, $ticket, 'Open my Work Inbox.');

        $action = AiSupportMessageAction::query()->sole();
        $this->assertSame(AiSupportMessageAction::TYPE_NAVIGATE, $action->action_type);
        $this->assertSame('caregiver.work_inbox', $action->payload['target_id']);
        $this->assertDatabaseCount('ai_support_request_drafts', 0);
        $this->assertDatabaseCount('care_requests', 0);
    }

    public function test_expired_confirmation_cannot_write_and_one_step_renewal_preserves_the_draft(): void
    {
        $now = CarbonImmutable::parse('2026-08-14 10:00:00', 'America/New_York');
        CarbonImmutable::setTestNow($now);
        try {
            [, $family] = $this->eligibleFamily(true);
            $account = app(FamilyAccountContext::class)->account($family);
            FamilyHouseholdProfile::query()->create([
                'family_account_id' => $account->id, 'family_user_id' => $family->id,
                'address_line1' => '5 Elm Street', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
            ]);
            $task = CareTask::query()->create(['name' => 'Errands']);
            $ticket = $this->automatedTicket($family);
            $drafts = app(AiSupportRequestDraftService::class);
            $draft = $drafts->start($family, $ticket, CareRequest::TYPE_ONE_TIME);
            $draft = $drafts->applyPatch($family, $ticket, [
                'patch_fields' => ['recipient_is_requester', 'recipient_full_name', 'task_ids', 'requested_start_date', 'requested_start_time', 'duration_minutes'],
                'recipient_is_requester' => true, 'recipient_full_name' => $family->name,
                'task_ids' => [$task->id], 'requested_start_date' => '2026-08-16',
                'requested_start_time' => '11:00', 'duration_minutes' => 90,
            ], $draft->version);
            $recaps = app(AiSupportRecapService::class);
            $old = $recaps->issue($family, $ticket, $draft);

            CarbonImmutable::setTestNow($now->addMinutes(31));
            try {
                $recaps->confirm($family, $ticket, $old->id);
                $this->fail('Expired confirmation must not publish.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('confirmation', $exception->errors());
            }
            $this->assertDatabaseCount('care_requests', 0);

            $renewed = $recaps->renew($family, $ticket, $old->id);
            $this->assertTrue($renewed->isActive());
            $this->assertSame($draft->id, $renewed->payload['recap']['draft_id']);
            $this->assertSame(90, $draft->fresh()->payload['duration_minutes']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_logout_invalidates_confirmation_but_keeps_seven_day_draft_for_fresh_review(): void
    {
        [, $family] = $this->eligibleFamily(true);
        $account = app(FamilyAccountContext::class)->account($family);
        FamilyHouseholdProfile::query()->create([
            'family_account_id' => $account->id, 'family_user_id' => $family->id,
            'address_line1' => '8 Lake Street', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
        ]);
        $task = CareTask::query()->create(['name' => 'Companionship']);
        $ticket = $this->automatedTicket($family);
        $drafts = app(AiSupportRequestDraftService::class);
        $draft = $drafts->start($family, $ticket, CareRequest::TYPE_ONE_TIME);
        $draft = $drafts->applyPatch($family, $ticket, [
            'patch_fields' => ['recipient_is_requester', 'recipient_full_name', 'task_ids', 'requested_start_date', 'requested_start_time', 'duration_minutes'],
            'recipient_is_requester' => true, 'recipient_full_name' => $family->name,
            'task_ids' => [$task->id], 'requested_start_date' => now('America/New_York')->addDays(2)->toDateString(),
            'requested_start_time' => '10:00', 'duration_minutes' => 120,
        ], $draft->version);
        $action = app(AiSupportRecapService::class)->issue($family, $ticket, $draft);

        event(new Logout('web', $family));

        $this->assertNotNull($draft->fresh()->payload);
        $this->assertSame('actor_logged_out', $action->fresh()->invalidation_reason);
        $this->assertTrue($action->fresh()->payload['renewal_available']);
        $this->assertDatabaseCount('care_requests', 0);
        $renewed = app(AiSupportRecapService::class)->renew($family, $ticket, $action->id);
        $this->assertTrue($renewed->isActive());
    }

    private function eligibleFamily(bool $publication = false, bool $recurringPublication = false): array
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
        foreach (['master_enabled', 'user_visible_enabled', 'role.family', 'capability.support_answers_v1',
            'capability.semantic_navigation_v1', 'capability.family_context_v1', 'capability.care_intake_v1',
            'capability.care_request_draft_v1', 'capability.care_request_recap_v1', 'capability.care_24h_handoff_v1'] as $key) {
            $controls->set($admin, $key, true, 'Open exact-user interactive test capability');
        }
        $controls->set($admin, 'human_only', false, 'Permit exact-user automated test conversation');
        if ($publication) {
            foreach (['capability.care_request_publish_v1', 'commit.one_time', 'tool.care-request.publish.one-time'] as $key) {
                $controls->set($admin, $key, true, 'Open confirmed one-time publication test');
            }
        }
        if ($recurringPublication) {
            foreach (['commit.recurring', 'tool.care-request.publish.recurring'] as $key) {
                $controls->set($admin, $key, true, 'Open confirmed recurring publication test');
            }
        }
        app(AiSupportPilotGrantService::class)->grant(
            $admin,
            $family,
            'family_support_v1',
            CarbonImmutable::now(),
            CarbonImmutable::now()->addDays(14),
            'Exact-user interactive runtime test',
            (string) Str::uuid(),
        );

        return [$admin, $family];
    }

    private function automatedTicket(User $family): SupportTicket
    {
        $account = app(FamilyAccountContext::class)->account($family);

        return SupportTicket::query()->create([
            'family_account_id' => $account->id,
            'family_visibility' => 'opener_only',
            'opener_user_id' => $family->id,
            'source' => SupportTicket::SOURCE_CHAT_WIDGET,
            'responder_mode' => SupportTicket::RESPONDER_MODE_AUTOMATED,
            'category' => 'general',
            'status' => SupportTicket::STATUS_OPEN,
            'priority' => 'normal',
            'subject' => 'AI support test',
            'description' => 'Help me create a care request.',
            'initial_client_message_id' => (string) Str::uuid(),
        ]);
    }

    private function eligibleCaregiver(): array
    {
        config([
            'ai_support.runtime_available' => true,
            'ai_support.provider_enabled' => true,
            'services.openai.api_key' => 'test-key',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $controls = app(AiSupportControlService::class);
        foreach (['master_enabled', 'user_visible_enabled', 'role.caregiver', 'capability.support_answers_v1', 'capability.semantic_navigation_v1'] as $key) {
            $controls->set($admin, $key, true, 'Open exact-user Caregiver navigation test');
        }
        $controls->set($admin, 'human_only', false, 'Permit exact-user Caregiver automated test');
        app(AiSupportPilotGrantService::class)->grant(
            $admin, $caregiver, 'caregiver_support_v1', CarbonImmutable::now(), CarbonImmutable::now()->addDays(14),
            'Exact-user Caregiver runtime test', (string) Str::uuid(),
        );

        return [$admin, $caregiver];
    }

    /** @return array<string,mixed> */
    private function emptyPatch(): array
    {
        return [
            'patch_fields' => [], 'recipient_is_requester' => null, 'recipient_profile_id' => null,
            'recipient_full_name' => null, 'recipient_relationship' => null, 'task_ids' => [],
            'task_notes' => [], 'requested_start_date' => null, 'requested_start_time' => null,
            'duration_minutes' => null, 'recurring_days' => [], 'recurring_schedule' => [],
            'recurring_starts_on' => null, 'recurring_ends_on' => null, 'address_line1' => null,
            'address_line2' => null, 'city' => null, 'state' => null, 'zip' => null,
            'additional_info' => null, 'home_access_notes' => null, 'preferred_response_hours' => null,
        ];
    }

    /** @param array<string,mixed> $result @return array<string,mixed> */
    private function providerEnvelope(array $result): array
    {
        return [
            'output' => [[
                'type' => 'message',
                'content' => [['type' => 'output_text', 'text' => json_encode($result, JSON_THROW_ON_ERROR)]],
            ]],
            'usage' => [
                'input_tokens' => 500,
                'input_tokens_details' => ['cached_tokens' => 100],
                'output_tokens' => 80,
            ],
        ];
    }
}
