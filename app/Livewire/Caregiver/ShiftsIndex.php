<?php

namespace App\Livewire\Caregiver;

use App\Models\CareBooking;
use App\Models\CareRequestApplication;
use App\Services\Caregiver\CaregiverVisitTimelineService;
use App\Services\ContinuousCoverage\ContinuousCoveragePricingService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ShiftsIndex extends Component
{
    use WithPagination;

    public string $status = 'all';

    public function mount(): void
    {
        abort_unless(auth()->user()?->role === 'caregiver', 403);
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function render(
        CaregiverVisitTimelineService $timeline,
        ContinuousCoveragePricingService $coveragePricing,
    ) {
        $caregiverId = (int) auth()->id();
        $caregiver = auth()->user()->loadMissing('caregiverProfile');
        $visitTimeline = $timeline->paginate($caregiver, $this->status, 10);
        $coverageEarningEstimates = $visitTimeline->getCollection()
            ->where('kind', 'coverage')
            ->mapWithKeys(function (array $visit) use ($coveragePricing, $caregiver): array {
                $shift = $visit['coverage_shift'];

                return [
                    $shift->id => $coveragePricing->caregiverEarningsLabel(
                        $shift->plan,
                        $caregiver,
                        $shift->scheduled_minutes,
                    ),
                ];
            });

        $hiredWithoutBooking = CareRequestApplication::query()
            ->with(['careRequest:id,title,city,state,request_type,requested_start_at,requested_end_at'])
            ->where('caregiver_user_id', $caregiverId)
            ->where('status', CareRequestApplication::STATUS_HIRED)
            ->whereDoesntHave('booking')
            ->latest()
            ->limit(6)
            ->get();

        $coverageCounts = $timeline->unpreparedCoverageCounts($caregiver);
        $nextShift = CareBooking::query()
            ->with(['careRequest:id,title,address_line1,city,state,zip', 'carePlan:id,title', 'payment:id,care_booking_id,status'])
            ->where('caregiver_user_id', $caregiverId)
            ->where('status', CareBooking::STATUS_SCHEDULED)
            ->whereScheduledCheckInNotExpired()
            ->whereNotNull('scheduled_start_at')
            ->orderBy('scheduled_start_at')
            ->first();
        $counts = [
            'active' => CareBooking::query()
                ->where('caregiver_user_id', $caregiverId)
                ->whereIn('status', [CareBooking::STATUS_IN_PROGRESS, CareBooking::STATUS_PAUSED])
                ->count(),
            'scheduled' => CareBooking::query()
                ->where('caregiver_user_id', $caregiverId)
                ->where('status', CareBooking::STATUS_SCHEDULED)
                ->whereScheduledCheckInNotExpired()
                ->count() + $coverageCounts['scheduled'],
            'completed' => CareBooking::query()
                ->where('caregiver_user_id', $caregiverId)
                ->whereIn('status', [CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED])
                ->count(),
            'issues' => CareBooking::query()
                ->where('caregiver_user_id', $caregiverId)
                ->whereIn('status', [CareBooking::STATUS_DISPUTED, CareBooking::STATUS_CANCELLED])
                ->count() + $coverageCounts['issues'],
            'time_correction_actions' => CareBooking::query()
                ->where('caregiver_user_id', $caregiverId)
                ->whereHas('timeCorrections', fn ($query) => $query
                    ->where('status', \App\Models\CareBookingTimeCorrection::STATUS_CHANGES_REQUESTED))
                ->count(),
        ];

        return view('livewire.caregiver.shifts-index', [
            'visitTimeline' => $visitTimeline,
            'hiredWithoutBooking' => $hiredWithoutBooking,
            'counts' => $counts,
            'nextShift' => $nextShift,
            'nextVisit' => $timeline->nextScheduled($caregiver),
            'coverageEarningEstimates' => $coverageEarningEstimates,
            'coveragePreparationHours' => (int) config('marketplace.continuous_coverage.booking_horizon_hours', 48),
        ]);
    }
}
