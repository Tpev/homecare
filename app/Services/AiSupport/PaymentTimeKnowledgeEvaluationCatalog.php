<?php

namespace App\Services\AiSupport;

use DomainException;

class PaymentTimeKnowledgeEvaluationCatalog
{
    public const VERSION = 'payment-time-kb-evals-v1';

    public function __construct(private readonly PaymentTimeKnowledgeBaseCatalog $knowledge) {}

    /** @return list<array<string,mixed>> */
    public function cases(): array
    {
        $manifest = require resource_path('ai-support/evaluations/payment-time-kb-v1.php');
        $cases = array_values((array) ($manifest['cases'] ?? []));
        $expectedIds = collect($this->knowledge->entries())
            ->flatMap(fn (array $definition): array => $definition['evaluation_ids'])
            ->sort()->values()->all();
        if (($manifest['version'] ?? null) !== self::VERSION
            || count($cases) !== 90
            || count(array_unique(array_column($cases, 'id'))) !== 90
            || collect($cases)->pluck('id')->sort()->values()->all() !== $expectedIds) {
            throw new DomainException('Batch 4 knowledge evaluation inventory must contain 90 linked unique cases.');
        }

        foreach ($cases as $case) {
            if (! in_array($case['stable_id'] ?? null, PaymentTimeKnowledgeBaseCatalog::APPROVED_STABLE_IDS, true)
                || ! in_array($case['type'] ?? null, ['positive', 'boundary', 'wrong_role', 'unsupported_state', 'handoff'], true)
                || trim((string) ($case['user_message'] ?? '')) === '') {
                throw new DomainException('Invalid Batch 4 knowledge evaluation: '.($case['id'] ?? 'unknown').'.');
            }
        }

        return $cases;
    }
}
