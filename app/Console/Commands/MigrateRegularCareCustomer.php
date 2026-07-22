<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\RegularCare\RegularCareMigrationService;

class MigrateRegularCareCustomer extends AuditRegularCare
{
    protected $signature = 'homecare:migrate-regular-care-customer
        {--request= : Source recurring care request ID}
        {--plan= : Existing care plan ID}
        {--email= : Family email used to resolve the latest recurring request}
        {--days= : Confirmed weekdays as comma-separated 0=Sunday through 6=Saturday}
        {--start-time= : Confirmed local start time in HH:MM}
        {--end-time= : Confirmed local end time in HH:MM}
        {--starts-on= : Confirmed start date in YYYY-MM-DD}
        {--ends-on= : Optional confirmed end date in YYYY-MM-DD}
        {--timezone= : Confirmed IANA timezone, for example America/New_York}
        {--actor= : Administrator user ID responsible for this migration}
        {--confirm-request= : Exact request ID confirmation required with --execute}
        {--execute : Apply the migration after reviewing the dry run}';

    protected $description = 'Safely migrate one existing recurring customer; defaults to dry-run.';

    public function handle(RegularCareMigrationService $migration): int
    {
        $request = $this->resolveRequest();
        if (! $request) {
            $this->error('No matching recurring request or care plan was found.');

            return self::FAILURE;
        }
        $schedule = $this->confirmedScheduleOptions();
        if ($schedule === false) {
            return self::FAILURE;
        }
        $report = $migration->inspect($request, $schedule ?: null);
        $this->renderReport($report);
        if (! $this->option('execute')) {
            $this->info('Dry run only. Re-run with --execute to apply database changes. Stripe was not called.');

            return self::SUCCESS;
        }

        if (! $schedule) {
            $this->error('Execution requires --days, --start-time, --end-time, --starts-on, and --timezone from the customer-confirmed schedule.');

            return self::FAILURE;
        }
        if ((int) $this->option('confirm-request') !== (int) $request->id) {
            $this->error('Execution requires --confirm-request='.$request->id.' to confirm the exact customer record.');

            return self::FAILURE;
        }
        $actor = User::query()->find((int) $this->option('actor'));
        if (! $actor?->isAdministrator()) {
            $this->error('Execution requires --actor with a valid administrator user ID.');

            return self::FAILURE;
        }

        $plan = $migration->execute($request, $schedule, $actor->id);
        $this->info('Migration complete for care plan #'.$plan->id.'. Existing booking and payment history were preserved.');
        $this->renderReport($migration->inspect($request->fresh(), $schedule));

        return self::SUCCESS;
    }

    /** @return array<string,mixed>|false|null */
    private function confirmedScheduleOptions(): array|false|null
    {
        $values = [
            'days' => $this->option('days'),
            'start_time' => $this->option('start-time'),
            'end_time' => $this->option('end-time'),
            'starts_on' => $this->option('starts-on'),
            'ends_on' => $this->option('ends-on'),
            'timezone' => $this->option('timezone'),
        ];
        $provided = collect($values)->filter(fn ($value) => filled($value));
        if ($provided->isEmpty()) {
            return null;
        }
        foreach (['days', 'start_time', 'end_time', 'starts_on', 'timezone'] as $required) {
            if (! filled($values[$required])) {
                $this->error('A confirmed schedule is incomplete. Missing --'.str_replace('_', '-', $required).'.');

                return false;
            }
        }

        return $values;
    }
}
