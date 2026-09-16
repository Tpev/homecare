<?php

namespace App\Services\Analytics;

use App\Models\CareBooking;
use Illuminate\Support\Carbon;

class CustomerBookedHoursReport
{
    /** @return array<string, mixed> */
    public function build(Carbon $start, Carbon $end, string $grouping): array
    {
        $monthly = $grouping === 'month';
        $periods = [];
        $cursor = $monthly ? $start->copy()->startOfMonth() : $start->copy()->startOfWeek(Carbon::MONDAY);
        while ($cursor->lte($end)) {
            $periodEnd = $monthly ? $cursor->copy()->endOfMonth() : $cursor->copy()->endOfWeek(Carbon::SUNDAY);
            $periods[] = [
                'key' => $cursor->toDateString(),
                'label' => $monthly ? $cursor->format('M Y') : $cursor->format('M j').' – '.$periodEnd->format('M j, Y'),
                'partial' => $cursor->lt($start) || $periodEnd->gt($end),
            ];
            $monthly ? $cursor->addMonth() : $cursor->addWeek();
        }

        $periodMinutes = array_fill_keys(array_column($periods, 'key'), 0);
        $customers = [];
        $bookings = CareBooking::query()
            ->whereIn('status', [CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED])
            ->where('worked_minutes', '>', 0)
            ->where('no_show_flag', false)
            ->whereNull('replacement_released_at')
            ->whereBetween('completed_at', [$start, $end])
            ->with(['family:id,name,email', 'familyAccount:id,owner_user_id', 'familyAccount.owner:id,name,email'])
            ->get(['id', 'family_account_id', 'family_user_id', 'worked_minutes', 'completed_at']);

        foreach ($bookings as $booking) {
            // A shared family account is one customer, even if its owner changes.
            $customerKey = $booking->family_account_id ? 'account:'.$booking->family_account_id : 'user:'.$booking->family_user_id;
            $owner = $booking->familyAccount?->owner ?? $booking->family;
            $periodKey = ($monthly ? $booking->completed_at->copy()->startOfMonth()
                : $booking->completed_at->copy()->startOfWeek(Carbon::MONDAY))->toDateString();
            $customers[$customerKey] ??= [
                'key' => $customerKey,
                'user_id' => $owner?->id,
                'name' => $owner?->name ?? 'Family account #'.$booking->family_account_id,
                'email' => $owner?->email,
                'minutes_by_period' => array_fill_keys(array_keys($periodMinutes), 0),
                'total_minutes' => 0,
                'visits' => 0,
            ];
            $minutes = (int) $booking->worked_minutes;
            $customers[$customerKey]['minutes_by_period'][$periodKey] += $minutes;
            $customers[$customerKey]['total_minutes'] += $minutes;
            $customers[$customerKey]['visits']++;
            $periodMinutes[$periodKey] += $minutes;
        }

        $periodCount = count($periods);
        $customerCount = count($customers);
        $totalHours = array_sum($periodMinutes) / 60;

        return [
            'periods' => $periods,
            'customers' => collect($customers)
                ->sortBy([['total_minutes', 'desc'], ['name', 'asc'], ['key', 'asc']])
                ->map(fn (array $customer) => [
                    ...$customer,
                    'hours_by_period' => array_map(fn (int $minutes) => $minutes / 60, $customer['minutes_by_period']),
                    'total_hours' => $customer['total_minutes'] / 60,
                    'average_hours_per_period' => $periodCount > 0 ? $customer['total_minutes'] / 60 / $periodCount : 0,
                ])->values()->all(),
            'period_hours' => array_map(fn (int $minutes) => $minutes / 60, $periodMinutes),
            'period_average_hours' => array_map(fn (int $minutes) => $customerCount > 0 ? $minutes / 60 / $customerCount : 0, $periodMinutes),
            'customer_count' => $customerCount,
            'visit_count' => $bookings->count(),
            'total_hours' => $totalHours,
            'average_hours_per_customer' => $customerCount > 0 ? $totalHours / $customerCount : 0,
            'average_hours_per_period' => $periodCount > 0 ? $totalHours / $periodCount : 0,
            'average_hours_per_customer_period' => $customerCount > 0 && $periodCount > 0 ? $totalHours / $customerCount / $periodCount : 0,
        ];
    }
}
