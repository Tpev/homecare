<div class="hc-page py-8 space-y-6">
    <x-card>
        <x-slot:header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Caregiver Lifecycle Funnel</h1>
                    <p class="mt-1 text-sm text-slate-600">Cohort based on caregiver registrations since {{ $start->format('M d, Y') }}.</p>
                </div>
                <x-select.styled
                    wire:model.live="days"
                    :options="[
                        ['label' => 'Last 7 days', 'value' => 7],
                        ['label' => 'Last 14 days', 'value' => 14],
                        ['label' => 'Last 30 days', 'value' => 30],
                        ['label' => 'Last 60 days', 'value' => 60],
                        ['label' => 'Last 90 days', 'value' => 90],
                    ]"
                />
            </div>
        </x-slot:header>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Registered</p>
                <p class="mt-1 text-3xl font-black text-slate-900">{{ number_format($summary['registered']) }}</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-xs uppercase tracking-[0.14em] text-emerald-700">Activated</p>
                <p class="mt-1 text-3xl font-black text-emerald-900">{{ number_format($summary['activated']) }}</p>
                <p class="text-xs text-emerald-700">{{ number_format($summary['activation_rate'], 1) }}% of registrations</p>
            </div>
            <div class="rounded-xl border border-cyan-200 bg-cyan-50 p-4">
                <p class="text-xs uppercase tracking-[0.14em] text-cyan-700">Completed Shift</p>
                <p class="mt-1 text-3xl font-black text-cyan-900">{{ number_format($summary['completed_shift']) }}</p>
                <p class="text-xs text-cyan-700">{{ number_format($summary['completion_rate'], 1) }}% of registrations</p>
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

        @if(($summary['registered'] ?? 0) === 0)
            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                No caregiver registrations found in this date range yet.
            </div>
        @endif
    </x-card>
</div>
