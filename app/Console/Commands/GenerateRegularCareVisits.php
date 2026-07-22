<?php

namespace App\Console\Commands;

use App\Models\CarePlan;
use App\Services\RegularCare\CarePlanOccurrenceService;
use Illuminate\Console\Command;

class GenerateRegularCareVisits extends Command
{
    protected $signature = 'homecare:generate-regular-care-visits
        {--dry-run : Preview visits without writing to the database}
        {--plan= : Limit generation to one care plan ID}
        {--weeks= : Number of weeks to keep materialized; defaults to marketplace.regular_care.visit_window_weeks}';

    protected $description = 'Materialize the rolling visit window for active regular-care plans.';

    public function handle(CarePlanOccurrenceService $occurrences): int
    {
        $weeks = max(1, min(12, (int) ($this->option('weeks') ?: config('marketplace.regular_care.visit_window_weeks', 6))));
        $dryRun = (bool) $this->option('dry-run');
        $planId = $this->option('plan');

        $query = CarePlan::query()
            ->with(['family', 'caregiver', 'relationship'])
            ->whereIn('status', [CarePlan::STATUS_ACTIVE, CarePlan::STATUS_PAYMENT_ATTENTION, CarePlan::STATUS_PAUSED])
            ->when($planId, fn ($builder) => $builder->whereKey((int) $planId))
            ->orderBy('id');

        $plans = $query->get();
        if ($plans->isEmpty()) {
            $this->info('No active regular-care plans matched.');

            return self::SUCCESS;
        }

        $created = 0;
        $existing = 0;
        foreach ($plans as $plan) {
            $through = now($plan->timezone ?: config('app.timezone'))->addWeeks($weeks)->endOfDay();
            $result = $occurrences->materialize($plan, $dryRun, $through);
            $wouldCreate = count($result['planned']) - $result['existing']->count();
            $created += $dryRun ? max(0, $wouldCreate) : $result['created']->count();
            $existing += $result['existing']->count();

            $verb = $dryRun ? 'would create' : 'created';
            $this->line('Plan #'.$plan->id.': '.$verb.' '.($dryRun ? max(0, $wouldCreate) : $result['created']->count()).' visit(s); '.$result['existing']->count().' already present.');
        }

        $prefix = $dryRun ? 'Dry run: ' : '';
        $this->info($prefix.$created.' visit(s) '.($dryRun ? 'would be created' : 'created').'; '.$existing.' existing visit(s) preserved.');

        return self::SUCCESS;
    }
}
