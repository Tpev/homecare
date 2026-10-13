<?php

namespace App\Console\Commands;

use App\Services\Screening\Imports\ScreeningDatasetImporter;
use Illuminate\Console\Command;

class ImportScreeningDataset extends Command
{
    protected $signature = 'screening:import {source : nc_dac or nc_sbi} {--directory= : Directory containing the official source files} {--downloaded-at= : Actual source download date, required for local files}';

    protected $description = 'Validate and atomically activate an official screening dataset snapshot';

    public function handle(ScreeningDatasetImporter $importer): int
    {
        try {
            $import = $importer->import($this->argument('source'), $this->option('directory'), $this->option('downloaded-at'));
            $this->info("Activated {$import->source} snapshot {$import->id}: {$import->record_count} source IDs.");

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $safeMessage = get_class($exception) === \RuntimeException::class ? $exception->getMessage() : 'Import failed during download, parsing, or database write. The previous snapshot remains active.';
            \Illuminate\Support\Facades\Log::error('Screening dataset import failed.', ['source' => $this->argument('source'), 'exception_class' => get_class($exception), 'message' => $safeMessage]);
            $this->error($safeMessage);

            return self::FAILURE;
        }
    }
}
