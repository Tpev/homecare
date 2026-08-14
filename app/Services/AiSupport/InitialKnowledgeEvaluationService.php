<?php

namespace App\Services\AiSupport;

class InitialKnowledgeEvaluationService
{
    private const WRITABLE_ACTION_PATTERNS = [
        '/\baccept_invitation\b/i',
        '/\bdecline_invitation\b/i',
        '/\bapply_for_work\b/i',
        '/\bchange_(?:account|payment_method)\b/i',
        '/\bcreate_request\b/i',
        '/\bedit_profile\b/i',
        '/\bpublish_request\b/i',
        '/\bsend_message\b/i',
        '/\b(?:start|end)_visit\b/i',
    ];

    private const ARBITRARY_NAVIGATION_PATTERNS = [
        '/https?:\/\//i',
        '/(?:css|xpath|selector|coordinate)/i',
        '/#[a-z0-9_-]+/i',
        '/\bresource[_ -]?id\b/i',
    ];

    public function __construct(
        private readonly InitialKnowledgeBaseCatalog $knowledge,
        private readonly InitialKnowledgeEvaluationCatalog $evaluations,
        private readonly NavigationTargetRegistry $navigation,
    ) {}

    /**
     * @return array{
     *   passed:bool,
     *   knowledge_version:string,
     *   evaluation_version:string,
     *   entry_count:int,
     *   entry_case_count:int,
     *   regression_case_count:int,
     *   case_count:int,
     *   critical_case_count:int,
     *   errors:list<string>
     * }
     */
    public function validate(): array
    {
        $entries = $this->knowledge->entries();
        $entryCases = $this->evaluations->cases();
        $regressions = $this->evaluations->criticalRegressions();
        $cases = [...$entryCases, ...$regressions];
        $casesById = collect($entryCases)->keyBy('id');
        $errors = [];

        foreach ($entries as $entry) {
            $stableId = $entry['stable_id'];
            foreach ($entry['evaluation_ids'] as $evaluationId) {
                if (! $casesById->has($evaluationId)) {
                    $errors[] = $stableId.' is missing evaluation fixture '.$evaluationId.'.';
                }
            }

            foreach ($entry['route_target_ids'] as $targetId) {
                $definition = $this->navigation->definition($targetId);
                if (! $definition || array_diff($entry['roles'], $definition['roles']) !== []) {
                    $errors[] = $stableId.' has a role/target authorization mismatch for '.$targetId.'.';
                }
            }

            $nextActions = implode("\n", $entry['next_actions']);
            foreach (self::WRITABLE_ACTION_PATTERNS as $pattern) {
                if (preg_match($pattern, $nextActions) === 1) {
                    $errors[] = $stableId.' next_actions contains a writable action pattern.';
                    break;
                }
            }
            foreach (self::ARBITRARY_NAVIGATION_PATTERNS as $pattern) {
                if (preg_match($pattern, $nextActions) === 1) {
                    $errors[] = $stableId.' next_actions contains arbitrary or resource-specific navigation.';
                    break;
                }
            }
        }

        foreach ($cases as $case) {
            $expected = $case['expected'];
            if (($case['case_type'] === 'positive') && $expected['navigation_target'] !== null) {
                $definition = $this->navigation->definition($expected['navigation_target']);
                if (! $definition || ! in_array($case['actor_role'], $definition['roles'], true)) {
                    $errors[] = $case['id'].' expects navigation not allowed for its actor role.';
                }
            }
            if ($case['case_type'] !== 'positive' && $expected['navigation_target'] !== null) {
                $errors[] = $case['id'].' authorizes navigation outside a positive case.';
            }
            if (array_intersect($expected['forbidden_actions'], [
                'accept_invitation',
                'decline_invitation',
                'apply_for_work',
                'change_account',
                'change_payment_method',
                'create_request',
                'edit_profile',
                'publish_request',
                'send_message',
                'start_visit',
                'end_visit',
            ]) === []) {
                $errors[] = $case['id'].' does not forbid the read-only pack writable actions.';
            }
            if ($expected['must_transfer_human_only'] && ! $expected['must_suppress_after_handoff']) {
                $errors[] = $case['id'].' transfers without suppressing later automation.';
            }
            if ($expected['must_transfer_human_only'] && ! $expected['may_transfer_human']) {
                $errors[] = $case['id'].' requires a transfer that its contract does not permit.';
            }
        }

        foreach ($regressions as $case) {
            $expected = $case['expected'];
            if ($expected['navigation_target'] !== null) {
                $errors[] = $case['id'].' critical regression pre-authorizes navigation.';
            }

            if ($case['case_type'] === 'emergency_precedence'
                && ($expected['outcome'] !== 'emergency_instruction'
                    || ! in_array('911', $expected['required_phrases'], true))) {
                $errors[] = $case['id'].' does not enforce emergency instruction precedence.';
            }

            if (in_array($case['case_type'], ['handoff_precedence', 'handoff_race'], true)
                && (! $expected['must_transfer_human_only'] || ! $expected['must_suppress_after_handoff'])) {
                $errors[] = $case['id'].' does not enforce final human-only handoff.';
            }

            if ($expected['must_transfer_human_only'] && ! $expected['may_transfer_human']) {
                $errors[] = $case['id'].' requires a transfer that its contract does not permit.';
            }

            if ($case['case_type'] === 'unauthorized_context'
                && (! $expected['must_not_reveal_role_data'] || $expected['navigation_target'] !== null)) {
                $errors[] = $case['id'].' does not fail closed for an unauthorized context.';
            }

            if ($case['case_type'] === 'credential' && $expected['forbidden_phrases'] === []) {
                $errors[] = $case['id'].' does not prohibit repeating the synthetic secret.';
            }

            if ($case['case_type'] === 'missing_target'
                && ((array) $case['available_navigation_targets'] !== [] || $expected['navigation_target'] !== null)) {
                $errors[] = $case['id'].' does not fail closed when its semantic target is absent.';
            }
        }

        return [
            'passed' => $errors === [],
            'knowledge_version' => InitialKnowledgeBaseCatalog::VERSION,
            'evaluation_version' => InitialKnowledgeEvaluationCatalog::VERSION,
            'entry_count' => count($entries),
            'entry_case_count' => count($entryCases),
            'regression_case_count' => count($regressions),
            'case_count' => count($cases),
            'critical_case_count' => collect($cases)->where('critical', true)->count(),
            'errors' => array_values(array_unique($errors)),
        ];
    }
}
