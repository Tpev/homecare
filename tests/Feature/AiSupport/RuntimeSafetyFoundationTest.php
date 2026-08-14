<?php

namespace Tests\Feature\AiSupport;

use App\Livewire\Admin\SupportTicketShow as AdminSupportTicketShow;
use App\Models\SupportTicket;
use App\Models\SupportTicketActivity;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\AiSupport\AiSupportActionEvidenceService;
use App\Services\AiSupport\AiSupportContextContract;
use App\Services\AiSupport\AiSupportControlService;
use App\Services\AiSupport\AiSupportEligibilityService;
use App\Services\AiSupport\AiSupportEventRecorder;
use App\Services\AiSupport\AiSupportHandoffService;
use App\Services\AiSupport\AiSupportIncidentService;
use App\Services\AiSupport\AiSupportPilotGrantService;
use App\Services\AiSupport\NavigationTargetRegistry;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Services\Support\SupportTicketMessagingService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class RuntimeSafetyFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_context_is_authorized_minimized_and_navigation_is_registered_and_role_aware(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $otherFamily = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $ticket = $this->createTicket($family, ['origin_route' => 'support.index']);
        $public = $this->message($ticket, $family, 'Canonical public content');
        $this->message($ticket, User::factory()->create(['role' => 'admin']), 'Private note content', SupportTicketMessage::KIND_INTERNAL_NOTE);

        $manifest = app(AiSupportContextContract::class)->manifest($family, $ticket, 'family.care_requests');

        $this->assertSame('support-context-v1', $manifest['contract_version']);
        $this->assertSame((string) $family->id, $manifest['authenticated_actor']['user_reference']);
        $this->assertSame('family.care_requests', $manifest['semantic_screen_target']);
        $this->assertSame([(string) $public->id], $manifest['canonical_message_references']);
        $this->assertStringNotContainsString('Canonical public content', json_encode($manifest, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('Private note content', json_encode($manifest, JSON_THROW_ON_ERROR));

        $navigation = app(NavigationTargetRegistry::class);
        $this->assertSame(route('family.requests.index'), $navigation->urlFor($family, 'family.care_requests'));
        $this->assertFalse($navigation->allowedFor($caregiver, 'family.care_requests'));

        try {
            $navigation->urlFor($family, 'arbitrary.javascript.selector');
            $this->fail('An arbitrary navigation target must be rejected.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $this->expectException(AuthorizationException::class);
        app(AiSupportContextContract::class)->manifest($otherFamily, $ticket);
    }

    public function test_interaction_events_accept_only_compact_content_free_fields(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $ticket = $this->createTicket($family);
        $events = app(AiSupportEventRecorder::class);

        $event = $events->record($ticket, 'answer_suppressed', [
            'capability_id' => 'support_answers_v1',
            'reason_code' => 'human_only',
            'result_code' => 'suppressed',
            'safe_metadata' => ['delivery_suppressed' => true],
        ], $family);

        $stored = DB::table('ai_support_interaction_events')->where('id', $event->id)->first();
        $this->assertSame('support-event-v1', $stored->event_contract_version);
        $this->assertStringNotContainsString('Do not store me', json_encode($stored, JSON_THROW_ON_ERROR));

        foreach ([
            ['body' => 'Do not store me'],
            ['safe_metadata' => ['body' => 'Do not store me']],
            ['reason_code' => 'customer wrote Do not store me'],
            ['safe_metadata' => ['policy_result' => 'Do not store me']],
            ['knowledge_version_ids' => ['Do not store me']],
        ] as $unsafe) {
            try {
                $events->record($ticket, 'unsafe_attempt', $unsafe, $family);
                $this->fail('Content-bearing event fields must be rejected.');
            } catch (\InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }

        $this->assertDatabaseCount('ai_support_interaction_events', 1);
    }

    public function test_handoff_is_atomic_final_invalidates_previews_and_can_only_return_deliberately(): void
    {
        Notification::fake();
        [$admin, $family] = $this->eligibleFamily();
        $ticket = $this->createTicket($family, ['responder_mode' => SupportTicket::RESPONDER_MODE_AUTOMATED]);
        $preview = app(AiSupportActionEvidenceService::class)->createPreview(
            $family,
            $ticket,
            'support_answers_v1',
            'test.preview',
            'v1',
            ['private_detail' => 'Pending sensitive action'],
            now()->addMinutes(10),
        )['preview'];

        $handoff = app(AiSupportHandoffService::class);
        $transferred = $handoff->transfer($family, $ticket, 'user_requested');
        $body = SupportTicketMessage::query()
            ->where('support_ticket_id', $ticket->id)
            ->where('kind', SupportTicketMessage::KIND_PUBLIC)
            ->sole()->body;

        $this->assertTrue($transferred->isHumanOnly());
        $this->assertSame("I've sent this conversation to LoLo Support. You can keep using this chat, and you won't need to repeat what you already told me.", $body);
        $this->assertDoesNotMatchRegularExpression('/queue|position|wait[ -]?time/i', $body);
        $this->assertFalse($handoff->mayDeliverAutomatedReply($ticket));
        $this->assertNotNull($preview->fresh()->content_deleted_at);
        $this->assertNull(DB::table('ai_support_action_previews')->where('id', $preview->id)->value('preview_payload'));
        $this->assertDatabaseHas('ai_support_interaction_events', [
            'support_ticket_id' => $ticket->id,
            'event_type' => 'transferred_to_human',
            'result_code' => 'human_only',
        ]);

        $handoff->transfer($family, $ticket->fresh(), 'user_requested');
        $this->assertSame(1, SupportTicketMessage::query()->where('support_ticket_id', $ticket->id)->where('kind', SupportTicketMessage::KIND_PUBLIC)->count());
        $this->assertSame(1, SupportTicketMessage::query()->where('support_ticket_id', $ticket->id)->where('kind', SupportTicketMessage::KIND_INTERNAL_NOTE)->count());

        $returned = $handoff->returnToAutomation($admin, $ticket->fresh(), 'User remains in the named pilot');
        $this->assertSame(SupportTicket::RESPONDER_MODE_AUTOMATED, $returned->responder_mode);
        $this->assertTrue($handoff->mayDeliverAutomatedReply($returned));
        $this->assertDatabaseHas('ai_support_interaction_events', [
            'support_ticket_id' => $ticket->id,
            'event_type' => 'returned_to_automation',
            'result_code' => 'automated',
        ]);

        $reply = app(SupportTicketMessagingService::class)->sendAdminReply(
            $ticket->fresh(),
            $admin,
            'A person has reviewed this and is responding.',
            (string) Str::uuid(),
        );
        $this->assertSame(SupportTicketMessage::RESPONDER_HUMAN, $reply->responder_type);
        $this->assertTrue($ticket->fresh()->isHumanOnly());
        $this->assertFalse($handoff->mayDeliverAutomatedReply($ticket));
    }

    public function test_notification_failure_never_restarts_automation_or_emits_an_extra_message(): void
    {
        User::factory()->create(['role' => 'admin']);
        $family = User::factory()->create(['role' => 'family']);
        $ticket = $this->createTicket($family, ['responder_mode' => SupportTicket::RESPONDER_MODE_AUTOMATED]);
        $notifications = Mockery::mock(MarketplaceNotificationService::class);
        $notifications->shouldReceive('notify')->once()->andThrow(new RuntimeException('notification unavailable'));
        $handoff = new AiSupportHandoffService(
            app(AiSupportEventRecorder::class),
            app(AiSupportEligibilityService::class),
            $notifications,
            app(AiSupportIncidentService::class),
        );

        $handoff->transfer($family, $ticket);

        $this->assertTrue($ticket->fresh()->isHumanOnly());
        $this->assertSame(1, SupportTicketMessage::query()->where('support_ticket_id', $ticket->id)->where('kind', SupportTicketMessage::KIND_PUBLIC)->count());
        $this->assertSame(1, SupportTicketMessage::query()->where('support_ticket_id', $ticket->id)->where('kind', SupportTicketMessage::KIND_INTERNAL_NOTE)->count());
        $this->assertDatabaseHas('ai_support_interaction_events', ['event_type' => 'transferred_to_human']);
    }

    public function test_confirmation_is_actor_bound_atomic_idempotent_and_content_minimized(): void
    {
        [$admin, $family] = $this->eligibleFamily();
        $other = User::factory()->create(['role' => 'family']);
        $ticket = $this->createTicket($family, ['responder_mode' => SupportTicket::RESPONDER_MODE_AUTOMATED]);
        $actions = app(AiSupportActionEvidenceService::class);

        try {
            $actions->createPreview($family, $ticket, 'care_request_create', 'care-request.create', 'v1', [], now()->addHours(25));
            $this->fail('Preview content must never be retained beyond 24 hours.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $created = $actions->createPreview(
            $family,
            $ticket,
            'care_request_create',
            'care-request.create',
            'v1',
            ['recipient' => 'Sensitive Person', 'hours' => 3],
            now()->addMinutes(15),
        );
        $preview = $created['preview'];
        $reference = $created['confirmation_reference'];
        $rawPreview = (string) DB::table('ai_support_action_previews')->where('id', $preview->id)->value('preview_payload');
        $this->assertStringNotContainsString('Sensitive Person', $rawPreview);
        $this->assertNotSame($reference, $preview->confirmation_reference_hash);
        $this->assertDatabaseHas('ai_support_interaction_events', [
            'support_ticket_id' => $ticket->id,
            'event_type' => 'action_previewed',
            'capability_id' => 'care_request_create',
            'result_code' => 'preview_created',
        ]);

        try {
            $actions->commitConfirmedAction(
                $family,
                $reference,
                (string) Str::uuid(),
                'create_request',
                function (): array {
                    SupportTicketActivity::query()->create([
                        'support_ticket_id' => SupportTicket::query()->value('id'),
                        'action' => 'failed_domain_write',
                        'created_at' => now(),
                    ]);
                    throw new RuntimeException('authoritative write failed');
                },
            );
            $this->fail('A failed authoritative commit must fail the whole transaction.');
        } catch (RuntimeException) {
            $this->assertTrue(true);
        }
        $this->assertDatabaseMissing('support_ticket_activities', ['action' => 'failed_domain_write']);
        $this->assertDatabaseCount('ai_support_confirmed_action_evidence', 0);
        $this->assertNull($preview->fresh()->content_deleted_at);

        try {
            $actions->commitConfirmedAction(
                $family,
                $reference,
                (string) Str::uuid(),
                'create_request',
                fn (): array => [
                    'outcome_code' => 'created',
                    'domain_reference_type' => 'care_request',
                    'domain_reference_id' => 'Sensitive Person',
                    'receipt_reference' => 'care-request-123',
                ],
            );
            $this->fail('Content-bearing receipt values must be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
        $this->assertDatabaseCount('ai_support_confirmed_action_evidence', 0);

        $idempotencyKey = (string) Str::uuid();
        $commitCalls = 0;
        $commit = function (array $payload) use (&$commitCalls, $ticket): array {
            $commitCalls++;
            SupportTicketActivity::query()->create([
                'support_ticket_id' => $ticket->id,
                'action' => 'authoritative_domain_write',
                'metadata' => ['hours' => $payload['hours']],
                'created_at' => now(),
            ]);

            return [
                'outcome_code' => 'created',
                'domain_reference_type' => 'care_request',
                'domain_reference_id' => '123',
                'receipt_reference' => 'care-request-123',
            ];
        };
        $evidence = $actions->commitConfirmedAction($family, $reference, $idempotencyKey, 'create_request', $commit);
        $again = $actions->commitConfirmedAction($family, $reference, $idempotencyKey, 'create_request', $commit);

        $this->assertSame($evidence->id, $again->id);
        $this->assertSame(1, $commitCalls);
        $this->assertDatabaseCount('ai_support_confirmed_action_evidence', 1);
        $this->assertSame($ticket->id, $evidence->support_ticket_id);
        $this->assertNotNull($evidence->pilot_grant_id);
        $this->assertDatabaseHas('ai_support_interaction_events', [
            'support_ticket_id' => $ticket->id,
            'event_type' => 'action_committed',
            'capability_id' => 'care_request_create',
            'result_code' => 'created',
        ]);
        $this->assertNull(DB::table('ai_support_action_previews')->where('id', $preview->id)->value('preview_payload'));
        $this->assertNotNull($preview->fresh()->content_deleted_at);
        $storedEvidence = json_encode(DB::table('ai_support_confirmed_action_evidence')->first(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('Sensitive Person', $storedEvidence);

        $this->artisan('ai-support:apply-retention --execute')->assertSuccessful();
        $this->assertDatabaseMissing('ai_support_action_previews', ['id' => $preview->id]);
        $afterPreviewDeletion = $actions->commitConfirmedAction($family, $reference, $idempotencyKey, 'create_request', $commit);
        $this->assertSame($evidence->id, $afterPreviewDeletion->id);
        $this->assertSame(1, $commitCalls);

        $this->expectException(AuthorizationException::class);
        $actions->commitConfirmedAction($other, $reference, $idempotencyKey, 'create_request', $commit);
    }

    public function test_unregistered_action_tool_is_denied_and_admin_can_see_compact_ticket_evidence(): void
    {
        [$admin, $family] = $this->eligibleFamily();
        $ticket = $this->createTicket($family, ['responder_mode' => SupportTicket::RESPONDER_MODE_AUTOMATED]);
        app(AiSupportEventRecorder::class)->record($ticket, 'answer_delivered', [
            'capability_id' => 'support_answers_v1',
            'result_code' => 'delivered',
        ], $family);

        Livewire::actingAs($admin)
            ->test(AdminSupportTicketShow::class, ['ticket' => $ticket])
            ->assertSee('AI evidence')
            ->assertSee('answer delivered')
            ->assertSee('support-event-v1')
            ->assertDontSee('private chain-of-thought');

        $this->expectException(AuthorizationException::class);
        app(AiSupportActionEvidenceService::class)->createPreview(
            $family,
            $ticket,
            'support_answers_v1',
            'unregistered.tool',
            'v1',
            [],
            now()->addMinute(),
        );
    }

    public function test_grant_revocation_immediately_deletes_pending_preview_content_and_blocks_commit(): void
    {
        [$admin, $family] = $this->eligibleFamily();
        $ticket = $this->createTicket($family, ['responder_mode' => SupportTicket::RESPONDER_MODE_AUTOMATED]);
        $actions = app(AiSupportActionEvidenceService::class);
        $created = $actions->createPreview(
            $family,
            $ticket,
            'care_request_create',
            'care-request.create',
            'v1',
            ['recipient' => 'Pending private content'],
            now()->addMinutes(10),
        );

        app(AiSupportPilotGrantService::class)->revoke(
            $admin,
            $family->aiSupportPilotGrants()->sole(),
            'End named pilot access now',
        );

        $preview = $created['preview']->fresh();
        $this->assertSame('pilot_grant_revoked', $preview->invalidation_reason);
        $this->assertNotNull($preview->content_deleted_at);
        $this->assertNull(DB::table('ai_support_action_previews')->where('id', $preview->id)->value('preview_payload'));

        $this->expectException(ValidationException::class);
        $actions->commitConfirmedAction(
            $family,
            $created['confirmation_reference'],
            (string) Str::uuid(),
            'create_request',
            fn (): array => throw new RuntimeException('Commit must not run.'),
        );
    }

    public function test_event_failure_rolls_back_domain_commit_receipt_and_preview_consumption(): void
    {
        [, $family] = $this->eligibleFamily();
        $ticket = $this->createTicket($family, ['responder_mode' => SupportTicket::RESPONDER_MODE_AUTOMATED]);
        $created = app(AiSupportActionEvidenceService::class)->createPreview(
            $family,
            $ticket,
            'care_request_create',
            'care-request.create',
            'v1',
            ['hours' => 2],
            now()->addMinutes(10),
        );
        $failingEvents = Mockery::mock(AiSupportEventRecorder::class);
        $failingEvents->shouldReceive('record')->once()->andThrow(new RuntimeException('event storage unavailable'));
        $actions = new AiSupportActionEvidenceService(app(AiSupportEligibilityService::class), $failingEvents);

        try {
            $actions->commitConfirmedAction(
                $family,
                $created['confirmation_reference'],
                (string) Str::uuid(),
                'create_request',
                function () use ($ticket): array {
                    SupportTicketActivity::query()->create([
                        'support_ticket_id' => $ticket->id,
                        'action' => 'must_roll_back_with_event',
                        'created_at' => now(),
                    ]);

                    return [
                        'outcome_code' => 'created',
                        'domain_reference_type' => 'care_request',
                        'domain_reference_id' => '456',
                        'receipt_reference' => 'care-request-456',
                    ];
                },
            );
            $this->fail('Event storage failure must fail the authoritative transaction.');
        } catch (RuntimeException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseMissing('support_ticket_activities', ['action' => 'must_roll_back_with_event']);
        $this->assertDatabaseCount('ai_support_confirmed_action_evidence', 0);
        $this->assertNull($created['preview']->fresh()->content_deleted_at);
        $this->assertNotNull($created['preview']->fresh()->preview_payload);
    }

    private function eligibleFamily(): array
    {
        $controlDefaults = (array) config('ai_support.controls');
        $controlDefaults['capability.care_request_create'] = false;
        $controlDefaults['tool.test.preview'] = false;
        $controlDefaults['tool.care-request.create'] = false;
        config([
            'ai_support.runtime_available' => true,
            'ai_support.bundles.family_support_v1.capabilities' => ['support_answers_v1', 'care_request_create'],
            'ai_support.controls' => $controlDefaults,
            'ai_support.tools' => [
                'test.preview' => [
                    'capability_id' => 'support_answers_v1',
                    'versions' => ['v1'],
                    'preview_validity_minutes' => 15,
                ],
                'care-request.create' => [
                    'capability_id' => 'care_request_create',
                    'versions' => ['v1'],
                    'preview_validity_minutes' => 15,
                ],
            ],
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $family = User::factory()->create(['role' => 'family']);
        $controls = app(AiSupportControlService::class);
        $controls->set($admin, 'master_enabled', true, 'Open named pilot master control');
        $controls->set($admin, 'user_visible_enabled', true, 'Permit named pilot user experience');
        $controls->set($admin, 'human_only', false, 'Permit only otherwise eligible conversations');
        $controls->set($admin, 'role.family', true, 'Release family support role for pilot');
        $controls->set($admin, 'capability.support_answers_v1', true, 'Release answer-only support capability');
        $controls->set($admin, 'capability.care_request_create', true, 'Test registered confirmation foundation');
        $controls->set($admin, 'tool.test.preview', true, 'Test short lived preview foundation');
        $controls->set($admin, 'tool.care-request.create', true, 'Test bound confirmation foundation');
        app(AiSupportPilotGrantService::class)->grant(
            $admin,
            $family,
            'family_support_v1',
            CarbonImmutable::now(),
            CarbonImmutable::now()->addDays(14),
            'Named runtime foundation pilot',
            (string) Str::uuid(),
        );

        return [$admin, $family];
    }

    private function createTicket(User $opener, array $overrides = []): SupportTicket
    {
        return SupportTicket::query()->create(array_merge([
            'opener_user_id' => $opener->id,
            'source' => SupportTicket::SOURCE_CHAT_WIDGET,
            'category' => 'general',
            'status' => SupportTicket::STATUS_OPEN,
            'priority' => 'normal',
            'subject' => 'Need help',
            'description' => 'Please help me use LoLo.',
        ], $overrides));
    }

    private function message(
        SupportTicket $ticket,
        User $sender,
        string $body,
        string $kind = SupportTicketMessage::KIND_PUBLIC,
    ): SupportTicketMessage {
        return SupportTicketMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'sender_user_id' => $sender->id,
            'kind' => $kind,
            'responder_type' => SupportTicketMessage::RESPONDER_HUMAN,
            'body' => $body,
            'client_message_id' => (string) Str::uuid(),
        ]);
    }
}
