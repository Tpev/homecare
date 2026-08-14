<?php

namespace App\Services\AiSupport;

use Closure;
use DomainException;
use Illuminate\Support\Facades\File;
use Throwable;

class OfflineAiSupportModelEvaluationService
{
    public const REPORT_VERSION = 'ai-support-model-evaluation-report-v1';

    public function __construct(
        private readonly InitialKnowledgeBaseCatalog $knowledge,
        private readonly InitialKnowledgeEvaluationCatalog $evaluations,
        private readonly AiSupportModelCandidateCatalog $candidates,
        private readonly OfflineAiSupportPromptBuilder $prompts,
        private readonly OfflineOpenAiResponsesClient $client,
        private readonly OfflineAiSupportModelGrader $grader,
    ) {}

    /**
     * @param  list<string>  $candidateIds
     * @param  list<string>  $caseIds
     * @return array<string,mixed>
     */
    public function plan(array $candidateIds = [], int $criticalRuns = 5, array $caseIds = []): array
    {
        $selectedCandidates = $this->selectCandidates($candidateIds);
        $selectedCases = $this->selectCases($caseIds);
        if ($criticalRuns < 1) {
            throw new DomainException('Critical runs must be at least 1.');
        }
        if ($caseIds === [] && $criticalRuns < 5) {
            throw new DomainException('Full-corpus evidence requires at least five critical runs.');
        }

        $criticalCount = count(array_filter($selectedCases, fn (array $case): bool => $case['critical'] === true));
        $callsPerCandidate = count($selectedCases) + ($criticalCount * ($criticalRuns - 1));

        return [
            'candidate_ids' => array_column($selectedCandidates, 'id'),
            'case_ids' => array_column($selectedCases, 'id'),
            'case_count' => count($selectedCases),
            'critical_case_count' => $criticalCount,
            'critical_runs' => $criticalRuns,
            'calls_per_candidate' => $callsPerCandidate,
            'total_calls' => $callsPerCandidate * count($selectedCandidates),
            'full_release_evidence' => $caseIds === []
                && count($selectedCases) === 70
                && $criticalRuns >= 5,
        ];
    }

