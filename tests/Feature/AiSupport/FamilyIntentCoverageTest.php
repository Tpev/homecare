<?php

namespace Tests\Feature\AiSupport;

use App\Services\AiSupport\FamilyIntentEvaluationCatalog;
use Tests\TestCase;

class FamilyIntentCoverageTest extends TestCase
{
    public function test_mass_runner_plan_is_safe_and_filterable(): void
    {
        $this->artisan('ai-support:test-family-intents', [
            '--plan' => true,
            '--batch' => [1],
            '--domain' => ['payments'],
        ])
            ->expectsOutputToContain('6 / 45')
            ->expectsOutputToContain('Routing precheck: PASS')
            ->expectsOutputToContain('No test database, provider call, production write, or report write occurred')
            ->assertSuccessful();
    }

    public function test_mass_runner_rejects_unknown_registry_intent_filters(): void
    {
        $this->artisan('ai-support:test-family-intents', [
            '--plan' => true,
            '--intent' => ['FAM-NOT-REAL-999'],
        ])
            ->expectsOutputToContain('Unknown Family intent')
            ->assertFailed();
    }

    public function test_mass_runner_can_filter_batch_six_and_includes_all_registered_phrases(): void
    {
        $this->artisan('ai-support:test-family-intents', [
            '--plan' => true,
            '--batch' => [6],
        ])
            ->expectsOutputToContain('25 / 86')
            ->expectsOutputToContain('100')
            ->expectsOutputToContain('Routing precheck: PASS')
            ->assertSuccessful();
    }

    public function test_mass_runner_can_filter_batches_eight_and_nine_with_the_complete_administration_corpus(): void
    {
        $this->artisan('ai-support:test-family-intents', [
            '--plan' => true,
            '--batch' => [8],
        ])
            ->expectsOutputToContain('72 / 118')
            ->expectsOutputToContain('288')
            ->expectsOutputToContain('220')
            ->expectsOutputToContain('Routing precheck: PASS')
            ->assertSuccessful();

        $this->artisan('ai-support:test-family-intents', [
            '--plan' => true,
            '--batch' => [9],
        ])
            ->expectsOutputToContain('46 / 118')
            ->expectsOutputToContain('184')
            ->expectsOutputToContain('220')
            ->expectsOutputToContain('Routing precheck: PASS')
            ->assertSuccessful();
    }

    public function test_every_deep_runtime_intent_has_three_routing_phrases_and_runtime_evidence(): void
    {
        $catalog = app(FamilyIntentEvaluationCatalog::class);
        $manifest = $catalog->manifest();

        $this->assertSame(FamilyIntentEvaluationCatalog::VERSION, $manifest['version']);
        $this->assertCount(45, $manifest['cases']);
        $this->assertGreaterThanOrEqual(135, array_sum(array_map(
            static fn (array $case): int => count($case['phrases']),
            $manifest['cases'],
        )));

        foreach ($manifest['cases'] as $case) {
            foreach ($case['phrases'] as $index => $phrase) {
                $this->assertSame(
                    $case['handler'],
                    $catalog->classify($phrase),
                    $case['intent_id'].' phrase '.($index + 1).' routed incorrectly: '.$phrase,
                );
            }
        }
    }

    public function test_nearby_unsupported_intents_do_not_collide_with_deep_runtime_handlers(): void
    {
        $catalog = app(FamilyIntentEvaluationCatalog::class);

        foreach ($catalog->manifest()['negative_cases'] as $case) {
            $this->assertNull(
                $catalog->classify($case['message']),
                $case['id'].' was incorrectly captured by a deep runtime handler: '.$case['message'],
            );
        }
    }
}
