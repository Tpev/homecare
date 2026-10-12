<?php

namespace App\Console\Commands;

use App\Services\Marketing\RegisteredCaregiverCount;
use Illuminate\Console\Command;

class RefreshCaregiverCount extends Command
{
    protected $signature = 'homecare:refresh-caregiver-count';

    protected $description = 'Refresh the saved number of registered caregivers for the homepage';

    public function handle(RegisteredCaregiverCount $count): int
    {
        $this->info('Registered caregivers: '.number_format($count->refresh()));

        return self::SUCCESS;
    }
}
