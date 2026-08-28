<?php

namespace Tests\Feature\AiSupport;

use App\Livewire\Support\ChatWidget;
use App\Models\AiSupportConfirmedActionEvidence;
use App\Models\AiSupportMessageAction;
use App\Models\AiSupportPilotGrant;
use App\Models\AiSupportPreparation;
use App\Models\AiSupportRequestDraft;
use App\Models\CareBooking;
use App\Models\CareRecipientProfile;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareTask;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\AiSupport\AiSupportControlService;
use App\Services\AiSupport\AiSupportPilotGrantService;
use App\Services\AiSupport\AiSupportRecapService;
use App\Services\AiSupport\AiSupportRequestDraftService;
use App\Services\AiSupport\AiSupportRuntimeService;
use App\Services\AiSupport\FamilyIntentResolver;
use App\Services\AiSupport\FamilyLifecycleActionService;
use App\Services\CareRecipientProfiles\CareRecipientProfileService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class Batch5FamilyLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_is_collected_recapped_confirmed_saved_and_verified_without_provider(): void
    {
        [, $family] = $this->eligibleFamily();
        $ticket = $this->ticket($family, 'Create a care receiver profile for Maria.');
        Http::fake();

        app(AiSupportRuntimeService::class)->respond($family, $ticket, 'Create a care receiver profile for Maria.');

        $action = AiSupportMessageAction::query()->where('action_type', AiSupportMessageAction::TYPE_DOMAIN_RECAP)->sole();
        $this->assertSame('family-profile.save-draft', data_get($action->payload, 'tool_id'));
        $this->assertSame('Maria', collect((array) data_get($action->payload, 'fields'))->firstWhere('label', 'Preferred Name')['value']);
        $this->assertSame(0, CareRecipientProfile::query()->count());

        $evidence = app(FamilyLifecycleActionService::class)->confirm($family, $ticket, $action->id);
        $profile = CareRecipientProfile::query()->sole();
        $this->assertSame('Maria', $profile->preferred_name);
        $this->assertSame(CareRecipientProfile::STATUS_DRAFT, $profile->status);
        $this->assertSame('profile_saved_verified', $evidence->outcome_code);
        $this->assertDatabaseHas('ai_support_confirmed_action_evidence', [
            'id' => $evidence->id,
            'domain_reference_type' => 'care_profile',
            'domain_reference_id' => (string) $profile->id,
        ]);
        $this->assertDatabaseHas('ai_support_message_actions', ['action_type' => AiSupportMessageAction::TYPE_DOMAIN_RECEIPT]);
        Http::assertNothingSent();
    }

    public function test_profile_can_be_made_ready_only_after_exact_readiness_and_is_idempotent(): void
    {
        [, $family] = $this->eligibleFamily();
        $profile = app(CareRecipientProfileService::class)->saveDraft($family, null, [
            'preferred_name' => 'Maria',
            'about_them' => 'Enjoys quiet music and short walks.',
        ]);
        $ticket = $this->ticket($family, 'Mark Maria’s profile ready for care requests.');

        app(AiSupportRuntimeService::class)->respond($family, $ticket, 'Mark Maria’s profile ready for care requests.');
        $action = AiSupportMessageAction::query()->where('action_type', AiSupportMessageAction::TYPE_DOMAIN_RECAP)->sole();
        $first = app(FamilyLifecycleActionService::class)->confirm($family, $ticket, $action->id);
        $second = app(FamilyLifecycleActionService::class)->confirm($family, $ticket, $action->id);

        $this->assertSame($first->id, $second->id);
        $this->assertTrue($profile->fresh()->isReady());
        $this->assertSame(1, AiSupportConfirmedActionEvidence::query()->count());
        $this->assertSame(1, $profile->versions()->count());
    }

    public function test_natural_finish_edit_and_add_phrasing_keeps_profile_editing_conversational(): void
    {
        [, $family] = $this->eligibleFamily();
        $profile = app(CareRecipientProfileService::class)->saveDraft($family, null, [
            'preferred_name' => 'Batch Five Final Profile',
        ]);
        $ticket = $this->ticket($family, 'I need to finish and edit Batch Five Final Profile.');
        Http::fake();

        app(AiSupportRuntimeService::class)->respond($family, $ticket, 'I need to finish and edit Batch Five Final Profile.');

        $preparation = AiSupportPreparation::query()->sole();
        $this->assertSame($profile->id, $preparation->resource_id);
        $this->assertStringContainsString(
            'What would you like to change in Batch Five Final Profile',
            $ticket->publicMessages()->latest()->firstOrFail()->body,
        );

        $message = 'Add that Batch Five Final Profile enjoys classical music and prefers quiet mornings.';
        app(AiSupportRuntimeService::class)->respond($family, $ticket, $message);

        $action = AiSupportMessageAction::query()
            ->where('action_type', AiSupportMessageAction::TYPE_DOMAIN_RECAP)
            ->latest('created_at')
            ->firstOrFail();
        $this->assertSame('family-profile.save-draft', data_get($action->payload, 'tool_id'));
        $this->assertStringContainsString(
            'enjoys classical music and prefers quiet mornings',
            (string) collect((array) data_get($action->payload, 'fields'))->firstWhere('label', 'About Them')['value'],
        );

        $evidence = app(FamilyLifecycleActionService::class)->confirm($family, $ticket, $action->id);
        $this->assertSame('profile_saved_verified', $evidence->outcome_code);
        $this->assertStringContainsString('enjoys classical music', (string) $profile->fresh()->about_them);
        Http::assertNothingSent();
    }

    public function test_one_time_request_sentence_with_a_profile_name_never_becomes_profile_creation(): void
    {
        $message = 'Create a one-time care request for Batch Five Final Profile on August 28, 2026 at 10:00 AM for 2 hours, at 123 Pilot Test Way, Raleigh, NC 27601, for companionship.';

        $resolution = app(FamilyIntentResolver::class)->resolve($message);

        $this->assertSame(FamilyIntentResolver::STATUS_RECOGNIZED, $resolution['status']);
        $this->assertSame('FAM-START-008', $resolution['intent_id']);
    }

    public function test_care_profile_purpose_question_routes_to_the_explanation_intent(): void
    {
        $resolution = app(FamilyIntentResolver::class)->resolve('What is a care profile and what should it contain?');

        $this->assertSame(FamilyIntentResolver::STATUS_RECOGNIZED, $resolution['status']);
        $this->assertSame('FAM-PROFILE-001', $resolution['intent_id']);
    }

    public function test_profile_update_strips_instructional_say_prefix_from_about_them(): void
    {
        [, $family] = $this->eligibleFamily();
        $profile = app(CareRecipientProfileService::class)->saveDraft($family, null, [
            'preferred_name' => 'Batch Five Final Profile',
        ]);
        $message = 'Update Batch Five Final Profile to say: Enjoys classical music and prefers quiet mornings.';
        $ticket = $this->ticket($family, $message);

        app(AiSupportRuntimeService::class)->respond($family, $ticket, $message);

        $action = AiSupportMessageAction::query()
            ->where('action_type', AiSupportMessageAction::TYPE_DOMAIN_RECAP)
            ->sole();
        $about = (string) collect((array) data_get($action->payload, 'fields'))
            ->firstWhere('label', 'About Them')['value'];
        $this->assertSame('Enjoys classical music and prefers quiet mornings.', $about);

        app(FamilyLifecycleActionService::class)->confirm($family, $ticket, $action->id);
        $this->assertSame('Enjoys classical music and prefers quiet mornings.', $profile->fresh()->about_them);
    }

    public function test_family_can_cancel_an_incomplete_profile_change_from_the_chat_composer(): void
    {
        [, $family] = $this->eligibleFamily();
        $ticket = $this->ticket($family, 'Create a care receiver profile.');
        app(AiSupportRuntimeService::class)->respond($family, $ticket, 'Create a care receiver profile.');
        $preparation = AiSupportPreparation::query()->sole();

        Livewire::actingAs($family)
            ->test(ChatWidget::class)
            ->assertSee('Cancel current profile change')
            ->call('cancelActiveProfileChange')
            ->assertSee('The current profile change was discarded. Nothing was saved.')
            ->assertDontSee('Cancel current profile change');

        $this->assertSame(AiSupportPreparation::STATE_CANCELLED, $preparation->fresh()->state);
        $this->assertDatabaseCount('care_recipient_profiles', 0);
    }

    public function test_cancelling_a_profile_change_invalidates_its_existing_confirmation(): void
    {
        [, $family] = $this->eligibleFamily();
        $ticket = $this->ticket($family, 'Create a care receiver profile for Maria.');
        app(AiSupportRuntimeService::class)->respond($family, $ticket, 'Create a care receiver profile for Maria.');
        $action = AiSupportMessageAction::query()
            ->where('action_type', AiSupportMessageAction::TYPE_DOMAIN_RECAP)
            ->sole();

        Livewire::actingAs($family)
            ->test(ChatWidget::class)
            ->call('cancelActiveProfileChange');

        $this->assertNotNull($action->fresh()->invalidated_at);
        $this->assertSame('preparation_cancelled', $action->fresh()->invalidation_reason);
        try {
            app(FamilyLifecycleActionService::class)->confirm($family, $ticket, $action->id);
            $this->fail('Expected the cancelled profile confirmation to be denied.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('expired or changed', (string) collect($exception->errors())->flatten()->first());
        }
        $this->assertDatabaseCount('care_recipient_profiles', 0);
        $this->assertDatabaseCount('ai_support_confirmed_action_evidence', 0);
    }

    public function test_multi_turn_profile_details_use_one_bounded_model_patch_then_require_confirmation(): void
    {
        [, $family] = $this->eligibleFamily();
        $ticket = $this->ticket($family, 'Create a care receiver profile.');
        app(AiSupportRuntimeService::class)->respond($family, $ticket, 'Create a care receiver profile.');
        $this->assertDatabaseCount('care_recipient_profiles', 0);
        $this->assertStringContainsString('what name', mb_strtolower($ticket->publicMessages()->latest()->first()->body));

        Http::fake(['*/responses' => Http::response([
            'output' => [[
                'type' => 'message',
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode([
                        'operation' => 'profile_patch',
                        'message' => '',
                        'navigation_target_id' => null,
                        'care_path' => null,
                        'clarifying_question' => null,
                        'confidence_band' => 'clear',
                        'kb_stable_ids' => [],
                        'draft_patch' => [],
                        'profile_patch' => [
                            'patch_fields' => ['preferred_name', 'interests_and_comforts'],
                            'preferred_name' => 'Maria',
                            'interests_and_comforts' => 'Enjoys quiet jazz.',
                        ],
                    ], JSON_THROW_ON_ERROR),
                ]],
            ]],
            'usage' => [
                'input_tokens' => 300,
                'input_tokens_details' => ['cached_tokens' => 50],
                'output_tokens' => 50,
            ],
        ])]);

        app(AiSupportRuntimeService::class)->respond($family, $ticket, 'Call her Maria. She likes quiet jazz.');

        $action = AiSupportMessageAction::query()->where('action_type', AiSupportMessageAction::TYPE_DOMAIN_RECAP)->sole();
        $this->assertSame('Maria', collect((array) data_get($action->payload, 'fields'))->firstWhere('label', 'Preferred Name')['value']);
        $this->assertDatabaseCount('care_recipient_profiles', 0);
        app(FamilyLifecycleActionService::class)->confirm($family, $ticket, $action->id);
        $profile = CareRecipientProfile::query()->sole();
        $this->assertSame('Enjoys quiet jazz.', $profile->interests_and_comforts);
        Http::assertSentCount(1);
    }

    public function test_stale_profile_confirmation_is_denied_and_does_not_archive(): void
    {
        [, $family] = $this->eligibleFamily();
        $profile = app(CareRecipientProfileService::class)->saveDraft($family, null, [
            'preferred_name' => 'Maria', 'about_them' => 'Likes music.',
        ]);
        $ticket = $this->ticket($family, 'Archive Maria’s profile.');

        app(AiSupportRuntimeService::class)->respond($family, $ticket, 'Archive Maria’s profile.');
        $action = AiSupportMessageAction::query()->where('action_type', AiSupportMessageAction::TYPE_DOMAIN_RECAP)->sole();
        $profile->forceFill(['revision' => $profile->revision + 1])->save();

        try {
            app(FamilyLifecycleActionService::class)->confirm($family, $ticket, $action->id);
            $this->fail('Expected a stale confirmation denial.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('changed', (string) collect($exception->errors())->flatten()->first());
        }
        $this->assertFalse($profile->fresh()->isArchived());
        $this->assertSame(0, AiSupportConfirmedActionEvidence::query()->count());
    }

    public function test_explicit_archive_wins_over_incidental_complete_wording(): void
    {
        [, $family] = $this->eligibleFamily();
        $profile = app(CareRecipientProfileService::class)->saveDraft($family, null, [
            'preferred_name' => 'Batch Five Test Profile', 'about_them' => 'Synthetic pilot profile.',
        ]);
        $message = 'Archive the Batch Five Test Profile now that the pilot test is complete.';
        $ticket = $this->ticket($family, $message);

        app(AiSupportRuntimeService::class)->respond($family, $ticket, $message);

        $action = AiSupportMessageAction::query()->where('action_type', AiSupportMessageAction::TYPE_DOMAIN_RECAP)->sole();
        $this->assertSame('family-profile.archive', data_get($action->payload, 'tool_id'));
        $this->assertStringContainsString('Archive', (string) data_get($action->payload, 'title'));
        $this->assertFalse($profile->fresh()->isArchived());
    }

    public function test_default_archive_and_restore_are_each_confirmed_and_database_verified(): void
    {
        [, $family] = $this->eligibleFamily();
        $profiles = app(CareRecipientProfileService::class);
        $first = $profiles->saveDraft($family, null, [
            'preferred_name' => 'Maria', 'about_them' => 'Enjoys music.',
        ]);
        $second = $profiles->saveDraft($family, null, [
            'preferred_name' => 'Rosa', 'about_them' => 'Enjoys gardening.',
        ]);
        $ticket = $this->ticket($family, 'Manage Rosa’s profile.');

        app(AiSupportRuntimeService::class)->respond($family, $ticket, 'Make Rosa the default profile.');
        $defaultRecap = AiSupportMessageAction::query()
            ->where('support_ticket_id', $ticket->id)
            ->where('action_type', AiSupportMessageAction::TYPE_DOMAIN_RECAP)
            ->latest('created_at')->firstOrFail();
        $this->assertSame('family-profile.make-default', data_get($defaultRecap->payload, 'tool_id'));
        $defaultEvidence = app(FamilyLifecycleActionService::class)->confirm($family, $ticket, $defaultRecap->id);
        $this->assertSame('profile_default_verified', $defaultEvidence->outcome_code);
        $this->assertSame($second->id, app(FamilyAccountContext::class)->account($family)->fresh()->default_care_recipient_profile_id);

        app(AiSupportRuntimeService::class)->respond($family, $ticket, 'Archive Rosa’s profile.');
        $archiveRecap = AiSupportMessageAction::query()
            ->where('support_ticket_id', $ticket->id)
            ->where('action_type', AiSupportMessageAction::TYPE_DOMAIN_RECAP)
            ->latest('created_at')->firstOrFail();
        $this->assertSame('family-profile.archive', data_get($archiveRecap->payload, 'tool_id'));
        $archiveEvidence = app(FamilyLifecycleActionService::class)->confirm($family, $ticket, $archiveRecap->id);
        $this->assertSame('profile_archived_verified', $archiveEvidence->outcome_code);
        $this->assertTrue($second->fresh()->isArchived());
        $this->assertSame($first->id, app(FamilyAccountContext::class)->account($family)->fresh()->default_care_recipient_profile_id);

        app(AiSupportRuntimeService::class)->respond($family, $ticket, 'Restore Rosa’s archived profile.');
        $restoreRecap = AiSupportMessageAction::query()
            ->where('support_ticket_id', $ticket->id)
            ->where('action_type', AiSupportMessageAction::TYPE_DOMAIN_RECAP)
            ->latest('created_at')->firstOrFail();
        $this->assertSame('family-profile.restore', data_get($restoreRecap->payload, 'tool_id'));
        $restoreEvidence = app(FamilyLifecycleActionService::class)->confirm($family, $ticket, $restoreRecap->id);
        $this->assertSame('profile_restored_verified', $restoreEvidence->outcome_code);
        $this->assertFalse($second->fresh()->isArchived());
        $this->assertSame($first->id, app(FamilyAccountContext::class)->account($family)->fresh()->default_care_recipient_profile_id);
        $this->assertSame(3, AiSupportConfirmedActionEvidence::query()->count());
        $this->assertSame(3, AiSupportMessageAction::query()->where('action_type', AiSupportMessageAction::TYPE_DOMAIN_RECEIPT)->count());
    }

    public function test_wrong_account_profile_is_never_offered_or_changed(): void
    {
        [, $family] = $this->eligibleFamily();
        [, $other] = $this->eligibleFamily();
        $foreign = app(CareRecipientProfileService::class)->saveDraft($other, null, [
            'preferred_name' => 'Private Rosa', 'about_them' => 'Private account detail.',
        ]);
        $ticket = $this->ticket($family, 'Archive profile #'.$foreign->id.'.');

        app(AiSupportRuntimeService::class)->respond($family, $ticket, 'Archive profile #'.$foreign->id.'.');

        $this->assertDatabaseCount('ai_support_confirmed_action_evidence', 0);
        $this->assertSame(0, AiSupportMessageAction::query()->where('action_type', AiSupportMessageAction::TYPE_DOMAIN_RECAP)->count());
        $this->assertFalse($foreign->fresh()->isArchived());
        $this->assertStringContainsString('could not safely identify', mb_strtolower($ticket->publicMessages()->latest()->first()->body));
    }

    public function test_open_request_withdrawal_has_recap_confirmation_receipt_and_authoritative_status(): void
    {
        [, $family] = $this->eligibleFamily();
        $request = $this->request($family, CareRequest::STATUS_OPEN);
        $ticket = $this->ticket($family, 'Withdraw request #'.$request->id.'.');

        app(AiSupportRuntimeService::class)->respond($family, $ticket, 'Withdraw request #'.$request->id.'.');
        $action = AiSupportMessageAction::query()->where('action_type', AiSupportMessageAction::TYPE_DOMAIN_RECAP)->sole();
        $this->assertSame('care-request.withdraw', data_get($action->payload, 'tool_id'));
        $this->assertSame(CareRequest::STATUS_OPEN, $request->fresh()->status);

        $evidence = app(FamilyLifecycleActionService::class)->confirm($family, $ticket, $action->id);
        $this->assertSame(CareRequest::STATUS_CANCELLED, $request->fresh()->status);
        $this->assertSame('request_withdrawn_verified', $evidence->outcome_code);
        $this->assertTrue($ticket->publicMessages()->pluck('body')->contains(
            fn (string $body): bool => str_contains($body, 'withdrawn and checked'),
        ));
    }

    public function test_expired_request_creates_fresh_private_copy_clears_schedule_and_keeps_original(): void
    {
        [, $family] = $this->eligibleFamily();
        $source = $this->request($family, CareRequest::STATUS_EXPIRED);
        $task = CareTask::query()->create(['name' => 'Companionship']);
        $source->tasks()->attach($task->id, ['task_note' => 'Read together.']);
        $ticket = $this->ticket($family, 'Create a fresh copy of expired request #'.$source->id.'.');

        app(AiSupportRuntimeService::class)->respond($family, $ticket, 'Create a fresh copy of expired request #'.$source->id.'.');

        $draft = AiSupportRequestDraft::query()->sole();
        $this->assertSame($source->id, (int) data_get($draft->payload, '_source_request.id'));
        $this->assertSame('expired_copy', data_get($draft->payload, '_source_request.mode'));
        $this->assertArrayNotHasKey('requested_start_date', (array) $draft->payload);
        $this->assertArrayNotHasKey('requested_start_time', (array) $draft->payload);
        $this->assertSame(CareRequest::STATUS_EXPIRED, $source->fresh()->status);
        $this->assertSame(1, CareRequest::query()->count());
        $this->assertStringContainsString('original remains unchanged', $ticket->publicMessages()->latest()->first()->body);
    }

    public function test_reuse_duplicate_live_replacement_type_replacement_and_withdrawn_copy_are_distinct(): void
    {
        [, $family] = $this->eligibleFamily();
        $task = CareTask::query()->create(['name' => 'Companionship']);
        $scenarios = [
            ['message' => 'Reuse request #%d.', 'status' => CareRequest::STATUS_OPEN, 'mode' => 'reuse', 'type' => CareRequest::TYPE_ONE_TIME],
            ['message' => 'Duplicate request #%d.', 'status' => CareRequest::STATUS_OPEN, 'mode' => 'duplicate', 'type' => CareRequest::TYPE_ONE_TIME],
            ['message' => 'Edit live request #%d.', 'status' => CareRequest::STATUS_OPEN, 'mode' => 'replacement', 'type' => CareRequest::TYPE_ONE_TIME],
            ['message' => 'Change one-time request #%d to recurring.', 'status' => CareRequest::STATUS_OPEN, 'mode' => 'replacement', 'type' => CareRequest::TYPE_RECURRING],
            ['message' => 'Create a fresh copy of withdrawn request #%d.', 'status' => CareRequest::STATUS_CANCELLED, 'mode' => 'withdrawn_copy', 'type' => CareRequest::TYPE_ONE_TIME],
        ];

        foreach ($scenarios as $scenario) {
            $source = $this->request($family, $scenario['status']);
            $source->tasks()->attach($task->id, ['task_note' => 'Read together.']);
            $message = sprintf($scenario['message'], $source->id);
            $ticket = $this->ticket($family, $message);

            app(AiSupportRuntimeService::class)->respond($family, $ticket, $message);

            $draft = AiSupportRequestDraft::query()->where('support_ticket_id', $ticket->id)->sole();
            $this->assertSame($scenario['mode'], data_get($draft->payload, '_source_request.mode'));
            $this->assertSame($scenario['type'], $draft->request_type);
            $this->assertSame($source->id, (int) data_get($draft->payload, '_source_request.id'));
            $this->assertArrayNotHasKey('requested_start_date', (array) $draft->payload);
            $this->assertSame($scenario['status'], $source->fresh()->status);
            $this->assertSame('collecting', $draft->state);
        }
    }

    public function test_copied_request_recovers_from_validation_failure_and_publishes_as_a_new_request(): void
    {
        [, $family] = $this->eligibleFamily();
        $source = $this->request($family, CareRequest::STATUS_EXPIRED);
        $task = CareTask::query()->create(['name' => 'Companionship']);
        $source->tasks()->attach($task->id, ['task_note' => 'Read together.']);
        $ticket = $this->ticket($family, 'Copy expired request #'.$source->id.'.');
        app(AiSupportRuntimeService::class)->respond($family, $ticket, 'Create a fresh copy of expired request #'.$source->id.'.');
        $drafts = app(AiSupportRequestDraftService::class);
        $draft = AiSupportRequestDraft::query()->where('support_ticket_id', $ticket->id)->sole();

        $invalid = $drafts->applyPatch($family, $ticket, [
            'patch_fields' => ['requested_start_date', 'requested_start_time', 'duration_minutes'],
            'requested_start_date' => now('America/New_York')->subDay()->toDateString(),
            'requested_start_time' => '10:00',
            'duration_minutes' => 120,
        ], $draft->version);
        $this->assertSame(AiSupportRequestDraft::STATE_COLLECTING, $invalid->state);
        $this->assertSame('start_not_future', $invalid->last_error_code);
        $this->assertStringContainsString('future', mb_strtolower((string) $drafts->nextQuestion($family, $invalid)));

        $start = now('America/New_York')->addDays(7)->setTime(10, 0);
        $ready = $drafts->applyPatch($family, $ticket, [
            'patch_fields' => ['requested_start_date', 'requested_start_time', 'duration_minutes'],
            'requested_start_date' => $start->toDateString(),
            'requested_start_time' => $start->format('H:i'),
            'duration_minutes' => 120,
        ], $invalid->version);
        $this->assertSame(AiSupportRequestDraft::STATE_READY_FOR_RECAP, $ready->state);

        $recap = app(AiSupportRecapService::class)->issue($family, $ticket, $ready);
        $this->assertStringContainsString('original stays unchanged', (string) data_get($recap->payload, 'recap.source_disclosure'));
        $published = app(AiSupportRecapService::class)->confirm($family, $ticket, $recap->id);

        $this->assertNotSame($source->id, $published->id);
        $this->assertSame(CareRequest::STATUS_OPEN, $published->status);
        $this->assertSame(CareRequest::STATUS_EXPIRED, $source->fresh()->status);
        $this->assertSame($published->id, $ready->fresh()->published_care_request_id);
        $this->assertSame(2, CareRequest::query()->count());
    }

    public function test_exact_request_status_blockers_and_applicant_count_are_read_from_authorized_state(): void
    {
        [, $family] = $this->eligibleFamily();
        Http::fake();
        $open = $this->request($family, CareRequest::STATUS_OPEN);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        CareRequestApplication::query()->create([
            'care_request_id' => $open->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_APPLIED,
            'proposed_rate' => 27,
        ]);
        $applicantTicket = $this->ticket($family, 'How many caregivers applied to request #'.$open->id.'?');
        app(AiSupportRuntimeService::class)->respond($family, $applicantTicket, 'How many caregivers applied to request #'.$open->id.'?');
        $this->assertStringContainsString('1 caregiver is waiting for your review', $applicantTicket->publicMessages()->latest()->first()->body);

        foreach ([
            CareRequest::STATUS_DRAFT => ['Draft — not visible to caregivers', 'not been published'],
            CareRequest::STATUS_FILLED => ['Caregiver selected', 'selected caregiver'],
            CareRequest::STATUS_CANCELLED => ['Withdrawn', 'fresh copy'],
            CareRequest::STATUS_EXPIRED => ['Expired', 'fresh copy'],
        ] as $status => [$label, $blocker]) {
            $request = $this->request($family, $status);
            $message = 'What is the status of request #'.$request->id.'?';
            $ticket = $this->ticket($family, $message);
            app(AiSupportRuntimeService::class)->respond($family, $ticket, $message);
            $body = $ticket->publicMessages()->latest()->first()->body;
            $this->assertStringContainsString($label, $body);
            $this->assertStringContainsString($blocker, $body);
        }
        Http::assertNothingSent();
    }

    public function test_wrong_account_request_is_never_offered_or_copied(): void
    {
        [, $family] = $this->eligibleFamily();
        [, $other] = $this->eligibleFamily();
        $foreign = $this->request($other, CareRequest::STATUS_OPEN);
        $ticket = $this->ticket($family, 'Withdraw request #'.$foreign->id.'.');

        app(AiSupportRuntimeService::class)->respond($family, $ticket, 'Withdraw request #'.$foreign->id.'.');

        $this->assertSame(0, AiSupportMessageAction::query()->where('action_type', AiSupportMessageAction::TYPE_DOMAIN_RECAP)->count());
        $this->assertSame(CareRequest::STATUS_OPEN, $foreign->fresh()->status);
        $this->assertStringContainsString('could not find', mb_strtolower($ticket->publicMessages()->latest()->first()->body));
    }

    public function test_disabled_batch_five_capability_cannot_copy_or_change_a_request(): void
    {
        [$admin, $family] = $this->eligibleFamily();
        $source = $this->request($family, CareRequest::STATUS_EXPIRED);
        AiSupportPilotGrant::query()->where('user_id', $family->id)->get()->each(function (AiSupportPilotGrant $grant): void {
            $grant->forceFill(['capability_ids' => collect($grant->capability_ids)
                ->reject(fn (string $id): bool => $id === 'family_lifecycle_action_v1')->values()->all()])->save();
        });
        app(AiSupportControlService::class)->set(
            $admin,
            'capability.family_lifecycle_action_v1',
            false,
            'Prove Batch 5 lifecycle denial',
        );
        $ticket = $this->ticket($family, 'Create a fresh copy of expired request #'.$source->id.'.');
        Http::fake();

        app(AiSupportRuntimeService::class)->respond($family, $ticket, 'Create a fresh copy of expired request #'.$source->id.'.');

        $this->assertDatabaseCount('ai_support_request_drafts', 0);
        $this->assertDatabaseCount('ai_support_confirmed_action_evidence', 0);
        $this->assertSame(CareRequest::STATUS_EXPIRED, $source->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_expired_profile_recap_can_be_reviewed_again_and_then_confirmed(): void
    {
        [, $family] = $this->eligibleFamily();
        $ticket = $this->ticket($family, 'Create a care receiver profile for Maria.');
        app(AiSupportRuntimeService::class)->respond($family, $ticket, 'Create a care receiver profile for Maria.');
        $expired = AiSupportMessageAction::query()->where('action_type', AiSupportMessageAction::TYPE_DOMAIN_RECAP)->sole();
        $expired->forceFill(['expires_at' => now()->subMinute()])->save();

        try {
            app(FamilyLifecycleActionService::class)->confirm($family, $ticket, $expired->id);
            $this->fail('Expected the expired recap to be denied.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('expired', mb_strtolower((string) collect($exception->errors())->flatten()->first()));
        }

        $renewed = app(FamilyLifecycleActionService::class)->renew($family, $ticket, $expired->id);
        $this->assertTrue($renewed->isActive());
        $evidence = app(FamilyLifecycleActionService::class)->confirm($family, $ticket, $renewed->id);
        $this->assertSame('profile_saved_verified', $evidence->outcome_code);
        $this->assertSame('Maria', CareRecipientProfile::query()->sole()->preferred_name);
    }

    public function test_request_with_a_visit_cannot_be_withdrawn_from_chat(): void
    {
        [, $family] = $this->eligibleFamily();
        $request = $this->request($family, CareRequest::STATUS_OPEN);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        CareBooking::query()->create([
            'care_request_id' => $request->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => now()->addDay(),
            'scheduled_end_at' => now()->addDay()->addHours(2),
        ]);
        $ticket = $this->ticket($family, 'Withdraw request #'.$request->id.'.');
        app(AiSupportRuntimeService::class)->respond($family, $ticket, 'Withdraw request #'.$request->id.'.');
        $action = AiSupportMessageAction::query()->where('action_type', AiSupportMessageAction::TYPE_DOMAIN_RECAP)->sole();

        try {
            app(FamilyLifecycleActionService::class)->confirm($family, $ticket, $action->id);
            $this->fail('Expected a request with a visit to be denied.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('visit', mb_strtolower((string) collect($exception->errors())->flatten()->first()));
        }

        $this->assertSame(CareRequest::STATUS_OPEN, $request->fresh()->status);
        $this->assertDatabaseCount('ai_support_confirmed_action_evidence', 0);
    }

    public function test_permanent_profile_deletion_transfers_without_deleting_anything(): void
    {
        [, $family] = $this->eligibleFamily();
        $profile = app(CareRecipientProfileService::class)->saveDraft($family, null, [
            'preferred_name' => 'Maria',
            'about_them' => 'Likes music.',
        ]);
        $ticket = $this->ticket($family, 'Delete a care profile permanently.');

        app(AiSupportRuntimeService::class)->respond($family, $ticket, 'Delete a care profile permanently.');

        $this->assertTrue($ticket->fresh()->isHumanOnly());
        $this->assertNotNull($profile->fresh());
        $this->assertDatabaseCount('ai_support_confirmed_action_evidence', 0);
    }

    public function test_batch_five_activation_extends_only_the_two_pilot_grants_and_keeps_everyone_off(): void
    {
        [$admin, $first] = $this->eligibleFamily();
        [, $second] = $this->eligibleFamily($admin);
        config(['ai_support.initial_pilot.approved_user_ids' => [$first->id, $second->id]]);
        AiSupportPilotGrant::query()->get()->each(function (AiSupportPilotGrant $grant): void {
            $grant->forceFill(['capability_ids' => collect($grant->capability_ids)
                ->reject(fn (string $id): bool => in_array($id, ['family_lifecycle_action_v1', 'care_request_publish_v1'], true))->values()->all()])->save();
        });

        $this->artisan('ai-support:activate-batch5-pilot', ['--actor-email' => $admin->email])
            ->expectsOutputToContain('One-time request confirmation')
            ->expectsOutputToContain('Recurring request confirmation')
            ->expectsOutputToContain('Batch 5 is active for the existing two-user pilot only')
            ->assertSuccessful();

        $this->assertTrue(AiSupportPilotGrant::query()->get()->every(
            fn (AiSupportPilotGrant $grant): bool => in_array('family_lifecycle_action_v1', $grant->capability_ids, true)
                && in_array('care_request_publish_v1', $grant->capability_ids, true),
        ));
        $controls = app(AiSupportControlService::class);
        $this->assertTrue($controls->enabled('capability.family_lifecycle_action_v1'));
        $this->assertTrue($controls->enabled('tool.care-request.withdraw'));
        $this->assertTrue($controls->enabled('commit.one_time'));
        $this->assertTrue($controls->enabled('commit.recurring'));
        $this->assertTrue($controls->enabled('tool.care-request.publish.one-time'));
        $this->assertTrue($controls->enabled('tool.care-request.publish.recurring'));
        $this->assertFalse($controls->enabled('general_release_enabled'));
    }

    /** @return array{User,User} */
    private function eligibleFamily(?User $admin = null): array
    {
        config([
            'ai_support.runtime_available' => true,
            'ai_support.provider_enabled' => true,
            'services.openai.api_key' => 'test-key',
            'services.stripe.bypass' => true,
        ]);
        $admin ??= User::factory()->create(['role' => 'admin']);
        $controls = app(AiSupportControlService::class);
        foreach ([
            'master_enabled', 'user_visible_enabled', 'role.family',
            'capability.support_answers_v1', 'capability.semantic_navigation_v1',
            'capability.family_context_v1', 'capability.care_intake_v1',
            'capability.care_request_draft_v1', 'capability.care_request_recap_v1',
            'capability.care_request_publish_v1', 'capability.family_lifecycle_action_v1',
            'tool.family-profile.save-draft', 'tool.family-profile.make-ready',
            'tool.family-profile.make-default', 'tool.family-profile.archive',
            'tool.family-profile.restore', 'tool.care-request.withdraw',
            'commit.one_time', 'tool.care-request.publish.one-time',
        ] as $key) {
            if (! $controls->enabled($key)) {
                $controls->set($admin, $key, true, 'Batch 5 Family lifecycle test');
            }
        }
        if ($controls->enabled('human_only')) {
            $controls->set($admin, 'human_only', false, 'Batch 5 Family lifecycle test');
        }
        $family = User::factory()->create(['role' => 'family']);
        app(FamilyAccountContext::class)->account($family);
        app(AiSupportPilotGrantService::class)->grant(
            $admin, $family, 'family_support_v1', CarbonImmutable::now(), CarbonImmutable::now()->addDays(14),
            'Exact-user Batch 5 test', (string) Str::uuid(),
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
            'subject' => 'Batch 5 test', 'description' => $description,
            'initial_client_message_id' => (string) Str::uuid(),
        ]);
    }

    private function request(User $family, string $status): CareRequest
    {
        $request = CareRequest::query()->create([
            'family_account_id' => app(FamilyAccountContext::class)->account($family)->id,
            'family_user_id' => $family->id, 'created_by_user_id' => $family->id,
            'is_system_generated' => false, 'title' => 'Morning help', 'status' => $status,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addDay(), 'requested_end_at' => now()->addDay()->addHours(2),
            'preferred_response_hours' => 12,
            'address_line1' => '123 Main Street', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
        ]);
        $request->recipient()->create([
            'recipient_is_requester' => false,
            'full_name' => 'Maria Example',
            'relationship_to_family' => 'Mother',
        ]);

        return $request;
    }
}
