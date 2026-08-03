<?php

namespace App\Livewire\Family;

use App\Models\ContinuousCoveragePlan;
use App\Models\ContinuousCoverageShift;
use App\Services\ContinuousCoverage\ContinuousCoverageScheduleService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ContinuousCoverageIndex extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->role === 'family', 403);
    }

    public function render(ContinuousCoverageScheduleService $schedule)
    {
        $plans = ContinuousCoveragePlan::query()
            ->where('family_user_id', auth()->id())
            ->with(['shifts' => fn ($query) => $query
                ->where('scheduled_start_at', '>=', now())
                ->whereNull('metadata->superseded_by_schedule_version')
                ->where('status', '!=', ContinuousCoverageShift::STATUS_CANCELLED)
                ->orderBy('scheduled_start_at')
                ->limit(120), 'shifts.assignedCaregiver:id,name'])
            ->latest()
            ->get()
            ->map(function (ContinuousCoveragePlan $plan) use ($schedule): ContinuousCoveragePlan {
                $timezone = $plan->timezone;
                $weekStartLocal = now($timezone)->startOfWeek();
                $from = $weekStartLocal->copy()->setTimezone(config('app.timezone'));
                $through = $weekStartLocal->copy()->addWeek()->setTimezone(config('app.timezone'));
                $plan->setAttribute('coverage_summary', $schedule->coverageSummary($plan, $from, $through));
                $plan->setRelation('nextShift', $plan->shifts->first(fn (ContinuousCoverageShift $shift) => $shift->scheduled_start_at->isFuture()));

                return $plan;
            });

        return view('livewire.family.continuous-coverage-index', [
            'activePlans' => $plans->where('status', '!=', ContinuousCoveragePlan::STATUS_ENDED)->values(),
            'pastPlans' => $plans->where('status', ContinuousCoveragePlan::STATUS_ENDED)->values(),
        ]);
    }
}
