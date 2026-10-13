<?php

namespace App\Console\Commands;

use App\Services\AiSupport\AiSupportHealthMonitorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MonitorAiSupportHealth extends Command
{
    protected $signature = 'ai-support:monitor-health';

    protected $description = 'Evaluate AI Support latency, cost, and operations-notification health and open bounded incidents.';

    public function handle(AiSupportHealthMonitorService $monitor): int
    {
        if (! Schema::hasTable('ai_support_incidents')) {
            $this->warn('AI Support health monitor skipped until the readiness migration is present.');

            return self::SUCCESS;
        }

        try {
            $result = $monitor->run();
        } catch (Throwable $exception) {
            report($exception);
            $this->error('AI Support health monitor failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Metric', 'Value'], [
            ['Successful provider calls / P95, last hour', $result['conversation_sample'].' / '.($result['conversation_sample'] ? $result['conversation_p95_ms'].' ms' : 'No sample')],
            ['Provider latency warning sample', $result['conversation_sample_sufficient'] ? 'Sufficient' : 'Below '.$result['minimum_sample'].' calls; no latency pass implied'],
            ['Failed model turns, last hour', $result['failed_model_turns']],
            ['Recorded tool sample / P95, last hour', $result['tool_sample'].' / '.($result['tool_sample'] ? $result['tool_p95_ms'].' ms' : 'No sample')],
            ['Failed operations notifications, last hour', $result['failed_notifications']],
            ['Operations notifications queued at least 10 minutes', $result['queued_notifications_over_ten_minutes']],
            ['Estimated recorded model cost today', '$'.number_format($result['daily_cost_microunits'] / 1_000_000, 6)],
            ['Daily cost ceiling', $result['daily_cost_stopped'] ? 'Reached; inspect current Availability' : 'Not reached'],
        ]);

        return self::SUCCESS;
    }
}
