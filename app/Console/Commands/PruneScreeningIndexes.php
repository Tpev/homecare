<?php

namespace App\Console\Commands;

use App\Services\Screening\Imports\ScreeningDatasetRetentionService;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class PruneScreeningIndexes extends Command
{
    protected $signature = 'screening:prune-indexes {source? : nc_dac or nc_sbi; omit for both} {--execute : Delete eligible historical searchable rows; otherwise preview only}';

    protected $description = 'Preview or prune inactive screening indexes while retaining source archives and review evidence';

    public function handle(ScreeningDatasetRetentionService $retention): int
    {
        try {
            $execute = (bool) $this->option('execute');
            $results = $execute ? $retention->execute($this->argument('source')) : $retention->preview($this->argument('source'));
            if ($results === []) {
                $this->info('No eligible inactive dataset indexes.');
            } else {
                $this->table(['Import', 'Source', 'Name rows', 'Record rows'], array_map(fn ($row) => array_values($row), $results));
            }
            $this->info($execute
                ? 'Cleanup complete. Import metadata, archives, cases, candidates and audit evidence were preserved.'
                : 'Preview only; no data changed. Add --execute to remove the listed searchable rows.');

            return self::SUCCESS;
        } catch (Throwable $error) {
            $this->error($error instanceof RuntimeException && get_class($error) === RuntimeException::class
                ? $error->getMessage() : 'Index cleanup stopped. Completed batches remain removed; rerun this command to resume.');

            return self::FAILURE;
        }
    }
}
