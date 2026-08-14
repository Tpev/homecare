<?php

namespace App\Services\AiSupport;

use DomainException;

class InteractiveKnowledgeEvaluationCatalog
{
    public const VERSION = 'interactive-kb-evals-v1';

    /** @return list<array<string,mixed>> */
    public function cases(): array
    {
        $manifest = require resource_path('ai-support/evaluations/interactive-kb-v1.php');
        if (($manifest['version'] ?? null) !== self::VERSION) {
            throw new DomainException('Interactive KB evaluation version is invalid.');
        }
        $cases = array_values((array) ($manifest['cases'] ?? []));
        if (count($cases) !== 60 || count(array_unique(array_column($cases, 'id'))) !== 60) {
            throw new DomainException('Interactive KB evaluation inventory must contain 60 unique cases.');
        }
        $allowedTypes = ['positive', 'boundary', 'wrong_role', 'unsupported_state', 'handoff'];
        foreach ($cases as $case) {
            if (! in_array($case['stable_id'] ?? null, InteractiveKnowledgeBaseCatalog::APPROVED_STABLE_IDS, true)
                || ! in_array($case['type'] ?? null, $allowedTypes, true)
                || trim((string) ($case['user_message'] ?? '')) === '') {
                throw new DomainException('Interactive KB evaluation case is invalid: '.($case['id'] ?? 'unknown').'.');
            }
        }

        return $cases;
    }
}
