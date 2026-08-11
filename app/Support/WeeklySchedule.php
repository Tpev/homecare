<?php

namespace App\Support;

use Carbon\Carbon;

final class WeeklySchedule
{
    public const DAY_LABELS = [
        0 => 'Sun',
        1 => 'Mon',
        2 => 'Tue',
        3 => 'Wed',
        4 => 'Thu',
        5 => 'Fri',
        6 => 'Sat',
    ];

    /**
     * Return a canonical list of per-day time slots. New records use $slots while
     * legacy records continue to resolve from their shared day/start/end fields.
     *
     * @return list<array{day:int,start_time:string,end_time:string}>
     */
    public static function normalize(
        mixed $slots,
        mixed $legacyDays = [],
        mixed $legacyStartTime = null,
        mixed $legacyEndTime = null
    ): array {
        if (is_string($slots)) {
            $decoded = json_decode($slots, true);
            $slots = is_array($decoded) ? $decoded : [];
        }

        $normalized = collect(is_array($slots) ? $slots : [])
            ->map(function (mixed $slot, mixed $key): ?array {
                if (! is_array($slot)) {
                    return null;
                }

                $day = filter_var($slot['day'] ?? $key, FILTER_VALIDATE_INT);
                $start = self::normalizeTime($slot['start_time'] ?? null);
                $end = self::normalizeTime($slot['end_time'] ?? null);

                if ($day === false || $day < 0 || $day > 6 || $start === null || $end === null) {
                    return null;
                }

                if (self::timeToMinutes($end) <= self::timeToMinutes($start)) {
                    return null;
                }

                return ['day' => $day, 'start_time' => $start, 'end_time' => $end];
            })
            ->filter()
            ->keyBy('day')
            ->sortKeys()
            ->values()
            ->all();

        if ($normalized !== []) {
            return $normalized;
        }

        $start = self::normalizeTime($legacyStartTime);
        $end = self::normalizeTime($legacyEndTime);
        if ($start === null || $end === null || self::timeToMinutes($end) <= self::timeToMinutes($start)) {
            return [];
        }

        return collect(is_array($legacyDays) ? $legacyDays : [])
            ->map(fn (mixed $day): int => (int) $day)
            ->filter(fn (int $day): bool => $day >= 0 && $day <= 6)
            ->unique()
            ->sort()
            ->map(fn (int $day): array => [
                'day' => $day,
                'start_time' => $start,
                'end_time' => $end,
            ])
            ->values()
            ->all();
    }

    /** @param list<array{day:int,start_time:string,end_time:string}> $slots */
    public static function days(array $slots): array
    {
        return collect($slots)->pluck('day')->map(fn ($day) => (int) $day)->values()->all();
    }

    /** @param list<array{day:int,start_time:string,end_time:string}> $slots */
    public static function forDay(array $slots, int $day): ?array
    {
        return collect($slots)->first(fn (array $slot): bool => (int) $slot['day'] === $day);
    }

    /** @param list<array{day:int,start_time:string,end_time:string}> $slots */
    public static function first(array $slots): ?array
    {
        return $slots[0] ?? null;
    }

    /** @param list<array{day:int,start_time:string,end_time:string}> $slots */
    public static function label(array $slots): string
    {
        if ($slots === []) {
            return '';
        }

        $groups = collect($slots)->groupBy(fn (array $slot): string => $slot['start_time'].'|'.$slot['end_time']);

        return $groups->map(function ($group): string {
            $first = $group->first();
            $days = $group
                ->map(fn (array $slot): string => self::DAY_LABELS[(int) $slot['day']] ?? '')
                ->filter()
                ->implode(', ');

            return $days.' · '.self::formatTime($first['start_time']).'–'.self::formatTime($first['end_time']);
        })->implode('; ');
    }

    public static function normalizeTime(mixed $time): ?string
    {
        $value = trim((string) $time);
        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value) !== 1) {
            return null;
        }

        return substr($value, 0, 5);
    }

    public static function timeToMinutes(string $time): int
    {
        return ((int) substr($time, 0, 2) * 60) + (int) substr($time, 3, 2);
    }

    public static function durationMinutes(array $slot): int
    {
        return self::timeToMinutes($slot['end_time']) - self::timeToMinutes($slot['start_time']);
    }

    private static function formatTime(string $time): string
    {
        return Carbon::createFromFormat('H:i', substr($time, 0, 5))->format('g:i A');
    }
}
