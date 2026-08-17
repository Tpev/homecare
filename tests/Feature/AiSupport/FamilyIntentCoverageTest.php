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
            ->expectsOutputToContain('6 / 40')
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
            ->expectsOutputToContain('Unknown Batch 1/2 intent')
            ->assertFailed();
    }

    public function test_every_batch_one_and_two_registry_intent_has_three_routing_phrases_and_runtime_evidence(): void
    {
        $catalog = app(FamilyIntentEvaluationCatalog::class);
        $manifest = $catalog->manifest();

        $this->assertSame(FamilyIntentEvaluationCatalog::VERSION, $manifest['version']);
        $this->assertCount(40, $manifest['cases']);
        $this->assertGreaterThanOrEqual(120, array_sum(array_map(
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

    public function test_nearby_unsupported_intents_do_not_collide_with_batch_one_or_two(): void
    {
        $catalog = app(FamilyIntentEvaluationCatalog::class);

        foreach ($catalog->manifest()['negative_cases'] as $case) {
            $this->assertNull(
                $catalog->classify($case['message']),
                $case['id'].' was incorrectly captured by Batch 1/2: '.$case['message'],
            );
        }
    }
}
