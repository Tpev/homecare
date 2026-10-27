<?php

namespace App\Support;

use App\Models\CareBooking;
use App\Models\CarePlan;
use App\Models\CareRequest;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeZone;
use Illuminate\Database\Eloquent\Model;

final class AdminCareRequestSchedule
{
    /** @return list<string> */
    public static function relations(): array
    {
        return ['booking.carePlan.nextUpcomingBooking', 'carePlan.nextUpcomingBooking', 'sourceCarePlans.nextUpcomingBooking'];
    }

    /**
     * All relations must be eager loaded with relations(); presenting a page never
     * issues per-request queries or changes the scheduling state.
     *
     * @return array{requested:array,confirmed:?array,primary:array,next_at:?CarbonImmutable,next:?string}
     */
    public function present(CareRequest $request): array
    {
        $booking = $this->loaded($request, 'booking');
        $sourcePlans = ($this->loaded($request, 'sourceCarePlans') ?? collect())
            ->sort(fn (CarePlan $a, CarePlan $b) => [$b->isLive(), $b->id] <=> [$a->isLive(), $a->id]);
        $plans = collect([
            $this->loaded($request, 'carePlan'),
            $booking ? $this->loaded($booking, 'carePlan') : null,
            ...$sourcePlans->all(),
        ])->filter()->unique(fn (CarePlan $plan) => $plan->id ?? spl_object_id($plan));
        $timezone = $this->timezone($plans->first()?->timezone);
        $requested = $request->isRecurring()
            ? $this->recurring($request, $timezone)
            : $this->window($request->requested_start_at, $request->requested_end_at, $timezone, 'Requested care');

        $bookingTimezone = $this->timezone($booking ? ($this->loaded($booking, 'carePlan')?->timezone ?? $timezone) : $timezone);
        $futureBookings = collect($booking ? [['booking' => $booking, 'timezone' => $bookingTimezone]] : []);
        if ($request->isRecurring()) {
            foreach ($plans as $plan) {
                $nextBooking = $this->loaded($plan, 'nextUpcomingBooking');
                if ($nextBooking) {
                    $futureBookings->push(['booking' => $nextBooking, 'timezone' => $this->timezone($plan->timezone)]);
                }
            }
        }
        $nextEntry = $futureBookings
            ->filter(fn (array $candidate) => $this->isUpcomingBooking($candidate['booking']))
            ->sortBy(fn (array $candidate) => $candidate['booking']->scheduled_start_at->getTimestamp())
            ->first();
        $nextBooking = $nextEntry['booking'] ?? null;
        $displayBooking = $nextBooking ?? $booking;
        $confirmed = $displayBooking ? $this->window(
            $displayBooking->scheduled_start_at,
            $displayBooking->scheduled_end_at,
            $nextEntry['timezone'] ?? $bookingTimezone,
            $request->isRecurring() && $nextBooking ? 'Next confirmed visit' : 'Confirmed booking',
        ) : null;
        if ($confirmed && ! in_array($displayBooking->status, [CareBooking::STATUS_SCHEDULED, CareBooking::STATUS_IN_PROGRESS, CareBooking::STATUS_PAUSED], true)) {
            $confirmed['notice'] = trim($confirmed['notice'].' Booking '.str_replace('_', ' ', (string) $displayBooking->status).'.');
        }

        $nextAt = null;
        if (in_array($request->status, [CareRequest::STATUS_OPEN, CareRequest::STATUS_FILLED], true)) {
            if ($nextBooking) {
                $nextAt = CarbonImmutable::instance($nextBooking->scheduled_start_at)
                    ->setTimezone($nextEntry['timezone']);
            } elseif ($request->status === CareRequest::STATUS_OPEN) {
                if ($request->isRecurring()) {
                    $nextAt = $this->nextRequestedOccurrence($request, $timezone);
                } elseif (! $booking && $request->requested_start_at && $request->requested_start_at->isFuture()
                    && (! $request->requested_end_at || $request->requested_end_at->gt($request->requested_start_at))) {
                    $nextAt = CarbonImmutable::instance($request->requested_start_at)->setTimezone($timezone);
                }
            }
        }

        if ($request->isRecurring() && $request->status === CareRequest::STATUS_FILLED && ! $nextAt) {
            $requested['notice'] = trim($requested['notice'].' No upcoming confirmed visit.');
        }

        return [
            'requested' => $requested,
            'confirmed' => $confirmed,
            'primary' => $request->isRecurring() ? $requested : ($confirmed ?? $requested),
            'next_at' => $nextAt,
            'next' => $nextAt ? $nextAt->format('D, M j, Y · g:i A T').' ('.$nextAt->timezoneName.')' : null,
        ];
    }

    private function window(?CarbonInterface $start, ?CarbonInterface $end, string $timezone, string $label): array
    {
        $start = $start ? CarbonImmutable::instance($start)->setTimezone($timezone) : null;
        $end = $end ? CarbonImmutable::instance($end)->setTimezone($timezone) : null;
        $summary = $this->emptySummary($label, $timezone);

        if (! $start && ! $end) {
            $summary['date'] = 'Schedule not set';
            $summary['time'] = 'Start and end times missing';

            return $summary;
        }

        $datedTimezone = $start ?? $end;
        $summary['timezone'] = $timezone.' · '.$datedTimezone->format('T').' (UTC'.$datedTimezone->format('P').')';
        $summary['date'] = $start?->format('D, M j, Y') ?? 'Ends '.$end->format('D, M j, Y');
        if ($start && $end && $start->toDateString() !== $end->toDateString()) {
            $summary['date'] .= ' – '.$end->format('D, M j, Y');
        }
        if (! $start || ! $end) {
            $summary['time'] = $start ? $start->format('g:i A').' · End time missing' : 'Start time missing · Ends '.$end->format('g:i A');
            $summary['notice'] = $start ? 'End time missing.' : 'Start time missing.';

            return $summary;
        }

        $format = $start->offset !== $end->offset ? 'g:i A T' : 'g:i A';
        $summary['time'] = $start->format($format).'–'.$end->format($format);
        if ($end->lte($start)) {
            $summary['notice'] = 'End time must be after start time.';

            return $summary;
        }

        $minutes = (int) round($start->diffInSeconds($end) / 60);
        $summary['duration'] = $minutes > 0 ? $this->duration($minutes) : 'Less than 1 minute';
        if ($start->offset !== $end->offset) {
            $summary['notice'] = 'Clock change during this visit; duration is elapsed time.';
        }

        return $summary;
    }