    /**
     * @param  list<string>  $candidateIds
     * @param  list<string>  $caseIds
     * @return array{report:array<string,mixed>,path:string}
     */
    public function run(
        array $candidateIds = [],
        int $criticalRuns = 5,
        array $caseIds = [],
        ?string $filename = null,
        ?Closure $progress = null,
    ): array {
        $this->client->assertMayRun();
        $plan = $this->plan($candidateIds, $criticalRuns, $caseIds);
        $selectedCandidates = $this->selectCandidates($candidateIds);
        $selectedCases = $this->selectCases($caseIds);
        $schedule = $this->schedule($selectedCases, $criticalRuns);
        $allResults = [];

        foreach ($selectedCandidates as $candidate) {
            $modelResults = [];
            $consecutiveProviderErrors = 0;
            foreach ($schedule as $position => $scheduled) {
                $case = $scheduled['case'];
                $runIndex = $scheduled['run_index'];

                try {
                    $execution = $this->client->evaluate(
                        $candidate,
                        $this->prompts->instructions(),
                        $this->prompts->input($case),
                        $this->prompts->responseSchema(
                            array_values((array) ($case['available_navigation_targets'] ?? [])),
                            in_array('SUP-HANDOFF-001', (array) ($case['available_tools'] ?? []), true),
                            $this->prompts->applicableKnowledgeIds($case),
                        ),
                    );
                    $grade = $this->grader->grade($case, $execution['response']);
                    $usage = $execution['usage'];
                    $modelResults[] = [
                        'case_id' => $case['id'],
                        'critical' => $case['critical'],
                        'run_index' => $runIndex,
                        'provider_succeeded' => true,
                        'hard_passed' => $grade['hard_passed'],
                        'quality_passed' => $grade['quality_passed'],
                        'hard_failures' => $grade['hard_failures'],
                        'quality_failures' => $grade['quality_failures'],
                        'scores' => $grade['scores'],
                        'latency_ms' => $execution['latency_ms'],
                        'retries' => $execution['retries'],
                        'usage' => $usage,
                        'estimated_cost_usd' => round($this->candidates->estimatedCost($candidate, $usage), 10),
                        'response_hash' => $execution['response_hash'],
                        'response_evidence' => $this->compactResponseEvidence($execution['response']),
                    ];
                    $consecutiveProviderErrors = 0;
                } catch (Throwable $exception) {
                    $consecutiveProviderErrors++;
                    $errorCode = $this->providerErrorCode($exception);
                    $modelResults[] = [
                        'case_id' => $case['id'],
                        'critical' => $case['critical'],
                        'run_index' => $runIndex,
                        'provider_succeeded' => false,
                        'hard_passed' => false,
                        'quality_passed' => false,
                        'hard_failures' => [$errorCode],
                        'quality_failures' => ['unusable_response'],
                        'scores' => [
                            'outcome' => false,
                            'required_content' => false,
                            'forbidden_content' => false,
                            'navigation' => false,
                            'handoff' => false,
                            'plain_language' => false,
                        ],
                        'latency_ms' => 0,
                        'retries' => 0,
                        'usage' => $this->emptyUsage(),
                        'estimated_cost_usd' => 0.0,
                        'response_hash' => null,
                        'response_evidence' => null,
                    ];
                }

                if ($progress !== null) {
                    $progress($candidate['id'], $position + 1, count($schedule), end($modelResults));
                }

                if ($consecutiveProviderErrors >= 3) {
                    break;
                }
            }
            $allResults[$candidate['id']] = $modelResults;
        }

        $summaries = [];
        foreach ($selectedCandidates as $candidate) {
            $summaries[$candidate['id']] = $this->summarize(
                $candidate,
                $allResults[$candidate['id']],
                $schedule,
                $selectedCases,
            );
        }

        $passingCandidateIds = array_keys(array_filter(
            $summaries,
            fn (array $summary): bool => $summary['gate_passed'] === true,
        ));
        $eligible = array_filter(
            $summaries,
            fn (array $summary): bool => $summary['gate_passed'] === true
                && $summary['baseline_eligible'] === true,
        );
        uasort($eligible, fn (array $left, array $right): int => [
            $left['estimated_cost_usd'],
            $left['latency_p95_ms'],
        ] <=> [
            $right['estimated_cost_usd'],
            $right['latency_p95_ms'],
        ]);
        $baselineEligiblePassingCandidateIds = array_keys($eligible);
        $recommended = $plan['full_release_evidence'] ? array_key_first($eligible) : null;

        $candidateManifest = $this->candidates->manifest();
        $report = [
            'report_version' => self::REPORT_VERSION,
            'generated_at' => now('UTC')->toIso8601String(),
            'environment' => app()->environment(),
            'synthetic_only' => true,
            'customer_runtime_invoked' => false,
            'application_database_writes' => false,
            'raw_prompts_persisted' => false,
            'raw_model_outputs_persisted' => false,
            'full_release_evidence' => $plan['full_release_evidence'],
            'versions' => [
                'application_commit' => $this->applicationCommit(),
                'knowledge' => InitialKnowledgeBaseCatalog::VERSION,
                'evaluation_dataset' => InitialKnowledgeEvaluationCatalog::VERSION,
                'candidate_manifest' => AiSupportModelCandidateCatalog::VERSION,
                'prompt' => OfflineAiSupportPromptBuilder::VERSION,
                'grader' => OfflineAiSupportModelGrader::VERSION,
            ],
            'checksums' => [
                'knowledge_sha256' => hash_file('sha256', resource_path('ai-support/knowledge-base/v1.php')),
                'dataset_sha256' => hash_file('sha256', resource_path('ai-support/evaluations/v1.php')),
                'candidates_sha256' => hash_file('sha256', resource_path('ai-support/evaluations/models-v1.php')),
            ],
            'pricing' => [
                'checked_on' => $candidateManifest['pricing_checked_on'],
                'currency' => $candidateManifest['currency'],
            ],
            'plan' => $plan,
            'candidates' => collect($selectedCandidates)->map(fn (array $candidate): array => [
                'id' => $candidate['id'],
                'provider' => $candidate['provider'],
                'model' => $candidate['model'],
                'endpoint' => $candidate['endpoint'],
                'reasoning_effort' => $candidate['reasoning_effort'],
                'max_output_tokens' => $candidate['max_output_tokens'],
                'baseline_eligible' => $candidate['baseline_eligible'],
                'pricing_per_million_tokens' => $candidate['pricing_per_million_tokens'],
                'source_url' => $candidate['source_url'],
            ])->values()->all(),
            'summaries' => $summaries,
            'passing_candidate_ids' => $passingCandidateIds,
            'baseline_eligible_passing_candidate_ids' => $baselineEligiblePassingCandidateIds,
            'recommended_candidate_id' => $recommended,
            'results' => $allResults,
        ];

        $directory = storage_path('app/private/ai-support-evaluations');
        File::ensureDirectoryExists($directory);
        $filename ??= 'ai-support-model-evaluation-'.now('UTC')->format('Ymd-His').'.json';
        if (! preg_match('/^[A-Za-z0-9._-]+\.json$/', $filename)) {
            throw new DomainException('Evaluation report filename must be a simple .json filename.');
        }
        $path = $directory.DIRECTORY_SEPARATOR.$filename;
        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);

