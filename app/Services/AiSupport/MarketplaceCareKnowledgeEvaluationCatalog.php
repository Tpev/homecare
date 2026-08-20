<?php

namespace App\Services\AiSupport;

use DomainException;

class MarketplaceCareKnowledgeEvaluationCatalog
{
    public const VERSION = 'marketplace-care-kb-evals-v1';

    public function __construct(private readonly MarketplaceCareKnowledgeBaseCatalog $knowledge) {}

    /** @return list<array<string,mixed>> */
    public function cases(): array
    {
        $manifest = require resource_path('ai-support/evaluations/marketplace-care-kb-v1.php');
        $cases = array_values((array) ($manifest['cases'] ?? []));
        $expectedIds = collect($this->knowledge->entries())->flatMap(fn (array $entry): array => $entry['evaluation_ids'])->sort()->values()->all();
        $expectedCount = count(MarketplaceCareKnowledgeBaseCatalog::APPROVED_STABLE_IDS) * 5;
        if (($manifest['version'] ?? null) !== self::VERSION
            || count($cases) !== $expectedCount
            || count(array_unique(array_column($cases, 'id'))) !== $expectedCount
            || collect($cases)->pluck('id')->sort()->values()->all() !== $expectedIds) {
            throw new DomainException('Batches 6 and 7 knowledge evaluation inventory is incomplete or stale.');
        }

        return $cases;
    }
}
