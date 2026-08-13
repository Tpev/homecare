<?php

namespace Tests\Feature\AiSupport;

use App\Services\AiSupport\AiSupportModelCandidateCatalog;
use App\Services\AiSupport\OfflineAiSupportModelEvaluationService;
use App\Services\AiSupport\OfflineOpenAiResponsesClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class OfflineModelEvaluationTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidate_manifest_and_full_plan_are_versioned_and_repeat_every_critical_case(): void
    {
        $manifest = app(AiSupportModelCandidateCatalog::class)->manifest();
        $plan = app(OfflineAiSupportModelEvaluationService::class)->plan();

        $this->assertSame('ai-support-model-candidates-v1', $manifest['version']);
        $this->assertSame('2026-08-14', $manifest['pricing_checked_on']);
        $this->assertSame(
            ['gpt-5-nano-low', 'gpt-5.6-luna-low', 'gpt-5.4-mini-low'],
            array_column($manifest['candidates'], 'id'),
        );
        $this->assertFalse($manifest['candidates'][0]['baseline_eligible']);
        $this->assertTrue($manifest['candidates'][1]['baseline_eligible']);
        $this->assertTrue($manifest['candidates'][2]['baseline_eligible']);
        $this->assertSame(70, $plan['case_count']);
        $this->assertSame(52, $plan['critical_case_count']);
        $this->assertSame(5, $plan['critical_runs']);
        $this->assertSame(278, $plan['calls_per_candidate']);
        $this->assertSame(834, $plan['total_calls']);
        $this->assertTrue($plan['full_release_evidence']);
    }

    public function test_command_is_plan_only_by_default_and_makes_no_provider_or_database_writes(): void
    {
        Http::fake();

        $this->artisan('ai-support:evaluate-models')
            ->expectsOutputToContain('Plan only. No provider call was made.')
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertDatabaseCount('knowledge_base_entries', 0);
        $this->assertDatabaseCount('ai_support_pilot_grants', 0);
        $this->assertDatabaseCount('ai_support_control_versions', 0);
    }

    public function test_real_execution_refuses_disabled_and_production_contexts_before_provider_call(): void
    {
        Http::fake();
        config([
            'ai_support.offline_evaluation_enabled' => false,
            'ai_support.runtime_available' => false,
            'services.openai.api_key' => 'synthetic-test-key',
        ]);

        $this->artisan('ai-support:evaluate-models', [
            '--run' => true,
            '--model' => ['gpt-5-nano-low'],
            '--case' => ['EVAL-KB-FAM-001-POS'],
            '--critical-runs' => 1,
        ])->expectsOutputToContain('Offline AI Support evaluation is disabled.')
            ->assertFailed();

        config(['ai_support.offline_evaluation_enabled' => true]);
        $previousEnvironment = app()->environment();
        try {
            app()->instance('env', 'production');
            app(OfflineOpenAiResponsesClient::class)->assertMayRun();
            $this->fail('Production must refuse offline model evaluation.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('prohibited in production', $exception->getMessage());
        } finally {
            app()->instance('env', $previousEnvironment);
        }

        Http::assertNothingSent();
    }

    public function test_synthetic_smoke_run_uses_strict_responses_output_and_persists_metrics_only(): void
    {
        config([
            'ai_support.offline_evaluation_enabled' => true,
            'ai_support.runtime_available' => false,
            'services.openai.api_key' => 'synthetic-test-key',
            'services.openai.base_url' => 'https://api.openai.com/v1',
        ]);

        $structured = [
            'answer' => 'Your Family dashboard shows the current items that may need your attention.',
            'outcome' => 'answer',
            'navigation_target' => 'family.dashboard',
            'action' => 'none',
            'handoff_human_only' => false,
            'suppress_after_handoff' => false,
            'cited_kb_ids' => ['KB-FAM-001'],
        ];
        Http::fake([
            '*' => Http::response([
                'id' => 'resp_synthetic',
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode($structured, JSON_THROW_ON_ERROR),
                    ]],
                ]],
                'usage' => [
                    'input_tokens' => 100,
                    'input_tokens_details' => ['cached_tokens' => 20],
                    'output_tokens' => 30,
                    'output_tokens_details' => ['reasoning_tokens' => 5],
                ],
            ], 200),
        ]);

        $filename = 'test-ai-support-eval-'.Str::uuid().'.json';
        try {
            $result = app(OfflineAiSupportModelEvaluationService::class)->run(
                ['gpt-5-nano-low'],
                1,
                ['EVAL-KB-FAM-001-POS'],
                $filename,
            );

            $summary = $result['report']['summaries']['gpt-5-nano-low'];
            $this->assertSame(1, $summary['completed_calls']);
            $this->assertSame(0, $summary['hard_failure_calls']);
            $this->assertSame(1.0, $summary['quality_pass_rate']);
            $this->assertFalse($result['report']['full_release_evidence']);
            $this->assertFalse($result['report']['application_database_writes']);
            $this->assertFalse($result['report']['raw_prompts_persisted']);
            $this->assertFalse($result['report']['raw_model_outputs_persisted']);
            $this->assertSame(['gpt-5-nano-low'], $result['report']['passing_candidate_ids']);
            $this->assertSame([], $result['report']['baseline_eligible_passing_candidate_ids']);
            $this->assertNull($result['report']['recommended_candidate_id']);

            $persisted = File::get($result['path']);
            $this->assertStringNotContainsString($structured['answer'], $persisted);
            $this->assertStringNotContainsString('synthetic-test-key', $persisted);
            $this->assertStringContainsString('response_hash', $persisted);
        } finally {
            if (isset($result['path']) && is_file($result['path'])) {
                File::delete($result['path']);
            }
        }

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $request->url() === 'https://api.openai.com/v1/responses'
                && $payload['store'] === false
                && data_get($payload, 'text.format.type') === 'json_schema'
                && data_get($payload, 'text.format.strict') === true
                && ! array_key_exists('tools', $payload)
                && str_contains((string) $payload['input'], '"synthetic_evaluation": true')
                && ! str_contains((string) $payload['input'], '"expected"')
                && ! str_contains((string) $payload['input'], 'required_phrases');
        });

        $this->assertDatabaseCount('knowledge_base_entries', 0);
        $this->assertDatabaseCount('ai_support_pilot_grants', 0);
        $this->assertDatabaseCount('ai_support_control_versions', 0);
    }

    public function test_estimated_cost_separates_cached_input_and_output_tokens(): void
    {
        $catalog = app(AiSupportModelCandidateCatalog::class);
        $candidate = $catalog->candidate('gpt-5-nano-low');

        $cost = $catalog->estimatedCost($candidate, [
            'input_tokens' => 100,
            'cached_input_tokens' => 20,
            'output_tokens' => 10,
        ]);

        $this->assertEqualsWithDelta(0.0000081, $cost, 0.0000000001);
    }
}
