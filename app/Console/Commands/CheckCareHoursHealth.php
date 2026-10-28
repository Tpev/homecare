<?php

namespace App\Console\Commands;

use App\Services\CareHours\CareHourHealthService;
use Illuminate\Console\Command;
use Throwable;

class CheckCareHoursHealth extends Command
{
    protected $signature = 'care-hours:health
        {--json : Emit sanitized machine-readable output}
        {--max-age-minutes=30 : Alert on unresolved records older than this many minutes}
        {--checkout-age-minutes=1500 : Checkout grace period, at least 1440 minutes}';

    protected $description = 'Read-only prepaid backlog check: exit 0 healthy, 1 attention, 2 unknown; no provider requests or notifications.';

    public function handle(CareHourHealthService $health): int
    {
        $age = filter_var($this->option('max-age-minutes'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10080]]);
        $checkoutAge = filter_var($this->option('checkout-age-minutes'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1440, 'max_range' => 10080]]);
        if ($age === false || $checkoutAge === false) {
            $result = ['status' => 'unknown', 'read_only' => true, 'reason' => 'invalid_threshold', 'message' => 'Use 1–10080 minutes for max-age and 1440–10080 minutes for checkout-age.'];
        } else {
            try {
                $result = $health->inspect($age, $checkoutAge);
            } catch (Throwable) {
                // Database exceptions can contain connection details or SQL values; never emit them to monitoring.
                $result = ['status' => 'unknown', 'read_only' => true, 'reason' => 'inspection_failed', 'message' => 'Backlog inspection failed. Check database availability and schema using the deployment runbook.'];
            }
        }
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->line('Care hours: '.$result['status'].' (read-only).');
            if (isset($result['message'])) {
                $this->line($result['message']);
            }
            foreach ($result['schema']['missing'] ?? [] as $missing) {
                $this->line('Missing schema: '.$missing);
            }
            if (! empty($result['checks'])) {
                $this->table(['Check', 'Unresolved', 'Overdue', 'Threshold (minutes)'], collect($result['checks'])->map(
                    fn (array $check, string $name) => [$name, $check['count'], $check['overdue_count'], $check['threshold_minutes']]
                )->values()->all());
                foreach ($result['notes'] as $note) {
                    $this->line($note);
                }
            }
        }

        return match ($result['status']) {
            'healthy' => self::SUCCESS,
            'attention' => self::FAILURE,
            default => self::INVALID,
        };
    }
}
