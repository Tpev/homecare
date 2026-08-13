<?php

namespace App\Services\AiSupport;

use DomainException;

class InitialKnowledgeEvaluationCatalog
{
    public const VERSION = 'initial-kb-evals-v1';

    public const APPROVED_CRITICAL_REGRESSION_IDS = [
        'EVAL-REG-EMERGENCY-PRECEDENCE',
        'EVAL-REG-HUMAN-PRECEDENCE',
        'EVAL-REG-MARKETPLACE-AMBIGUITY',
        'EVAL-REG-REMOVED-FAMILY-MEMBER',
        'EVAL-REG-SIGNED-OUT-CONTEXT',
        'EVAL-REG-ADMIN-CONTEXT',
        'EVAL-REG-PROMPT-INJECTION',
        'EVAL-REG-SECRET-PASTE',
        'EVAL-REG-MISSING-SEMANTIC-TARGET',
        'EVAL-REG-HANDOFF-IN-FLIGHT',
    ];

    public function __construct(
        private readonly NavigationTargetRegistry $navigation,
        private readonly InitialKnowledgeBaseCatalog $knowledge,
    ) {}

    /** @return array{version:string,cases:list<array<string,mixed>>,critical_regressions:list<array<string,mixed>>} */
    public function manifest(): array
    {
        $path = resource_path('ai-support/evaluations/v1.php');
        if (! is_file($path)) {
            throw new DomainException('Initial knowledge evaluation manifest is missing.');
        }

        $manifest = require $path;
        if (! is_array($manifest)) {
            throw new DomainException('Initial knowledge evaluation manifest must return an array.');
        }

        return $this->validate($manifest);
    }

    /** @return list<array<string,mixed>> */
    public function cases(): array
    {
        return $this->manifest()['cases'];
    }

    /** @return list<array<string,mixed>> */
    public function criticalRegressions(): array
    {
        return $this->manifest()['critical_regressions'];
    }

    /** @return list<array<string,mixed>> */
    public function allCases(): array
    {
        return [...$this->cases(), ...$this->criticalRegressions()];
    }

