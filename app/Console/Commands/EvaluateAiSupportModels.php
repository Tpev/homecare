<?php

namespace App\Console\Commands;

use App\Services\AiSupport\OfflineAiSupportModelEvaluationService;
use Illuminate\Console\Command;
use Throwable;

class EvaluateAiSupportModels extends Command
{
    protected $signature = 'ai-support:evaluate-models
        {--run : Make real provider calls; omitted means plan only}
        {--model=* : Candidate ID from the governed candidate manifest}
        {--case=* : Optional case ID for a non-release smoke run}
        {--critical-runs=5 : Repetitions for each critical case}
        {--output= : Simple JSON filename under private evaluation storage}';

    protected $description = 'Plan or run the disabled-by-default synthetic offline AI Support model comparison.';

    public function handle(OfflineAiSupportModelEvaluationService $evaluations): int
    {
        $candidateIds = array_values(array_filter(array_map('strval', (array) $this->option('model'))));
        $caseIds = array_values(array_filter(array_map('strval', (array) $this->option('case'))));
        $criticalRuns = (int) $this->option('critical-runs');

        try {
            $plan = $evaluations->plan($candidateIds, $criticalRuns, $caseIds);
        } catch (Throwable $exception) {
            $this->error('AI Support model evaluation plan failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Artifact', 'Value'], [
            ['Candidates', implode(', ', $plan['candidate_ids'])],
            ['Synthetic cases', $plan['case_count']],
            ['Critical cases', $plan['critical_case_count']],
            ['Runs per critical case', $plan['critical_runs']],
            ['Calls per candidate', $plan['calls_per_candidate']],
            ['Total planned provider calls', $plan['total_calls']],
            ['Full release evidence', $plan['full_release_evidence'] ? 'yes' : 'no'],
            ['Customer runtime calls', 0],
            ['Application database writes', 0],
        ]);

        if (! $this->option('run')) {
            $this->info('Plan only. No provider call was made. Use --run with the separate offline-evaluation switch enabled.');

            return self::SUCCESS;
        }

        $this->warn('Running synthetic offline evaluation. Customer runtime and domain tools remain unavailable.');
        $progress = function (string $candidateId, int $completed, int $total, array $latest): void {
            if ($completed === 1 || $completed % 10 === 0 || $completed === $total || $latest['provider_succeeded'] === false) {
                $status = $latest['provider_succeeded'] === true
                    ? ($latest['hard_passed'] === true ? 'hard-pass' : 'hard-fail')
                    : 'provider/schema error';
                $this->line($candidateId.': '.$completed.'/'.$total.' — '.$status);
            }
        };

        try {
            $result = $evaluations->run(
                $candidateIds,
                $criticalRuns,
                $caseIds,
                $this->option('output') ? (string) $this->option('output') : null,
                $progress,
            );
        } catch (Throwable $exception) {
            $this->error('AI Support model evaluation failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $rows = [];
        foreach ($result['report']['summaries'] as $summary) {
            $rows[] = [
                $summary['candidate_id'],
                $summary['completed_calls'].'/'.$summary['expected_calls'],
                $summary['hard_failure_calls'],
                $summary['critical_hard_failure_calls'],
                number_format($summary['quality_pass_rate'] * 100, 1).'%',
                $summary['latency_p50_ms'].' / '.$summary['latency_p95_ms'],
                '$'.number_format($summary['estimated_cost_usd'], 6),
                $summary['gate_passed'] ? 'PASS' : 'FAIL',
            ];
        }
        $this->table(
            ['Candidate', 'Calls', 'Hard failures', 'Critical hard', 'Quality', 'p50/p95 ms', 'Cost', 'Gate'],
            $rows,
        );
        $this->line('Compact report: '.$result['path']);
        if ($result['report']['recommended_candidate_id'] !== null) {
            $this->info('Lowest-cost passing candidate: '.$result['report']['recommended_candidate_id']);
        } elseif (! $result['report']['full_release_evidence']) {
            $this->line('Diagnostic run only. It cannot select or approve DEC-012.');
        } else {
            $this->warn('No candidate passed every baseline gate. DEC-012 must remain pending.');
        }

        $providerAndHardPass = collect($result['report']['summaries'])->every(
            fn (array $summary): bool => $summary['completed_calls'] === $summary['expected_calls']
                && $summary['provider_successes'] === $summary['expected_calls']
                && $summary['hard_failure_calls'] === 0,
        );
        if (! $providerAndHardPass) {
            return self::FAILURE;
        }
        if ($result['report']['full_release_evidence'] && $result['report']['recommended_candidate_id'] === null) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
