<?php

namespace App\Services\ContinuousCoverage;

use App\Models\ContinuousCoverageEvent;
use App\Models\ContinuousCoveragePlan;
use App\Models\ContinuousCoverageShift;
use App\Models\User;

class ContinuousCoverageEventRecorder
{
    /** @param array<string,mixed> $payload */
    public function record(
        ContinuousCoveragePlan $plan,
        string $eventType,
        ?User $actor = null,
        ?ContinuousCoverageShift $shift = null,
        array $payload = [],
    ): ContinuousCoverageEvent {
        return ContinuousCoverageEvent::query()->create([
            'continuous_coverage_plan_id' => $plan->id,
            'continuous_coverage_shift_id' => $shift?->id,
            'actor_user_id' => $actor?->id,
            'event_type' => $eventType,
            'payload' => $payload,
            'happened_at' => now(),
        ]);
    }
}
