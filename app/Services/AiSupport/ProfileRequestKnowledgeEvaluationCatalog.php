<?php

namespace App\Services\AiSupport;

use DomainException;

class ProfileRequestKnowledgeEvaluationCatalog
{
    public const VERSION = 'profile-request-kb-evals-v1';

    public function __construct(private readonly ProfileRequestKnowledgeBaseCatalog $knowledge) {}

    /** @return list<array<string,mixed>> */
    public function cases(): array
    {
        $manifest = require resource_path('ai-support/evaluations/profile-request-kb-v1.php');
        $cases = array_values((array) ($manifest['cases'] ?? []));
        $expectedIds = collect($this->knowledge->entries())
            ->flatMap(fn (array $definition): array => $definition['evaluation_ids'])
            ->sort()->values()->all();
        $expectedCount = count(ProfileRequestKnowledgeBaseCatalog::APPROVED_STABLE_IDS) * 5;
        if (($manifest['version'] ?? null) !== self::VERSION
            || count($cases) !== $expectedCount
            || count(array_unique(array_column($cases, 'id'))) !== $expectedCount
            || collect($cases)->pluck('id')->sort()->values()->all() !== $expectedIds) {
            throw new DomainException('Batch 5 knowledge evaluation inventory is incomplete or stale.');
        }

        foreach ($cases as $case) {
            if (! in_array($case['stable_id'] ?? null, ProfileRequestKnowledgeBaseCatalog::APPROVED_STABLE_IDS, true)
                || ! in_array($case['type'] ?? null, ['positive', 'boundary', 'wrong_account', 'stale_or_unavailable', 'handoff'], true)
                || trim((string) ($case['user_message'] ?? '')) === '') {
                throw new DomainException('Invalid Batch 5 knowledge evaluation: '.($case['id'] ?? 'unknown').'.');
            }
        }

        return $cases;
    }
}
