<?php

namespace App\Console\Commands;

use App\Services\RegularCare\CarePlanPaymentWindowService;
use Illuminate\Console\Command;

class PrepareRegularCarePayments extends Command
{
    protected $signature = 'homecare:prepare-regular-care-payments
        {--dry-run : Preview due authorizations without calling Stripe or writing}
        {--hours=48 : Authorization window in hours}
        {--plan= : Limit preparation to one care plan ID}';

    protected $description = 'Authorize payment for regular-care visits entering the payment window.';

    public function handle(CarePlanPaymentWindowService $window): int
    {
        $hours = max(1, min(168, (int) $this->option('hours')));
        $dryRun = (bool) $this->option('dry-run');
        $planId = $this->option('plan');

        $bookings = $window->dueBookingsQuery($hours)
            ->when($planId, fn ($query) => $query->where('care_plan_id', (int) $planId))
            ->orderBy('scheduled_start_at')
            ->get();

        if ($bookings->isEmpty()) {
            $this->info('No regular-care visits need payment preparation.');

            return self::SUCCESS;
        }

        foreach ($bookings as $booking) {
            $this->line(($dryRun ? 'Due' : 'Preparing').' booking #'.$booking->id.' at '.$booking->scheduled_start_at?->toDateTimeString().'.');
        }

        $result = $window->prepareBookings($bookings, $dryRun);
        if ($dryRun) {
            $this->info('Dry run: '.$result['ready']->count().' visit(s) would be authorized.');

            return self::SUCCESS;
        }

        $this->info($result['ready']->count().' visit(s) protected; '.$result['needs_action']->count().' need family action.');

        return self::SUCCESS;
    }
}
