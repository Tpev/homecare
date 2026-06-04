<?php

namespace App\Services\Earnings;

use App\Models\CareBooking;
use App\Models\CaregiverPayout;
use App\Models\CaregiverPayoutItem;
use App\Models\User;
use App\Support\MarketplacePricing;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CaregiverEarningsService
{
    /**
     * @return array{
     *   summary: array<string, float|int>,
     *   nextPayout: array<string, mixed>,
     *   goal: array<string, float|int>,
     *   trend: array<int, array{label:string, amount:float}>,
     *   shiftItems: array<int, array<string, mixed>>,
     *   payouts: array<int, array<string, mixed>>,
     *   nextAction: array<string, string>
     * }
     */
    public function forCaregiver(User $caregiver, string $range = 'week', int $weeklyGoal = 600): array
    {
        $now = now();
        $profileRate = (float) ($caregiver->caregiverProfile?->resolvePlatformHourlyRate() ?? 0);

        $rangeStart = $this->resolveRangeStart($range, $now);

        $rangeBookings = $this->baseBookingsQuery($caregiver->id)
            ->when($rangeStart, function ($query) use ($rangeStart) {
                $query->where(function ($nested) use ($rangeStart) {
                    $nested->where('scheduled_start_at', '>=', $rangeStart)
                        ->orWhere('started_at', '>=', $rangeStart)
                        ->orWhere('completed_at', '>=', $rangeStart)
                        ->orWhere('updated_at', '>=', $rangeStart);
                });
            })
            ->orderByDesc('scheduled_start_at')
            ->orderByDesc('updated_at')
            ->limit(120)
            ->get();

        $statsBookings = $this->baseBookingsQuery($caregiver->id)
            ->where(function ($query) use ($now) {
                $query->where('scheduled_start_at', '>=', $now->copy()->subDays(120))
                    ->orWhere('started_at', '>=', $now->copy()->subDays(120))
                    ->orWhere('completed_at', '>=', $now->copy()->subDays(120))
                    ->orWhereIn('status', [CareBooking::STATUS_SCHEDULED, CareBooking::STATUS_IN_PROGRESS, CareBooking::STATUS_PAUSED]);
            })
            ->orderByDesc('scheduled_start_at')
            ->limit(240)
            ->get();

        $normalizedShifts = $rangeBookings
            ->map(fn (CareBooking $booking) => $this->normalizeBooking($booking, $profileRate, $now))
            ->values();

        $normalizedStats = $statsBookings
            ->map(fn (CareBooking $booking) => $this->normalizeBooking($booking, $profileRate, $now))
            ->values();

        $todayStart = $now->copy()->startOfDay();
        $weekStart = $now->copy()->startOfWeek();
        $monthStart = $now->copy()->startOfMonth();

        $todayGross = $this->sumGrossSince($normalizedStats, $todayStart);
        $weekGross = $this->sumGrossSince($normalizedStats, $weekStart);
        $monthGross = $this->sumGrossSince($normalizedStats, $monthStart);

        $availableBalance = $this->sumGrossByStatus($normalizedStats, ['eligible']);
        $pendingBalance = $this->sumGrossByStatus($normalizedStats, ['pending_confirmation', 'scheduled_payout', 'in_progress', 'paused']);
        $disputedHold = $this->sumGrossByStatus($normalizedStats, ['disputed']);
        $paidThisMonth = round($normalizedStats
            ->filter(function (array $item) use ($monthStart) {
                if ($item['status_key'] !== 'paid') {
                    return false;
                }

                $paidAt = $item['paid_at'];

                return $paidAt instanceof Carbon && $paidAt->greaterThanOrEqualTo($monthStart);
            })
            ->sum('gross_amount'), 2);

        $pendingConfirmations = (int) $normalizedStats
            ->where('status_key', 'pending_confirmation')
            ->count();
        $activeShiftsCount = (int) $normalizedStats
            ->filter(fn (array $item) => in_array($item['status_key'], ['in_progress', 'paused'], true))
            ->count();

        $payouts = CaregiverPayout::query()
            ->withCount('items')
            ->where('caregiver_user_id', $caregiver->id)
            ->orderByRaw("CASE status
                WHEN 'processing' THEN 0
                WHEN 'scheduled' THEN 1
                WHEN 'paid' THEN 2
                WHEN 'failed' THEN 3
                ELSE 4 END")
            ->orderByDesc('scheduled_for')
            ->orderByDesc('created_at')
            ->limit(12)
            ->get();

        $nextScheduledPayout = $payouts
            ->first(fn (CaregiverPayout $payout) => in_array($payout->status, [CaregiverPayout::STATUS_SCHEDULED, CaregiverPayout::STATUS_PROCESSING], true));

        $nextPayoutDate = $nextScheduledPayout?->scheduled_for ?: $this->nextEstimatedPayoutDate($now);
        $nextPayoutAmount = $nextScheduledPayout ? (float) $nextScheduledPayout->net_amount : $availableBalance;
        $nextPayoutType = $nextScheduledPayout ? 'scheduled' : 'estimated';
        $nextPayoutSubtitle = $nextScheduledPayout
            ? 'Scheduled payout batch'
            : 'Estimated from confirmed unpaid shifts';

        $goalCurrent = $weekGross;
        $goalTarget = max(1, $weeklyGoal);
        $goalProgress = min(100, (int) round(($goalCurrent / $goalTarget) * 100));
        $goalRemaining = max(0, round($goalTarget - $goalCurrent, 2));

        $trend = $this->buildTrend($normalizedStats, $now);

        $nextAction = $this->buildNextAction(
            $normalizedShifts,
            $pendingConfirmations,
            $availableBalance
        );

        return [
            'summary' => [
                'today_gross' => $todayGross,
                'week_gross' => $weekGross,
                'month_gross' => $monthGross,
                'available_balance' => $availableBalance,
                'pending_balance' => $pendingBalance,
                'paid_this_month' => $paidThisMonth,
                'disputed_hold' => $disputedHold,
                'pending_confirmations' => $pendingConfirmations,
                'active_shifts' => $activeShiftsCount,
            ],
            'nextPayout' => [
                'date' => $nextPayoutDate,
                'amount' => round($nextPayoutAmount, 2),
                'type' => $nextPayoutType,
                'subtitle' => $nextPayoutSubtitle,
            ],
            'goal' => [
                'target' => (float) $goalTarget,
                'current' => $goalCurrent,
                'remaining' => $goalRemaining,
                'progress' => $goalProgress,
            ],
            'trend' => $trend,
            'shiftItems' => $normalizedShifts->all(),
            'payouts' => $payouts->map(function (CaregiverPayout $payout) {
                return [
                    'id' => $payout->id,
                    'status' => $payout->status,
                    'period_start_on' => $payout->period_start_on,
                    'period_end_on' => $payout->period_end_on,
                    'scheduled_for' => $payout->scheduled_for,
                    'paid_at' => $payout->paid_at,
                    'net_amount' => (float) $payout->net_amount,
                    'gross_amount' => (float) $payout->gross_amount,
                    'adjustments_amount' => (float) $payout->adjustments_amount,
                    'items_count' => (int) $payout->items_count,
                    'currency' => $payout->currency,
                ];
            })->all(),
            'nextAction' => $nextAction,
        ];
    }

    private function baseBookingsQuery(int $caregiverId)
    {
        return CareBooking::query()
            ->with([
                'careRequest:id,title,city,state',
                'family:id,email',
                'application:id,care_request_id,caregiver_user_id,proposed_rate',
                'payoutItem:id,caregiver_payout_id,caregiver_user_id,care_booking_id,status,amount,included_at,paid_at',
                'payoutItem.payout:id,status,scheduled_for,paid_at',
            ])
            ->where('caregiver_user_id', $caregiverId)
            ->whereIn('status', [
                CareBooking::STATUS_SCHEDULED,
                CareBooking::STATUS_IN_PROGRESS,
                CareBooking::STATUS_PAUSED,
                CareBooking::STATUS_COMPLETED,
                CareBooking::STATUS_REVIEWED,
                CareBooking::STATUS_DISPUTED,
                CareBooking::STATUS_CANCELLED,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeBooking(CareBooking $booking, float $profileRate, Carbon $now): array
    {
        $workedMinutes = $this->computeWorkedMinutes($booking, $now);
        $hourlyRate = app(MarketplacePricing::class)->hourlyRateForBooking(
            $booking,
            (float) ($booking->application?->proposed_rate ?: $profileRate)
        );
        $grossAmount = $workedMinutes > 0 && $hourlyRate > 0
            ? round(($workedMinutes / 60) * $hourlyRate, 2)
            : 0.0;

        $statusKey = $this->resolveStatusKey($booking, $booking->payoutItem);
        $referenceAt = $booking->completed_at ?: $booking->started_at ?: $booking->scheduled_start_at ?: $booking->updated_at;
        $paidAt = $booking->payoutItem?->paid_at ?: $booking->payoutItem?->payout?->paid_at;

        return [
            'booking_id' => $booking->id,
            'care_request_id' => $booking->care_request_id,
            'title' => $booking->careRequest?->title ?: 'Care shift',
            'city' => $booking->careRequest?->city,
            'state' => $booking->careRequest?->state,
            'status' => $booking->status,
            'status_key' => $statusKey,
            'status_label' => $this->statusLabel($statusKey),
            'hourly_rate' => round($hourlyRate, 2),
            'worked_minutes' => $workedMinutes,
            'worked_label' => sprintf('%02d:%02d', intdiv($workedMinutes, 60), $workedMinutes % 60),
            'gross_amount' => $grossAmount,
            'scheduled_start_at' => $booking->scheduled_start_at,
            'scheduled_end_at' => $booking->scheduled_end_at,
            'reference_at' => $referenceAt,
            'family_confirmed_at' => $booking->family_confirmed_at,
            'paid_at' => $paidAt,
            'payout_item_status' => $booking->payoutItem?->status,
            'payout_scheduled_for' => $booking->payoutItem?->payout?->scheduled_for,
        ];
    }

    private function computeWorkedMinutes(CareBooking $booking, Carbon $now): int
    {
        if (! is_null($booking->worked_minutes)) {
            return max(0, (int) $booking->worked_minutes);
        }

        if (! $booking->started_at) {
            return 0;
        }

        $endAt = match ($booking->status) {
            CareBooking::STATUS_COMPLETED,
            CareBooking::STATUS_REVIEWED,
            CareBooking::STATUS_DISPUTED,
            CareBooking::STATUS_CANCELLED => $booking->completed_at ?: $booking->updated_at ?: $now,
            default => $now,
        };

        $elapsedSeconds = max(0, $booking->started_at->diffInSeconds($endAt));
        $totalPausedSeconds = (int) ($booking->total_paused_seconds ?? 0);

        if ($booking->status === CareBooking::STATUS_PAUSED && $booking->paused_at) {
            $totalPausedSeconds += $booking->paused_at->diffInSeconds($now);
        }

        $netSeconds = max(0, $elapsedSeconds - $totalPausedSeconds);

        return (int) floor($netSeconds / 60);
    }

    private function resolveStatusKey(CareBooking $booking, ?CaregiverPayoutItem $payoutItem): string
    {
        if ($booking->status === CareBooking::STATUS_CANCELLED) {
            return 'cancelled';
        }

        if ($booking->status === CareBooking::STATUS_DISPUTED) {
            return 'disputed';
        }

        if ($payoutItem) {
            if ($payoutItem->status === CaregiverPayoutItem::STATUS_PAID || $payoutItem->paid_at) {
                return 'paid';
            }

            return 'scheduled_payout';
        }

        if ($booking->status === CareBooking::STATUS_IN_PROGRESS) {
            return 'in_progress';
        }

        if ($booking->status === CareBooking::STATUS_PAUSED) {
            return 'paused';
        }

        if ($booking->status === CareBooking::STATUS_SCHEDULED) {
            return 'scheduled';
        }

        if (in_array($booking->status, [CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED], true)) {
            return $booking->family_confirmed_at ? 'eligible' : 'pending_confirmation';
        }

        return 'pending_confirmation';
    }

    private function statusLabel(string $statusKey): string
    {
        return match ($statusKey) {
            'paid' => 'Paid',
            'scheduled_payout' => 'Scheduled payout',
            'eligible' => 'Eligible',
            'pending_confirmation' => 'Pending confirmation',
            'in_progress' => 'In progress',
            'paused' => 'Paused',
            'scheduled' => 'Scheduled',
            'disputed' => 'Disputed',
            'cancelled' => 'Cancelled',
            default => 'Pending',
        };
    }

    private function resolveRangeStart(string $range, Carbon $now): ?Carbon
    {
        return match ($range) {
            'today' => $now->copy()->startOfDay(),
            'week' => $now->copy()->startOfWeek(),
            'month' => $now->copy()->startOfMonth(),
            'all' => null,
            default => $now->copy()->startOfWeek(),
        };
    }

    private function sumGrossSince(Collection $items, Carbon $start): float
    {
        return round((float) $items
            ->filter(function (array $item) use ($start) {
                if ($item['status_key'] === 'cancelled') {
                    return false;
                }

                return $item['reference_at'] instanceof Carbon
                    && $item['reference_at']->greaterThanOrEqualTo($start);
            })
            ->sum('gross_amount'), 2);
    }

    /**
     * @param  array<int, string>  $statuses
     */
    private function sumGrossByStatus(Collection $items, array $statuses): float
    {
        return round((float) $items
            ->filter(fn (array $item) => in_array($item['status_key'], $statuses, true))
            ->sum('gross_amount'), 2);
    }

    /**
     * @return array<int, array{label:string, amount:float}>
     */
    private function buildTrend(Collection $items, Carbon $now): array
    {
        $trend = [];

        for ($i = 7; $i >= 0; $i--) {
            $start = $now->copy()->startOfWeek()->subWeeks($i);
            $end = $start->copy()->endOfWeek();

            $amount = round((float) $items
                ->filter(function (array $item) use ($start, $end) {
                    if ($item['status_key'] === 'cancelled') {
                        return false;
                    }

                    if (! $item['reference_at'] instanceof Carbon) {
                        return false;
                    }

                    return $item['reference_at']->between($start, $end);
                })
                ->sum('gross_amount'), 2);

            $trend[] = [
                'label' => $start->format('M d'),
                'amount' => $amount,
            ];
        }

        return $trend;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<string, string>
     */
    private function buildNextAction(Collection $items, int $pendingConfirmations, float $availableBalance): array
    {
        $activeShift = $items->first(fn (array $item) => in_array($item['status_key'], ['in_progress', 'paused'], true));
        if ($activeShift) {
            return [
                'title' => 'Keep this shift moving',
                'description' => 'You have a live shift right now. Continue tracking to protect payout accuracy.',
                'cta_label' => 'Open active shift',
                'cta_href' => route('care-requests.apply', $activeShift['care_request_id']),
            ];
        }

        if ($pendingConfirmations > 0) {
            return [
                'title' => 'Follow up on confirmations',
                'description' => $pendingConfirmations.' shift(s) are waiting family confirmation before payout eligibility.',
                'cta_label' => 'Review completed shifts',
                'cta_href' => route('caregiver.shifts.index'),
            ];
        }

        if ($availableBalance > 0) {
            return [
                'title' => 'You have payout-ready earnings',
                'description' => 'Confirmed earnings are waiting for the next payout cycle.',
                'cta_label' => 'View payout details',
                'cta_href' => route('caregiver.earnings.index', ['tab' => 'payouts']),
            ];
        }

        return [
            'title' => 'Add more earnings this week',
            'description' => 'Apply to open requests and keep your schedule full.',
            'cta_label' => 'Browse open requests',
            'cta_href' => route('care-requests.index'),
        ];
    }

    private function nextEstimatedPayoutDate(Carbon $now): Carbon
    {
        $daysUntilFriday = (Carbon::FRIDAY - $now->dayOfWeek + 7) % 7;
        if ($daysUntilFriday === 0) {
            $daysUntilFriday = 7;
        }

        $next = $now->copy()->startOfDay()->addDays($daysUntilFriday);

        return $next->setTime(10, 0);
    }
}
