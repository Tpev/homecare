<?php

namespace Tests\Feature\AiSupport;

use App\Models\AiSupportControlVersion;
use App\Models\AiSupportPilotGrant;
use App\Models\KnowledgeBaseEntry;
use App\Models\KnowledgeBaseVersion;
use App\Models\User;
use App\Services\AiSupport\FamilyAdministrationKnowledgeBaseCatalog;
use App\Services\AiSupport\FamilyAdministrationKnowledgeBaseImportService;
use App\Services\AiSupport\FamilyAdministrationKnowledgeEvaluationCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FamilyAdministrationKnowledgeContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_has_forty_four_entries_two_hundred_twenty_cases_and_complete_batch_eight_nine_coverage(): void
    {
        $entries = collect(app(FamilyAdministrationKnowledgeBaseCatalog::class)->entries());
        $cases = collect(app(FamilyAdministrationKnowledgeEvaluationCatalog::class)->cases());
        $intents = $entries->flatMap(fn (array $entry): array => $entry['intent_ids'])->unique();

        $this->assertCount(44, $entries);
        $this->assertCount(220, $cases);
        $this->assertCount(220, $cases->pluck('id')->unique());
        foreach (['START' => 17, 'ACCOUNT' => 20, 'ACCESS' => 20, 'COMMS' => 17, 'HISTORY' => 15, 'COVERAGE' => 26, 'SUPPORT' => 20] as $domain => $count) {
            foreach (range(1, $count) as $number) {
                $this->assertContains(sprintf('FAM-%s-%03d', $domain, $number), $intents);
            }
        }
        foreach ($entries as $entry) {
            $this->assertSame(['boundary', 'handoff', 'positive', 'stale_or_unavailable', 'wrong_account'],
                $cases->where('stable_id', $entry['stable_id'])->pluck('type')->sort()->values()->all());
        }
        $answers = mb_strtolower($entries->pluck('answer_body')->implode("\n"));
        $this->assertStringContainsString('queue position', $answers);
        $this->assertStringContainsString('does not', $answers);
        $this->assertStringContainsString('human-owned', $answers);
        $this->assertStringContainsString('another family', $answers);
    }

    public function test_import_is_validated_idempotent_and_does_not_change_runtime_or_pilot_state(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $importer = app(FamilyAdministrationKnowledgeBaseImportService::class);

        $this->assertSame(44, $importer->plan()['counts']['creates']);
        $result = $importer->apply($admin);
        $this->assertCount(44, $result['created']);
        $this->assertSame(44, KnowledgeBaseEntry::query()->whereIn('stable_id', FamilyAdministrationKnowledgeBaseCatalog::APPROVED_STABLE_IDS)
            ->whereHas('workingVersion', fn ($query) => $query->where('status', KnowledgeBaseVersion::STATUS_DRAFT)->whereNotNull('validated_at'))->count());
        $again = $importer->apply($admin);
        $this->assertCount(44, $again['noops']);
        $this->assertCount(0, $again['created']);
        $this->assertSame(0, AiSupportPilotGrant::query()->count());
        $this->assertSame(0, AiSupportControlVersion::query()->count());
    }

    public function test_exact_publish_command_publishes_only_the_approved_package(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'batch89-kb@example.com']);
        $this->artisan('ai-support:import-family-administration-kb', [
            '--publish' => true, '--actor-email' => $admin->email,
            '--reason' => 'Publish approved Batches 8 and 9 Family administration support knowledge.',
            '--confirm' => 'PUBLISH-FAMILY-ADMINISTRATION-SUPPORT-KB',
        ])->expectsOutputToContain('44 entries published')->assertSuccessful();

        $this->assertSame(44, KnowledgeBaseEntry::query()->whereIn('stable_id', FamilyAdministrationKnowledgeBaseCatalog::APPROVED_STABLE_IDS)
            ->whereNotNull('published_version_id')->count());
        $this->assertSame(0, AiSupportPilotGrant::query()->count());
        $this->assertSame(0, AiSupportControlVersion::query()->count());
    }
}
