<?php

namespace Tests\Feature\AiSupport;

use App\Models\AiSupportControlVersion;
use App\Models\AiSupportPilotGrant;
use App\Models\KnowledgeBaseEntry;
use App\Models\KnowledgeBaseVersion;
use App\Models\User;
use App\Services\AiSupport\ProfileRequestKnowledgeBaseCatalog;
use App\Services\AiSupport\ProfileRequestKnowledgeBaseImportService;
use App\Services\AiSupport\ProfileRequestKnowledgeEvaluationCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileRequestKnowledgeContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_has_twenty_entries_one_hundred_cases_and_all_profile_request_intents(): void
    {
        $entries = collect(app(ProfileRequestKnowledgeBaseCatalog::class)->entries());
        $cases = collect(app(ProfileRequestKnowledgeEvaluationCatalog::class)->cases());
        $intents = $entries->flatMap(fn (array $entry): array => $entry['intent_ids'])->unique();

        $this->assertCount(20, $entries);
        $this->assertCount(100, $cases);
        $this->assertCount(100, $cases->pluck('id')->unique());
        foreach (range(1, 26) as $number) {
            $this->assertContains(sprintf('FAM-PROFILE-%03d', $number), $intents);
        }
        foreach (range(1, 45) as $number) {
            $this->assertContains(sprintf('FAM-REQUEST-%03d', $number), $intents);
        }
        foreach ($entries as $entry) {
            $this->assertSame(
                ['boundary', 'handoff', 'positive', 'stale_or_unavailable', 'wrong_account'],
                $cases->where('stable_id', $entry['stable_id'])->pluck('type')->sort()->values()->all(),
            );
        }

        $boundaries = $entries->pluck('answer_body')->implode("\n");
        $this->assertStringContainsString('not reopened in place', $boundaries);
        $this->assertStringContainsString('source request is never changed', $boundaries);
        $this->assertStringContainsString('human', mb_strtolower($boundaries));
        $this->assertStringContainsString('24/7', $boundaries);
    }

    public function test_import_is_idempotent_validated_and_does_not_change_availability(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $importer = app(ProfileRequestKnowledgeBaseImportService::class);

        $this->assertSame(20, $importer->plan()['counts']['creates']);
        $result = $importer->apply($admin);
        $this->assertCount(20, $result['created']);
        $this->assertSame(0, $result['published_count_after']);
        $this->assertSame(20, KnowledgeBaseEntry::query()
            ->whereIn('stable_id', ProfileRequestKnowledgeBaseCatalog::APPROVED_STABLE_IDS)
            ->whereHas('workingVersion', fn ($query) => $query
                ->where('status', KnowledgeBaseVersion::STATUS_DRAFT)
                ->whereNotNull('validated_at'))
            ->count());

        $again = $importer->apply($admin);
        $this->assertCount(20, $again['noops']);
        $this->assertCount(0, $again['created']);
        $this->assertSame(0, AiSupportPilotGrant::query()->count());
        $this->assertSame(0, AiSupportControlVersion::query()->count());
    }

    public function test_exact_publish_command_publishes_only_batch_five_knowledge(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'batch5-kb@example.com']);

        $this->artisan('ai-support:import-profile-request-kb', [
            '--publish' => true,
            '--actor-email' => $admin->email,
            '--reason' => 'Publish approved Batch 5 profile and request knowledge.',
            '--confirm' => 'PUBLISH-PROFILE-REQUEST-KB',
        ])->expectsOutputToContain('20 entries published')
            ->expectsOutputToContain('care profiles, and care requests were not changed')
            ->assertSuccessful();

        $this->assertSame(20, KnowledgeBaseEntry::query()
            ->whereIn('stable_id', ProfileRequestKnowledgeBaseCatalog::APPROVED_STABLE_IDS)
            ->whereNotNull('published_version_id')->count());
        $this->assertSame(0, AiSupportPilotGrant::query()->count());
        $this->assertSame(0, AiSupportControlVersion::query()->count());
    }
}
