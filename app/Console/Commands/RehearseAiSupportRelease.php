<?php

namespace App\Console\Commands;

use App\Services\AiSupport\InteractiveAiSupportModelEvaluationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class RehearseAiSupportRelease extends Command
{
    protected $signature = 'ai-support:rehearse-release
        {--execute : Run the isolated browser rehearsal}
        {--live-provider : Also run the frozen synthetic corpus against the provider}
        {--skip-browser : Run only the synthetic provider gate; result is partial}
        {--output=ai-support/rehearsal-latest.json : Content-free report path under storage/app}';

    protected $description = 'Run the production-like AI Support rehearsal with synthetic data and no production side effects.';

    public function handle(InteractiveAiSupportModelEvaluationService $evaluation): int
    {
        if (app()->environment('production')) {
            $this->error('The rehearsal is prohibited in production. Use a development or dedicated rehearsal checkout.');

            return self::FAILURE;
        }
        if ((bool) config('ai_support.runtime_available', false)) {
            $this->error('The rehearsal refuses to run while the customer runtime guard is available.');

            return self::FAILURE;
        }

        $this->table(['Rehearsal stage', 'Planned behavior'], [
            ['Browser', $this->option('skip-browser') ? 'Skipped; report will be partial' : 'Isolated SQLite, seeded synthetic users, no live provider'],
            ['Provider', $this->option('live-provider') ? 'Frozen synthetic corpus through Responses API' : 'Skipped; no model call'],
            ['Production data', 'Prohibited'],
            ['Temporary database', 'Destroyed after the run'],
        ]);
        if (! $this->option('execute') && ! $this->option('live-provider')) {
            $this->warn('Plan only. No process, provider call, database mutation, or report write occurred.');

            return self::SUCCESS;
        }

        $database = database_path('playwright.sqlite');
        $databaseRoot = realpath(database_path());
        if ($databaseRoot === false || dirname($database) !== $databaseRoot) {
            $this->error('The isolated database target could not be validated.');

            return self::FAILURE;
        }
        if (! $this->trackedWorktreeIsClean()) {
            $this->error('The rehearsal requires a committed release candidate with no tracked working-tree changes.');

            return self::FAILURE;
        }

        $report = [
            'contract' => 'DEC-067',
            'generated_at' => now('UTC')->toIso8601String(),
            'commit' => $this->commitHash(),
            'tracked_worktree_clean' => true,
            'environment' => app()->environment(),
            'browser' => ['attempted' => false, 'passed' => false],
            'provider' => ['attempted' => false, 'passed' => false],
            'temporary_database_destroyed' => false,
            'content_policy' => 'metrics_hashes_and_safe_identifiers_only',
        ];

        try {
            if (! $this->option('skip-browser')) {
                File::delete($database);
                $report['browser']['attempted'] = true;
                $build = Process::path(base_path())->timeout(600)->run($this->nodeCommand('npm', ['run', 'build']));
                if (! $build->successful()) {
                    throw new RuntimeException('Asset build failed during rehearsal.');
                }
                $browser = Process::path(base_path())->timeout(900)->run($this->nodeCommand('npx', [
                    'playwright', 'test', 'tests/e2e/specs/ai-support-interactive.spec.ts', '--project=chromium',
                ]));
                if (! $browser->successful()) {
                    throw new RuntimeException('Isolated AI Support browser rehearsal failed.');
                }
                $report['browser']['passed'] = true;
            }

            if ($this->option('live-provider')) {
                config(['ai_support.offline_evaluation_enabled' => true]);
                $report['provider']['attempted'] = true;
                $modelReport = $evaluation->execute('gpt-5.6-luna-low');
                $report['provider'] = [
                    'attempted' => true,
                    'passed' => (bool) $modelReport['summary']['release_gate_passed'],
                    'corpus_version' => $modelReport['corpus_version'],
                    'prompt_version' => $modelReport['prompt_version'],
                    'candidate_id' => $modelReport['candidate_id'],
                    'summary' => $modelReport['summary'],
                    'failed_cases' => collect($modelReport['results'])
                        ->where('passed', false)
                        ->map(fn (array $result): array => [
                            'case_id' => $result['case_id'],
                            'hard_failure' => $result['hard_failure'],
                            'error_codes' => $result['error_codes'],
                        ])
                        ->values()
                        ->all(),
                    'result_hash' => hash('sha256', json_encode($modelReport['results'], JSON_THROW_ON_ERROR)),
                ];
                if (! $report['provider']['passed']) {
                    throw new RuntimeException('The frozen live-provider release gate failed.');
                }
            }
        } catch (Throwable $exception) {
            report($exception);
            $report['failure_code'] = str($exception->getMessage())->slug('_')->limit(120, '')->value();
            $this->error($exception->getMessage());
        } finally {
            File::delete($database);
            $report['temporary_database_destroyed'] = ! File::exists($database);
        }

        $complete = (bool) $report['browser']['passed']
            && (bool) $report['provider']['passed']
            && (bool) $report['temporary_database_destroyed'];
        $report['complete'] = $complete;
        $output = ltrim(str_replace('..', '', str_replace('\\', '/', (string) $this->option('output'))), '/');
        Storage::disk('local')->put($output, json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $this->line('Content-free report written to storage/app/'.$output);
        $complete ? $this->info('COMPLETE REHEARSAL PASS') : $this->warn('PARTIAL OR FAILED REHEARSAL');

        return $complete ? self::SUCCESS : self::FAILURE;
    }

    /** @param list<string> $arguments @return list<string> */
    private function nodeCommand(string $binary, array $arguments): array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $binary .= '.cmd';
        }

        return [$binary, ...$arguments];
    }

    private function commitHash(): string
    {
        $result = Process::path(base_path())->timeout(10)->run(['git', 'rev-parse', 'HEAD']);

        return $result->successful() ? trim($result->output()) : 'unavailable';
    }

    private function trackedWorktreeIsClean(): bool
    {
        $result = Process::path(base_path())->timeout(10)->run([
            'git', 'status', '--porcelain', '--untracked-files=no',
        ]);

        return $result->successful() && trim($result->output()) === '';
    }
}
