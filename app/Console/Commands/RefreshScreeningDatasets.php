<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RefreshScreeningDatasets extends Command
{
    protected $signature = 'screening:refresh-datasets';

    protected $description = 'Download and import every required public screening source';

    public function handle(): int
    {
        $failed = false;
        foreach (config('screening.required_sources', ['nc_dac', 'nc_sbi']) as $source) {
            $failed = $this->call('screening:import', ['source' => $source]) !== self::SUCCESS || $failed;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
