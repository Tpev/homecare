<?php

namespace Tests\Feature\AiSupport;

use App\Models\KnowledgeBaseEntry;
use App\Models\KnowledgeBaseVersion;
use App\Models\User;
use App\Services\AiSupport\InteractiveKnowledgeBaseCatalog;
use App\Services\AiSupport\InteractiveKnowledgeBaseImportService;
use App\Services\AiSupport\InteractiveKnowledgeEvaluationCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InteractiveKnowledgeContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_interactive_catalog_has_twelve_entries_sixty_unique_evaluations_and_current_pricing(): void
    {
        $catalog = app(InteractiveKnowledgeBaseCatalog::class);
        $entries = collect($catalog->entries());
        $evaluations = $entries->flatMap(fn (array $entry): array => $entry['evaluation_ids']);

        $this->assertSame(InteractiveKnowledgeBaseCatalog::APPROVED_STABLE_IDS, $entries->pluck('stable_id')->all());
        $this->assertCount(60, $evaluations);
        $this->assertCount(60, $evaluations->unique());
        $pricing = $entries->firstWhere('stable_id', 'KB-CARE-006');
        $this->assertSame(['support_answers_v1'], $pricing['capability_ids']);
        $this->assertStringContainsString('$30 per worked hour', $pricing['answer_body']);
        $this->assertStringContainsString('$3 per worked hour', $pricing['answer_body']);
        $this->assertTrue($entries->every(fn (array $entry): bool => count($entry['sources']) >= 1));
        $cases = collect(app(InteractiveKnowledgeEvaluationCatalog::class)->cases());
        $this->assertCount(60, $cases);
        $this->assertEqualsCanonicalizing($evaluations->all(), $cases->pluck('id')->all());
        foreach (InteractiveKnowledgeBaseCatalog::APPROVED_STABLE_IDS as $stableId) {
            $this->assertSame(
                ['boundary', 'handoff', 'positive', 'unsupported_state', 'wrong_role'],
                $cases->where('stable_id', $stableId)->pluck('type')->sort()->values()->all(),
            );
        }
    }

    public function test_interactive_import_is_dry_run_then_creates_validated_drafts_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $import = app(InteractiveKnowledgeBaseImportService::class);

        $plan = $import->plan();
        $this->assertSame(12, $plan['counts']['creates']);
        $this->assertDatabaseCount('knowledge_base_entries', 0);

        $result = $import->apply($admin);
        $this->assertCount(12, $result['created']);
        $this->assertSame(0, $result['published_count_after']);
        $this->assertDatabaseCount('knowledge_base_entries', 12);
        $this->assertSame(12, KnowledgeBaseVersion::query()
            ->where('status', KnowledgeBaseVersion::STATUS_DRAFT)
            ->whereNotNull('validated_at')->count());
        $this->assertSame(0, KnowledgeBaseEntry::query()->whereNotNull('published_version_id')->count());

        $again = $import->apply($admin);
        $this->assertCount(12, $again['noops']);
        $this->assertDatabaseCount('knowledge_base_entries', 12);
    }
}