    private function recurring(CareRequest $request, string $timezone): array
    {
        $summary = $this->emptySummary('Requested weekly care', $timezone);
        $slots = $request->recurringScheduleSlots();
        $summary['range'] = ($request->recurring_starts_on ? 'Starts '.$request->recurring_starts_on->format('D, M j, Y') : 'Start date not set')
            .' · '.($request->recurring_ends_on ? 'Ends '.$request->recurring_ends_on->format('D, M j, Y') : 'No end date specified');
        if ($slots === []) {
            $summary['date'] = 'Schedule not set';
            $summary['time'] = 'Weekly days and times missing or invalid';
            $summary['notice'] = 'Complete the weekly schedule to calculate visits and duration.';

            return $summary;
        }

        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $summary['rows'] = array_map(fn (array $slot) => [
            'day' => $days[$slot['day']],
            'time' => $this->time($slot['start_time']).'–'.$this->time($slot['end_time']),
            'duration' => $this->duration(WeeklySchedule::durationMinutes($slot)),
        ], $slots);
        $summary['date'] = implode(', ', array_map(fn (array $slot) => WeeklySchedule::DAY_LABELS[$slot['day']], $slots));
        $summary['time'] = WeeklySchedule::label($slots);
        $minutes = array_sum(array_map(fn (array $slot) => WeeklySchedule::durationMinutes($slot), $slots));
        $summary['duration'] = count($slots).' '.(count($slots) === 1 ? 'visit' : 'visits').' · '.$this->duration($minutes).'/week';
        if (! $request->recurring_starts_on) {
            $summary['notice'] = 'Start date not set; next visit cannot be determined.';
        } elseif ($request->recurring_ends_on && $request->recurring_ends_on->lt($request->recurring_starts_on)) {
            $summary['notice'] = 'End date must be on or after the start date.';
        } elseif ($request->recurring_ends_on && $request->recurring_ends_on->toDateString() < CarbonImmutable::now($timezone)->toDateString()) {
            $summary['notice'] = 'Recurring schedule has ended.';
        }
        $rawSlots = $request->recurring_schedule;
        if (is_array($rawSlots) && count($rawSlots) > count(WeeklySchedule::normalize($rawSlots))) {
            $summary['notice'] = trim($summary['notice'].' Some weekly times are incomplete or invalid; totals include valid visits only.');
        }

        return $summary;
    }

    private function nextRequestedOccurrence(CareRequest $request, string $timezone): ?CarbonImmutable
    {
        if (! $request->recurring_starts_on) {
            return null;
        }
        $now = CarbonImmutable::now($timezone);
        $firstDate = max($now->toDateString(), $request->recurring_starts_on->toDateString());
        $cursor = CarbonImmutable::parse($firstDate, $timezone)->startOfDay();
        $slots = $request->recurringScheduleSlots();
        for ($offset = 0; $offset <= 7; $offset++) {
            $date = $cursor->addDays($offset);
            if ($request->recurring_ends_on && $date->toDateString() > $request->recurring_ends_on->toDateString()) {
                return null;
            }
            $slot = WeeklySchedule::forDay($slots, $date->dayOfWeek);
            if (! $slot) {
                continue;
            }
            $start = $date->setTimeFromTimeString($slot['start_time']);
            if ($start->gt($now)) {
                return $start;
            }
        }

        return null;
    }

    private function isUpcomingBooking(CareBooking $booking): bool
    {
        return $booking->status === CareBooking::STATUS_SCHEDULED
            && ! $booking->replacement_released_at
            && $booking->scheduled_start_at?->isFuture()
            && (! $booking->scheduled_end_at || $booking->scheduled_end_at->gt($booking->scheduled_start_at));
    }

    private function emptySummary(string $label, string $timezone): array
    {
        return ['label' => $label, 'date' => '', 'time' => '', 'duration' => 'Duration unavailable', 'timezone' => $timezone, 'range' => '', 'notice' => '', 'rows' => []];
    }

    private function duration(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return trim(($hours ? $hours.' '.($hours === 1 ? 'hour' : 'hours') : '').($hours && $remaining ? ' ' : '').($remaining ? $remaining.' '.($remaining === 1 ? 'minute' : 'minutes') : ''));
    }

    private function time(string $time): string
    {
        $hour = (int) substr($time, 0, 2);

        return ($hour % 12 ?: 12).substr($time, 2, 3).' '.($hour < 12 ? 'AM' : 'PM');
    }

    private function timezone(?string $timezone): string
    {
        try {
            return (new DateTimeZone($timezone ?: (string) config('app.timezone', 'America/New_York')))->getName();
        } catch (\Exception) {
            return (string) config('app.timezone', 'America/New_York');
        }
    }

    private function loaded(Model $model, string $relation): mixed
    {
        return $model->relationLoaded($relation) ? $model->getRelation($relation) : null;
    }
}
