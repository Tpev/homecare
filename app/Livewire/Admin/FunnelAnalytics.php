<?php

namespace App\Livewire\Admin;

use App\Models\CareBooking;
use App\Models\CareRequestApplication;
use App\Models\CaregiverProfile;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class FunnelAnalytics extends Component
{
    public int $days = 30;

    public function getStartProperty(): Carbon
    {
        return now()->subDays($this->days)->startOfDay();
    }

    public function render()
    {
        $registeredIds = User::query()
            ->where('role', 'caregiver')
            ->where('created_at', '>=', $this->start)
            ->pluck('id')
            ->values()
            ->all();

        $filledProfileIds = CaregiverProfile::query()
            ->whereIn('user_id', $registeredIds)
            ->whereNotNull('bio')
            ->where('bio', '!=', '')
            ->whereNotNull('years_experience')
            ->whereNotNull('service_area_zip')
            ->where('service_area_zip', '!=', '')
            ->whereHas('skills')
            ->whereHas('languages')
            ->whereHas('availabilities')
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();

        $underReviewIds = CaregiverProfile::query()
            ->whereIn('user_id', $filledProfileIds)
            ->whereNotNull('review_submitted_at')
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();

        $activeIds = CaregiverProfile::query()
            ->whereIn('user_id', $underReviewIds)
            ->where('status', 'active')
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();

        $appliedIds = CareRequestApplication::query()
            ->whereIn('caregiver_user_id', $activeIds)
            ->select('caregiver_user_id')
            ->distinct()
            ->pluck('caregiver_user_id')
            ->values()
            ->all();

        $completedShiftIds = CareBooking::query()
            ->whereIn('caregiver_user_id', $appliedIds)
            ->whereNotNull('completed_at')
            ->select('caregiver_user_id')
            ->distinct()
            ->pluck('caregiver_user_id')
            ->values()
            ->all();

        $steps = [
            ['label' => 'Registered', 'ids' => $registeredIds],
            ['label' => 'Profile Info Filled', 'ids' => $filledProfileIds],
            ['label' => 'Under Review', 'ids' => $underReviewIds],
            ['label' => 'Fully Validated Profile', 'ids' => $activeIds],
            ['label' => 'Applied for a Shift', 'ids' => $appliedIds],
            ['label' => 'Completed a Shift', 'ids' => $completedShiftIds],
        ];

        $baseCount = count($registeredIds);
        $maxCount = max($baseCount, 1);

        foreach ($steps as $index => &$step) {
            $currentCount = count($step['ids']);
            $previousCount = $index > 0 ? count($steps[$index - 1]['ids']) : $currentCount;
            $dropoff = $index > 0 ? max($previousCount - $currentCount, 0) : 0;

            $step['count'] = $currentCount;
            $step['step_conversion_percent'] = $index === 0
                ? 100.0
                : ($previousCount > 0 ? round(($currentCount / $previousCount) * 100, 1) : 0.0);
            $step['overall_conversion_percent'] = $baseCount > 0
                ? round(($currentCount / $baseCount) * 100, 1)
                : 0.0;
            $step['dropoff_count'] = $dropoff;
            $step['dropoff_percent'] = $index === 0
                ? 0.0
                : ($previousCount > 0 ? round(($dropoff / $previousCount) * 100, 1) : 0.0);
            $step['visual_width'] = $currentCount > 0
                ? max((int) round(($currentCount / $maxCount) * 100), 26)
                : 26;
        }
        unset($step);

        $summary = [
            'registered' => $baseCount,
            'activated' => $steps[3]['count'] ?? 0,
            'completed_shift' => $steps[5]['count'] ?? 0,
            'activation_rate' => $steps[3]['overall_conversion_percent'] ?? 0.0,
            'completion_rate' => $steps[5]['overall_conversion_percent'] ?? 0.0,
        ];

        return view('livewire.admin.funnel-analytics', [
            'start' => $this->start,
            'steps' => $steps,
            'summary' => $summary,
        ]);
    }
}
