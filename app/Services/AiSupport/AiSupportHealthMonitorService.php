<?php

namespace App\Services\AiSupport;

use App\Models\AiSupportIncident;
use App\Models\AiSupportInteractionEvent;
use App\Models\AiSupportReadinessEvidence;
use App\Models\MarketplaceNotificationDelivery;
use Illuminate\Support\Facades\Cache;

class AiSupportHealthMonitorService
{
    public const HEARTBEAT_KEY = 'ai_support.health_monitor';

    public function __construct(
        private readonly AiSupportIncidentService $incidents,
        private readonly AiSupportControlService $controls,
    ) {}

    /** @return array<string,int|bool> */
    public function run(): array
    {
        $heartbeat = [
            'last_attempt_at' => now()->toIso8601String(),
            'last_success_at' => data_get(Cache::get(self::HEARTBEAT_KEY, []), 'last_success_at'),
            'status' => 'running',
        ];
        Cache::put(self::HEARTBEAT_KEY, $heartbeat, now()->addDays(7));
        try {
            $result = $this->evaluate(true);
            Cache::put(self::HEARTBEAT_KEY, [
                ...$heartbeat, 'status' => 'succeeded', 'last_success_at' => now()->toIso8601String(),
            ], now()->addDays(7));

            return $result;
        } catch (\Throwable $exception) {
            Cache::put(self::HEARTBEAT_KEY, [...$heartbeat, 'status' => 'failed'], now()->addDays(7));
            throw $exception;
        }
    }

    /** Read current measurements without opening incidents or changing controls. */
    public function snapshot(): array
    {
        return ['metrics' => $this->evaluate(false), 'last_run' => Cache::get(self::HEARTBEAT_KEY, [])];
    }

