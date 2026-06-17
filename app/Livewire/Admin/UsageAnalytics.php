<?php

namespace App\Livewire\Admin;

use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestMessage;
use App\Models\FunnelEvent;
use App\Models\PageViewEvent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class UsageAnalytics extends Component
{
    public string $startDate = '';

    public string $endDate = '';

    public string $grouping = 'week';

    protected array $allowedGroupings = ['week', 'month'];

    protected $queryString = [
        'startDate' => ['except' => ''],
        'endDate' => ['except' => ''],
        'grouping' => ['except' => 'week'],
    ];

    public function mount(): void
    {
        if ($this->startDate === '') {
            $this->startDate = now()->subDays(29)->toDateString();
        }

        if ($this->endDate === '') {
            $this->endDate = now()->toDateString();
        }

        $this->grouping = $this->normalizedGrouping();
    }

    public function updatedGrouping(): void
    {
        $this->grouping = $this->normalizedGrouping();
    }

    public function render()
    {
        [$start, $end] = $this->dateWindow();
        $activeEvents = $this->activeEvents($start, $end);
        $dailyActiveUsers = $this->dailyActiveUsers($activeEvents, $start, $end);
        $bucketRows = $this->bucketRows($activeEvents, $dailyActiveUsers, $start, $end);
        $summary = $this->summary($activeEvents, $dailyActiveUsers, $start, $end);

        return view('livewire.admin.usage-analytics', [
            'start' => $start,
            'end' => $end,
            'summary' => $summary,
            'bucketRows' => $bucketRows,
            'dailyActiveUsers' => $dailyActiveUsers,
            'groupingLabel' => $this->normalizedGrouping() === 'month' ? 'Monthly' : 'Weekly',
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function dateWindow(): array
    {
        try {
            $start = Carbon::parse($this->startDate)->startOfDay();
        } catch (\Throwable) {
            $start = now()->subDays(29)->startOfDay();
            $this->startDate = $start->toDateString();
        }

        try {
            $end = Carbon::parse($this->endDate)->endOfDay();
        } catch (\Throwable) {
            $end = now()->endOfDay();
            $this->endDate = $end->toDateString();
        }

        if ($start->gt($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
            $this->startDate = $start->toDateString();
            $this->endDate = $end->toDateString();
        }

        return [$start, $end];
    }

    private function normalizedGrouping(): string
    {
        return in_array($this->grouping, $this->allowedGroupings, true)
            ? $this->grouping
            : 'week';
    }

    /**
     * @return array{
     *     family_signups: int,
     *     caregiver_signups: int,
     *     active_users: int,
     *     avg_dau: float,
     *     peak_dau: int,
     *     latest_dau: int,
     *     requests_posted: int,
     *     requests_filled: int,
     *     posting_families: int,
     *     avg_requests_per_posting_family: float,
     *     tracked_minutes: int,
     *     tracked_hours: float,
     *     money_spent_cents: int,
     *     money_spent_dollars: float
     * }
     */
    private function summary(Collection $activeEvents, array $dailyActiveUsers, Carbon $start, Carbon $end): array
    {
        $requestsPosted = $this->postedRequests($start, $end);
        $postingFamilies = $requestsPosted->pluck('family_user_id')->unique()->count();
        $trackedMinutes = $this->trackedBookings($start, $end)->sum('worked_minutes');
        $moneySpentCents = $this->capturedPayments($start, $end)->sum(fn (CareBookingPayment $payment): int => $this->netFamilySpendCents($payment));
        $dailyCounts = collect($dailyActiveUsers)->pluck('count');

        return [
            'family_signups' => $this->signups('family', $start, $end)->count(),
            'caregiver_signups' => $this->signups('caregiver', $start, $end)->count(),
            'active_users' => $activeEvents->pluck('user_id')->unique()->count(),
            'avg_dau' => $dailyCounts->count() > 0 ? round((float) $dailyCounts->avg(), 1) : 0.0,
            'peak_dau' => (int) ($dailyCounts->max() ?? 0),
            'latest_dau' => (int) ($dailyCounts->last() ?? 0),
            'requests_posted' => $requestsPosted->count(),
            'requests_filled' => $this->filledRequests($start, $end)->count(),
            'posting_families' => $postingFamilies,
            'avg_requests_per_posting_family' => $postingFamilies > 0
                ? round($requestsPosted->count() / $postingFamilies, 2)
                : 0.0,
            'tracked_minutes' => (int) $trackedMinutes,
            'tracked_hours' => round($trackedMinutes / 60, 1),
            'money_spent_cents' => (int) $moneySpentCents,
            'money_spent_dollars' => round($moneySpentCents / 100, 2),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bucketRows(Collection $activeEvents, array $dailyActiveUsers, Carbon $start, Carbon $end): array
    {
        $buckets = $this->initializeBuckets($start, $end);

        $this->signups('family', $start, $end)->each(function (User $user) use (&$buckets): void {
            $this->incrementBucket($buckets, $user->created_at, 'family_signups');
        });

        $this->signups('caregiver', $start, $end)->each(function (User $user) use (&$buckets): void {
            $this->incrementBucket($buckets, $user->created_at, 'caregiver_signups');
        });

        $this->postedRequests($start, $end)->each(function (CareRequest $request) use (&$buckets): void {
            $key = $this->bucketKey($request->created_at);
            if (! isset($buckets[$key])) {
                return;
            }

            $buckets[$key]['requests_posted']++;
            $buckets[$key]['posting_family_ids'][(int) $request->family_user_id] = true;
        });

        $this->filledRequests($start, $end)->each(function (CareRequest $request) use (&$buckets): void {
            $this->incrementBucket($buckets, $this->requestFilledAt($request), 'requests_filled');
        });

        $this->trackedBookings($start, $end)->each(function (CareBooking $booking) use (&$buckets): void {
            $key = $this->bucketKey($this->bookingTrackedAt($booking));
            if (! isset($buckets[$key])) {
                return;
            }

            $buckets[$key]['tracked_minutes'] += (int) $booking->worked_minutes;
        });

        $this->capturedPayments($start, $end)->each(function (CareBookingPayment $payment) use (&$buckets): void {
            $key = $this->bucketKey($payment->captured_at);
            if (! isset($buckets[$key])) {
                return;
            }

            $buckets[$key]['money_spent_cents'] += $this->netFamilySpendCents($payment);
        });

        $activeEvents->each(function (array $event) use (&$buckets): void {
            $key = $this->bucketKey($event['at']);
            if (! isset($buckets[$key])) {
                return;
            }

            $buckets[$key]['active_user_ids'][(int) $event['user_id']] = true;
        });

        foreach ($dailyActiveUsers as $point) {
            $key = $this->bucketKey(Carbon::parse($point['date']));
            if (! isset($buckets[$key])) {
                continue;
            }

            $buckets[$key]['dau_sum'] += (int) $point['count'];
            $buckets[$key]['dau_days']++;
        }

        return collect($buckets)
            ->map(function (array $bucket): array {
                $postingFamilies = count($bucket['posting_family_ids']);
                $trackedMinutes = (int) $bucket['tracked_minutes'];
                $moneySpentCents = (int) $bucket['money_spent_cents'];

                return [
                    'key' => $bucket['key'],
                    'label' => $bucket['label'],
                    'family_signups' => (int) $bucket['family_signups'],
                    'caregiver_signups' => (int) $bucket['caregiver_signups'],
                    'active_users' => count($bucket['active_user_ids']),
                    'avg_dau' => $bucket['dau_days'] > 0 ? round($bucket['dau_sum'] / $bucket['dau_days'], 1) : 0.0,
                    'requests_posted' => (int) $bucket['requests_posted'],
                    'requests_filled' => (int) $bucket['requests_filled'],
                    'posting_families' => $postingFamilies,
                    'avg_requests_per_posting_family' => $postingFamilies > 0
                        ? round($bucket['requests_posted'] / $postingFamilies, 2)
                        : 0.0,
                    'tracked_hours' => round($trackedMinutes / 60, 1),
                    'money_spent_dollars' => round($moneySpentCents / 100, 2),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function initializeBuckets(Carbon $start, Carbon $end): array
    {
        $buckets = [];
        $cursor = $this->normalizedGrouping() === 'month'
            ? $start->copy()->startOfMonth()
            : $start->copy()->startOfWeek();
        $last = $this->normalizedGrouping() === 'month'
            ? $end->copy()->startOfMonth()
            : $end->copy()->startOfWeek();

        while ($cursor->lte($last)) {
            $key = $this->bucketKey($cursor);
            $buckets[$key] = [
                'key' => $key,
                'label' => $this->bucketLabel($cursor),
                'family_signups' => 0,
                'caregiver_signups' => 0,
                'active_user_ids' => [],
                'dau_sum' => 0,
                'dau_days' => 0,
                'requests_posted' => 0,
                'requests_filled' => 0,
                'posting_family_ids' => [],
                'tracked_minutes' => 0,
                'money_spent_cents' => 0,
            ];

            $cursor = $this->normalizedGrouping() === 'month'
                ? $cursor->addMonth()
                : $cursor->addWeek();
        }

        return $buckets;
    }

    private function incrementBucket(array &$buckets, mixed $at, string $field): void
    {
        if (! $at) {
            return;
        }

        $key = $this->bucketKey(Carbon::parse($at));
        if (! isset($buckets[$key])) {
            return;
        }

        $buckets[$key][$field]++;
    }

    private function bucketKey(mixed $at): string
    {
        $date = Carbon::parse($at);

        return $this->normalizedGrouping() === 'month'
            ? $date->copy()->startOfMonth()->format('Y-m')
            : $date->copy()->startOfWeek()->toDateString();
    }

    private function bucketLabel(Carbon $bucketStart): string
    {
        return $this->normalizedGrouping() === 'month'
            ? $bucketStart->format('M Y')
            : $bucketStart->format('M d').' - '.$bucketStart->copy()->endOfWeek()->format('M d');
    }

    /**
     * @return Collection<int, User>
     */
    private function signups(string $role, Carbon $start, Carbon $end): Collection
    {
        return User::query()
            ->where('role', $role)
            ->whereBetween('created_at', [$start, $end])
            ->get(['id', 'role', 'created_at']);
    }

    /**
     * @return Collection<int, CareRequest>
     */
    private function postedRequests(Carbon $start, Carbon $end): Collection
    {
        return CareRequest::query()
            ->where('status', '!=', CareRequest::STATUS_DRAFT)
            ->whereBetween('created_at', [$start, $end])
            ->get(['id', 'family_user_id', 'status', 'created_at']);
    }

    /**
     * @return Collection<int, CareRequest>
     */
    private function filledRequests(Carbon $start, Carbon $end): Collection
    {
        return CareRequest::query()
            ->where('status', CareRequest::STATUS_FILLED)
            ->where(function ($query) use ($start, $end): void {
                $query->whereBetween('first_hire_at', [$start, $end])
                    ->orWhere(function ($fallbackQuery) use ($start, $end): void {
                        $fallbackQuery->whereNull('first_hire_at')
                            ->whereBetween('updated_at', [$start, $end]);
                    });
            })
            ->get(['id', 'family_user_id', 'status', 'first_hire_at', 'created_at', 'updated_at']);
    }

    private function requestFilledAt(CareRequest $request): Carbon
    {
        return $request->first_hire_at
            ? Carbon::parse($request->first_hire_at)
            : Carbon::parse($request->updated_at);
    }

    /**
     * @return Collection<int, CareBooking>
     */
    private function trackedBookings(Carbon $start, Carbon $end): Collection
    {
        return CareBooking::query()
            ->whereNotNull('worked_minutes')
            ->where('worked_minutes', '>', 0)
            ->where(function ($query) use ($start, $end): void {
                $query->whereBetween('family_confirmed_at', [$start, $end])
                    ->orWhereBetween('timesheet_submitted_at', [$start, $end])
                    ->orWhereBetween('completed_at', [$start, $end])
                    ->orWhereBetween('updated_at', [$start, $end]);
            })
            ->get([
                'id',
                'family_user_id',
                'caregiver_user_id',
                'worked_minutes',
                'started_at',
                'completed_at',
                'timesheet_submitted_at',
                'family_confirmed_at',
                'created_at',
                'updated_at',
            ])
            ->filter(fn (CareBooking $booking): bool => $this->bookingTrackedAt($booking)->betweenIncluded($start, $end))
            ->values();
    }

    private function bookingTrackedAt(CareBooking $booking): Carbon
    {
        foreach (['family_confirmed_at', 'timesheet_submitted_at', 'completed_at', 'updated_at'] as $field) {
            if ($booking->{$field}) {
                return Carbon::parse($booking->{$field});
            }
        }

        return Carbon::parse($booking->created_at);
    }

    /**
     * @return Collection<int, CareBookingPayment>
     */
    private function capturedPayments(Carbon $start, Carbon $end): Collection
    {
        return CareBookingPayment::query()
            ->whereNotNull('captured_at')
            ->whereBetween('captured_at', [$start, $end])
            ->get([
                'id',
                'family_user_id',
                'caregiver_user_id',
                'status',
                'amount_captured_cents',
                'amount_overage_cents',
                'amount_refunded_cents',
                'captured_at',
            ]);
    }

    private function netFamilySpendCents(CareBookingPayment $payment): int
    {
        $captured = (int) ($payment->amount_captured_cents ?? 0);
        $overage = (int) ($payment->amount_overage_cents ?? 0);
        $refunded = (int) ($payment->amount_refunded_cents ?? 0);

        return max($captured + $overage - $refunded, 0);
    }

    /**
     * @return Collection<int, array{user_id: int, at: Carbon}>
     */
    private function activeEvents(Carbon $start, Carbon $end): Collection
    {
        $events = collect();
        $push = function (mixed $userId, mixed $at) use ($events, $start, $end): void {
            if (! $userId || ! $at) {
                return;
            }

            $date = Carbon::parse($at);
            if (! $date->betweenIncluded($start, $end)) {
                return;
            }

            $events->push([
                'user_id' => (int) $userId,
                'at' => $date,
            ]);
        };

        FunnelEvent::query()
            ->whereNotNull('user_id')
            ->whereBetween('occurred_at', [$start, $end])
            ->get(['user_id', 'occurred_at'])
            ->each(fn (FunnelEvent $event) => $push($event->user_id, $event->occurred_at));

        PageViewEvent::query()
            ->whereNotNull('user_id')
            ->whereBetween('created_at', [$start, $end])
            ->get(['user_id', 'created_at'])
            ->each(fn (PageViewEvent $event) => $push($event->user_id, $event->created_at));

        User::query()
            ->whereBetween('created_at', [$start, $end])
            ->get(['id', 'created_at'])
            ->each(fn (User $user) => $push($user->id, $user->created_at));

        CareRequest::query()
            ->whereBetween('created_at', [$start, $end])
            ->get(['family_user_id', 'created_at'])
            ->each(fn (CareRequest $request) => $push($request->family_user_id, $request->created_at));

        CareRequestApplication::query()
            ->whereBetween('created_at', [$start, $end])
            ->get(['caregiver_user_id', 'created_at'])
            ->each(fn (CareRequestApplication $application) => $push($application->caregiver_user_id, $application->created_at));

        CareRequestMessage::query()
            ->whereBetween('created_at', [$start, $end])
            ->get(['sender_user_id', 'created_at'])
            ->each(fn (CareRequestMessage $message) => $push($message->sender_user_id, $message->created_at));

        CareBooking::query()
            ->where(function ($query) use ($start, $end): void {
                foreach (['started_at', 'completed_at', 'timesheet_submitted_at', 'family_confirmed_at'] as $field) {
                    $query->orWhereBetween($field, [$start, $end]);
                }
            })
            ->get([
                'family_user_id',
                'caregiver_user_id',
                'started_at',
                'completed_at',
                'timesheet_submitted_at',
                'family_confirmed_at',
            ])
            ->each(function (CareBooking $booking) use ($push): void {
                foreach (['started_at', 'completed_at', 'timesheet_submitted_at', 'family_confirmed_at'] as $field) {
                    if (! $booking->{$field}) {
                        continue;
                    }

                    $push($booking->family_user_id, $booking->{$field});
                    $push($booking->caregiver_user_id, $booking->{$field});
                }
            });

        CareBookingPayment::query()
            ->where(function ($query) use ($start, $end): void {
                $query->whereBetween('authorized_at', [$start, $end])
                    ->orWhereBetween('captured_at', [$start, $end])
                    ->orWhereBetween('transferred_at', [$start, $end]);
            })
            ->get(['family_user_id', 'caregiver_user_id', 'authorized_at', 'captured_at', 'transferred_at'])
            ->each(function (CareBookingPayment $payment) use ($push): void {
                foreach (['authorized_at', 'captured_at', 'transferred_at'] as $field) {
                    if (! $payment->{$field}) {
                        continue;
                    }

                    $push($payment->family_user_id, $payment->{$field});
                    $push($payment->caregiver_user_id, $payment->{$field});
                }
            });

        return $events;
    }

    /**
     * @return array<int, array{date: string, label: string, count: int, show_label: bool}>
     */
    private function dailyActiveUsers(Collection $activeEvents, Carbon $start, Carbon $end): array
    {
        $days = [];
        $cursor = $start->copy()->startOfDay();
        $last = $end->copy()->startOfDay();

        while ($cursor->lte($last)) {
            $key = $cursor->toDateString();
            $days[$key] = [
                'date' => $key,
                'label' => $cursor->format('M d'),
                'user_ids' => [],
            ];
            $cursor->addDay();
        }

        $activeEvents->each(function (array $event) use (&$days): void {
            $key = $event['at']->toDateString();
            if (! isset($days[$key])) {
                return;
            }

            $days[$key]['user_ids'][(int) $event['user_id']] = true;
        });

        $total = count($days);
        $labelStep = max(1, (int) ceil(max($total, 1) / 10));

        return collect($days)
            ->values()
            ->map(function (array $day, int $index) use ($total, $labelStep): array {
                return [
                    'date' => $day['date'],
                    'label' => $day['label'],
                    'count' => count($day['user_ids']),
                    'show_label' => $index % $labelStep === 0 || $index === $total - 1,
                ];
            })
            ->all();
    }
}