    /** @param array<string,mixed> $manifest @return array{version:string,cases:list<array<string,mixed>>,critical_regressions:list<array<string,mixed>>} */
    private function validate(array $manifest): array
    {
        if (($manifest['version'] ?? null) !== self::VERSION) {
            throw new DomainException('Initial knowledge evaluation version must be '.self::VERSION.'.');
        }

        $cases = array_values((array) ($manifest['cases'] ?? []));
        if (count($cases) < 60) {
            throw new DomainException('Initial knowledge evaluation manifest must contain at least 60 cases.');
        }

        $allowedTypes = ['positive', 'boundary', 'wrong_role', 'unsupported_state', 'handoff', 'no_mutation', 'credential', 'verification'];
        $knowledgeById = collect($this->knowledge->entries())->keyBy('stable_id');
        $seen = [];
        foreach ($cases as $index => $case) {
            if (! is_array($case)) {
                throw new DomainException('Evaluation case '.($index + 1).' must be an array.');
            }

            $id = trim((string) ($case['id'] ?? ''));
            if (! preg_match('/^EVAL-KB-[A-Z0-9-]+$/', $id)) {
                throw new DomainException('Invalid evaluation ID: '.$id.'.');
            }
            if (isset($seen[$id])) {
                throw new DomainException('Duplicate evaluation ID: '.$id.'.');
            }
            $seen[$id] = true;

            $stableId = trim((string) ($case['kb_stable_id'] ?? ''));
            if (! in_array($stableId, InitialKnowledgeBaseCatalog::APPROVED_STABLE_IDS, true)) {
                throw new DomainException($id.' references an unapproved KB stable ID.');
            }
            $knowledgeEntry = $knowledgeById->get($stableId);
            if (! in_array($case['case_type'] ?? null, $allowedTypes, true)) {
                throw new DomainException($id.' contains an unsupported case type.');
            }
            if (! in_array($case['actor_role'] ?? null, ['family', 'caregiver'], true)) {
                throw new DomainException($id.' contains an unsupported actor role.');
            }
            if (! in_array($case['membership_state'] ?? null, ['active', 'removed', 'unresolved'], true)) {
                throw new DomainException($id.' contains an unsupported membership state.');
            }
            if (trim((string) ($case['user_message'] ?? '')) === '') {
                throw new DomainException($id.' requires a synthetic user message.');
            }
            if (empty($case['expected']['outcome'] ?? null)) {
                throw new DomainException($id.' requires an expected outcome.');
            }

            foreach (['required_phrases', 'forbidden_phrases', 'forbidden_actions'] as $field) {
                $case['expected'][$field] = array_values(array_unique(array_filter(array_map(
                    fn (mixed $value): string => trim((string) $value),
                    (array) ($case['expected'][$field] ?? []),
                ))));
            }

            $expectedTarget = $case['expected']['navigation_target'] ?? null;
            if ($expectedTarget !== null && ! is_string($expectedTarget)) {
                throw new DomainException($id.' navigation_target must be a string or null.');
            }
            if ($expectedTarget !== null && ! $this->navigation->has($expectedTarget)) {
                throw new DomainException($id.' references an unknown navigation target.');
            }
            if ($expectedTarget !== null && ! in_array($expectedTarget, (array) ($case['available_navigation_targets'] ?? []), true)) {
                throw new DomainException($id.' expects a navigation target that is not available to the model.');
            }
            if (($case['case_type'] ?? null) !== 'positive' && $expectedTarget !== null) {
                throw new DomainException($id.' non-positive case must not authorize navigation.');
            }
            if ($expectedTarget !== null && ! in_array($expectedTarget, $knowledgeEntry['route_target_ids'], true)) {
                throw new DomainException($id.' navigation target does not match its KB entry.');
            }
            if (($case['case_type'] ?? null) === 'wrong_role'
                && in_array($case['actor_role'], $knowledgeEntry['roles'], true)
                && count($knowledgeEntry['roles']) === 1) {
                throw new DomainException($id.' wrong-role case uses an allowed role.');
            }
            if (($case['case_type'] ?? null) !== 'wrong_role'
                && ! in_array($case['actor_role'], $knowledgeEntry['roles'], true)) {
                throw new DomainException($id.' non-wrong-role case uses a role outside its KB entry.');
            }
            if (($case['expected']['must_transfer_human_only'] ?? false) === true
                && ! in_array('SUP-HANDOFF-001', (array) ($case['available_tools'] ?? []), true)) {
                throw new DomainException($id.' requires human transfer without exposing the handoff capability.');
            }

            $cases[$index] = $case;
        }

        $linkedIds = collect($this->knowledge->entries())->flatMap(fn (array $entry): array => $entry['evaluation_ids'])->unique()->sort()->values();
        $caseIds = collect(array_keys($seen))->sort()->values();
        if ($linkedIds->diff($caseIds)->isNotEmpty() || $caseIds->diff($linkedIds)->isNotEmpty()) {
            throw new DomainException('Evaluation fixtures must match the evaluation IDs linked by the initial KB manifest exactly.');
        }

        $regressions = array_values((array) ($manifest['critical_regressions'] ?? []));
        if (count($regressions) !== count(self::APPROVED_CRITICAL_REGRESSION_IDS)) {
            throw new DomainException('Initial knowledge evaluation manifest must contain the complete critical regression set.');
        }

        $allowedRegressionTypes = [
            'emergency_precedence',
            'handoff_precedence',
            'marketplace_ambiguity',
            'unauthorized_context',
            'prompt_injection',
            'credential',
            'missing_target',
            'handoff_race',
        ];
        $regressionIds = [];
        foreach ($regressions as $index => $case) {
            if (! is_array($case)) {
                throw new DomainException('Critical regression '.($index + 1).' must be an array.');
            }

            $id = trim((string) ($case['id'] ?? ''));
            if (! in_array($id, self::APPROVED_CRITICAL_REGRESSION_IDS, true)) {
                throw new DomainException('Unapproved critical regression ID: '.$id.'.');
            }
            if (isset($seen[$id]) || isset($regressionIds[$id])) {
                throw new DomainException('Duplicate evaluation ID: '.$id.'.');
            }
            $regressionIds[$id] = true;

            $stableIds = array_values(array_unique(array_filter(array_map(
                fn (mixed $value): string => trim((string) $value),
                (array) ($case['kb_stable_ids'] ?? []),
            ))));
            if ($stableIds === [] || array_diff($stableIds, InitialKnowledgeBaseCatalog::APPROVED_STABLE_IDS) !== []) {
                throw new DomainException($id.' references an invalid KB stable-ID set.');
            }
            $case['kb_stable_ids'] = $stableIds;

            if (! in_array($case['case_type'] ?? null, $allowedRegressionTypes, true)) {
                throw new DomainException($id.' contains an unsupported critical regression type.');
            }
            if (($case['critical'] ?? null) !== true) {
                throw new DomainException($id.' must remain critical.');
            }
            if (! in_array($case['actor_role'] ?? null, ['family', 'caregiver', 'admin', 'signed_out'], true)) {
                throw new DomainException($id.' contains an unsupported actor context.');
            }
            if (! in_array($case['membership_state'] ?? null, ['active', 'removed', 'unresolved', 'not_applicable'], true)) {
                throw new DomainException($id.' contains an unsupported membership state.');
            }
            if (trim((string) ($case['user_message'] ?? '')) === '' || empty($case['expected']['outcome'] ?? null)) {
                throw new DomainException($id.' requires a synthetic message and expected outcome.');
            }
            if (data_get($case, 'authorized_context.synthetic_only') !== true) {
                throw new DomainException($id.' must use synthetic context only.');
            }

            foreach ((array) ($case['available_navigation_targets'] ?? []) as $targetId) {
                if (! is_string($targetId) || ! $this->navigation->has($targetId)) {
                    throw new DomainException($id.' exposes an unknown semantic target.');
                }
            }
            if (($case['expected']['navigation_target'] ?? null) !== null) {
                throw new DomainException($id.' must not pre-authorize navigation.');
            }
            if (($case['expected']['must_transfer_human_only'] ?? false) === true
                && ! in_array('SUP-HANDOFF-001', (array) ($case['available_tools'] ?? []), true)) {
                throw new DomainException($id.' requires human transfer without exposing the handoff capability.');
            }

            foreach (['required_phrases', 'forbidden_phrases', 'forbidden_actions'] as $field) {
                $case['expected'][$field] = array_values(array_unique(array_filter(array_map(
                    fn (mixed $value): string => trim((string) $value),
                    (array) ($case['expected'][$field] ?? []),
                ))));
            }
            if ($case['expected']['forbidden_actions'] === []) {
                throw new DomainException($id.' must explicitly forbid writable actions.');
            }

            $regressions[$index] = $case;
        }

        $approvedRegressionIds = self::APPROVED_CRITICAL_REGRESSION_IDS;
        $actualRegressionIds = array_keys($regressionIds);
        sort($approvedRegressionIds);
        sort($actualRegressionIds);
        if ($approvedRegressionIds !== $actualRegressionIds) {
            throw new DomainException('Critical regression fixtures do not match the approved build-plan inventory.');
        }

        return [
            'version' => self::VERSION,
            'cases' => $cases,
            'critical_regressions' => $regressions,
        ];
    }
}
