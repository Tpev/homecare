<?php

namespace Tests\Feature\AiSupport;

use App\Livewire\Admin\AiSupport\Overview;
use App\Models\AiSupportReadinessEvidence;
use App\Models\KnowledgeBaseEntry;
use App\Models\KnowledgeBaseVersion;
use App\Models\User;
use App\Services\AiSupport\AiSupportReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class OperationalEvidenceOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_historical_pass_exposes_tested_identity_and_age_without_becoming_a_current_pass(): void
    {
        AiSupportReadinessEvidence::query()->create([
            'id' => (string) Str::uuid(), 'evidence_key' => 'staff_rehearsal', 'version' => 1,
            'status' => 'passed', 'summary' => 'Historical synthetic rehearsal.',
            'safe_metadata' => ['model' => 'historical-model', 'prompt_version' => 'interactive-support-v4', 'result_hash' => str_repeat('a', 64)],
            'observed_at' => now()->subDays(28), 'retain_until' => now()->addYear(), 'created_at' => now()->subDays(28),
        ]);
        $evidence = app(AiSupportReadinessService::class)->operationalEvidence();

        $this->assertSame('historical-model', $evidence['historical_records'][0]['model']);
        $this->assertSame('interactive-support-v4', $evidence['historical_records'][0]['prompt_version']);
        $this->assertSame(28, $evidence['historical_records'][0]['age_days']);
        $this->assertStringContainsString('current behavior not established', $evidence['historical_records'][0]['scope']);
        $this->assertNull($evidence['deterministic_validation']);
    }

    public function test_regression_report_matches_only_exact_source_config_and_published_knowledge(): void
    {
        $service = app(AiSupportReadinessService::class);
        $identity = $service->currentEvidenceIdentity();
        Storage::disk('local')->put('ai-support/evaluations/current-validation.json', json_encode([
            ...$identity, 'kind' => 'deterministic_regression', 'observed_at' => now()->toIso8601String(),
            'passed' => true, 'tests' => 15, 'assertions' => 95,
        ], JSON_THROW_ON_ERROR));
        $this->assertSame('Matches current source, configuration, and published knowledge', $service->operationalEvidence()['deterministic_validation']['scope']);

        $entry = KnowledgeBaseEntry::query()->create(['stable_id' => 'KB-TEST-OPS', 'ever_released' => true]);
        $version = KnowledgeBaseVersion::query()->create([
            'knowledge_base_entry_id' => $entry->id, 'version_number' => 1, 'status' => 'published',
            'type' => 'navigation', 'title' => 'Synthetic help', 'answer_body' => 'Synthetic navigation answer.',
            'sensitivity' => 'authenticated', 'product_area' => 'support', 'locale' => 'en-US',
            'roles' => ['family'], 'capability_ids' => [], 'evaluation_ids' => [],
            'change_note' => 'Synthetic identity fixture', 'review_by' => today()->addMonth(), 'published_at' => now(),
        ]);
        $entry->update(['published_version_id' => $version->id]);
        $withKnowledge = $service->currentEvidenceIdentity();
        $this->assertNotSame($identity['knowledge_hash'], $withKnowledge['knowledge_hash']);
        $this->assertStringContainsString('Historical regression', $service->operationalEvidence()['deterministic_validation']['scope']);

        $version->forceFill(['updated_at' => now()->addMinute()])->save();
        $this->assertSame($withKnowledge['knowledge_hash'], $service->currentEvidenceIdentity()['knowledge_hash']);
        $version->forceFill(['answer_body' => 'A changed synthetic answer.'])->save();
        $this->assertNotSame($withKnowledge['knowledge_hash'], $service->currentEvidenceIdentity()['knowledge_hash']);
        config(['ai_support.prompt_schema_version' => 'changed-prompt']);
        $this->assertNotSame($identity['configuration_hash'], $service->currentEvidenceIdentity()['configuration_hash']);
    }

    public function test_admin_overview_exposes_missing_evidence_and_actionable_delivery_filters(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Livewire::actingAs($admin)->test(Overview::class)
            ->assertSee('No current deterministic regression report')
            ->assertSee('Declared delivery paths, not measured customer success')
            ->assertSee('no successful run observed in the last 10 minutes')
            ->set('intentDisposition', 'human')
            ->assertViewHas('intentRecords', fn ($records): bool => $records->isNotEmpty() && $records->every(fn ($record): bool => $record['delivery'] === 'human'))
            ->set('intentNeedsKnowledge', true)
            ->assertViewHas('intentRecords', fn ($records): bool => $records->every(fn ($record): bool => $record['missing_knowledge'] !== []));
    }
}
