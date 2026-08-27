<?php

namespace Tests\Feature\AiSupport;

use App\Models\AiSupportControlVersion;
use App\Models\AiSupportPilotGrant;
use App\Models\KnowledgeBaseEntry;
use App\Models\KnowledgeBaseVersion;
use App\Models\User;
use App\Services\AiSupport\FamilyOperationsKnowledgeBaseCatalog;
use App\Services\AiSupport\FamilyOperationsKnowledgeBaseImportService;
use App\Services\AiSupport\FamilyOperationsKnowledgeEvaluationCatalog;
use App\Services\AiSupport\InitialKnowledgeBaseImportService;
use App\Services\AiSupport\KnowledgeBaseWorkflowService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FamilyOperationsKnowledgeContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-08-18 12:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_catalog_has_fifty_five_entries_one_revision_and_280_linked_evaluations(): void
    {
        $catalog = app(FamilyOperationsKnowledgeBaseCatalog::class);
        $definitions = collect($catalog->allDefinitions());
        $cases = collect(app(FamilyOperationsKnowledgeEvaluationCatalog::class)->cases());
        $evaluationIds = $definitions->flatMap(fn (array $definition): array => $definition['evaluation_ids']);
        $registry = file_get_contents(base_path('docs/product/support-agent/38-family-intent-action-coverage-registry.md'));
        preg_match_all('/FAM-[A-Z]+-[0-9]{3}/', (string) $registry, $matches);
        $knownIntents = collect($matches[0])->unique();

        $this->assertCount(55, $catalog->entries());
        $this->assertCount(1, $catalog->revisions());
        $this->assertCount(280, $evaluationIds);
        $this->assertCount(280, $evaluationIds->unique());
        $this->assertCount(280, $cases);
        $this->assertEqualsCanonicalizing($evaluationIds->all(), $cases->pluck('id')->all());
        $this->assertTrue($definitions->every(fn (array $definition): bool => $definition['roles'] === ['family']));
        $this->assertTrue($definitions->flatMap(fn (array $definition): array => $definition['intent_ids'])
            ->every(fn (string $intentId): bool => $knownIntents->contains($intentId)));
        $this->assertTrue($definitions->flatMap(fn (array $definition): array => $definition['intent_ids'])->contains('FAM-PAY-028'));
        $this->assertTrue($definitions->flatMap(fn (array $definition): array => $definition['intent_ids'])->contains('FAM-PAY-029'));

        foreach ($definitions as $definition) {
            $this->assertSame(
                ['boundary', 'handoff', 'positive', 'unsupported_state', 'wrong_role'],
                $cases->where('stable_id', $definition['stable_id'])->pluck('type')->sort()->values()->all(),
            );
        }
    }

    public function test_apply_creates_validated_drafts_and_a_corrective_revision_without_publishing(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->publishLegacyFamilyAccess($admin);
        $importer = app(FamilyOperationsKnowledgeBaseImportService::class);

        $plan = $importer->plan();
        $this->assertSame(55, $plan['counts']['creates']);
        $this->assertSame(1, $plan['counts']['revisions']);
        $this->assertSame(0, $plan['counts']['conflicts']);

        $result = $importer->apply($admin);
        $this->assertCount(55, $result['created']);
        $this->assertCount(1, $result['revised']);
        $this->assertSame(1, $result['published_count_after']);
        $this->assertSame(56, KnowledgeBaseEntry::query()
            ->whereIn('stable_id', array_merge(
                FamilyOperationsKnowledgeBaseCatalog::APPROVED_STABLE_IDS,
                FamilyOperationsKnowledgeBaseCatalog::REVISION_STABLE_IDS,
            ))
            ->whereHas('workingVersion', fn ($query) => $query
                ->where('status', KnowledgeBaseVersion::STATUS_DRAFT)
                ->whereNotNull('validated_at'))
            ->count());

        $familyAccess = KnowledgeBaseEntry::query()->where('stable_id', 'KB-FAM-004')->firstOrFail();
        $this->assertSame(1, $familyAccess->publishedVersion->version_number);
        $this->assertSame(2, $familyAccess->workingVersion->version_number);
        $this->assertStringContainsString('Active Family members', $familyAccess->workingVersion->answer_body);

        $again = $importer->apply($admin);
        $this->assertCount(56, $again['noops']);
        $this->assertCount(0, $again['created']);
        $this->assertCount(0, $again['revised']);
    }

    public function test_exact_publish_command_publishes_package_without_changing_pilot_or_availability(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'kb-operator@example.com']);
        $this->publishLegacyFamilyAccess($admin);

        $this->artisan('ai-support:import-family-operations-kb', [
            '--publish' => true,
            '--actor-email' => $admin->email,
            '--reason' => 'Publish approved Family Operations KB Wave 1.',
            '--confirm' => 'PUBLISH-FAMILY-OPERATIONS-KB',
        ])->expectsOutputToContain('56 entries published')
            ->expectsOutputToContain('two-user pilot boundary were not changed')
            ->assertSuccessful();

        $this->assertSame(56, KnowledgeBaseEntry::query()
            ->whereIn('stable_id', array_merge(
                FamilyOperationsKnowledgeBaseCatalog::APPROVED_STABLE_IDS,
                FamilyOperationsKnowledgeBaseCatalog::REVISION_STABLE_IDS,
            ))
            ->whereNotNull('published_version_id')->count());
        $this->assertSame(2, KnowledgeBaseEntry::query()->where('stable_id', 'KB-FAM-004')->firstOrFail()->publishedVersion->version_number);
        $this->assertSame(0, AiSupportPilotGrant::query()->count());
        $this->assertSame(0, AiSupportControlVersion::query()->count());
    }

    private function publishLegacyFamilyAccess(User $admin): void
    {
        app(InitialKnowledgeBaseImportService::class)->apply($admin);
        $entry = KnowledgeBaseEntry::query()->where('stable_id', 'KB-FAM-004')->firstOrFail();
        $version = $entry->workingVersion;
        $version->forceFill([
            'answer_body' => 'Legacy guidance: only the Account owner can change the saved Family payment method.',
        ])->save();
        $workflow = app(KnowledgeBaseWorkflowService::class);
        $version = $workflow->submitForReview($admin, $version);
        $version = $workflow->approve($admin, $version);
        $workflow->publish($admin, $version, 'Publish legacy test fixture.');
    }
}
