<?php

namespace App\Console\Commands;

use App\Services\Family\FamilyOnboardingDeliveryService;
use Illuminate\Console\Command;

class DispatchFamilyOnboardingEmails extends Command
{
    protected $signature = 'family-onboarding:dispatch-emails {--limit=100}';

    protected $description = 'Recover pending onboarding emails and flag interrupted deliveries for review';

    public function handle(FamilyOnboardingDeliveryService $deliveries): int
    {
        $count = $deliveries->recover(max(1, min(1000, (int) $this->option('limit'))));
        $this->info("Dispatched {$count} pending onboarding deliveries.");

        return self::SUCCESS;
    }
}
