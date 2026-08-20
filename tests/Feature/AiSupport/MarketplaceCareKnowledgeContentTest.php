<?php

namespace Tests\Feature\AiSupport;

use App\Models\AiSupportControlVersion;
use App\Models\AiSupportPilotGrant;
use App\Models\KnowledgeBaseEntry;
use App\Models\KnowledgeBaseVersion;
use App\Models\User;
use App\Services\AiSupport\MarketplaceCareKnowledgeBaseCatalog;
use App\Services\AiSupport\MarketplaceCareKnowledgeBaseImportService;
use App\Services\AiSupport\MarketplaceCareKnowledgeEvaluationCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceCareKnowledgeContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_has_thirty_two_entries_one_hundred_sixty_cases_and_every_batch_six_seven_intent(): void
    {
        $entries = collect(app(MarketplaceCareKnowledgeBaseCatalog::class)->entries());
        $cases = collect(app(MarketplaceCareKnowledgeEvaluationCatalog::class)->cases());
        $intents = $entries->flatMap(fn (array $entry): array => $entry['intent_ids'])->unique();

        $this->assertCount(32, $entries);
        $this->assertCount(160, $cases);
        $this->assertCount(160, $cases->pluck('id')->unique());
        foreach ([
            'MATCH' => 25,
            'VISIT' => 35,
            'REGULAR' => 26,
        ] as $domain => $count) {
            foreach (range(1, $count) as $number) {
                $this->assertContains(sprintf('FAM-%s-%03d', $domain, $number), $intents);
            }
        }
        foreach ($entries as $entry) {
            $this->assertSame(
                ['boundary', 'handoff', 'positive', 'stale_or_unavailable', 'wrong_account'],
                $cases->where('stable_id', $entry['stable_id'])->pluck('type')->sort()->values()->all(),
            );
        }

        $answers = $entries->pluck('answer_body')->implode("\n");
        $this->assertStringContainsString('$30/hour', $answers);
        $this->assertStringContainsString('$27/hour', $answers);
        $this->assertStringContainsString('$3/hour', $answers);
        $this->assertStringContainsString('not a promise', mb_strtolower($answers));
        $this->assertStringContainsString('human', mb_strtolower($answers));
    }

    public function test_draft_import_is_validated_idempotent_and_does_not_change_availability(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $importer = app(MarketplaceCareKnowledgeBaseImportService::class);

        $this->assertSame(32, $importer->plan()['counts']['creates']);
        $result = $importer->apply($admin);
        $this->assertCount(32, $result['created']);
        $this->assertSame(0, $result['published_count_after']);
        $this->assertSame(32, KnowledgeBaseEntry::query()
            ->whereIn('stable_id', MarketplaceCareKnowledgeBaseCatalog::APPROVED_STABLE_IDS)
            ->whereHas('workingVersion', fn ($query) => $query
                ->where('status', KnowledgeBaseVersion::STATUS_DRAFT)
                ->whereNotNull('validated_at'))
            ->count());

        $again = $importer->apply($admin);
        $this->assertCount(32, $again['noops']);
        $this->assertCount(0, $again['created']);
        $this->assertSame(0, AiSupportPilotGrant::query()->count());
        $this->assertSame(0, AiSupportControlVersion::query()->count());
    }

    public function test_exact_publish_command_publishes_only_batch_six_seven_knowledge(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'batch67-kb@example.com']);

        $this->artisan('ai-support:import-marketplace-care-kb', [
            '--publish' => true,
            '--actor-email' => $admin->email,
            '--reason' => 'Publish approved Batches 6 and 7 marketplace-care knowledge.',
            '--confirm' => 'PUBLISH-MARKETPLACE-CARE-KB',
        ])->expectsOutputToContain('32 entries published')
            ->expectsOutputToContain('pilot users, caregivers, visits, payments, and regular-care plans were not changed')
            ->assertSuccessful();

        $this->assertSame(32, KnowledgeBaseEntry::query()
            ->whereIn('stable_id', MarketplaceCareKnowledgeBaseCatalog::APPROVED_STABLE_IDS)
            ->whereNotNull('published_version_id')->count());
        $this->assertSame(0, AiSupportPilotGrant::query()->count());
        $this->assertSame(0, AiSupportControlVersion::query()->count());
    }
}
