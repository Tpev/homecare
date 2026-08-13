<?php

namespace App\Console\Commands;

use App\Services\AiSupport\InitialKnowledgeEvaluationService;
use Illuminate\Console\Command;
use Throwable;

class ValidateInitialAiSupportKnowledge extends Command
{
    protected $signature = 'ai-support:validate-initial-content';

    protected $description = 'Validate the approved initial KB manifest and executable evaluation fixture catalog.';

    public function handle(InitialKnowledgeEvaluationService $evaluations): int
    {
        try {
            $result = $evaluations->validate();
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Initial AI Support content validation failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Artifact', 'Value'], [
            ['Knowledge version', $result['knowledge_version']],
            ['Evaluation version', $result['evaluation_version']],
            ['Entries', $result['entry_count']],
            ['Entry-level evaluation cases', $result['entry_case_count']],
            ['Critical cross-entry regressions', $result['regression_case_count']],
            ['Total evaluation cases', $result['case_count']],
            ['Critical cases', $result['critical_case_count']],
            ['Production model calls', 0],
            ['User-visible behavior', 'unchanged'],
        ]);

        if (! $result['passed']) {
            $this->error('Initial AI Support content validation failed.');
            foreach ($result['errors'] as $error) {
                $this->line(' - '.$error);
            }

            return self::FAILURE;
        }

        $this->info('Initial AI Support content validation passed.');

        return self::SUCCESS;
    }
}
