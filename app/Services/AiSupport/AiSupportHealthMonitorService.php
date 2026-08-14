<?php

namespace App\Services\AiSupport;

use App\Models\AiSupportIncident;
use App\Models\AiSupportInteractionEvent;
use App\Models\MarketplaceNotificationDelivery;

class AiSupportHealthMonitorService
{
    public function __construct(
        private readonly AiSupportIncidentService $incidents,
        private readonly AiSupportControlService $controls,
    ) {}

    /** @return array<string,int|bool> */
    public function run(): array
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
        if ($conversationWarning) {
            $this->incidents->open(
                'conversation_latency_p95_warning',
                'AI Support conversational P95 latency exceeded its five-second target across the minimum sample.',
                safeMetadata: ['sample_size' => count($conversationLatencies), 'p95_ms' => $conversationP95],
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
        if ($toolWarning) {
            $this->incidents->open(
                'tool_latency_p95_warning',
                'AI Support tool-action P95 latency exceeded its eight-second target across the minimum sample.',
                safeMetadata: ['sample_size' => count($toolLatencies), 'p95_ms' => $toolP95],
                severity: AiSupportIncident::SEVERITY_WARNING,
            );
        }

        $failedNotifications = MarketplaceNotificationDelivery::query()
            ->where('status', 'failed')
            ->where('updated_at', '>=', $hourStartedAt)
            ->where(function ($query): void {
                $query->where('dedupe_key', 'like', 'ai-support-%')
                    ->orWhere('dedupe_key', 'like', 'support-handoff-%');
            })
            ->count();
        if ($failedNotifications > 0) {
            $this->incidents->open(
                'operations_notification_failed',
                'One or more AI Support operations notifications failed during the last hour.',
                safeMetadata: ['failed_delivery_count' => $failedNotifications],
            );
        }

        $dailyCost = (int) AiSupportInteractionEvent::query()
            ->where('occurred_at', '>=', now('UTC')->startOfDay())
            ->sum('cost_microunits');
        $dailyCostStopped = $dailyCost >= (int) config('ai_support.pilot_daily_cost_stop_microunits', 5_000_000);
        if ($dailyCostStopped) {
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
            'tool_sample' => count($toolLatencies),
            'tool_p95_ms' => $toolP95,
            'tool_warning' => $toolWarning,
            'failed_notifications' => $failedNotifications,
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