    /** @return array<string,int|bool> */
    private function evaluate(bool $recordIncidents): array
    {
        $hourStartedAt = now()->subHour();
        $minimum = (int) config('ai_support.performance_minimum_sample', 20);
        $conversationLatencies = AiSupportInteractionEvent::query()
            ->where('event_type', 'model_turn_completed')
            ->where('occurred_at', '>=', $hourStartedAt)
            ->whereNotNull('latency_ms')
            ->pluck('latency_ms')
            ->map(fn ($value): int => (int) $value)
            ->all();
        $conversationP95 = $this->percentile($conversationLatencies, 95);
        $conversationWarning = count($conversationLatencies) >= $minimum
            && $conversationP95 > (int) config('ai_support.conversation_p95_target_ms', 5_000);
        if ($recordIncidents && $conversationWarning) {
            $this->incidents->open(
                'conversation_latency_p95_warning',
                'Successful AI Support provider-call P95 exceeded its configured latency target across the minimum sample in the last hour.',
                safeMetadata: [
                    'sample_size' => count($conversationLatencies), 'p95_ms' => $conversationP95,
                    'metric_scope' => 'successful_provider_calls_last_hour',
                    'target_ms' => (int) config('ai_support.conversation_p95_target_ms', 5_000),
                ],
                severity: AiSupportIncident::SEVERITY_WARNING,
            );
        }

        $toolLatencies = AiSupportInteractionEvent::query()
            ->whereNotNull('tool_id')
            ->where('occurred_at', '>=', $hourStartedAt)
            ->whereNotNull('latency_ms')
            ->pluck('latency_ms')
            ->map(fn ($value): int => (int) $value)
            ->all();
        $toolP95 = $this->percentile($toolLatencies, 95);
        $toolWarning = count($toolLatencies) >= $minimum
            && $toolP95 > (int) config('ai_support.tool_p95_target_ms', 8_000);
        if ($recordIncidents && $toolWarning) {
            $this->incidents->open(
                'tool_latency_p95_warning',
                'Recorded AI Support tool-action P95 exceeded its configured latency target across the minimum sample in the last hour.',
                safeMetadata: ['sample_size' => count($toolLatencies), 'p95_ms' => $toolP95],
                severity: AiSupportIncident::SEVERITY_WARNING,
            );
        }

        $lastPassedOperationsAlertAt = AiSupportReadinessEvidence::query()
            ->current()
            ->where('evidence_key', 'operations_alert_delivery')
            ->where('status', AiSupportReadinessEvidence::STATUS_PASSED)
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->value('created_at');
        $failedNotifications = MarketplaceNotificationDelivery::query()
            ->where('status', 'failed')
            ->where('updated_at', '>=', $hourStartedAt)
            ->where(function ($query): void {
                $query->where('dedupe_key', 'like', 'ai-support-%')
                    ->orWhere('dedupe_key', 'like', 'support-handoff-%');
            })
            ->when($lastPassedOperationsAlertAt, function ($query) use ($lastPassedOperationsAlertAt): void {
                $query->where(function ($query) use ($lastPassedOperationsAlertAt): void {
                    $query->where('dedupe_key', 'not like', 'ai-support-operations-test-%')
                        ->orWhere('updated_at', '>', $lastPassedOperationsAlertAt);
                });
            })
            ->count();
        if ($recordIncidents && $failedNotifications > 0) {
            $this->incidents->open(
                'operations_notification_failed',
                'One or more AI Support operations notifications failed during the last hour.',
                safeMetadata: ['failed_delivery_count' => $failedNotifications],
            );
        }

        $queuedNotifications = MarketplaceNotificationDelivery::query()
            ->where('status', 'queued')->where('created_at', '<=', now()->subMinutes(10))
            ->where(fn ($query) => $query->where('dedupe_key', 'like', 'ai-support-%')
                ->orWhere('dedupe_key', 'like', 'support-handoff-%'))
            ->count();
        if ($recordIncidents && $queuedNotifications > 0) {
            $this->incidents->open(
                'operations_notification_delayed',
                'AI Support operations or human-handoff notifications have been queued for at least ten minutes.',
                safeMetadata: ['queued_delivery_count' => $queuedNotifications, 'minimum_age_minutes' => 10],
                severity: AiSupportIncident::SEVERITY_WARNING,
            );
        }

        $dailyCost = (int) AiSupportInteractionEvent::query()
            ->where('occurred_at', '>=', now()->startOfDay())
            ->sum('cost_microunits');
        $dailyCostStopped = $dailyCost >= (int) config('ai_support.pilot_daily_cost_stop_microunits', 5_000_000);
        if ($recordIncidents && $dailyCostStopped) {
            $this->controls->systemStop(
                'capability.support_answers_v1',
                'pilot_daily_cost_stop',
                'Automatic stop because the AI Support pilot reached its daily provider cost ceiling.',
            );
        }

        return [
            'conversation_sample' => count($conversationLatencies),
            'conversation_p95_ms' => $conversationP95,
            'conversation_warning' => $conversationWarning,
            'conversation_sample_sufficient' => count($conversationLatencies) >= $minimum,
            'minimum_sample' => $minimum,
            'failed_model_turns' => AiSupportInteractionEvent::query()
                ->where('event_type', 'model_turn_failed')->where('occurred_at', '>=', $hourStartedAt)->count(),
            'tool_sample' => count($toolLatencies),
            'tool_p95_ms' => $toolP95,
            'tool_warning' => $toolWarning,
            'failed_notifications' => $failedNotifications,
            'queued_notifications_over_ten_minutes' => $queuedNotifications,
            'daily_cost_microunits' => $dailyCost,
            'daily_cost_stopped' => $dailyCostStopped,
        ];
    }

    /** @param list<int> $values */
    private function percentile(array $values, int $percentile): int
    {
        if ($values === []) {
            return 0;
        }
        sort($values);
        $index = (int) ceil(($percentile / 100) * count($values)) - 1;

        return $values[max(0, min(count($values) - 1, $index))];
    }
}
