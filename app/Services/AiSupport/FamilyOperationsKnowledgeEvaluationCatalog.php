<?php

namespace App\Services\AiSupport;

use DomainException;

class FamilyOperationsKnowledgeEvaluationCatalog
{
    public const VERSION = 'family-operations-kb-evals-v1';

    public function __construct(private readonly FamilyOperationsKnowledgeBaseCatalog $knowledge) {}

    /** @return list<array<string,mixed>> */
    public function cases(): array
    {
        $manifest = require resource_path('ai-support/evaluations/family-operations-kb-v1.php');
        if (($manifest['version'] ?? null) !== self::VERSION) {
            throw new DomainException('Family operations KB evaluation version is invalid.');
        }

        $cases = array_values((array) ($manifest['cases'] ?? []));
        $expectedIds = collect($this->knowledge->allDefinitions())
            ->flatMap(fn (array $definition): array => $definition['evaluation_ids'])
            ->all();
        if (count($cases) !== 280
            || count(array_unique(array_column($cases, 'id'))) !== 280
            || collect($cases)->pluck('id')->sort()->values()->all() !== collect($expectedIds)->sort()->values()->all()) {
            throw new DomainException('Family operations KB evaluation inventory must contain the 280 linked unique cases.');
        }

        $stableIds = array_merge(
            FamilyOperationsKnowledgeBaseCatalog::APPROVED_STABLE_IDS,
            FamilyOperationsKnowledgeBaseCatalog::REVISION_STABLE_IDS,
        );
        $types = ['positive', 'boundary', 'wrong_role', 'unsupported_state', 'handoff'];
        foreach ($cases as $case) {
            if (! in_array($case['stable_id'] ?? null, $stableIds, true)
                || ! in_array($case['type'] ?? null, $types, true)
                || trim((string) ($case['user_message'] ?? '')) === '') {
                throw new DomainException('Invalid Family operations KB evaluation: '.($case['id'] ?? 'unknown').'.');
            }
        }

        return $cases;
    }
}
