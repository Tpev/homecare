<?php

namespace App\Services\Caregiver;

use App\Models\CareBooking;
use App\Models\CareBookingTimeCorrection;
use App\Models\ContinuousCoveragePlan;
use App\Models\ContinuousCoverageShift;
use App\Models\User;
use App\Services\ContinuousCoverage\ContinuousCoverageAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CaregiverVisitTimelineService
{
    public function __construct(
        private readonly ContinuousCoverageAccess $coverageAccess,
    ) {}

    public function paginate(User $caregiver, string $filter = 'all', int $perPage = 10, string $pageName = 'page'): LengthAwarePaginator
    {
        $paginator = $this->orderedRows($caregiver, $filter)
            ->paginate($perPage, ['*'], $pageName);
        $paginator->setCollection($this->hydrate($paginator->getCollection()));

        return $paginator;
    }

    /** @return Collection<int, array<string, mixed>> */
    public function quickAccess(User $caregiver, int $limit = 3): Collection
    {
        return $this->hydrate(
            $this->orderedRows($caregiver, 'all')->limit(max(1, $limit))->get(),
        );
    }

    /** @return array<string, mixed>|null */
    public function nextScheduled(User $caregiver): ?array
    {
        $row = $this->orderedRows($caregiver, CareBooking::STATUS_SCHEDULED)->first();

        return $row ? $this->hydrate(collect([$row]))->first() : null;
    }

    /** @return array{scheduled:int,issues:int} */
    public function unpreparedCoverageCounts(User $caregiver): array
    {
        if (! $this->coverageAccess->allows($caregiver)) {
            return ['scheduled' => 0, 'issues' => 0];
        }

        $query = $this->unpreparedCoverageQuery($caregiver);

        return [
            'scheduled' => (clone $query)->where('continuous_coverage_shifts.status', ContinuousCoverageShift::STATUS_CONFIRMED)->count(),
            'issues' => (clone $query)->where('continuous_coverage_shifts.status', ContinuousCoverageShift::STATUS_PAYMENT_ATTENTION)->count(),
        ];
    }

    private function orderedRows(User $caregiver, string $filter): Builder
    {
        $rows = $this->bookingRows($caregiver, $filter);
        if ($this->coverageAccess->allows($caregiver) && $this->filterIncludesCoverage($filter)) {
            $rows->unionAll($this->coverageRows($caregiver, $filter));
        }

        return DB::query()
            ->fromSub($rows, 'caregiver_visit_timeline')
            ->orderBy('timeline_group')
            ->orderByRaw('CASE WHEN timeline_group < 2 THEN scheduled_start_at END ASC')
            ->orderByRaw('CASE WHEN timeline_group = 2 THEN scheduled_start_at END DESC')
            ->orderBy('source_type')
            ->orderBy('source_id');
    }

    private function bookingRows(User $caregiver, string $filter): Builder
    {
        $cutoff = now()->subMinutes(CareBooking::regularCareCheckInGraceMinutes());
        $query = DB::table('care_bookings')
            ->select([
                DB::raw("'booking' as source_type"),
                'care_bookings.id as source_id',
                'care_bookings.scheduled_start_at',
                'care_bookings.scheduled_end_at',
                'care_bookings.status',
            ])
            ->selectRaw("CASE
                WHEN care_bookings.status IN ('in_progress', 'paused') THEN 0
                WHEN care_bookings.status = 'scheduled' AND (
                    care_bookings.care_plan_id IS NULL
                    OR care_bookings.scheduled_start_at >= ?
                    OR (care_bookings.check_in_override_at IS NOT NULL AND care_bookings.check_in_override_by_user_id IS NOT NULL)
                ) THEN 1
                ELSE 2 END as timeline_group", [$cutoff])
            ->where('care_bookings.caregiver_user_id', $caregiver->id);

        if ($filter === 'time_correction') {
            $query->whereExists(fn (Builder $corrections) => $corrections
                ->selectRaw('1')
                ->from('care_booking_time_corrections')
                ->whereColumn('care_booking_time_corrections.care_booking_id', 'care_bookings.id')
                ->where('care_booking_time_corrections.status', CareBookingTimeCorrection::STATUS_CHANGES_REQUESTED));
        } elseif (in_array($filter, ['issues', CareBooking::STATUS_DISPUTED], true)) {
            $query->whereIn('care_bookings.status', [CareBooking::STATUS_DISPUTED, CareBooking::STATUS_CANCELLED]);
        } elseif ($filter !== 'all') {
            $query->where('care_bookings.status', $filter);
            if ($filter === CareBooking::STATUS_SCHEDULED) {
                $query->where(function (Builder $scheduled) use ($cutoff): void {
                    $scheduled
                        ->whereNull('care_bookings.care_plan_id')
                        ->orWhereNull('care_bookings.scheduled_start_at')
                        ->orWhere('care_bookings.scheduled_start_at', '>=', $cutoff)
                        ->orWhere(function (Builder $override): void {
                            $override
                                ->whereNotNull('care_bookings.check_in_override_at')
                                ->whereNotNull('care_bookings.check_in_override_by_user_id');
                        });
                });
            }
        }

        return $query;
    }

    private function coverageRows(User $caregiver, string $filter): Builder
    {
        $query = $this->unpreparedCoverageQuery($caregiver)
            ->select([
                DB::raw("'coverage' as source_type"),
                'continuous_coverage_shifts.id as source_id',
                'continuous_coverage_shifts.scheduled_start_at',
                'continuous_coverage_shifts.scheduled_end_at',
                'continuous_coverage_shifts.status',
                DB::raw('1 as timeline_group'),
            ]);

        if ($filter === CareBooking::STATUS_SCHEDULED) {
            $query->where('continuous_coverage_shifts.status', ContinuousCoverageShift::STATUS_CONFIRMED);
        } elseif (in_array($filter, ['issues', CareBooking::STATUS_DISPUTED], true)) {
            $query->where('continuous_coverage_shifts.status', ContinuousCoverageShift::STATUS_PAYMENT_ATTENTION);
        }

        return $query;
    }

    private function unpreparedCoverageQuery(User $caregiver): Builder
    {
        return DB::table('continuous_coverage_shifts')
            ->join('continuous_coverage_plans', 'continuous_coverage_plans.id', '=', 'continuous_coverage_shifts.continuous_coverage_plan_id')
            ->where('continuous_coverage_plans.status', ContinuousCoveragePlan::STATUS_ACTIVE)
            ->where('continuous_coverage_shifts.assigned_caregiver_user_id', $caregiver->id)
            ->whereNull('continuous_coverage_shifts.care_booking_id')
            ->where('continuous_coverage_shifts.scheduled_start_at', '>=', now())
            ->whereIn('continuous_coverage_shifts.status', [
                ContinuousCoverageShift::STATUS_CONFIRMED,
                ContinuousCoverageShift::STATUS_PAYMENT_ATTENTION,
            ]);
    }

    private function filterIncludesCoverage(string $filter): bool
    {
        return in_array($filter, ['all', CareBooking::STATUS_SCHEDULED, 'issues', CareBooking::STATUS_DISPUTED], true);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function hydrate(Collection $rows): Collection
    {
        $bookingIds = $rows->where('source_type', 'booking')->pluck('source_id')->map(fn ($id) => (int) $id);
        $coverageIds = $rows->where('source_type', 'coverage')->pluck('source_id')->map(fn ($id) => (int) $id);
        $bookings = CareBooking::query()
            ->with([
                'careRequest:id,title,address_line1,address_line2,city,state,zip,request_type,requested_start_at,requested_end_at,recurring_days,recurring_start_time,recurring_end_time,recurring_schedule',
                'careRequest.recipient:id,care_request_id,full_name,recipient_is_requester,relationship_to_family',
                'carePlan:id,title,status',
                'payment:id,care_booking_id,status,amount_authorized_cents,caregiver_amount_cents,last_error',
                'family:id,name',
                'application:id,care_request_id,caregiver_user_id,status,proposed_rate',
                'application.conversation:id,care_request_application_id',
                'timeCorrections' => fn ($query) => $query->orderByDesc('version'),
            ])
            ->whereIn('id', $bookingIds)
            ->get()
            ->keyBy('id');
        $coverageShifts = ContinuousCoverageShift::query()
            ->with(['plan.family:id,name'])
            ->whereIn('id', $coverageIds)
            ->get()
            ->keyBy('id');

        return $rows->map(function (object $row) use ($bookings, $coverageShifts): ?array {
            $sourceId = (int) $row->source_id;
            if ($row->source_type === 'booking') {
                $booking = $bookings->get($sourceId);

                return $booking ? [
                    'key' => 'booking-'.$sourceId,
                    'kind' => 'booking',
                    'booking' => $booking,
                    'coverage_shift' => null,
                    'scheduled_start_at' => $booking->scheduled_start_at,
                ] : null;
            }

            $shift = $coverageShifts->get($sourceId);

            return $shift ? [
                'key' => 'coverage-'.$sourceId,
                'kind' => 'coverage',
                'booking' => null,
                'coverage_shift' => $shift,
                'scheduled_start_at' => $shift->scheduled_start_at,
            ] : null;
        })->filter()->values();
    }
}
