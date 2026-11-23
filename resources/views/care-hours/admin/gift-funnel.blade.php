<section class="hc-surface space-y-5 p-5 sm:p-6" aria-labelledby="gift-funnel-title">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div><h2 id="gift-funnel-title" class="font-display text-2xl font-semibold">Gift-card funnel</h2><p class="mt-1 text-sm text-[#485E53]">From the first visit to a paid and claimed gift.</p></div>
        <span class="rounded-full bg-slate-100 px-3 py-2 text-xs font-semibold">OPS Slack: {{ $funnel['slackEnabled'] ? 'enabled' : 'disabled / not configured' }}</span>
    </div>
    <form method="GET" class="flex flex-wrap items-end gap-3">
        <input type="hidden" name="search" value="{{ $search }}"><input type="hidden" name="state" value="{{ $state }}">
        <label class="text-sm font-semibold" for="gift-funnel-from">From<input class="mt-1 block min-h-11 rounded-xl border-slate-300" id="gift-funnel-from" name="funnel_from" type="date" value="{{ $funnel['from'] }}" required></label>
        <label class="text-sm font-semibold" for="gift-funnel-to">Through<input class="mt-1 block min-h-11 rounded-xl border-slate-300" id="gift-funnel-to" name="funnel_to" type="date" value="{{ $funnel['to'] }}" max="{{ $funnel['observed']->toDateString() }}" required></label>
        <button type="submit" class="hc-primary-button">Update funnel</button>
        <p class="pb-2 text-xs text-slate-600">{{ $funnel['timezone'] }} · updated {{ $funnel['observed']->format('M j, H:i T') }}</p>
    </form>
    @unless($funnel['available'])
        <p class="rounded-xl bg-amber-50 p-4 text-sm text-amber-950">Gift analytics are not installed yet. Run the gift tracking migration before using these totals.</p>
    @else
        <p class="text-xs text-slate-600">Tracking began {{ $funnel['started']?->format('M j, Y H:i T') ?? 'at an unknown time' }}. {{ $funnel['partial'] ? 'This selection includes untracked history; totals cover only recorded activity.' : 'Earlier visits and actions are not reconstructed.' }} Purchase-list filters below do not filter this funnel.</p>
        @if($funnel['deliveryAttention'])<p role="status" class="rounded-xl bg-amber-50 p-4 text-sm text-amber-950">{{ $funnel['deliveryAttention'] }} OPS notification(s) across all dates need delivery review. Check Slack before replaying an uncertain delivery.</p>@endif
        <p class="text-xs text-slate-600 sm:hidden">Swipe tables sideways to see all columns.</p>
        <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Gift event activity table">
            <table class="w-full text-left text-sm">
                <caption class="pb-3 text-left font-semibold">Activity recorded during the selected dates</caption>
                <thead><tr class="border-b border-slate-200"><th class="py-3 pr-4">Event</th><th class="px-3 py-3 text-right">Events / views</th><th class="px-3 py-3 text-right">Unique visitor sessions</th><th class="py-3 pl-3 text-right">Distinct gifts</th></tr></thead>
                <tbody>@foreach($funnel['rows'] as $row)<tr class="border-b border-slate-100"><th class="py-3 pr-4 font-medium">{{ $row['label'] }}</th><td class="px-3 py-3 text-right tabular-nums">{{ number_format($row['events']) }}</td><td class="px-3 py-3 text-right tabular-nums">{{ number_format($row['visitors']) }}</td><td class="py-3 pl-3 text-right tabular-nums">{{ $row['purchases'] ?: '—' }}</td></tr>@endforeach</tbody>
            </table>
        </div>
        <p class="text-sm"><strong>Confirmed gift sales:</strong>
            @forelse($funnel['revenue'] as $amount)
                ${{ number_format($amount->cents / 100, 2) }} {{ strtoupper($amount->currency) }}{{ ! $loop->last ? ' · ' : '' }}
            @empty
                $0.00 USD
            @endforelse
            <span class="text-xs text-slate-600">Gross payments, before fees or later refunds.</span>
        </p>
        <details class="text-xs text-slate-600"><summary class="cursor-pointer font-semibold">How these numbers are counted</summary><p class="mt-3">Page views count page loads, including repeat visits. Known bots, prefetches and signed-in staff views are excluded. Unique visitor sessions are anonymous browser sessions, not verified people or households. Code requests mean accepted requests, not confirmed email delivery. Checkout means a provider session was created, not proof the hosted payment page loaded. Known Stripe test purchases are excluded ({{ $funnel['testPurchases'] }} confirmed test purchases in this range). {{ $funnel['unattributedPurchases'] }} confirmed purchases have no matched visitor session.</p></details>
        <div class="overflow-x-auto border-t border-slate-200 pt-5" tabindex="0" role="region" aria-label="Gift visitor conversion table">
            <table class="w-full text-left text-sm">
                <caption class="pb-3 text-left"><strong>Conversion for new gift-shop visitor sessions</strong><span class="mt-1 block text-xs text-slate-600">First shop visit in the selected dates; matched outcomes through {{ $funnel['observed']->format('M j, Y H:i T') }}. Recent sessions still have time to convert.</span></caption>
                <thead><tr class="border-b border-slate-200"><th class="py-3 pr-4">Step</th><th class="px-3 py-3 text-right">Sessions</th><th class="px-3 py-3 text-right">Of shop visitors</th><th class="px-3 py-3 text-right">From prior step</th><th class="py-3 pl-3 text-right">Not yet advanced</th></tr></thead>
                <tbody>@foreach($funnel['cohort'] as $row)<tr class="border-b border-slate-100"><th class="py-3 pr-4 font-medium">{{ $row['label'] }}</th><td class="px-3 py-3 text-right">{{ $row['count'] }}</td><td class="px-3 py-3 text-right">{{ $row['of_shop'] === null ? 'N/A' : $row['of_shop'].'%' }}</td><td class="px-3 py-3 text-right">{{ $row['from_previous'] === null ? 'N/A' : $row['from_previous'].'%' }}</td><td class="py-3 pl-3 text-right">{{ $row['not_advanced'] ?? '—' }}</td></tr>@endforeach</tbody>
            </table>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <p class="rounded-xl bg-slate-50 p-4 text-sm"><strong>Landing page → shop</strong><span class="mt-1 block">{{ $funnel['landedInShop'] }} of {{ $funnel['landingVisitors'] }} new landing-page sessions reached the shop ({{ $funnel['landingVisitors'] ? round(100 * $funnel['landedInShop'] / $funnel['landingVisitors'], 1).'%' : 'N/A' }}).</span></p>
            <p class="rounded-xl bg-slate-50 p-4 text-sm"><strong>Verification requests → verified</strong><span class="mt-1 block">{{ $funnel['verificationCompletedVisitors'] }} of {{ $funnel['verificationRequestedVisitors'] }} requesting shop sessions verified their email ({{ $funnel['verificationRequestedVisitors'] ? round(100 * $funnel['verificationCompletedVisitors'] / $funnel['verificationRequestedVisitors'], 1).'%' : 'N/A' }}).</span></p>
        </div>
        <p class="text-xs text-slate-600">Verification is optional in the conversion path when a buyer already has a valid verification. Claims are matched back to the buyer’s checkout, even when a different person receives the gift. A session can buy multiple gifts but counts once in conversion rates.</p>
        <details class="border-t border-slate-200 pt-4">
            <summary class="cursor-pointer font-semibold">Recent actions and OPS delivery</summary>
            <ul class="mt-3 divide-y divide-slate-100">
                @forelse($funnel['recent'] as $event)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm"><div><strong>{{ \App\Services\CareHours\GiftFunnelRecorder::LABELS[$event->event] }}</strong><span class="block text-xs text-slate-600">{{ \Carbon\CarbonImmutable::parse($event->occurred_at, 'UTC')->timezone($funnel['timezone'])->format('M j, Y H:i:s T') }}@if($event->slack_status) · Slack: {{ $event->slack_status }}@endif</span></div>@if($event->public_id)<a href="{{ route('admin.care-hours.gifts.show', $event->public_id) }}" class="font-semibold underline">View gift →</a>@endif</li>
                @empty<li class="py-3 text-sm text-slate-600">No recorded actions in these dates.</li>@endforelse
            </ul>
        </details>
    @endunless
</section>
