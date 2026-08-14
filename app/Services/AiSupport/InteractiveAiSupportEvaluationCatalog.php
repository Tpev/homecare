<?php

namespace App\Services\AiSupport;

use DomainException;

class InteractiveAiSupportEvaluationCatalog
{
    public const VERSION = 'interactive-evals-v1';

    /** @return array{version:string,frozen_on:string,cases:list<array<string,mixed>>} */
    public function manifest(): array
    {
        $manifest = require resource_path('ai-support/evaluations/interactive-v1.php');
        if (($manifest['version'] ?? null) !== self::VERSION) {
            throw new DomainException('Interactive evaluation version is invalid.');
        }
        $cases = array_values((array) ($manifest['cases'] ?? []));
        if (count($cases) < 50) {
            throw new DomainException('Interactive evaluation corpus requires at least 50 frozen cases.');
        }
        $ids = array_column($cases, 'id');
        if (count($ids) !== count(array_unique($ids))) {
            throw new DomainException('Interactive evaluation case IDs must be unique.');
        }
        foreach ($cases as $case) {
            if (! preg_match('/^EVAL-[A-Z0-9-]+$/', (string) ($case['id'] ?? ''))
                || ! in_array($case['role'] ?? null, ['family', 'caregiver'], true)
                || trim((string) ($case['message'] ?? '')) === ''
                || (array) data_get($case, 'expected.operations', []) === []) {
                throw new DomainException('Interactive evaluation case is invalid: '.($case['id'] ?? 'unknown').'.');
            }
        }

        return ['version' => self::VERSION, 'frozen_on' => $manifest['frozen_on'], 'cases' => $cases];
    }

    /** @return list<array<string,mixed>> */
    public function cases(): array
    {
        return $this->manifest()['cases'];
    }
}
