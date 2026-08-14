<?php

namespace App\Console\Commands;

use App\Services\AiSupport\AiSupportControlService;
use App\Services\AiSupport\AiSupportReadinessService;
use Illuminate\Console\Command;
use Throwable;

class AiSupportReleasePreflight extends Command
{
    protected $signature = 'ai-support:release-preflight {--json : Emit the content-free snapshot as JSON}';

    protected $description = 'Read the fail-closed AI Support release gates without changing controls, grants, evidence, or data.';

    public function handle(AiSupportReadinessService $readiness, AiSupportControlService $controls): int
    {
        try {
            $snapshot = $readiness->snapshot($controls);
        } catch (Throwable $exception) {
            $this->error('AI Support preflight could not be completed: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($snapshot, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(
                ['Check', 'Result', 'Detail'],
                collect($snapshot['checks'])->map(fn (array $check): array => [
                    $check['label'],
                    $check['passed'] ? 'PASS' : 'BLOCKED',
                    $check['detail'],
                ])->all(),
            );
            $this->newLine();
            $snapshot['ready'] ? $this->info($snapshot['state']) : $this->warn($snapshot['state']);
            $this->line('Read-only preflight. No control, grant, provider call, or evidence record was changed.');
        }

        return $snapshot['ready'] ? self::SUCCESS : self::FAILURE;
    }
}
