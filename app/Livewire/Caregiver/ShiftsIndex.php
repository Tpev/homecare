<?php

namespace App\Livewire\Caregiver;

use App\Models\CareBooking;
use App\Models\CareRequestApplication;
use App\Models\ContinuousCoveragePlan;
use App\Models\ContinuousCoverageShift;
use App\Services\ContinuousCoverage\ContinuousCoverageAccess;
use App\Services\ContinuousCoverage\ContinuousCoveragePricingService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ShiftsIndex extends Component
{
    use WithPagination;

    public string $status = 'all';

    #[Url]
    public string $coverageShift = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->role === 'caregiver', 403);
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function render(
        ContinuousCoverageAccess $coverageAccess,
        ContinuousCoveragePricingService $coveragePricing,
    ) {
        $caregiverId = (int) auth()->id();
        $caregiver = auth()->user()->loadMissing('caregiverProfile');
        $expiredRegularCareCutoff = now()->subMinutes(CareBooking::regularCareCheckInGraceMinutes());

        $futureCoverageBase = ContinuousCoverageShift::query()
            ->with(['plan.family:id,name'])
            ->whereHas('plan', fn ($query) => $query->where('status', ContinuousCoveragePlan::STATUS_ACTIVE))
            ->where('assigned_caregiver_user_id', $caregiverId)
            ->whereNull('care_booking_id')
            ->where('scheduled_start_at', '>=', now())
            ->whereIn('status', [
                ContinuousCoverageShift::STATUS_CONFIRMED,
                ContinuousCoverageShift::STATUS_PAYMENT_ATTENTION,
            ]);
        $coverageIsVisible = $coverageAccess->allows($caregiver);
        $coverageScheduledCount = $coverageIsVisible
            ? (clone $futureCoverageBase)->where('status', ContinuousCoverageShift::STATUS_CONFIRMED)->count()
            : 0;
        $coverageAttentionCount = $coverageIsVisible
            ? (clone $futureCoverageBase)->where('status', ContinuousCoverageShift::STATUS_PAYMENT_ATTENTION)->count()
            : 0;
        $futureCoverageShifts = collect();
        if ($coverageIsVisible && in_array($this->status, ['all', CareBooking::STATUS_SCHEDULED, CareBooking::STATUS_DISPUTED], true)) {
            $futureCoverageQuery = clone $futureCoverageBase;
            if ($this->status === CareBooking::STATUS_SCHEDULED) {
                $futureCoverageQuery->where('status', ContinuousCoverageShift::STATUS_CONFIRMED);
            } elseif ($this->status === CareBooking::STATUS_DISPUTED) {
                $futureCoverageQuery->where('status', ContinuousCoverageShift::STATUS_PAYMENT_ATTENTION);
            }
            if (ctype_digit($this->coverageShift)) {
                $futureCoverageQuery->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [(int) $this->coverageShift]);
            }
            $futureCoverageShifts = $futureCoverageQuery
                ->orderBy('scheduled_start_at')
                ->limit(60)
                ->get();
        }
        $futureCoverageEarningEstimates = $futureCoverageShifts->mapWithKeys(
            fn (ContinuousCoverageShift $shift): array => [
                $shift->id => $coveragePricing->caregiverEarningsLabel($shift->plan, $caregiver, $shift->scheduled_minutes),
            ],
        );

        $statusOrderSql = "CASE status
            WHEN 'in_progress' THEN 0
            WHEN 'paused' THEN 1
            WHEN 'scheduled' THEN 2
            WHEN 'completed' THEN 3
            WHEN 'reviewed' THEN 4
            WHEN 'disputed' THEN 5
            WHEN 'cancelled' THEN 6
            ELSE 7 END";

        $bookingsQuery = CareBooking::query()
            ->with([
                'careRequest:id,title,address_line1,address_line2,city,state,zip,request_type,requested_start_at,requested_end_at,recurring_days,recurring_start_time,recurring_end_time',
                'careRequest.recipient:id,care_request_id,full_name,recipient_is_requester,relationship_to_family',
                'carePlan:id,title,status',
                'payment:id,care_booking_id,status,amount_authorized_cents,caregiver_amount_cents,last_error',
                'family:id,name',
                'application:id,care_request_id,caregiver_user_id,status,proposed_rate',
                'application.conversation:id,care_request_application_id',
                'timeCorrections' => fn ($query) => $query->orderByDesc('version'),
            ])
            ->where('caregiver_user_id', $caregiverId);

        if ($this->status === 'time_correction') {
            $bookingsQuery->whereHas('timeCorrections', fn ($query) => $query
                ->where('status', \App\Models\CareBookingTimeCorrection::STATUS_CHANGES_REQUESTED));
        } elseif ($this->status !== 'all') {
            $bookingsQuery->where('status', $this->status);
        }

        $bookings = $bookingsQuery
            ->orderByRaw(
                "CASE WHEN status = 'scheduled' AND care_plan_id IS NOT NULL AND scheduled_start_at < ? AND (check_in_override_at IS NULL OR check_in_override_by_user_id IS NULL) THEN 1 ELSE 0 END",
                [$expiredRegularCareCutoff]
            )
            ->orderByRaw($statusOrderSql)
            ->orderBy('scheduled_start_at')
            ->orderByDesc('updated_at')
            ->paginate(10);

        $hiredWithoutBooking = CareRequestApplication::query()
            ->with(['careRequest:id,title,city,state,request_type,requested_start_at,requested_end_at'])
            ->where('caregiver_user_id', $caregiverId)
            ->where('status', CareRequestApplication::STATUS_HIRED)
            ->whereDoesntHave('booking')
            ->latest()
            ->limit(6)
            ->get();

        $counts = [
            'active' => CareBooking::query()
                ->where('caregiver_user_id', $caregiverId)
                ->whereIn('status', [CareBooking::STATUS_IN_PROGRESS, CareBooking::STATUS_PAUSED])
                ->count(),
            'scheduled' => CareBooking::query()
                ->where('caregiver_user_id', $caregiverId)
                ->where('status', CareBooking::STATUS_SCHEDULED)
                ->whereScheduledCheckInNotExpired()
                ->count(),
            'completed' => CareBooking::query()
                ->where('caregiver_user_id', $caregiverId)
                ->whereIn('status', [CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED])
                ->count(),
            'issues' => CareBooking::query()
                ->where('caregiver_user_id', $caregiverId)
                ->whereIn('status', [CareBooking::STATUS_DISPUTED, CareBooking::STATUS_CANCELLED])
                ->count(),
            'time_correction_actions' => CareBooking::query()
                ->where('caregiver_user_id', $caregiverId)
                ->whereHas('timeCorrections', fn ($query) => $query
                    ->where('status', \App\Models\CareBookingTimeCorrection::STATUS_CHANGES_REQUESTED))
                ->count(),
        ];
        $counts['scheduled'] += $coverageScheduledCount;
        $counts['issues'] += $coverageAttentionCount;

        $nextShift = CareBooking::query()
            ->with(['careRequest:id,title,address_line1,city,state,zip', 'carePlan:id,title', 'payment:id,care_booking_id,status'])
            ->where('caregiver_user_id', $caregiverId)
            ->where('status', CareBooking::STATUS_SCHEDULED)
            ->whereScheduledCheckInNotExpired()
            ->whereNotNull('scheduled_start_at')
            ->orderBy('scheduled_start_at')
            ->first();

        return view('livewire.caregiver.shifts-index', [
            'bookings' => $bookings,
            'hiredWithoutBooking' => $hiredWithoutBooking,
            'counts' => $counts,
            'nextShift' => $nextShift,
            'futureCoverageShifts' => $futureCoverageShifts,
            'futureCoverageEarningEstimates' => $futureCoverageEarningEstimates,
            'coveragePreparationHours' => (int) config('marketplace.continuous_coverage.booking_horizon_hours', 48),
        ]);
    }
}
