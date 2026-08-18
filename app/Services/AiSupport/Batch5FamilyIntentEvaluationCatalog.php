<?php

namespace App\Services\AiSupport;

use DomainException;

class Batch5FamilyIntentEvaluationCatalog
{
    public const VERSION = 'family-lifecycle-evals-v1';

    public function __construct(private readonly FamilyIntentResolver $resolver) {}

    /** @return array{version:string,frozen_on:string,cases:list<array<string,mixed>>} */
    public function manifest(): array
    {
        $manifest = require resource_path('ai-support/evaluations/family-lifecycle-v1.php');
        $cases = array_values((array) ($manifest['cases'] ?? []));
        $ids = array_column($cases, 'intent_id');
        if (($manifest['version'] ?? null) !== self::VERSION
            || count($cases) !== 21
            || count(array_unique($ids)) !== 21) {
            throw new DomainException('Batch 5 Family lifecycle evaluation must contain 21 unique intents.');
        }
        foreach ($cases as $case) {
            if (($case['batch'] ?? null) !== 5
                || ! in_array($case['domain'] ?? null, ['profiles', 'requests'], true)
                || count((array) ($case['phrases'] ?? [])) < 3
                || trim((string) ($case['runtime_evidence'] ?? '')) === '') {
                throw new DomainException('Invalid Batch 5 lifecycle evaluation: '.($case['intent_id'] ?? 'unknown').'.');
            }
        }

        return [
            'version' => self::VERSION,
            'frozen_on' => (string) ($manifest['frozen_on'] ?? ''),
            'cases' => $cases,
        ];
    }

    /** @return array{passed:bool,total_phrases:int,passed_phrases:int,failed_intent_ids:list<string>,failures:list<string>} */
    public function evaluate(): array
    {
        $total = 0;
        $passed = 0;
        $failedIds = [];
        $failures = [];
        foreach ($this->manifest()['cases'] as $case) {
            foreach ($case['phrases'] as $phrase) {
                $total++;
                $actual = $this->resolver->resolve((string) $phrase)['intent_id'];
                if ($actual === $case['intent_id']) {
                    $passed++;
                } else {
                    $failedIds[] = $case['intent_id'];
                    $failures[] = $case['intent_id'].' routed to '.($actual ?: 'unmatched').' for "'.$phrase.'".';
                }
            }
        }

        return [
            'passed' => $failures === [],
            'total_phrases' => $total,
            'passed_phrases' => $passed,
            'failed_intent_ids' => array_values(array_unique($failedIds)),
            'failures' => $failures,
        ];
    }
}
