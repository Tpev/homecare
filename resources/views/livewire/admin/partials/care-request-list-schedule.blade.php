<div class="space-y-3 rounded-xl border border-sky-200 border-l-4 border-l-sky-600 bg-sky-50/70 p-4">
    <div>
        <p class="text-xs font-bold uppercase tracking-wide text-sky-800">{{ $schedule['primary']['label'] }}</p>
        <p class="mt-1 text-lg font-bold leading-snug text-slate-950">{{ $schedule['primary']['date'] }}</p>
    </div>

    @if ($schedule['primary']['rows'])
        <dl class="space-y-2 text-sm">
            @foreach ($schedule['primary']['rows'] as $slot)
                <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5">
                    <dt class="font-medium text-slate-700">{{ $slot['day'] }}</dt>
                    <dd class="font-bold tabular-nums text-slate-950">{{ $slot['time'] }} <span class="block text-xs font-normal text-slate-600">{{ $slot['duration'] }}</span></dd>
                </div>
            @endforeach
        </dl>
    @elseif ($schedule['primary']['time'])
        <p class="text-lg font-bold leading-snug tabular-nums text-slate-950">{{ $schedule['primary']['time'] }}</p>
    @endif

    <div class="space-y-1 border-t border-sky-200 pt-2">
        <p class="text-sm font-semibold text-slate-900">{{ $schedule['primary']['duration'] === 'Duration unavailable' ? 'Duration unavailable' : 'Duration: '.$schedule['primary']['duration'] }}</p>
        <p class="break-words text-xs text-slate-600">Time zone: {{ $schedule['primary']['timezone'] }}</p>
    </div>

    @if ($schedule['primary']['range'])
        <p class="text-sm text-slate-700">{{ $schedule['primary']['range'] }}</p>
    @endif

    @if ($schedule['next'] && $request->request_type === \App\Models\CareRequest::TYPE_RECURRING)
        <p class="rounded-lg border border-sky-200 bg-white px-3 py-2 text-sm font-semibold text-sky-900">
            <span class="block text-xs font-medium text-sky-800">{{ ($schedule['confirmed']['label'] ?? '') === 'Next confirmed visit' ? 'Next confirmed visit' : 'Next requested visit' }}</span>
            {{ $schedule['next'] }}
        </p>
    @endif

    @if ($schedule['primary']['notice'])
        <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm font-medium text-amber-900">{{ $schedule['primary']['notice'] }}</p>
    @endif

    @if ($schedule['confirmed'] && $request->request_type !== \App\Models\CareRequest::TYPE_RECURRING)
        <p class="border-t border-sky-200 pt-2 text-xs text-slate-600">
            Originally requested: {{ $schedule['requested']['date'] }}
            @if ($schedule['requested']['time'])
                · {{ $schedule['requested']['time'] }}
            @endif
            · {{ $schedule['requested']['timezone'] }}
        </p>
    @endif
</div>
