<?php

namespace App\Livewire\Admin;

use App\Models\CareBooking;
use App\Models\CareRequestApplication;
use App\Models\CaregiverProfile;
use App\Models\MarketplaceNotificationDelivery;
use App\Models\PageViewEvent;
use App\Models\User;
use App\Services\Analytics\PageViewTracker;
use App\Support\MarketplaceEvent;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class FunnelAnalytics extends Component
{
    public int $days = 30;
    public string $trendGranularity = 'day';

    protected array $allowedTrendGranularities = ['day', 'week', 'month'];

    protected $queryString = [
        'days' => ['except' => 30],
        'trendGranularity' => ['except' => 'day'],
    ];

    public function getStartProperty(): Carbon
    {
        return now()->subDays($this->days)->startOfDay();
    }

    public function updatedTrendGranularity(): void
    {
        $this->trendGranularity = $this->normalizedTrendGranularity();
    }

    public function render()
    {
        $landingIdentityKeys = PageViewEvent::query()
            ->where('event_name', PageViewTracker::CAREGIVER_LANDING_EVENT)
            ->where('created_at', '>=', $this->start)
            ->get(['user_id', 'anon_id'])
            ->map(function (PageViewEvent $event): ?string {
                if ($event->user_id) {
                    return 'u:'.$event->user_id;
                }

                if ($event->anon_id) {
                    return 'a:'.$event->anon_id;
                }

                return null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        $landingAuthenticatedCount = PageViewEvent::query()
            ->where('event_name', PageViewTracker::CAREGIVER_LANDING_EVENT)
            ->where('created_at', '>=', $this->start)
            ->whereNotNull('user_id')
            ->distinct('user_id')
            ->count('user_id');

        $landingAnonymousCount = PageViewEvent::query()
            ->where('event_name', PageViewTracker::CAREGIVER_LANDING_EVENT)
            ->where('created_at', '>=', $this->start)
            ->whereNotNull('anon_id')
            ->distinct('anon_id')
            ->count('anon_id');

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
            ['label' => 'Visited Landing Page', 'ids' => $landingIdentityKeys],
            ['label' => 'Registered', 'ids' => $registeredIds],
            ['label' => 'Profile Info Filled', 'ids' => $filledProfileIds],
            ['label' => 'Under Review', 'ids' => $underReviewIds],
            ['label' => 'Fully Validated Profile', 'ids' => $activeIds],
            ['label' => 'Applied for a Shift', 'ids' => $appliedIds],
            ['label' => 'Completed a Shift', 'ids' => $completedShiftIds],
        ];

        $baseCount = count($landingIdentityKeys);
        $maxCount = max(
            1,
            ...collect($steps)->map(fn (array $step): int => count($step['ids']))->all(),
        );

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
                ? min(100, max((int) round(($currentCount / $maxCount) * 100), 26))
                : 26;
        }
        unset($step);

        $summary = [
            'landing_visitors' => $baseCount,
            'landing_authenticated' => $landingAuthenticatedCount,
            'landing_anonymous' => $landingAnonymousCount,
            'registered' => $steps[1]['count'] ?? 0,
            'activated' => $steps[4]['count'] ?? 0,
            'completed_shift' => $steps[6]['count'] ?? 0,
            'registration_rate' => $steps[1]['overall_conversion_percent'] ?? 0.0,
            'activation_rate' => $steps[4]['overall_conversion_percent'] ?? 0.0,
            'completion_rate' => $steps[6]['overall_conversion_percent'] ?? 0.0,
        ];

        $trendStart = $this->start;
        $trendEnd = now();
        $signupBuckets = $this->initializeTrendBuckets($trendStart, $trendEnd);
        $landingViewBuckets = $this->initializeTrendBuckets($trendStart, $trendEnd);

        User::query()
            ->where('role', 'caregiver')
            ->where('created_at', '>=', $trendStart)
            ->pluck('created_at')
            ->each(function ($createdAt) use (&$signupBuckets): void {
                $this->incrementTrendBucket($signupBuckets, Carbon::parse($createdAt));
            });

        PageViewEvent::query()
            ->where('event_name', PageViewTracker::CAREGIVER_LANDING_EVENT)
            ->where('created_at', '>=', $trendStart)
            ->pluck('created_at')
            ->each(function ($createdAt) use (&$landingViewBuckets): void {
                $this->incrementTrendBucket($landingViewBuckets, Carbon::parse($createdAt));
            });

        $signupSeries = $this->buildTrendSeries($signupBuckets);
        $landingViewSeries = $this->buildTrendSeries($landingViewBuckets);
        $signupTotal = array_sum(array_map(fn (array $point): int => (int) $point['count'], $signupSeries));
        $landingViewsTotal = array_sum(array_map(fn (array $point): int => (int) $point['count'], $landingViewSeries));
        $signupFromViewsRate = $landingViewsTotal > 0
            ? round(($signupTotal / $landingViewsTotal) * 100, 1)
            : 0.0;
        $maxSignups = max(1, ...array_map(fn (array $point): int => (int) $point['count'], $signupSeries));
        $maxLandingViews = max(1, ...array_map(fn (array $point): int => (int) $point['count'], $landingViewSeries));

        $trend = [
            'granularity' => $this->normalizedTrendGranularity(),
            'bucket_label' => match ($this->normalizedTrendGranularity()) {
                'week' => 'weekly',
                'month' => 'monthly',
                default => 'daily',
            },
            'signups' => $signupSeries,
            'landing_views' => $landingViewSeries,
            'max_signups' => $maxSignups,
            'max_landing_views' => $maxLandingViews,
            'signup_total' => $signupTotal,
            'landing_views_total' => $landingViewsTotal,
            'signup_from_views_rate' => $signupFromViewsRate,
        ];

        $campaignKeys = [
            MarketplaceEvent::CAREGIVER_WELCOME,
            MarketplaceEvent::CAREGIVER_ONBOARDING_REMINDER_24H,
        ];

        $emailDeliveries = MarketplaceNotificationDelivery::query()
            ->where('channel', 'email')
            ->whereIn('event_key', $campaignKeys)
            ->where('sent_at', '>=', $this->start)
            ->get([
                'id',
                'user_id',
                'event_key',
                'status',
                'open_count',
                'click_count',
                'sent_at',
            ]);

        $campaignStats = collect([
            [
                'event_key' => MarketplaceEvent::CAREGIVER_WELCOME,
                'label' => 'Welcome email',
                'description' => 'Sent immediately after caregiver signup.',
            ],
            [
                'event_key' => MarketplaceEvent::CAREGIVER_ONBOARDING_REMINDER_24H,
                'label' => '24h incomplete reminder',
                'description' => 'Sent if profile/KYC is still incomplete after 24h.',
            ],
        ])->map(function (array $campaign) use ($emailDeliveries): array {
            $rows = $emailDeliveries
                ->where('event_key', $campaign['event_key'])
                ->where('status', 'sent')
                ->values();

            $sent = $rows->count();
            $opened = $rows->filter(fn ($row) => (int) $row->open_count > 0)->count();
            $clicked = $rows->filter(fn ($row) => (int) $row->click_count > 0)->count();

            $campaign['sent'] = $sent;
            $campaign['opened'] = $opened;
            $campaign['clicked'] = $clicked;
            $campaign['open_rate'] = $sent > 0 ? round(($opened / $sent) * 100, 1) : 0.0;
            $campaign['click_rate'] = $sent > 0 ? round(($clicked / $sent) * 100, 1) : 0.0;

            return $campaign;
        })->values()->all();

        $reminderRecipientIds = $emailDeliveries
            ->where('event_key', MarketplaceEvent::CAREGIVER_ONBOARDING_REMINDER_24H)
            ->where('status', 'sent')
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();

        $completedAfterReminder = $this->countProfileAndKycCompleteUsers($reminderRecipientIds);
        $reminderRecipients = count($reminderRecipientIds);

        $emailPerformance = [
            'campaigns' => $campaignStats,
            'reminder_recipients' => $reminderRecipients,
            'completed_after_reminder' => $completedAfterReminder,
            'completion_rate_after_reminder' => $reminderRecipients > 0
                ? round(($completedAfterReminder / $reminderRecipients) * 100, 1)
                : 0.0,
        ];

        return view('livewire.admin.funnel-analytics', [
            'start' => $this->start,
            'steps' => $steps,
            'summary' => $summary,
            'trend' => $trend,
            'emailPerformance' => $emailPerformance,
        ]);
    }

    private function normalizedTrendGranularity(): string
    {
        return in_array($this->trendGranularity, $this->allowedTrendGranularities, true)
            ? $this->trendGranularity
            : 'day';
    }

    /**
     * @return array<string, array{label_short: string, label_full: string, count: int}>
     */
    private function initializeTrendBuckets(Carbon $start, Carbon $end): array
    {
        $buckets = [];
        $granularity = $this->normalizedTrendGranularity();

        $cursor = match ($granularity) {
            'week' => $start->copy()->startOfWeek(),
            'month' => $start->copy()->startOfMonth(),
            default => $start->copy()->startOfDay(),
        };

        $last = match ($granularity) {
            'week' => $end->copy()->startOfWeek(),
            'month' => $end->copy()->startOfMonth(),
            default => $end->copy()->startOfDay(),
        };

        while ($cursor->lte($last)) {
            $bucketStart = $cursor->copy();
            $key = $this->trendBucketKey($bucketStart);
            $buckets[$key] = [
                'label_short' => $this->trendBucketShortLabel($bucketStart),
                'label_full' => $this->trendBucketFullLabel($bucketStart),
                'count' => 0,
            ];

            $cursor = match ($granularity) {
                'week' => $cursor->addWeek(),
                'month' => $cursor->addMonth(),
                default => $cursor->addDay(),
            };
        }

        return $buckets;
    }

    /**
     * @param array<string, array{label_short: string, label_full: string, count: int}> $buckets
     */
    private function incrementTrendBucket(array &$buckets, Carbon $at): void
    {
        $key = $this->trendBucketKey($at);

        if (isset($buckets[$key])) {
            $buckets[$key]['count']++;
        }
    }

    private function trendBucketKey(Carbon $date): string
    {
        return match ($this->normalizedTrendGranularity()) {
            'week' => $date->copy()->startOfWeek()->toDateString(),
            'month' => $date->copy()->startOfMonth()->format('Y-m'),
            default => $date->copy()->startOfDay()->toDateString(),
        };
    }

    private function trendBucketShortLabel(Carbon $bucketStart): string
    {
        return match ($this->normalizedTrendGranularity()) {
            'week' => $bucketStart->format('M d'),
            'month' => $bucketStart->format('M'),
            default => $bucketStart->format('M d'),
        };
    }

    private function trendBucketFullLabel(Carbon $bucketStart): string
    {
        return match ($this->normalizedTrendGranularity()) {
            'week' => $bucketStart->format('M d, Y').' - '.$bucketStart->copy()->endOfWeek()->format('M d, Y'),
            'month' => $bucketStart->format('F Y'),
            default => $bucketStart->format('M d, Y'),
        };
    }

    /**
     * @param array<string, array{label_short: string, label_full: string, count: int}> $buckets
     * @return array<int, array{label_short: string, label_full: string, count: int, show_label: bool}>
     */
    private function buildTrendSeries(array $buckets): array
    {
        $series = array_values($buckets);
        $total = count($series);
        $labelStep = max(1, (int) ceil($total / 8));

        foreach ($series as $index => &$bucket) {
            $bucket['show_label'] = $index % $labelStep === 0 || $index === $total - 1;
        }
        unset($bucket);

        return $series;
    }

    /**
     * @param list<int> $userIds
     */
    private function countProfileAndKycCompleteUsers(array $userIds): int
    {
        if ($userIds === []) {
            return 0;
        }

        return CaregiverProfile::query()
            ->whereIn('user_id', $userIds)
            ->whereNotNull('bio')
            ->where('bio', '!=', '')
            ->whereNotNull('years_experience')
            ->whereNotNull('service_area_zip')
            ->where('service_area_zip', '!=', '')
            ->whereNotNull('service_radius_miles')
            ->whereHas('skills')
            ->whereHas('languages')
            ->whereHas('availabilities')
            ->where(function ($query) {
                $query->whereNotNull('identity_verified_at')
                    ->orWhere('identity_verification_status', 'approved');
            })
            ->count();
    }
}
