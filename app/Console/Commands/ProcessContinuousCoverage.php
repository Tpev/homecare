<?php

namespace App\Console\Commands;

use App\Services\ContinuousCoverage\ContinuousCoverageOperationsService;
use Illuminate\Console\Command;

class ProcessContinuousCoverage extends Command
{
    protected $signature = 'homecare:process-continuous-coverage';

    protected $description = 'Generate and safely prepare near-term Continuous Coverage shifts.';

    public function handle(ContinuousCoverageOperationsService $operations): int
    {
        $counts = $operations->process();
        $this->info(sprintf(
            'Continuous Coverage: %d plans, %d shifts, %d bookings, %d payments, %d failures.',
            $counts['plans'],
            $counts['shifts_created'],
            $counts['bookings_linked'],
            $counts['payments_prepared'],
            $counts['failures'],
        ));

        return $counts['failures'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
