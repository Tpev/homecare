<div class="hc-page py-8 space-y-6">
    <x-card>
        <x-slot:header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Marketplace Funnel Analytics</h1>
                    <p class="mt-1 text-sm text-slate-600">Caregiver + family lifecycle cohorts since {{ $start->format('M d, Y') }}.</p>
                </div>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <x-select.styled
                        wire:model.live="days"
                        label="Range"
                        :options="[
                            ['label' => 'Last 7 days', 'value' => 7],
                            ['label' => 'Last 14 days', 'value' => 14],
                            ['label' => 'Last 30 days', 'value' => 30],
                            ['label' => 'Last 60 days', 'value' => 60],
                            ['label' => 'Last 90 days', 'value' => 90],
                            ['label' => 'Last 180 days', 'value' => 180],
                            ['label' => 'Last 365 days', 'value' => 365],
                        ]"
                    />
                    <x-select.styled
                        wire:model.live="trendGranularity"
                        label="Histogram grouping"
                        :options="[
                            ['label' => 'Daily', 'value' => 'day'],
                            ['label' => 'Weekly', 'value' => 'week'],
                            ['label' => 'Monthly', 'value' => 'month'],
                        ]"
                    />
                </div>
            </div>
        </x-slot:header>

        <div class="mt-1">
            <h2 class="text-base font-semibold text-slate-900">Caregiver lifecycle funnel</h2>
            <p class="text-xs text-slate-500">Cohort starts from unique caregiver landing visitors.</p>
        </div>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Landing Visitors</p>
                <p class="mt-1 text-3xl font-black text-slate-900">{{ number_format($summary['landing_visitors']) }}</p>
                <p class="text-xs text-slate-500">
                    {{ number_format($summary['landing_authenticated']) }} authenticated ·
                    {{ number_format($summary['landing_anonymous']) }} anonymous
                </p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Registered</p>
                <p class="mt-1 text-3xl font-black text-slate-900">{{ number_format($summary['registered']) }}</p>
                <p class="text-xs text-slate-500">{{ number_format($summary['registration_rate'], 1) }}% of landing visitors</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-xs uppercase tracking-[0.14em] text-emerald-700">Activated</p>
                <p class="mt-1 text-3xl font-black text-emerald-900">{{ number_format($summary['activated']) }}</p>
                <p class="text-xs text-emerald-700">{{ number_format($summary['activation_rate'], 1) }}% of landing visitors</p>
            </div>
            <div class="rounded-xl border border-cyan-200 bg-cyan-50 p-4">
                <p class="text-xs uppercase tracking-[0.14em] text-cyan-700">Completed Shift</p>
                <p class="mt-1 text-3xl font-black text-cyan-900">{{ number_format($summary['completed_shift']) }}</p>
                <p class="text-xs text-cyan-700">{{ number_format($summary['completion_rate'], 1) }}% of landing visitors</p>
            </div>
        </div>

        <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-950 px-4 py-6 md:px-8">
            <h2 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-300">Funnel Visualization</h2>
            <div class="mt-4 space-y-3">
                @foreach($steps as $index => $step)
                    <div class="mx-auto rounded-xl border border-slate-700 bg-gradient-to-r from-slate-900 via-blue-900 to-cyan-800 px-4 py-3 text-white"
                        style="width: {{ $step['visual_width'] }}%;">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-semibold">Step {{ $index + 1 }} · {{ $step['label'] }}</p>
                            <p class="text-sm font-black">{{ number_format($step['count']) }}</p>
                        </div>
                        @if($index > 0)
                            <p class="mt-1 text-xs text-cyan-100">
                                {{ number_format($step['step_conversion_percent'], 1) }}% from previous step
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-6 overflow-x-auto rounded-xl border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Step</th>
                        <th class="px-4 py-3">Users</th>
                        <th class="px-4 py-3">Step Conversion</th>
                        <th class="px-4 py-3">Overall Conversion</th>
                        <th class="px-4 py-3">Drop-off</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                @foreach($steps as $index => $step)
                    <tr>
                        <td class="px-4 py-3 font-semibold text-slate-900">Step {{ $index + 1 }} · {{ $step['label'] }}</td>
                        <td class="px-4 py-3 text-slate-800">{{ number_format($step['count']) }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ number_format($step['step_conversion_percent'], 1) }}%</td>
                        <td class="px-4 py-3 text-slate-700">{{ number_format($step['overall_conversion_percent'], 1) }}%</td>
                        <td class="px-4 py-3 text-slate-700">
                            @if($index === 0)
                                —
                            @else
                                {{ number_format($step['dropoff_count']) }} ({{ number_format($step['dropoff_percent'], 1) }}%)
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-4 md:p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Traffic & Signup Trends</h2>
                    <p class="text-xs text-slate-500">Histogram grouping: {{ $trend['bucket_label'] }} buckets.</p>
                </div>
                <div class="text-xs text-slate-500">Same date range as funnel filters</div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Caregiver signups</p>
                    <p class="mt-1 text-2xl font-black text-slate-900">{{ number_format($trend['signup_total']) }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Landing page views</p>
                    <p class="mt-1 text-2xl font-black text-slate-900">{{ number_format($trend['landing_views_total']) }}</p>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                    <p class="text-xs uppercase tracking-[0.14em] text-emerald-700">Signup / view ratio</p>
                    <p class="mt-1 text-2xl font-black text-emerald-900">{{ number_format($trend['signup_from_views_rate'], 1) }}%</p>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-5 xl:grid-cols-2">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-slate-900">Caregiver signups</h3>
                        <p class="text-xs text-slate-500">Peak {{ number_format($trend['max_signups']) }}</p>
                    </div>
                    <div class="mt-3 h-44">
                        <div class="flex h-full items-end gap-1">
                            @foreach($trend['signups'] as $point)
                                @php
                                    $height = $trend['max_signups'] > 0
                                        ? max(8, (int) round(($point['count'] / $trend['max_signups']) * 100))
                                        : 8;
                                @endphp
                                <div class="flex h-full min-w-0 flex-1 flex-col items-center justify-end">
                                    <div
                                        class="w-full rounded-t-md bg-indigo-500/80 hover:bg-indigo-500"
                                        style="height: {{ $height }}%;"
                                        title="{{ $point['label_full'] }}: {{ number_format($point['count']) }} signups"
                                    ></div>
                                    <p class="mt-1 h-4 text-[10px] leading-4 text-slate-500">
                                        {{ $point['show_label'] ? $point['label_short'] : '' }}
                                    </p>
                                    @if($point['count'] > 0 && $point['show_label'])
                                        <p class="h-3 text-[9px] leading-3 font-semibold text-slate-700">{{ $point['count'] }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-slate-900">Landing page views</h3>
                        <p class="text-xs text-slate-500">Peak {{ number_format($trend['max_landing_views']) }}</p>
                    </div>
                    <div class="mt-3 h-44">
                        <div class="flex h-full items-end gap-1">
                            @foreach($trend['landing_views'] as $point)
                                @php
                                    $height = $trend['max_landing_views'] > 0
                                        ? max(8, (int) round(($point['count'] / $trend['max_landing_views']) * 100))
                                        : 8;
                                @endphp
                                <div class="flex h-full min-w-0 flex-1 flex-col items-center justify-end">
                                    <div
                                        class="w-full rounded-t-md bg-cyan-500/80 hover:bg-cyan-500"
                                        style="height: {{ $height }}%;"
                                        title="{{ $point['label_full'] }}: {{ number_format($point['count']) }} views"
                                    ></div>
                                    <p class="mt-1 h-4 text-[10px] leading-4 text-slate-500">
                                        {{ $point['show_label'] ? $point['label_short'] : '' }}
                                    </p>
                                    @if($point['count'] > 0 && $point['show_label'])
                                        <p class="h-3 text-[9px] leading-3 font-semibold text-slate-700">{{ $point['count'] }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            @if(($trend['signup_total'] ?? 0) === 0 && ($trend['landing_views_total'] ?? 0) === 0)
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    No caregiver signup or landing-view data found in this range. Try switching to Last 365 days.
                </div>
            @endif
        </div>

        <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-4 md:p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Onboarding Email Performance</h2>
                    <p class="text-xs text-slate-500">Welcome + 24h reminder campaigns for caregivers.</p>
                </div>
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
                    Completion after reminder: {{ number_format($emailPerformance['completion_rate_after_reminder'], 1) }}%
                    ({{ number_format($emailPerformance['completed_after_reminder']) }}/{{ number_format($emailPerformance['reminder_recipients']) }})
                </div>
            </div>

            <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Campaign</th>
                            <th class="px-4 py-3">Sent</th>
                            <th class="px-4 py-3">Opened</th>
                            <th class="px-4 py-3">Clicked</th>
                            <th class="px-4 py-3">Open rate</th>
                            <th class="px-4 py-3">Click rate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @foreach($emailPerformance['campaigns'] as $campaign)
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-900">{{ $campaign['label'] }}</p>
                                    <p class="text-xs text-slate-500 mt-1">{{ $campaign['description'] }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-800">{{ number_format($campaign['sent']) }}</td>
                                <td class="px-4 py-3 text-slate-800">{{ number_format($campaign['opened']) }}</td>
                                <td class="px-4 py-3 text-slate-800">{{ number_format($campaign['clicked']) }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ number_format($campaign['open_rate'], 1) }}%</td>
                                <td class="px-4 py-3 text-slate-700">{{ number_format($campaign['click_rate'], 1) }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-8 border-t border-slate-200 pt-6">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Family lifecycle funnel</h2>
                <p class="text-xs text-slate-500">Cohort starts from unique family landing visitors.</p>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-5">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Landing visitors</p>
                    <p class="mt-1 text-3xl font-black text-slate-900">{{ number_format($familySummary['landing_visitors']) }}</p>
                    <p class="text-xs text-slate-500">
                        {{ number_format($familySummary['landing_authenticated']) }} authenticated ·
                        {{ number_format($familySummary['landing_anonymous']) }} anonymous
                    </p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Registered</p>
                    <p class="mt-1 text-3xl font-black text-slate-900">{{ number_format($familySummary['registered']) }}</p>
                    <p class="text-xs text-slate-500">{{ number_format($familySummary['registration_rate'], 1) }}% of landing visitors</p>
                </div>
                <div class="rounded-xl border border-sky-200 bg-sky-50 p-4">
                    <p class="text-xs uppercase tracking-[0.14em] text-sky-700">Posted request</p>
                    <p class="mt-1 text-3xl font-black text-sky-900">{{ number_format($familySummary['posted']) }}</p>
                    <p class="text-xs text-sky-700">{{ number_format($familySummary['posted_rate'], 1) }}% of landing visitors</p>
                </div>
                <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4">
                    <p class="text-xs uppercase tracking-[0.14em] text-indigo-700">Hired</p>
                    <p class="mt-1 text-3xl font-black text-indigo-900">{{ number_format($familySummary['hired']) }}</p>
                    <p class="text-xs text-indigo-700">{{ number_format($familySummary['hired_rate'], 1) }}% of landing visitors</p>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                    <p class="text-xs uppercase tracking-[0.14em] text-emerald-700">Completed + reviewed</p>
                    <p class="mt-1 text-3xl font-black text-emerald-900">{{ number_format($familySummary['reviewed']) }}</p>
                    <p class="text-xs text-emerald-700">{{ number_format($familySummary['review_rate'], 1) }}% of landing visitors</p>
                </div>
            </div>

            <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-950 px-4 py-6 md:px-8">
                <h3 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-300">Family Funnel Visualization</h3>
                <div class="mt-4 space-y-3">
                    @foreach($familySteps as $index => $step)
                        <div class="mx-auto rounded-xl border border-slate-700 bg-gradient-to-r from-slate-900 via-cyan-900 to-emerald-800 px-4 py-3 text-white"
                            style="width: {{ $step['visual_width'] }}%;">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-semibold">Step {{ $index + 1 }} · {{ $step['label'] }}</p>
                                <p class="text-sm font-black">{{ number_format($step['count']) }}</p>
                            </div>
                            @if($index > 0)
                                <p class="mt-1 text-xs text-cyan-100">
                                    {{ number_format($step['step_conversion_percent'], 1) }}% from previous step
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-5 xl:grid-cols-2">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-slate-900">Family signups</h3>
                        <p class="text-xs text-slate-500">Peak {{ number_format($familyTrend['max_signups']) }}</p>
                    </div>
                    <div class="mt-3 h-44">
                        <div class="flex h-full items-end gap-1">
                            @foreach($familyTrend['signups'] as $point)
                                @php
                                    $height = $familyTrend['max_signups'] > 0
                                        ? max(8, (int) round(($point['count'] / $familyTrend['max_signups']) * 100))
                                        : 8;
                                @endphp
                                <div class="flex h-full min-w-0 flex-1 flex-col items-center justify-end">
                                    <div
                                        class="w-full rounded-t-md bg-indigo-500/80 hover:bg-indigo-500"
                                        style="height: {{ $height }}%;"
                                        title="{{ $point['label_full'] }}: {{ number_format($point['count']) }} signups"
                                    ></div>
                                    <p class="mt-1 h-4 text-[10px] leading-4 text-slate-500">
                                        {{ $point['show_label'] ? $point['label_short'] : '' }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-slate-900">Family landing views</h3>
                        <p class="text-xs text-slate-500">Peak {{ number_format($familyTrend['max_landing_views']) }}</p>
                    </div>
                    <div class="mt-3 h-44">
                        <div class="flex h-full items-end gap-1">
                            @foreach($familyTrend['landing_views'] as $point)
                                @php
                                    $height = $familyTrend['max_landing_views'] > 0
                                        ? max(8, (int) round(($point['count'] / $familyTrend['max_landing_views']) * 100))
                                        : 8;
                                @endphp
                                <div class="flex h-full min-w-0 flex-1 flex-col items-center justify-end">
                                    <div
                                        class="w-full rounded-t-md bg-cyan-500/80 hover:bg-cyan-500"
                                        style="height: {{ $height }}%;"
                                        title="{{ $point['label_full'] }}: {{ number_format($point['count']) }} views"
                                    ></div>
                                    <p class="mt-1 h-4 text-[10px] leading-4 text-slate-500">
                                        {{ $point['show_label'] ? $point['label_short'] : '' }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if(($summary['landing_visitors'] ?? 0) === 0)
            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                No caregiver landing visitors found in this date range yet.
            </div>
        @endif

        @if(($familySummary['landing_visitors'] ?? 0) === 0)
            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                No family landing visitors found in this date range yet.
            </div>
        @endif
    </x-card>
</div>
