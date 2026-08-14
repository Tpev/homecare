<?php

namespace App\Console\Commands;

use App\Services\AiSupport\InteractiveAiSupportModelEvaluationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class EvaluateInteractiveAiSupport extends Command
{
    protected $signature = 'ai-support:evaluate-interactive
        {--execute : Run the frozen synthetic corpus against the provider}
        {--candidate=gpt-5.6-luna-low : Candidate ID}
        {--case=* : Optional exact case IDs}
        {--output= : Optional content-minimized JSON report path under storage/app}';

    protected $description = 'Plan or run the frozen interactive AI Support quality evaluation.';

    public function handle(InteractiveAiSupportModelEvaluationService $evaluation): int
    {
        $candidate = (string) $this->option('candidate');
        $caseIds = array_values(array_filter(array_map('strval', (array) $this->option('case'))));
        try {
            $plan = $evaluation->plan($candidate, $caseIds);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->table(['Property', 'Value'], [
            ['Corpus', $plan['corpus_version']],
            ['Prompt', $plan['prompt_version']],
            ['Candidate', $plan['candidate_id']],
            ['Cases', $plan['case_count']],
            ['Extraction cases', $plan['extraction_case_count']],
        ]);
        if (! $this->option('execute')) {
            $this->warn('Plan only. No provider call, database write, or report write occurred.');

            return self::SUCCESS;
        }

        try {
            $report = $evaluation->execute($candidate, $caseIds);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Interactive evaluation failed: '.$exception->getMessage());

            return self::FAILURE;
        }
        $summary = $report['summary'];
        $this->table(['Metric', 'Value'], [
            ['Passed / failed cases', $summary['passed_cases'].' / '.$summary['failed_cases']],
            ['Hard failures', $summary['hard_failures']],
            ['Extraction accuracy', number_format($summary['extraction_accuracy'] * 100, 2).'%'],
            ['Estimated cost', '$'.number_format($summary['estimated_cost_usd'], 6)],
            ['P50 / P95 latency', $summary['p50_latency_ms'].' / '.$summary['p95_latency_ms'].' ms'],
            ['Release gate', $summary['release_gate_passed'] ? 'PASS' : 'FAIL'],
        ]);

        $output = trim((string) $this->option('output'));
        if ($output !== '') {
            $output = ltrim(str_replace('..', '', str_replace('\\', '/', $output)), '/');
            Storage::disk('local')->put($output, json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $this->info('Content-minimized report written to storage/app/'.$output);
        }

        return $summary['release_gate_passed'] ? self::SUCCESS : self::FAILURE;
    }
}