        return ['report' => $report, 'path' => $path];
    }

    /** @param list<string> $ids @return list<array<string,mixed>> */
    private function selectCandidates(array $ids): array
    {
        $all = $this->candidates->candidates();
        if ($ids === []) {
            return $all;
        }

        $selected = [];
        foreach (array_values(array_unique($ids)) as $id) {
            $selected[] = $this->candidates->candidate($id);
        }

        return $selected;
    }

    /** @param list<string> $ids @return list<array<string,mixed>> */
    private function selectCases(array $ids): array
    {
        $all = $this->evaluations->allCases();
        if ($ids === []) {
            return $all;
        }

        $byId = collect($all)->keyBy('id');
        $selected = [];
        foreach (array_values(array_unique($ids)) as $id) {
            $case = $byId->get($id);
            if (! is_array($case)) {
                throw new DomainException('Unknown AI Support evaluation case: '.$id.'.');
            }
            $selected[] = $case;
        }

        return $selected;
    }

    /** @param list<array<string,mixed>> $cases @return list<array{case:array<string,mixed>,run_index:int}> */
    private function schedule(array $cases, int $criticalRuns): array
    {
        $schedule = [];
        foreach ($cases as $case) {
            $runs = $case['critical'] === true ? $criticalRuns : 1;
            for ($run = 1; $run <= $runs; $run++) {
                $schedule[] = ['case' => $case, 'run_index' => $run];
            }
        }

        return $schedule;
    }

    /**
     * @param  array<string,mixed>  $candidate
     * @param  list<array<string,mixed>>  $results
     * @param  list<array{case:array<string,mixed>,run_index:int}>  $schedule
     * @param  list<array<string,mixed>>  $cases
     * @return array<string,mixed>
     */
    private function summarize(array $candidate, array $results, array $schedule, array $cases): array
    {
        $expectedCalls = count($schedule);
        $latencies = array_values(array_map(
            fn (array $result): int => (int) $result['latency_ms'],
            array_filter($results, fn (array $result): bool => $result['provider_succeeded'] === true),
        ));
        sort($latencies);
        $successful = count(array_filter($results, fn (array $result): bool => $result['provider_succeeded'] === true));
        $hardFailures = array_filter($results, fn (array $result): bool => $result['hard_passed'] === false);
        $criticalHardFailures = array_filter(
            $results,
            fn (array $result): bool => $result['critical'] === true && $result['hard_passed'] === false,
        );
        $qualityPasses = count(array_filter($results, fn (array $result): bool => $result['quality_passed'] === true));
        $scoreRates = [];
        foreach (['outcome', 'required_content', 'forbidden_content', 'navigation', 'handoff', 'plain_language'] as $score) {
            $scoreRates[$score] = $expectedCalls === 0 ? 0.0 : round(count(array_filter(
                $results,
                fn (array $result): bool => data_get($result, 'scores.'.$score) === true,
            )) / $expectedCalls, 4);
        }

        $criticalCases = array_values(array_filter($cases, fn (array $case): bool => $case['critical'] === true));
        $criticalAllRunPasses = 0;
        foreach ($criticalCases as $case) {
            $caseResults = array_values(array_filter($results, fn (array $result): bool => $result['case_id'] === $case['id']));
            $expectedRuns = count(array_filter($schedule, fn (array $item): bool => $item['case']['id'] === $case['id']));
            if (count($caseResults) === $expectedRuns
                && count(array_filter($caseResults, fn (array $result): bool => $result['hard_passed'] === false)) === 0) {
                $criticalAllRunPasses++;
            }
        }

        $usage = $this->emptyUsage();
        $cost = 0.0;
        $retries = 0;
        $failureCodes = [];
        foreach ($results as $result) {
            foreach ($usage as $key => $_) {
                $usage[$key] += (int) data_get($result, 'usage.'.$key, 0);
            }
            $cost += (float) $result['estimated_cost_usd'];
            $retries += (int) $result['retries'];
            foreach ([...$result['hard_failures'], ...$result['quality_failures']] as $code) {
                $failureCodes[$code] = ($failureCodes[$code] ?? 0) + 1;
            }
        }
        ksort($failureCodes);

        $qualityRate = $expectedCalls === 0 ? 0.0 : $qualityPasses / $expectedCalls;
        $gatePassed = count($results) === $expectedCalls
            && $successful === $expectedCalls
            && count($criticalHardFailures) === 0
            && $scoreRates['outcome'] >= 0.95
            && $scoreRates['required_content'] >= 0.95
            && $scoreRates['forbidden_content'] === 1.0
            && $scoreRates['navigation'] === 1.0
            && $qualityRate >= 0.95;

        return [
            'candidate_id' => $candidate['id'],
            'model' => $candidate['model'],
            'baseline_eligible' => $candidate['baseline_eligible'],
            'expected_calls' => $expectedCalls,
            'completed_calls' => count($results),
            'provider_successes' => $successful,
            'hard_failure_calls' => count($hardFailures),
            'critical_hard_failure_calls' => count($criticalHardFailures),
            'quality_pass_rate' => round($qualityRate, 4),
            'critical_pass_at_all_runs' => count($criticalCases) === 0
                ? 1.0
                : round($criticalAllRunPasses / count($criticalCases), 4),
            'score_rates' => $scoreRates,
            'latency_p50_ms' => $this->percentile($latencies, 50),
            'latency_p95_ms' => $this->percentile($latencies, 95),
            'retries' => $retries,
            'usage' => $usage,
            'estimated_cost_usd' => round($cost, 8),
            'failure_codes' => $failureCodes,
            'gate_passed' => $gatePassed,
        ];
    }

    /** @return array<string,int> */
    private function emptyUsage(): array
    {
        return [
            'input_tokens' => 0,
            'cached_input_tokens' => 0,
            'output_tokens' => 0,
            'reasoning_tokens' => 0,
        ];
    }

    /** @param list<int> $values */
    private function percentile(array $values, int $percentile): int
    {
        if ($values === []) {
            return 0;
        }

        $index = (int) ceil(($percentile / 100) * count($values)) - 1;

        return $values[max(0, min(count($values) - 1, $index))];
    }

    private function applicationCommit(): string
    {
        $headPath = base_path('.git/HEAD');
        $head = is_file($headPath) ? trim((string) file_get_contents($headPath)) : '';
        if (str_starts_with($head, 'ref: ')) {
            $refPath = base_path('.git/'.substr($head, 5));
            if (is_file($refPath)) {
                return trim((string) file_get_contents($refPath));
            }
        }

        return preg_match('/^[0-9a-f]{40}$/', $head) ? $head : 'working-tree';
    }

    /** @param array<string,mixed> $response @return array<string,mixed> */
    private function compactResponseEvidence(array $response): array
    {
        return [
            'outcome' => $response['outcome'] ?? null,
            'navigation_target' => $response['navigation_target'] ?? null,
            'action' => $response['action'] ?? null,
            'handoff_human_only' => $response['handoff_human_only'] ?? null,
            'suppress_after_handoff' => $response['suppress_after_handoff'] ?? null,
            'cited_kb_ids' => array_values((array) ($response['cited_kb_ids'] ?? [])),
            'answer_word_count' => str_word_count((string) ($response['answer'] ?? '')),
        ];
    }

    private function providerErrorCode(Throwable $exception): string
    {
        $message = $exception->getMessage();
        if (preg_match('/HTTP (\d{3}); provider_code=([A-Za-z0-9._-]+); provider_param=([A-Za-z0-9._-]+)/', $message, $matches)) {
            return 'provider_http_'.$matches[1].'_'.$matches[2].'_'.$matches[3];
        }
        if (preg_match('/HTTP (\d{3})/', $message, $matches)) {
            return 'provider_http_'.$matches[1];
        }
        if (str_contains($message, 'connection failed')) {
            return 'provider_connection_error';
        }
        if (str_contains($message, 'refusal')) {
            return 'provider_refusal';
        }
        if (str_contains($message, 'valid JSON') || str_contains($message, 'structured output')) {
            return 'provider_schema_error';
        }
        if (str_contains($message, 'no output text')) {
            return 'provider_missing_output';
        }

        return 'provider_or_schema_error';
    }
}
