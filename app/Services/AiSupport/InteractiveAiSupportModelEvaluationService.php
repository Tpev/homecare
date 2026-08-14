<?php

namespace App\Services\AiSupport;

class InteractiveAiSupportModelEvaluationService
{
    public function __construct(
        private readonly InteractiveAiSupportEvaluationCatalog $catalog,
        private readonly AiSupportModelCandidateCatalog $candidates,
        private readonly OfflineOpenAiResponsesClient $client,
        private readonly AiSupportRuntimePromptBuilder $prompt,
        private readonly AiSupportOpenAiClient $runtimeClient,
        private readonly InteractiveAiSupportModelGrader $grader,
    ) {}

    /** @param list<string> $caseIds @return array<string,mixed> */
    public function plan(string $candidateId, array $caseIds = []): array
    {
        $candidate = $this->candidates->candidate($candidateId);
        $cases = collect($this->catalog->cases())
            ->when($caseIds !== [], fn ($items) => $items->whereIn('id', $caseIds))
            ->values();

        return [
            'corpus_version' => InteractiveAiSupportEvaluationCatalog::VERSION,
            'prompt_version' => AiSupportRuntimePromptBuilder::VERSION,
            'candidate_id' => $candidate['id'],
            'model' => $candidate['model'],
            'case_count' => $cases->count(),
            'extraction_case_count' => $cases->where('category', 'extraction')->count(),
            'case_ids' => $cases->pluck('id')->all(),
        ];
    }

    /** @param list<string> $caseIds @return array<string,mixed> */
    public function execute(string $candidateId, array $caseIds = []): array
    {
        $plan = $this->plan($candidateId, $caseIds);
        $candidate = $this->candidates->candidate($candidateId);
        $cases = collect($this->catalog->cases())->whereIn('id', $plan['case_ids'])->values();
        $results = [];
        $usage = ['input_tokens' => 0, 'cached_input_tokens' => 0, 'output_tokens' => 0];
        $latencies = [];
        foreach ($cases as $case) {
            $response = $this->client->evaluate(
                $candidate,
                $this->prompt->instructions(),
                $this->caseInput($case),
                $this->runtimeClient->schema(),
            );
            $grade = $this->grader->grade($case, $response['response']);
            foreach ($usage as $key => $value) {
                $usage[$key] += (int) ($response['usage'][$key] ?? 0);
            }
            $latencies[] = $response['latency_ms'];
            $results[] = [
                'case_id' => $case['id'],
                'category' => $case['category'],
                'passed' => $grade['passed'],
                'hard_failure' => $grade['hard_failure'],
                'error_codes' => $grade['errors'],
                'field_total' => $grade['field_total'],
                'field_passed' => $grade['field_passed'],
                'latency_ms' => $response['latency_ms'],
                'retries' => $response['retries'],
                'result_hash' => $response['response_hash'],
            ];
        }

        $fieldTotal = (int) collect($results)->sum('field_total');
        $fieldPassed = (int) collect($results)->sum('field_passed');
        $hardFailures = collect($results)->where('hard_failure', true)->count();
        $extractionAccuracy = $fieldTotal > 0 ? $fieldPassed / $fieldTotal : 1.0;
        $estimatedCost = $candidate['model'] === 'gpt-5.6-luna'
            ? (((max(0, $usage['input_tokens'] - $usage['cached_input_tokens'])) * 1.00)
                + ($usage['cached_input_tokens'] * 0.10)
                + ($usage['output_tokens'] * 6.00)) / 1_000_000
            : $this->candidates->estimatedCost($candidate, $usage);

        return [
            ...$plan,
            'generated_at' => now('UTC')->toIso8601String(),
            'summary' => [
                'passed_cases' => collect($results)->where('passed', true)->count(),
                'failed_cases' => collect($results)->where('passed', false)->count(),
                'hard_failures' => $hardFailures,
                'extraction_fields_passed' => $fieldPassed,
                'extraction_fields_total' => $fieldTotal,
                'extraction_accuracy' => round($extractionAccuracy, 6),
                'release_gate_passed' => $hardFailures === 0 && $extractionAccuracy >= 0.98,
                'input_tokens' => $usage['input_tokens'],
                'cached_input_tokens' => $usage['cached_input_tokens'],
                'output_tokens' => $usage['output_tokens'],
                'estimated_cost_usd' => round($estimatedCost, 8),
                'p50_latency_ms' => $this->percentile($latencies, 50),
                'p95_latency_ms' => $this->percentile($latencies, 95),
            ],
            'results' => $results,
            'content_policy' => 'case_ids_metrics_and_result_hashes_only',
        ];
    }

    /** @param array<string,mixed> $case */
    private function caseInput(array $case): string
    {
        $role = $case['role'];
        $draft = $case['draft'] ? [
            'id' => 'synthetic-draft',
            'version' => 1,
            'request_type' => $case['draft']['request_type'],
            'fields' => $case['draft']['fields'],
        ] : null;

        return json_encode([
            'current_date' => '2026-08-14',
            'timezone' => 'America/New_York (Eastern Time)',
            'actor' => ['id' => 'synthetic-user', 'role' => $role],
            'available_semantic_targets' => $role === 'family'
                ? ['support.center', 'family.dashboard', 'family.care_requests', 'family.new_care_request', 'family.access', 'account.profile']
                : ['support.center', 'caregiver.dashboard', 'caregiver.work_inbox', 'caregiver.shifts', 'account.profile'],
            'governed_knowledge' => [[
                'stable_id' => 'KB-SYNTHETIC-EVAL',
                'version_id' => 1,
                'title' => 'Synthetic governed evaluation context',
                'answer' => 'Family users may choose one-time or recurring care. Caregivers receive answers and navigation only. Pricing answers are held. Human transfer is always available.',
                'may_state' => ['Use only approved navigation and care paths.'],
                'must_not_infer' => ['Price, payment state, availability, or another account data.'],
                'targets' => [],
            ]],
            'authorized_family_context' => $role === 'family' ? [
                'recipient_profiles' => [['id' => 201, 'name' => 'Arthur', 'recipient_is_requester' => false, 'relationship' => 'Father']],
                'household' => null,
                'care_tasks' => [
                    ['id' => 101, 'name' => 'Companionship'],
                    ['id' => 102, 'name' => 'Meal preparation'],
                    ['id' => 103, 'name' => 'Light housekeeping'],
                ],
                'previous_request' => null,
            ] : null,
            'active_draft' => $draft,
            'recent_conversation' => [['speaker' => 'user', 'text' => $case['message']]],
            'newest_user_message' => $case['message'],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @param list<int> $values */
    private function percentile(array $values, int $percentile): int
    {
        if ($values === []) {
            return 0;
        }
        sort($values);
        $index = (int) ceil(($percentile / 100) * count($values)) - 1;

        return $values[max(0, min(count($values) - 1, $index))];
    }
}
