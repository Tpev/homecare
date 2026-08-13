<?php

namespace Tests\Feature\AiSupport;

use App\Livewire\Admin\AiSupport\KnowledgeEditor;
use App\Models\KnowledgeBaseEntry;
use App\Models\KnowledgeBaseVersion;
use App\Models\KnowledgeBaseVersionDependency;
use App\Models\User;
use App\Services\AiSupport\KnowledgeBaseRetrievalService;
use App\Services\AiSupport\KnowledgeBaseWorkflowService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class KnowledgeBaseGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_admin_can_create_validate_self_review_approve_and_publish_now(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $workflow = app(KnowledgeBaseWorkflowService::class);
        $entry = $workflow->createDraft($admin, $this->payload(), $this->sources());

        $this->assertStringStartsWith('KB-', $entry->stable_id);
        $this->assertSame(KnowledgeBaseVersion::STATUS_DRAFT, $entry->workingVersion->status);
        $this->assertDatabaseHas('ai_support_admin_audit_events', ['action' => 'knowledge_entry_created']);

        $validation = $workflow->validateAndStore($admin, $entry->workingVersion);
        $this->assertTrue($validation['passed']);
        $review = $workflow->submitForReview($admin, $entry->workingVersion->fresh());
        $approved = $workflow->approve($admin, $review);
        $published = $workflow->publish($admin, $approved, 'Approved source and evaluation verified');

        $this->assertSame(KnowledgeBaseVersion::STATUS_PUBLISHED, $published->status);
        $this->assertSame($admin->id, $published->authored_by_user_id);
        $this->assertSame($admin->id, $published->reviewed_by_user_id);
        $this->assertSame($admin->id, $published->approved_by_user_id);
        $this->assertSame($admin->id, $published->published_by_user_id);
        $this->assertTrue($entry->fresh()->ever_released);
        $this->assertSame($published->id, $entry->fresh()->published_version_id);
        $this->assertDatabaseHas('ai_support_admin_audit_events', [
            'action' => 'knowledge_published',
            'subject_id' => $entry->stable_id,
            'result' => 'succeeded',
        ]);
    }

    public function test_only_current_published_applicable_version_is_retrievable(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $workflow = app(KnowledgeBaseWorkflowService::class);
        $entry = $this->publishedEntry($admin, $workflow);
        $retrieval = app(KnowledgeBaseRetrievalService::class);

        $familyResults = $retrieval->applicable($family, 'support_answers_v1', 'active', 'support.center');
        $this->assertCount(1, $familyResults);
        $this->assertSame($entry->published_version_id, $familyResults->first()->id);
        $this->assertCount(0, $retrieval->applicable($caregiver, 'support_answers_v1'));
        $this->assertCount(0, $retrieval->applicable($family, 'unreleased_capability'));

        $workflow->pause($admin, $entry->publishedVersion, 'Urgent source revalidation');
        $this->assertCount(0, $retrieval->applicable($family, 'support_answers_v1'));
        $workflow->resume($admin, $entry->publishedVersion->fresh(), 'Source revalidated and current');
        $this->assertCount(1, $retrieval->applicable($family, 'support_answers_v1'));

        $draft = $workflow->createDraftFrom($admin, $entry->publishedVersion->fresh(), 'Clarify support wording');
        $this->assertSame(KnowledgeBaseVersion::STATUS_DRAFT, $draft->status);
        $this->assertCount(1, $retrieval->applicable($family, 'support_answers_v1'));
        $this->assertSame(1, $retrieval->applicable($family, 'support_answers_v1')->first()->version_number);
    }

    public function test_publishing_new_version_supersedes_old_version_and_preserves_retention(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $workflow = app(KnowledgeBaseWorkflowService::class);
        $entry = $this->publishedEntry($admin, $workflow);
        $old = $entry->publishedVersion;
        $draft = $workflow->createDraftFrom($admin, $old, 'Improve plain-language instruction');
        $payload = $this->payload(['title' => 'Updated support answer', 'change_note' => 'Improve plain-language instruction']);
        $draft = $workflow->updateWorkingVersion($admin, $draft, 1, $payload, $this->sources());
        $draft = $workflow->submitForReview($admin, $draft);
        $draft = $workflow->approve($admin, $draft);
        $new = $workflow->publish($admin, $draft, 'Replacement validated and approved');

        $old->refresh();
        $this->assertSame(KnowledgeBaseVersion::STATUS_SUPERSEDED, $old->status);
        $this->assertSame($new->id, $old->replaced_by_version_id);
        $this->assertNotNull($old->full_content_retain_until);
        $this->assertSame($new->id, $entry->fresh()->published_version_id);
    }

    public function test_validation_blocks_unknown_route_missing_source_and_missing_evaluation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $payload = $this->payload([
            'route_target_ids' => ['arbitrary.dom.selector'],
            'evaluation_ids' => [],
        ]);
        $entry = app(KnowledgeBaseWorkflowService::class)->createDraft($admin, $payload, []);
        $result = app(KnowledgeBaseWorkflowService::class)->validateAndStore($admin, $entry->workingVersion);

        $this->assertFalse($result['passed']);
        $this->assertArrayHasKey('route_target_ids', $result['errors']);
        $this->assertArrayHasKey('evaluation_ids', $result['errors']);
        $this->assertArrayHasKey('sources', $result['errors']);

        $this->expectException(ValidationException::class);
        app(KnowledgeBaseWorkflowService::class)->submitForReview($admin, $entry->workingVersion->fresh());
    }

    public function test_stale_edit_is_rejected_without_overwriting_newer_draft(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $workflow = app(KnowledgeBaseWorkflowService::class);
        $entry = $workflow->createDraft($admin, $this->payload(), $this->sources());
        $version = $entry->workingVersion;
        $workflow->updateWorkingVersion($admin, $version, 1, $this->payload(['title' => 'Newer title']), $this->sources());

        try {
            $workflow->updateWorkingVersion($admin, $version, 1, $this->payload(['title' => 'Stale title']), $this->sources());
            $this->fail('Expected a stale edit conflict.');
        } catch (ValidationException $exception) {
            $this->assertSame('Newer title', $version->fresh()->title);
            $this->assertArrayHasKey('version', $exception->errors());
        }
    }

    public function test_unreleased_draft_can_be_hard_deleted_but_dependency_blocks_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $workflow = app(KnowledgeBaseWorkflowService::class);
        $entry = $workflow->createDraft($admin, $this->payload(), $this->sources());
        KnowledgeBaseVersionDependency::query()->create([
            'knowledge_base_version_id' => $entry->workingVersion->id,
            'dependency_type' => 'evaluation',
            'dependency_id' => 'EVAL-PROTECTED-001',
            'created_at' => now(),
        ]);

        try {
            $workflow->delete($admin, $entry->workingVersion, 'Draft no longer needed');
            $this->fail('Expected protected dependency failure.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('knowledge_base_versions', ['id' => $entry->workingVersion->id]);
        }

        $entry->workingVersion->dependencies()->delete();
        $workflow->delete($admin, $entry->workingVersion->fresh(), 'Draft no longer needed');
        $this->assertDatabaseMissing('knowledge_base_versions', ['id' => $entry->working_version_id]);
        $this->assertNotNull($entry->fresh()->deleted_at);
        $this->assertDatabaseHas('ai_support_admin_audit_events', ['action' => 'knowledge_draft_deleted']);
    }

    public function test_released_entry_delete_withdraws_content_and_preserves_tombstone_schedule(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $workflow = app(KnowledgeBaseWorkflowService::class);
        $entry = $this->publishedEntry($admin, $workflow);
        $stableId = $entry->stable_id;
        $versionId = $entry->published_version_id;

        $workflow->delete($admin, $entry->publishedVersion, 'Product instruction permanently retired');

        $entry->refresh();
        $this->assertSame($stableId, $entry->stable_id);
        $this->assertNotNull($entry->deleted_at);
        $this->assertNull($entry->published_version_id);
        $this->assertDatabaseHas('knowledge_base_versions', [
            'id' => $versionId,
            'status' => KnowledgeBaseVersion::STATUS_DELETED,
        ]);
        $this->assertNotNull(KnowledgeBaseVersion::query()->findOrFail($versionId)->full_content_retain_until);
    }

    public function test_non_admin_cannot_manage_kb_and_audit_failure_rolls_back_create(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $this->actingAs($family)->get(route('admin.ai-support.knowledge.index'))->assertForbidden();

        try {
            app(KnowledgeBaseWorkflowService::class)->createDraft($family, $this->payload(), $this->sources());
            $this->fail('Expected authorization failure.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('knowledge_base_entries', 0);
        }

        $admin = User::factory()->create(['role' => 'admin']);
        DB::statement('DROP TABLE ai_support_admin_audit_events');
        try {
            app(KnowledgeBaseWorkflowService::class)->createDraft($admin, $this->payload(), $this->sources());
            $this->fail('Expected audit storage failure.');
        } catch (\Throwable) {
            $this->assertDatabaseCount('knowledge_base_entries', 0);
            $this->assertDatabaseCount('knowledge_base_versions', 0);
        }
    }

    public function test_admin_ui_can_create_draft_and_lists_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $component = Livewire::actingAs($admin)
            ->test(KnowledgeEditor::class)
            ->set('title', 'How to open support')
            ->set('answerBody', 'Open the Support Center from the account navigation to contact LoLo Support.')
            ->set('changeNote', 'Initial approved support navigation wording')
            ->set('evaluationIdsText', 'EVAL-SUPPORT-NAV-001')
            ->set('routeTargetIds', ['support.center'])
            ->set('sources.0.source_id', 'SRC-SUPPORT-CHAT-001')
            ->set('sources.0.title', 'Support live-chat specification')
            ->set('sources.0.fact_supported', 'The Support Center is the canonical human support route.')
            ->call('save')
            ->assertHasNoErrors();

        $entry = KnowledgeBaseEntry::query()->sole();
        $component->assertRedirect(route('admin.ai-support.knowledge.edit', $entry));
        $this->actingAs($admin)->get(route('admin.ai-support.knowledge.index'))
            ->assertOk()
            ->assertSee($entry->stable_id)
            ->assertSee('How to open support');
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'type' => 'product_fact',
            'title' => 'How to contact LoLo Support',
            'answer_body' => 'Use the Support Center to contact LoLo Support. Your existing conversation remains available there.',
            'sensitivity' => 'authenticated',
            'product_area' => 'support',
            'locale' => 'en-US',
            'roles' => ['family'],
            'membership_states' => ['active'],
            'route_target_ids' => ['support.center'],
            'capability_ids' => ['support_answers_v1'],
            'facts_may_state' => ['The Support Center contains the canonical support conversation.'],
            'facts_must_not_infer' => ['Do not promise a response time.'],
            'next_actions' => ['support.center'],
            'escalation_conditions' => ['User asks for a person.'],
            'retrieval_examples_match' => ['How do I contact support?'],
            'retrieval_examples_no_match' => ['Call emergency services.'],
            'evaluation_ids' => ['EVAL-SUPPORT-ANSWER-001'],
            'change_note' => 'Initial authoritative support answer',
            'review_by' => today()->addMonths(3)->format('Y-m-d'),
            'expires_on' => null,
        ], $overrides);
    }

    /** @return list<array<string, string|null>> */
    private function sources(): array
    {
        return [[
            'source_id' => 'SRC-SUPPORT-CHAT-001',
            'title' => 'Support live-chat specification',
            'url' => null,
            'section_anchor' => 'Product decision',
            'fact_supported' => 'The existing Support Center remains the canonical support conversation.',
        ]];
    }

    private function publishedEntry(User $admin, KnowledgeBaseWorkflowService $workflow): KnowledgeBaseEntry
    {
        $entry = $workflow->createDraft($admin, $this->payload(), $this->sources());
        $review = $workflow->submitForReview($admin, $entry->workingVersion);
        $approved = $workflow->approve($admin, $review);
        $workflow->publish($admin, $approved, 'Source, role, and evaluation evidence accepted');

        return $entry->fresh(['publishedVersion.sources', 'workingVersion']);
    }
}
