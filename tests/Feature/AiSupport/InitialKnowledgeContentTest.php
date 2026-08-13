<?php

namespace Tests\Feature\AiSupport;

use App\Livewire\Admin\AiSupport\Overview;
use App\Models\AiSupportControlVersion;
use App\Models\AiSupportPilotGrant;
use App\Models\KnowledgeBaseEntry;
use App\Models\KnowledgeBaseVersion;
use App\Models\User;
use App\Services\AiSupport\InitialKnowledgeBaseCatalog;
use App\Services\AiSupport\InitialKnowledgeBaseImportService;
use App\Services\AiSupport\InitialKnowledgeEvaluationCatalog;
use App\Services\AiSupport\InitialKnowledgeEvaluationService;
use App\Services\AiSupport\KnowledgeBaseRetrievalService;
use App\Services\AiSupport\KnowledgeBaseWorkflowService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class InitialKnowledgeContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-08-14 12:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_approved_catalog_contains_exact_inventory_and_sixty_linked_evaluations(): void
    {
        $catalog = app(InitialKnowledgeBaseCatalog::class);
        $evaluations = app(InitialKnowledgeEvaluationCatalog::class);
        $entries = collect($catalog->entries());
        $cases = collect($evaluations->cases());
        $regressions = collect($evaluations->criticalRegressions());

        $this->assertSame(InitialKnowledgeBaseCatalog::APPROVED_STABLE_IDS, $entries->pluck('stable_id')->all());
        $this->assertCount(12, $entries);
        $this->assertCount(60, $cases);
        $this->assertCount(60, $cases->pluck('id')->unique());
        $this->assertCount(10, $regressions);
        $this->assertSame(
            InitialKnowledgeEvaluationCatalog::APPROVED_CRITICAL_REGRESSION_IDS,
            $regressions->pluck('id')->all(),
        );
        $this->assertTrue($regressions->every(fn (array $case): bool => $case['critical'] === true));
        $this->assertSame(
            $entries->flatMap(fn (array $entry): array => $entry['evaluation_ids'])->sort()->values()->all(),
            $cases->pluck('id')->sort()->values()->all(),
        );

        foreach ($entries as $entry) {
            $this->assertSame('en-US', $entry['locale']);
            $this->assertSame('authenticated', $entry['sensitivity']);
            $this->assertSame(['support_answers_v1'], $entry['capability_ids']);
            $this->assertCount(5, $entry['evaluation_ids']);
            $this->assertCount(1, $entry['route_target_ids']);
            $this->assertNotEmpty($entry['sources']);
            foreach ($entry['next_actions'] as $action) {
                $this->assertMatchesRegularExpression('/^(navigate:[a-z0-9._-]+|handoff:SUP-HANDOFF-001|safety:[a-z0-9_]+|clarify:english_only)$/', $action);
            }
        }

        $validation = app(InitialKnowledgeEvaluationService::class)->validate();
        $this->assertTrue($validation['passed'], implode("\n", $validation['errors']));
        $this->assertSame(12, $validation['entry_count']);
        $this->assertSame(60, $validation['entry_case_count']);
        $this->assertSame(10, $validation['regression_case_count']);
        $this->assertSame(70, $validation['case_count']);
    }

    public function test_validation_command_runs_without_model_or_database_mutation(): void
    {
        $this->artisan('ai-support:validate-initial-content')
            ->expectsOutputToContain('Initial AI Support content validation passed.')
            ->assertSuccessful();

        $this->assertDatabaseCount('knowledge_base_entries', 0);
        $this->assertDatabaseCount('ai_support_pilot_grants', 0);
        $this->assertDatabaseCount('ai_support_control_versions', 0);
    }

    public function test_initial_import_is_dry_run_by_default(): void
    {
        $this->artisan('ai-support:import-initial-kb')
            ->expectsOutputToContain('Dry run only.')
            ->assertSuccessful();

        $this->assertDatabaseCount('knowledge_base_entries', 0);
        $this->assertDatabaseCount('knowledge_base_versions', 0);
        $this->assertDatabaseCount('ai_support_admin_audit_events', 0);
    }

    public function test_authorized_apply_creates_validated_drafts_only_and_repeated_apply_is_noop(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'kb-admin@example.com']);

        $this->artisan('ai-support:import-initial-kb', [
            '--apply' => true,
            '--actor-email' => $admin->email,
        ])->expectsOutputToContain('Initial KB Draft import completed.')
            ->assertSuccessful();

        $this->assertDatabaseCount('knowledge_base_entries', 12);
        $this->assertDatabaseCount('knowledge_base_versions', 12);
        $this->assertDatabaseCount(
            'knowledge_base_sources',
            collect(app(InitialKnowledgeBaseCatalog::class)->entries())->sum(
                fn (array $entry): int => count($entry['sources']),
            ),
        );
        $this->assertDatabaseCount('ai_support_pilot_grants', 0);
        $this->assertDatabaseCount('ai_support_control_versions', 0);
        $this->assertSame(0, KnowledgeBaseEntry::query()->whereNotNull('published_version_id')->count());
        $this->assertSame(12, KnowledgeBaseVersion::query()->where('status', KnowledgeBaseVersion::STATUS_DRAFT)->count());
        $this->assertSame(12, KnowledgeBaseVersion::query()->whereNotNull('validated_at')->count());
        $this->assertTrue(
            KnowledgeBaseVersion::query()->get()->every(
                fn (KnowledgeBaseVersion $version): bool => data_get($version->validation_results, 'passed') === true,
            ),
        );

        $family = User::factory()->create(['role' => 'family']);
        $this->assertCount(0, app(KnowledgeBaseRetrievalService::class)->applicable($family, 'support_answers_v1'));

        $auditCount = \App\Models\AiSupportAdminAuditEvent::query()->count();
        $this->artisan('ai-support:import-initial-kb', [
            '--apply' => true,
            '--actor-email' => $admin->email,
        ])->expectsOutputToContain('Identical no-ops')
            ->assertSuccessful();

        $this->assertDatabaseCount('knowledge_base_entries', 12);
        $this->assertDatabaseCount('knowledge_base_versions', 12);
        $this->assertSame($auditCount, \App\Models\AiSupportAdminAuditEvent::query()->count());
    }

    public function test_apply_requires_authorized_actor_and_never_changes_control_or_grant_state(): void
    {
        $family = User::factory()->create(['role' => 'family', 'email' => 'family-actor@example.com']);

        $this->artisan('ai-support:import-initial-kb', ['--apply' => true])
            ->expectsOutputToContain('--actor-email is required')
            ->assertFailed();

        $this->artisan('ai-support:import-initial-kb', [
            '--apply' => true,
            '--actor-email' => $family->email,
        ])->expectsOutputToContain('not an authorized knowledge-base Admin')
            ->assertFailed();

        $this->assertDatabaseCount('knowledge_base_entries', 0);
        $this->assertSame(0, AiSupportPilotGrant::query()->count());
        $this->assertSame(0, AiSupportControlVersion::query()->count());
    }

    public function test_existing_different_draft_or_tombstone_causes_fail_closed_conflict(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $importer = app(InitialKnowledgeBaseImportService::class);
        $importer->apply($admin);

        $entry = KnowledgeBaseEntry::query()->where('stable_id', 'KB-FAM-001')->firstOrFail();
        $entry->workingVersion->forceFill(['title' => 'Operator changed this Draft'])->save();

        $plan = $importer->plan();
        $this->assertSame(1, $plan['counts']['conflicts']);
        $this->assertSame('existing_draft_differs_from_manifest', $plan['conflicts'][0]['reason']);

        try {
            $importer->apply($admin);
            $this->fail('A differing Admin draft must block the import.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('conflict', $exception->getMessage());
        }

        $this->assertSame('Operator changed this Draft', $entry->workingVersion->fresh()->title);

        $entry->forceFill(['deleted_at' => now(), 'deletion_reason' => 'Test tombstone'])->save();
        $plan = $importer->plan();
        $this->assertContains('stable_id_is_deleted_or_tombstoned', collect($plan['conflicts'])->pluck('reason')->all());
    }

    public function test_workflow_can_create_an_approved_stable_id_once_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $catalog = app(InitialKnowledgeBaseCatalog::class);
        $definition = $catalog->entry('KB-FAM-001');
        $workflow = app(KnowledgeBaseWorkflowService::class);

        $entry = $workflow->createDraftWithStableId(
            $admin,
            'KB-FAM-001',
            $catalog->payload($definition),
            $catalog->sources($definition),
        );
        $this->assertSame('KB-FAM-001', $entry->stable_id);

        $this->expectException(ValidationException::class);
        $workflow->createDraftWithStableId(
            $admin,
            'KB-FAM-001',
            $catalog->payload($definition),
            $catalog->sources($definition),
        );
    }

    public function test_admin_overview_reports_actual_knowledge_counts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        app(InitialKnowledgeBaseImportService::class)->apply($admin);

        Livewire::actingAs($admin)
            ->test(Overview::class)
            ->assertSee('12 working')
            ->assertSee('12 Draft')
            ->assertSee('0 published')
            ->assertSee('0 paused')
            ->assertSee('0 overdue')
            ->assertDontSee('Foundation pending');
    }
}
