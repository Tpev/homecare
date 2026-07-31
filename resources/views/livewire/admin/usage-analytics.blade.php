<div class="hc-page py-8 space-y-6">
    <x-card>
        <x-slot:header>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold">Platform Usage Analytics</h1>
                    <p class="mt-1 text-sm text-slate-600">
                        Usage from {{ $start->format('M d, Y') }} to {{ $end->format('M d, Y') }}.
                    </p>
                </div>

                <div class="grid w-full grid-cols-1 gap-2 sm:w-auto sm:grid-cols-3">
                    <label class="text-xs font-medium text-slate-600">
                        Start date
                        <input
                            type="date"
                            wire:model.change="startDate"
                            class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-100"
                        >
                    </label>

                    <label class="text-xs font-medium text-slate-600">
                        End date
                        <input
                            type="date"
                            wire:model.change="endDate"
                            class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-100"
                        >
                    </label>

                    <label class="text-xs font-medium text-slate-600">
                        Group by
                        <select
                            wire:model.live="grouping"
                            class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-100"
                        >
                            <option value="week">Week</option>
                            <option value="month">Month</option>
                        </select>
                    </label>
                </div>
            </div>
        </x-slot:header>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Family signups</p>
                <p class="mt-1 text-3xl font-black text-slate-900">{{ number_format($summary['family_signups']) }}</p>
                <p class="text-xs text-slate-500">New family and care receiver accounts</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Caregiver signups</p>
                <p class="mt-1 text-3xl font-black text-slate-900">{{ number_format($summary['caregiver_signups']) }}</p>
                <p class="text-xs text-slate-500">New caregiver accounts</p>
            </div>

            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-xs uppercase tracking-[0.14em] text-emerald-700">Avg daily active users</p>
                <p class="mt-1 text-3xl font-black text-emerald-900">{{ number_format($summary['avg_dau'], 1) }}</p>
                <p class="text-xs text-emerald-700">
                    {{ number_format($summary['active_users']) }} unique active users - peak {{ number_format($summary['peak_dau']) }}/day
                </p>
            </div>

            <div class="rounded-xl border border-cyan-200 bg-cyan-50 p-4">
                <p class="text-xs uppercase tracking-[0.14em] text-cyan-700">Request volume</p>
                <p class="mt-1 text-3xl font-black text-cyan-900">{{ number_format($summary['requests_posted']) }}</p>
                <p class="text-xs text-cyan-700">{{ number_format($summary['requests_filled']) }} filled</p>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Families posting</p>
                <p class="mt-1 text-3xl font-black text-slate-900">{{ number_format($summary['posting_families']) }}</p>
                <p class="text-xs text-slate-500">{{ number_format($summary['avg_requests_per_posting_family'], 2) }} requests per posting family</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Hours tracked</p>
                <p class="mt-1 text-3xl font-black text-slate-900">{{ number_format($summary['tracked_hours'], 1) }}</p>
                <p class="text-xs text-slate-500">{{ number_format($summary['tracked_minutes']) }} worked minutes</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Family spend</p>
                <p class="mt-1 text-3xl font-black text-slate-900">${{ number_format($summary['money_spent_dollars'], 2) }}</p>
                <p class="text-xs text-slate-500">Captured net of refunds</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Final selected day</p>
                <p class="mt-1 text-3xl font-black text-slate-900">{{ number_format($summary['latest_dau']) }}</p>
                <p class="text-xs text-slate-500">Active users on the final selected day</p>
            </div>
        </div>

        <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-4 md:p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Daily active users</h2>
                    <p class="text-xs text-slate-500">Distinct authenticated users active each day.</p>
                </div>
                <p class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                    Peak {{ number_format($summary['peak_dau']) }}
                </p>
            </div>

            @php
                $maxDau = max(1, $summary['peak_dau']);
            @endphp

            <div class="mt-4 h-48">
                <div class="flex h-full items-end gap-1">
                    @foreach($dailyActiveUsers as $point)
                        @php
                            $height = max(6, (int) round(($point['count'] / $maxDau) * 100));
                        @endphp
                        <div class="flex h-full min-w-0 flex-1 flex-col items-center justify-end">
                            <div
                                class="w-full rounded-t-md bg-emerald-500/80 hover:bg-emerald-500"
                                style="height: {{ $height }}%;"
                                title="{{ $point['date'] }}: {{ number_format($point['count']) }} active users"
                            ></div>
                            <p class="mt-1 h-4 text-[10px] leading-4 text-slate-500">
                                {{ $point['show_label'] ? $point['label'] : '' }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-4 md:p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">{{ $groupingLabel }} usage breakdown</h2>
                    <p class="text-xs text-slate-500">Grouped summary for the selected date range.</p>
                </div>
                <p class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                    {{ count($bucketRows) }} {{ strtolower($groupingLabel) }} buckets
                </p>
            </div>

            <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Period</th>
                            <th class="px-4 py-3">Family signups</th>
                            <th class="px-4 py-3">Caregiver signups</th>
                            <th class="px-4 py-3">Active users</th>
                            <th class="px-4 py-3">Avg DAU</th>
                            <th class="px-4 py-3">Requests posted</th>
                            <th class="px-4 py-3">Requests filled</th>
                            <th class="px-4 py-3">Posting families</th>
                            <th class="px-4 py-3">Req/family</th>
                            <th class="px-4 py-3">Hours</th>
                            <th class="px-4 py-3">Family spend</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse($bucketRows as $row)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-slate-900 whitespace-nowrap">{{ $row['label'] }}</td>
                                <td class="px-4 py-3 text-slate-800">{{ number_format($row['family_signups']) }}</td>
                                <td class="px-4 py-3 text-slate-800">{{ number_format($row['caregiver_signups']) }}</td>
                                <td class="px-4 py-3 text-slate-800">{{ number_format($row['active_users']) }}</td>
                                <td class="px-4 py-3 text-slate-800">{{ number_format($row['avg_dau'], 1) }}</td>
                                <td class="px-4 py-3 text-slate-800">{{ number_format($row['requests_posted']) }}</td>
                                <td class="px-4 py-3 text-slate-800">{{ number_format($row['requests_filled']) }}</td>
                                <td class="px-4 py-3 text-slate-800">{{ number_format($row['posting_families']) }}</td>
                                <td class="px-4 py-3 text-slate-800">{{ number_format($row['avg_requests_per_posting_family'], 2) }}</td>
                                <td class="px-4 py-3 text-slate-800">{{ number_format($row['tracked_hours'], 1) }}</td>
                                <td class="px-4 py-3 text-slate-800">${{ number_format($row['money_spent_dollars'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-4 py-8 text-center text-sm text-slate-500">
                                    No usage data found in this range.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-card>
</div>
