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
            ['Conversation sample / P95', $result['conversation_sample'].' / '.$result['conversation_p95_ms'].' ms'],
            ['Tool sample / P95', $result['tool_sample'].' / '.$result['tool_p95_ms'].' ms'],
            ['Failed operations notifications', $result['failed_notifications']],
            ['Daily model cost', '$'.number_format($result['daily_cost_microunits'] / 1_000_000, 6)],
            ['Daily cost stop', $result['daily_cost_stopped'] ? 'ACTIVE' : 'Not reached'],
        ]);

        return self::SUCCESS;
    }
}
