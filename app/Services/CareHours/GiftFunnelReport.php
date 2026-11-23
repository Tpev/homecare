<?php

namespace App\Services\CareHours;

use App\Services\Ops\SlackOpsNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class GiftFunnelReport
{
    public function forRequest(Request $request): array
    {
        $timezone = 'Europe/Paris';
        $now = CarbonImmutable::now($timezone);
        $input = $request->validate([
            'funnel_from' => ['nullable', 'date_format:Y-m-d'],
            'funnel_to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $from = CarbonImmutable::parse($input['funnel_from'] ?? $now->startOfMonth()->toDateString(), $timezone)->startOfDay();
        $to = CarbonImmutable::parse($input['funnel_to'] ?? $now->toDateString(), $timezone)->startOfDay();
        if ($to->lt($from) || $from->diffInDays($to) > 366 || $to->gt($now->startOfDay())) {
            throw ValidationException::withMessages(['funnel_to' => 'Choose a range of up to 367 calendar days ending today or earlier.']);
        }
        $start = $from->utc()->format('Y-m-d H:i:s');
        $end = $to->addDay()->utc()->format('Y-m-d H:i:s');
        $observed = $now->utc()->format('Y-m-d H:i:s');
        $report = ['available' => Schema::hasTable('care_hour_gift_events'), 'timezone' => $timezone,
            'from' => $from->toDateString(), 'to' => $to->toDateString(), 'observed' => $now,
            'slackEnabled' => app(SlackOpsNotificationService::class)->enabled()];
        if (! $report['available']) {
            return $report;
        }
        $started = DB::table('care_hour_gift_events')->where('event', 'tracking_started')->value('occurred_at');
        $activity = $this->events()->where('e.occurred_at', '>=', $start)->where('e.occurred_at', '<', $end)
            ->where('e.occurred_at', '<=', $observed);
        $totals = (clone $activity)->select('e.event')->selectRaw('COUNT(*) AS events, COUNT(DISTINCT e.visitor_id) AS visitors, COUNT(DISTINCT e.care_hour_purchase_id) AS purchases')
            ->groupBy('e.event')->get()->keyBy('event');
        $rows = [];
        foreach (GiftFunnelRecorder::LABELS as $event => $label) {
            $row = $totals->get($event);
            $rows[] = ['event' => $event, 'label' => $label, 'events' => (int) ($row?->events ?? 0),
                'visitors' => (int) ($row?->visitors ?? 0), 'purchases' => (int) ($row?->purchases ?? 0)];
        }

        // A cohort starts at the session's first recorded shop view, not every repeat visit.
        $shopCohort = $this->cohort('shop_view', $start, $end);
        $outcomes = $this->outcomes($shopCohort, $observed);
        $shop = DB::query()->fromSub($shopCohort, 'cohort')->count();
        $checkout = (clone $outcomes)->where('e.event', 'checkout_started');
        $paid = (clone $outcomes)->where('e.event', 'payment_confirmed');
        $this->requiresPurchaseEvent($paid, 'checkout_started');
        $claimed = (clone $outcomes)->where('e.event', 'gift_claimed');
        $this->requiresPurchaseEvent($claimed, 'checkout_started');
        $this->requiresPurchaseEvent($claimed, 'payment_confirmed');
        $counts = [
            ['Gift shop visitors', $shop],
            ['Started checkout', $this->visitors($checkout)],
            ['Purchased a gift', $this->visitors($paid)],
            ['Gift subsequently claimed', $this->visitors($claimed)],
        ];
        $cohortRows = [];
        foreach ($counts as $index => [$label, $count]) {
            $previous = $index ? $counts[$index - 1][1] : null;
            $cohortRows[] = ['label' => $label, 'count' => $count, 'of_shop' => $shop ? round(100 * $count / $shop, 1) : null,
                'from_previous' => $previous ? round(100 * $count / $previous, 1) : null,
                'not_advanced' => $index ? max(0, $previous - $count) : null];
        }
        $landingCohort = $this->cohort('landing_view', $start, $end);
        $landingVisitors = DB::query()->fromSub($landingCohort, 'cohort')->count();
        $landedInShop = $this->visitors($this->outcomes($landingCohort, $observed)->where('e.event', 'shop_view'));
        $verification = (clone $outcomes)->where('e.event', 'verification_completed');
        $verification->whereExists(function (Builder $query): void {
            $query->selectRaw('1')->from('care_hour_gift_events as v')->where('v.event', 'verification_requested')
                ->whereColumn('v.verification_id', 'e.verification_id')->whereColumn('v.visitor_id', 'e.visitor_id')
                ->whereColumn('v.occurred_at', '>=', 'cohort.entered_at');
        });
        $requestedVisitors = $this->visitors((clone $outcomes)->where('e.event', 'verification_requested'));

        return $report + [
            'started' => $started ? CarbonImmutable::parse($started, 'UTC')->setTimezone($timezone) : null,
            'partial' => ! $started || $start < $started,
            'rows' => $rows, 'cohort' => $cohortRows,
            'landingVisitors' => $landingVisitors, 'landedInShop' => $landedInShop,
            'verificationRequestedVisitors' => $requestedVisitors,
            'verificationCompletedVisitors' => $this->visitors($verification),
            'revenue' => (clone $activity)->where('e.event', 'payment_confirmed')->select('e.currency')
                ->selectRaw('SUM(e.amount_cents) AS cents')->groupBy('e.currency')->get(),
            'unattributedPurchases' => (clone $activity)->where('e.event', 'payment_confirmed')->whereNull('e.visitor_id')->count(),
            'testPurchases' => DB::table('care_hour_gift_events as e')->join('care_hour_purchases as p', 'p.id', '=', 'e.care_hour_purchase_id')
                ->where('p.livemode', false)->where('e.event', 'payment_confirmed')->where('e.occurred_at', '>=', $start)
                ->where('e.occurred_at', '<', $end)->where('e.occurred_at', '<=', $observed)->count(),
            'recent' => (clone $activity)->whereNotIn('e.event', ['landing_view', 'shop_view', 'tracking_started'])
                ->select('e.*', 'p.public_id')->orderByDesc('e.occurred_at')->orderByDesc('e.id')->limit(25)->get(),
            'deliveryAttention' => DB::table('care_hour_gift_events')->whereIn('slack_status', ['failed', 'uncertain'])->count(),
        ];
    }

    private function events(): Builder
    {
        // Exclude known Stripe test purchases, including their linked checkout events.
        return DB::table('care_hour_gift_events as e')->leftJoin('care_hour_purchases as p', 'p.id', '=', 'e.care_hour_purchase_id')
            ->where(fn (Builder $query) => $query->whereNull('p.livemode')->orWhere('p.livemode', true));
    }

    private function cohort(string $event, string $start, string $end): Builder
    {
        return DB::table('care_hour_gift_events')->where('event', $event)->whereNotNull('visitor_id')
            ->select('visitor_id')->selectRaw('MIN(occurred_at) AS entered_at')->groupBy('visitor_id')
            ->havingRaw('MIN(occurred_at) >= ? AND MIN(occurred_at) < ?', [$start, $end]);
    }

    private function outcomes(Builder $cohort, string $observed): Builder
    {
        return $this->events()->joinSub($cohort, 'cohort', fn ($join) => $join->on('e.visitor_id', '=', 'cohort.visitor_id'))
            ->whereColumn('e.occurred_at', '>=', 'cohort.entered_at')->where('e.occurred_at', '<=', $observed);
    }

    private function requiresPurchaseEvent(Builder $query, string $event): void
    {
        $query->whereExists(function (Builder $match) use ($event): void {
            $match->selectRaw('1')->from('care_hour_gift_events as matched')->where('matched.event', $event)
                ->whereColumn('matched.care_hour_purchase_id', 'e.care_hour_purchase_id')
                ->whereColumn('matched.visitor_id', 'e.visitor_id')->whereColumn('matched.occurred_at', '>=', 'cohort.entered_at');
        });
    }

    private function visitors(Builder $query): int
    {
        return $query->distinct()->count('e.visitor_id');
    }
}
