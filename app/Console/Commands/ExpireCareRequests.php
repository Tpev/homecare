<?php

namespace App\Console\Commands;

use App\Services\CareRequests\CareRequestExpirationService;
use Illuminate\Console\Command;

class ExpireCareRequests extends Command
{
    protected $signature = 'homecare:expire-care-requests {--dry-run : Count eligible requests without changing them}';

    protected $description = 'Move unbooked one-time requests to history 24 hours after their service end, including existing old requests';

    public function handle(CareRequestExpirationService $expiration): int
    {
        if ($this->option('dry-run')) {
            $this->info($expiration->eligible()->count().' unbooked request(s) eligible for expiration.');

            return self::SUCCESS;
        }

        $this->info($expiration->expireDue().' unbooked request(s) expired and retained in history.');

        return self::SUCCESS;
    }
}
