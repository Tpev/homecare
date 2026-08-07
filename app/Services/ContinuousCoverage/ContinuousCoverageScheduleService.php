<?php

namespace App\Services\ContinuousCoverage;

use App\Models\ContinuousCoverageLaneRequest;
use App\Models\ContinuousCoveragePlan;
use App\Models\ContinuousCoverageShift;
use App\Models\ContinuousCoverageShiftTemplate;
use App\Models\User;
use App\Support\MarketplacePricing;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContinuousCoverageScheduleService
{
    public function __construct(
        private readonly ContinuousCoverageEventRecorder $events,
        private readonly ContinuousCoverageAccess $access,
        private readonly ContinuousCoverageNotificationService $notifications,
        private readonly MarketplacePricing $pricing,
    ) {}

    /** @param array<string,mixed> $data */
    public function createPlan(User $family, array $data): ContinuousCoveragePlan
    {
        if ($family->role !== 'family' || ! $this->access->allows($family)) {
            throw ValidationException::withMessages(['coverage' => 'Continuous Coverage is not available for this account.']);
        }

        return DB::transaction(function () use ($family, $data): ContinuousCoveragePlan {
            $plan = ContinuousCoveragePlan::query()->create([
                ...app(FamilyAccountContext::class)->ownershipAttributes($family),
                'created_by_user_id' => $family->id,
                'status' => ContinuousCoveragePlan::STATUS_ACTIVE,
                'title' => $data['title'],
                'timezone' => $data['timezone'],
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'] ?: null,
                'coverage_pattern' => $data['coverage_pattern'],
                'shift_length_minutes' => (int) $data['shift_length_minutes'],
                'weekly_schedule' => $data['custom_windows'] ?? null,
                'recipient_snapshot' => $data['recipient_snapshot'],
                'address_snapshot' => $data['address_snapshot'],
                'task_snapshot' => $data['task_snapshot'] ?? [],
                'care_notes' => $data['care_notes'] ?? null,
                'hourly_rate' => $this->pricing->hourlyRateForFamily($family, 30.0),
                'replacement_confirmation_mode' => $data['replacement_confirmation_mode'],
                'marketplace_applications_enabled' => (bool) ($data['marketplace_applications_enabled'] ?? false),
                'metadata' => [
                    'created_from' => 'continuous_coverage',
                    'coverage_start_time' => $data['coverage_start_time'] ?? null,
                    'coverage_end_time' => $data['coverage_end_time'] ?? null,
                ],
            ]);

            $definitions = $this->definitions($plan, $data);
            foreach ($definitions as $definition) {
                $plan->templates()->create($definition);
            }

            $through = Carbon::parse($plan->starts_on, $plan->timezone)
                ->addWeeks((int) config('marketplace.continuous_coverage.generation_weeks', 6));
            $this->generate($plan, $through);
            $this->events->record($plan, 'plan_created', $family, payload: [
                'coverage_pattern' => $plan->coverage_pattern,
                'shift_length_minutes' => $plan->shift_length_minutes,
                'template_count' => count($definitions),
            ]);

            return $plan->fresh(['templates', 'shifts']);
        });
    }

    public function generate(ContinuousCoveragePlan $plan, Carbon $through): int
    {
        $plan->loadMissing('family');
        if ($plan->status !== ContinuousCoveragePlan::STATUS_ACTIVE || ! $this->access->allows($plan->family)) {
            return 0;
        }

        $timezone = $plan->timezone ?: config('app.timezone');
        $from = Carbon::parse($plan->starts_on, $timezone)->startOfDay()
            ->max(now($timezone)->startOfDay());
        $until = $through->copy()->setTimezone($timezone)->endOfDay();
        if ($plan->ends_on) {
            $until = $until->min(Carbon::parse($plan->ends_on, $timezone)->endOfDay());
        }
        if ($until->lt($from)) {
            return 0;
        }

        $templates = $plan->templates()
            ->with('rosterMember.caregiver:id,name')
            ->where('status', '!=', ContinuousCoverageShiftTemplate::STATUS_SUPERSEDED)
            ->get()
            ->groupBy('day_of_week');
        $created = 0;

        for ($date = $from->copy(); $date->lte($until); $date->addDay()) {
            foreach ($templates->get($date->dayOfWeek, collect()) as $template) {
                if ($date->lt($template->effective_from->copy()->setTimezone($timezone)->startOfDay())) {
                    continue;
                }
                if ($template->effective_until && $date->gt($template->effective_until->copy()->setTimezone($timezone)->endOfDay())) {
                    continue;
                }

                $start = Carbon::parse($date->toDateString().' '.$template->starts_at, $timezone);
                $endDate = $template->spans_next_day ? $date->copy()->addDay() : $date->copy();
                $end = Carbon::parse($endDate->toDateString().' '.$template->ends_at, $timezone);
                if ($template->effective_start_at && $start->lt($template->effective_start_at->copy()->setTimezone($timezone))) {
                    continue;
                }
                $activeRoster = $template->status === ContinuousCoverageShiftTemplate::STATUS_ACTIVE
                    && $template->rosterMember?->isActive();

                $shift = ContinuousCoverageShift::query()->firstOrCreate(
                    [
                        'continuous_coverage_plan_id' => $plan->id,
                        'occurrence_key' => 'v'.$template->schedule_version.':'.$template->id.':'.$date->toDateString(),
                    ],
                    [
                        'shift_template_id' => $template->id,
                        'assigned_caregiver_user_id' => $activeRoster ? $template->rosterMember->caregiver_user_id : null,
                        'status' => $activeRoster ? ContinuousCoverageShift::STATUS_CONFIRMED : ContinuousCoverageShift::STATUS_UNCOVERED,
                        'scheduled_start_at' => $start->copy()->setTimezone(config('app.timezone')),
                        'scheduled_end_at' => $end->copy()->setTimezone(config('app.timezone')),
                        'scheduled_minutes' => $template->duration_minutes,
                        'caregiver_accepted_at' => $activeRoster ? $template->accepted_at : null,
                        'family_confirmed_at' => $activeRoster ? $template->accepted_at : null,
                        'confirmed_at' => $activeRoster ? $template->accepted_at : null,
                    ],
                );
                $created += $shift->wasRecentlyCreated ? 1 : 0;
                if ($shift->wasRecentlyCreated && $activeRoster) {
                    $this->events->record($plan, 'shift_confirmed', $template->rosterMember->caregiver, $shift, [
                        'source' => 'active_recurring_lane_generation',
                        'shift_template_id' => $template->id,
                    ]);
                }
            }
        }

        $plan->forceFill(['last_generated_at' => now()])->save();
        if ($created > 0) {
            $this->events->record($plan, 'schedule_generated', payload: [
                'shift_count' => $created,
                'through' => $until->toDateString(),
            ]);
        }

        return $created;
    }

    public function activateTemplate(ContinuousCoverageShiftTemplate $template): int
    {
        $template->loadMissing('rosterMember', 'plan.family');
        if (! $this->access->allows($template->plan->family) || ! $template->rosterMember?->isActive()) {
            throw ValidationException::withMessages(['caregiver' => 'The caregiver must accept the family-approved care team first.']);
        }

        $shifts = ContinuousCoverageShift::query()
            ->where('shift_template_id', $template->id)
            ->where('scheduled_start_at', '>=', now())
            ->whereNull('care_booking_id')
            ->whereIn('status', [
                ContinuousCoverageShift::STATUS_UNCOVERED,
                ContinuousCoverageShift::STATUS_OFFER_PENDING,
            ])
            ->get();
        if ($shifts->isEmpty()) {
            return 0;
        }

        $updated = ContinuousCoverageShift::query()
            ->whereKey($shifts->pluck('id'))
            ->update([
                'assigned_caregiver_user_id' => $template->rosterMember->caregiver_user_id,
                'status' => ContinuousCoverageShift::STATUS_CONFIRMED,
                'caregiver_accepted_at' => $template->accepted_at,
                'family_confirmed_at' => $template->accepted_at,
                'confirmed_at' => $template->accepted_at,
                'updated_at' => now(),
            ]);
        foreach ($shifts as $shift) {
            $shift->forceFill([
                'assigned_caregiver_user_id' => $template->rosterMember->caregiver_user_id,
                'status' => ContinuousCoverageShift::STATUS_CONFIRMED,
            ]);
            $this->events->record($template->plan, 'shift_confirmed', $template->rosterMember->caregiver, $shift, [
                'source' => 'accepted_recurring_lane',
                'shift_template_id' => $template->id,
            ]);
        }

        return $updated;
    }

    /** @param array<string,mixed> $data */
    public function replaceFutureSchedule(
        ContinuousCoveragePlan $plan,
        User $family,
        string $effectiveOn,
        array $data,
    ): ContinuousCoveragePlan {
        if (! $this->access->allows($family)
            || $family->role !== 'family'
            || ! app(FamilyAccountContext::class)->canAccessRecord($family, $plan)) {
            abort(403);
        }
        if ($plan->status !== ContinuousCoveragePlan::STATUS_ACTIVE) {
            throw ValidationException::withMessages(['scheduleEffectiveOn' => 'This coverage plan has ended and can no longer be changed.']);
        }

        try {
            $effective = Carbon::createFromFormat('Y-m-d', $effectiveOn, $plan->timezone)->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['scheduleEffectiveOn' => 'Choose a valid effective date.']);
        }
        if ($effective->toDateString() !== $effectiveOn || $effective->lte(now($plan->timezone)->startOfDay())) {
            throw ValidationException::withMessages(['scheduleEffectiveOn' => 'Schedule changes must start on a future date.']);
        }
        if ($plan->ends_on && $effective->gt($plan->ends_on->copy()->endOfDay())) {
            throw ValidationException::withMessages(['scheduleEffectiveOn' => 'The effective date must be within this coverage plan.']);
        }

        $nextVersion = max(1, (int) $plan->templates()->max('schedule_version')) + 1;
        $definitions = $this->definitions($plan, $data, $effective->toDateString(), $nextVersion);
        $effectiveUtc = Carbon::parse($definitions[0]['effective_start_at'], config('app.timezone'));
        $oldSchedule = [
            'coverage_pattern' => $plan->coverage_pattern,
            'shift_length_minutes' => $plan->shift_length_minutes,
            'weekly_schedule' => $plan->weekly_schedule,
        ];

        $unavailableRequestIds = [];
        $updated = DB::transaction(function () use (
            $plan,
            $family,
            $effective,
            $effectiveUtc,
            $definitions,
            $data,
            $nextVersion,
            $oldSchedule,
            &$unavailableRequestIds,
        ): ContinuousCoveragePlan {
            $lockedPlan = ContinuousCoveragePlan::query()->lockForUpdate()->findOrFail($plan->id);
            if ($lockedPlan->status !== ContinuousCoveragePlan::STATUS_ACTIVE) {
                throw ValidationException::withMessages(['scheduleEffectiveOn' => 'This coverage plan has ended and can no longer be changed.']);
            }
            if ($lockedPlan->shifts()
                ->where('scheduled_start_at', '>=', $effectiveUtc)
                ->whereNotNull('care_booking_id')
                ->exists()) {
                throw ValidationException::withMessages([
                    'scheduleEffectiveOn' => 'A visit is already prepared on or after this date. Change or cancel that visit through its existing visit workflow first.',
                ]);
            }

            $affectedTemplateIds = $lockedPlan->templates()
                ->where('schedule_version', '<', $nextVersion)
                ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>=', $effective->toDateString()))
                ->pluck('id');
            $unavailableRequestIds = ContinuousCoverageLaneRequest::query()
                ->whereIn('shift_template_id', $affectedTemplateIds)
                ->where('status', ContinuousCoverageLaneRequest::STATUS_PENDING)
                ->pluck('id')
                ->all();
            if ($unavailableRequestIds !== []) {
                ContinuousCoverageLaneRequest::query()->whereKey($unavailableRequestIds)->update([
                    'status' => ContinuousCoverageLaneRequest::STATUS_UNAVAILABLE,
                    'responded_at' => now(),
                    'responded_by_user_id' => $family->id,
                    'updated_at' => now(),
                ]);
            }

            $lockedPlan->templates()
                ->where('effective_from', '<', $effective->toDateString())
                ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>=', $effective->toDateString()))
                ->update(['effective_until' => $effective->copy()->subDay()->toDateString(), 'updated_at' => now()]);
            $supersededTemplateCount = $lockedPlan->templates()
                ->where('effective_from', '>=', $effective->toDateString())
                ->where('status', '!=', ContinuousCoverageShiftTemplate::STATUS_SUPERSEDED)
                ->update([
                    'status' => ContinuousCoverageShiftTemplate::STATUS_SUPERSEDED,
                    'offer_expires_at' => null,
                    'updated_at' => now(),
                ]);

            $futureShifts = $lockedPlan->shifts()
                ->where('scheduled_start_at', '>=', $effectiveUtc)
                ->whereNull('care_booking_id')
                ->get();
            foreach ($futureShifts as $shift) {
                $shift->forceFill([
                    'status' => ContinuousCoverageShift::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'metadata' => array_merge((array) $shift->metadata, [
                        'superseded_by_schedule_version' => $nextVersion,
                        'superseded_at' => now()->toIso8601String(),
                    ]),
                ])->save();
            }

            foreach ($definitions as $definition) {
                $lockedPlan->templates()->create($definition);
            }
            $lockedPlan->forceFill([
                'coverage_pattern' => $data['coverage_pattern'],
                'shift_length_minutes' => (int) $data['shift_length_minutes'],
                'weekly_schedule' => $data['custom_windows'] ?? null,
                'metadata' => array_merge((array) $lockedPlan->metadata, [
                    'coverage_start_time' => $data['coverage_start_time'] ?? null,
                    'coverage_end_time' => $data['coverage_end_time'] ?? null,
                ]),
            ])->save();

            $this->events->record($lockedPlan, 'schedule_changed', $family, payload: [
                'effective_on' => $effective->toDateString(),
                'old_schedule' => $oldSchedule,
                'new_schedule' => [
                    'coverage_pattern' => $data['coverage_pattern'],
                    'shift_length_minutes' => (int) $data['shift_length_minutes'],
                    'weekly_schedule' => $data['custom_windows'] ?? null,
                    'coverage_start_time' => $data['coverage_start_time'] ?? null,
                    'coverage_end_time' => $data['coverage_end_time'] ?? null,
                ],
                'schedule_version' => $nextVersion,
                'superseded_shift_count' => $futureShifts->count(),
                'superseded_template_count' => $supersededTemplateCount,
            ]);

            return $lockedPlan->fresh();
        });

        $through = now($updated->timezone)->addWeeks((int) config('marketplace.continuous_coverage.generation_weeks', 6));
        $this->generate($updated, $through);
        ContinuousCoverageLaneRequest::query()->whereIn('id', $unavailableRequestIds)->get()
            ->each(fn (ContinuousCoverageLaneRequest $request) => $this->notifications->laneRequestUnavailable($request));
        $this->notifications->scheduleChanged($updated, $effective->toDateString());

        return $updated->fresh(['templates']);
    }

    /** @return array{required_minutes:int,covered_minutes:int,uncovered_minutes:int,overlap_minutes:int,percent:int} */
    public function coverageSummary(ContinuousCoveragePlan $plan, Carbon $from, Carbon $through): array
    {
        $shifts = $plan->shifts()
            ->whereNull('metadata->superseded_by_schedule_version')
            ->where('scheduled_start_at', '<', $through)
            ->where('scheduled_end_at', '>', $from)
            ->get();
        $coveredStatuses = [
            ContinuousCoverageShift::STATUS_CONFIRMED,
            ContinuousCoverageShift::STATUS_IN_PROGRESS,
            ContinuousCoverageShift::STATUS_COMPLETED,
            ContinuousCoverageShift::STATUS_PAYMENT_ATTENTION,
        ];
        $shiftIntervals = [];
        $coveredIntervals = [];
        foreach ($shifts as $shift) {
            $interval = $this->wallClockInterval($plan, $shift, $from, $through);
            if ($interval === null) {
                continue;
            }
            $shiftIntervals[] = $interval;
            if (in_array($shift->status, $coveredStatuses, true)) {
                $coveredIntervals[] = $interval;
            }
        }
        // Templates preserve the intended coverage requirement even if shift
        // generation is temporarily delayed. Existing shift intervals are
        // also included so an effective-dated handoff remains represented
        // when an old shift legitimately crosses the new schedule's anchor.
        $requiredIntervals = array_merge(
            $this->requiredWallClockIntervals($plan, $from, $through),
            $shiftIntervals,
        );
        $required = $this->unionMinutes($requiredIntervals);
        $covered = $this->intersectionMinutes($requiredIntervals, $coveredIntervals);
        $scheduledMinutes = array_sum(array_map(
            fn (array $interval): int => $interval[1] - $interval[0],
            $shiftIntervals,
        ));
        $overlap = max(0, $scheduledMinutes - $this->unionMinutes($shiftIntervals));

        return [
            'required_minutes' => $required,
            'covered_minutes' => $covered,
            'uncovered_minutes' => max(0, $required - $covered),
            'overlap_minutes' => $overlap,
            'percent' => $required > 0 ? min(100, (int) round(($covered / $required) * 100)) : 0,
        ];
    }

    /** @return list<array{0:int,1:int}> */
    private function requiredWallClockIntervals(
        ContinuousCoveragePlan $plan,
        Carbon $from,
        Carbon $through,
    ): array {
        $timezone = $plan->timezone ?: config('app.timezone');
        $localFrom = $from->copy()->setTimezone($timezone);
        $localThrough = $through->copy()->setTimezone($timezone);
        $firstDate = $localFrom->copy()->startOfDay()->subDay();
        $lastDate = $localThrough->copy()->endOfDay();
        $templates = $plan->templates()
            ->where('status', '!=', ContinuousCoverageShiftTemplate::STATUS_SUPERSEDED)
            ->where('effective_from', '<=', $localThrough->toDateString())
            ->where(fn ($query) => $query
                ->whereNull('effective_until')
                ->orWhere('effective_until', '>=', $firstDate->toDateString()))
            ->get()
            ->groupBy('day_of_week');
        $intervals = [];

        for ($date = $firstDate->copy(); $date->lte($lastDate); $date->addDay()) {
            foreach ($templates->get($date->dayOfWeek, collect()) as $template) {
                $dateString = $date->toDateString();
                if ($dateString < $template->effective_from->toDateString()
                    || ($template->effective_until && $dateString > $template->effective_until->toDateString())) {
                    continue;
                }

                $start = Carbon::parse($dateString.' '.$template->starts_at, $timezone);
                $end = Carbon::parse(
                    ($template->spans_next_day ? $date->copy()->addDay() : $date)->toDateString().' '.$template->ends_at,
                    $timezone,
                );
                if ($template->effective_start_at
                    && $start->lt($template->effective_start_at->copy()->setTimezone($timezone))) {
                    continue;
                }

                $interval = $this->wallClockBounds($plan, $start, $end, $from, $through);
                if ($interval !== null) {
                    $intervals[] = $interval;
                }
            }
        }

        return $intervals;
    }

    /** @return array{0:int,1:int}|null */
    private function wallClockInterval(
        ContinuousCoveragePlan $plan,
        ContinuousCoverageShift $shift,
        Carbon $from,
        Carbon $through,
    ): ?array {
        return $this->wallClockBounds(
            $plan,
            $shift->scheduled_start_at,
            $shift->scheduled_end_at,
            $from,
            $through,
        );
    }

    /** @return array{0:int,1:int}|null */
    private function wallClockBounds(
        ContinuousCoveragePlan $plan,
        Carbon $startsAt,
        Carbon $endsAt,
        Carbon $from,
        Carbon $through,
    ): ?array {
        $timezone = $plan->timezone ?: config('app.timezone');
        $asWallClockMinute = static function (Carbon $value) use ($timezone): int {
            $local = $value->copy()->setTimezone($timezone);

            return intdiv(Carbon::createFromFormat('Y-m-d H:i:s', $local->format('Y-m-d H:i:s'), 'UTC')->timestamp, 60);
        };

        $start = max($asWallClockMinute($startsAt), $asWallClockMinute($from));
        $end = min($asWallClockMinute($endsAt), $asWallClockMinute($through));

        return $end > $start ? [$start, $end] : null;
    }

    /** @param list<array{0:int,1:int}> $intervals */
    private function unionMinutes(array $intervals): int
    {
        return array_sum(array_map(
            fn (array $interval): int => $interval[1] - $interval[0],
            $this->mergeIntervals($intervals),
        ));
    }

    /**
     * @param  list<array{0:int,1:int}>  $required
     * @param  list<array{0:int,1:int}>  $covered
     */
    private function intersectionMinutes(array $required, array $covered): int
    {
        $required = $this->mergeIntervals($required);
        $covered = $this->mergeIntervals($covered);
        $minutes = 0;
        $requiredIndex = 0;
        $coveredIndex = 0;

        while (isset($required[$requiredIndex], $covered[$coveredIndex])) {
            [$requiredStart, $requiredEnd] = $required[$requiredIndex];
            [$coveredStart, $coveredEnd] = $covered[$coveredIndex];
            $minutes += max(0, min($requiredEnd, $coveredEnd) - max($requiredStart, $coveredStart));

            if ($requiredEnd <= $coveredEnd) {
                $requiredIndex++;
            } else {
                $coveredIndex++;
            }
        }

        return $minutes;
    }

    /**
     * @param  list<array{0:int,1:int}>  $intervals
     * @return list<array{0:int,1:int}>
     */
    private function mergeIntervals(array $intervals): array
    {
        if ($intervals === []) {
            return [];
        }

        usort($intervals, fn (array $left, array $right): int => $left[0] <=> $right[0]);
        $merged = [array_shift($intervals)];
        foreach ($intervals as [$start, $end]) {
            $last = array_key_last($merged);
            if ($start > $merged[$last][1]) {
                $merged[] = [$start, $end];

                continue;
            }
            $merged[$last][1] = max($merged[$last][1], $end);
        }

        return $merged;
    }

    /**
     * Analyze a prospective weekly schedule without writing anything.
     *
     * @param  array<string,mixed>  $data
     * @return array{weekly_minutes:int,shift_count:int,overlap_minutes:int,uncovered_minutes:int,has_overlaps:bool,has_gaps:bool}
     */
    public function analyzePlanInput(array $data): array
    {
        $pattern = (string) ($data['coverage_pattern'] ?? ContinuousCoveragePlan::PATTERN_AROUND_THE_CLOCK);
        $windows = $this->windowsForInput(
            $pattern,
            (int) ($data['shift_length_minutes'] ?? 720),
            (string) ($data['coverage_start_time'] ?? '07:00'),
            (string) ($data['coverage_end_time'] ?? '07:00'),
            array_values((array) ($data['custom_windows'] ?? [])),
        );

        return $this->analyzeWeeklyWindows(
            $windows,
            $pattern === ContinuousCoveragePlan::PATTERN_AROUND_THE_CLOCK,
        );
    }

    /**
     * @param  list<array<string,mixed>>  $windows
     * @return array{weekly_minutes:int,shift_count:int,overlap_minutes:int,uncovered_minutes:int,has_overlaps:bool,has_gaps:bool}
     */
    public function analyzeWeeklyWindows(array $windows, bool $requiresContinuous = false): array
    {
        $segments = [];
        $weeklyMinutes = 0;

        foreach ($windows as $window) {
            $day = (int) ($window['day'] ?? $window['day_of_week'] ?? -1);
            $start = (string) ($window['start'] ?? $window['starts_at'] ?? '');
            $end = (string) ($window['end'] ?? $window['ends_at'] ?? '');
            $this->validateWindow($day, $start, $end);

            $startMinute = ($day * 1440) + $this->timeToMinutes($start);
            $duration = $this->windowDuration($start, $end);
            $endMinute = $startMinute + $duration;
            $weeklyMinutes += $duration;

            if ($endMinute <= 10080) {
                $segments[] = [$startMinute, $endMinute];
            } else {
                $segments[] = [$startMinute, 10080];
                $segments[] = [0, $endMinute - 10080];
            }
        }

        usort($segments, fn (array $left, array $right): int => $left[0] <=> $right[0]);
        $overlapMinutes = 0;
        $coveredMinutes = 0;
        $coveredThrough = null;
        $segmentStart = null;

        foreach ($segments as [$start, $end]) {
            if ($coveredThrough === null || $start >= $coveredThrough) {
                if ($coveredThrough !== null) {
                    $coveredMinutes += $coveredThrough - $segmentStart;
                }
                $segmentStart = $start;
                $coveredThrough = $end;

                continue;
            }

            $overlapMinutes += max(0, min($coveredThrough, $end) - $start);
            $coveredThrough = max($coveredThrough, $end);
        }
        if ($coveredThrough !== null) {
            $coveredMinutes += $coveredThrough - $segmentStart;
        }

        $uncoveredMinutes = $requiresContinuous ? max(0, 10080 - $coveredMinutes) : 0;

        return [
            'weekly_minutes' => $weeklyMinutes,
            'shift_count' => count($windows),
            'overlap_minutes' => $overlapMinutes,
            'uncovered_minutes' => $uncoveredMinutes,
            'has_overlaps' => $overlapMinutes > 0,
            'has_gaps' => $uncoveredMinutes > 0,
        ];
    }

    /** @param array<string,mixed> $data
     * @return list<array<string,mixed>>
     */
    private function definitions(
        ContinuousCoveragePlan $plan,
        array $data,
        ?string $effectiveFromOverride = null,
        int $scheduleVersion = 1,
    ): array {
        $effectiveFrom = $effectiveFromOverride ?: Carbon::parse($plan->starts_on)->toDateString();
        $effectiveUntil = $plan->ends_on?->toDateString();
        $pattern = (string) ($data['coverage_pattern'] ?? $plan->coverage_pattern);
        $windows = $this->windowsForInput(
            $pattern,
            (int) ($data['shift_length_minutes'] ?? $plan->shift_length_minutes),
            (string) ($data['coverage_start_time'] ?? '07:00'),
            (string) ($data['coverage_end_time'] ?? '07:00'),
            array_values((array) ($data['custom_windows'] ?? [])),
        );

        if ($windows === []) {
            throw ValidationException::withMessages(['customWindows' => 'Add at least one coverage window.']);
        }

        $analysis = $this->analyzeWeeklyWindows(
            $windows,
            $pattern === ContinuousCoveragePlan::PATTERN_AROUND_THE_CLOCK,
        );
        if ($analysis['has_overlaps']) {
            throw ValidationException::withMessages(['customWindows' => 'Coverage windows overlap. Adjust the times so each period is counted once.']);
        }
        if ($analysis['has_gaps']) {
            throw ValidationException::withMessages(['shiftLengthMinutes' => 'The 24/7 schedule contains an uncovered period.']);
        }

        $effectiveStartTime = $pattern === ContinuousCoveragePlan::PATTERN_AROUND_THE_CLOCK
            ? (string) ($data['coverage_start_time'] ?? data_get($plan->metadata, 'coverage_start_time', '07:00'))
            : '00:00';
        $effectiveStartAt = Carbon::parse($effectiveFrom.' '.$effectiveStartTime, $plan->timezone)
            ->setTimezone(config('app.timezone'));

        return collect($windows)->map(function (array $window) use ($effectiveFrom, $effectiveStartAt, $effectiveUntil, $scheduleVersion): array {
            $day = (int) ($window['day'] ?? -1);
            $start = (string) ($window['start'] ?? '');
            $end = (string) ($window['end'] ?? '');
            $this->validateWindow($day, $start, $end);
            $startMinutes = $this->timeToMinutes($start);
            $endMinutes = $this->timeToMinutes($end);
            $spans = $endMinutes <= $startMinutes;
            $duration = $this->windowDuration($start, $end);

            return [
                'day_of_week' => $day,
                'starts_at' => $start,
                'ends_at' => $end,
                'spans_next_day' => $spans,
                'duration_minutes' => $duration,
                'schedule_version' => $scheduleVersion,
                'status' => ContinuousCoverageShiftTemplate::STATUS_UNCOVERED,
                'effective_from' => $effectiveFrom,
                'effective_start_at' => $effectiveStartAt,
                'effective_until' => $effectiveUntil,
            ];
        })->all();
    }

    /** @param list<array<string,mixed>> $customWindows
     * @return list<array{day:int,start:string,end:string}>
     */
    private function windowsForInput(
        string $pattern,
        int $shiftLengthMinutes,
        string $coverageStartTime,
        string $coverageEndTime,
        array $customWindows,
    ): array {
        if ($pattern === ContinuousCoveragePlan::PATTERN_AROUND_THE_CLOCK) {
            if ($shiftLengthMinutes < 60 || $shiftLengthMinutes > 720 || 1440 % $shiftLengthMinutes !== 0) {
                throw ValidationException::withMessages([
                    'shiftLengthMinutes' => 'For 24/7 care, shift length must divide a full day without gaps.',
                ]);
            }
            $this->validateTime($coverageStartTime);
            $windows = [];
            $anchor = $this->timeToMinutes($coverageStartTime);
            for ($day = 0; $day <= 6; $day++) {
                for ($offset = 0; $offset < 1440; $offset += $shiftLengthMinutes) {
                    $startMinute = ($anchor + $offset) % 1440;
                    $endMinute = ($startMinute + $shiftLengthMinutes) % 1440;
                    $windows[] = [
                        'day' => $day,
                        'start' => $this->minutesToTime($startMinute),
                        'end' => $this->minutesToTime($endMinute),
                    ];
                }
            }

            return $windows;
        }

        if ($pattern === ContinuousCoveragePlan::PATTERN_OVERNIGHT) {
            return array_map(fn (int $day): array => [
                'day' => $day,
                'start' => $coverageStartTime,
                'end' => $coverageEndTime,
            ], range(0, 6));
        }

        if ($pattern !== ContinuousCoveragePlan::PATTERN_CUSTOM) {
            throw ValidationException::withMessages(['coveragePattern' => 'Choose a supported coverage pattern.']);
        }

        return $customWindows;
    }

    private function validateWindow(int $day, string $start, string $end): void
    {
        if ($day < 0 || $day > 6 || $start === $end) {
            throw ValidationException::withMessages(['customWindows' => 'Each coverage window needs a valid day, start time, and end time.']);
        }
        $this->validateTime($start);
        $this->validateTime($end);
    }

    private function validateTime(string $time): void
    {
        if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', substr($time, 0, 5))) {
            throw ValidationException::withMessages(['customWindows' => 'Use a valid time for every coverage window.']);
        }
    }

    private function windowDuration(string $start, string $end): int
    {
        $startMinutes = $this->timeToMinutes($start);
        $endMinutes = $this->timeToMinutes($end);

        return $endMinutes - $startMinutes + ($endMinutes <= $startMinutes ? 1440 : 0);
    }

    private function timeToMinutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', substr($time, 0, 5)));

        return ($hour * 60) + $minute;
    }

    private function minutesToTime(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60) % 24, $minutes % 60);
    }
}
