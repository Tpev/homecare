<section class="rounded-xl border {{ $primary ? 'border-cyan-200 bg-cyan-50/50' : 'border-slate-200 bg-slate-50' }} p-4 sm:p-5">
    <h2 class="text-sm font-semibold {{ $primary ? 'text-cyan-900' : 'text-slate-700' }}">{{ $summary['label'] }}</h2>
    <div class="mt-3 grid gap-4 sm:grid-cols-3">
        <div class="sm:col-span-2">
            <p class="text-xl font-semibold text-slate-900 sm:text-2xl">{{ $summary['date'] }}</p>
            @if(!$summary['rows'])
                <p class="mt-1 text-lg font-medium text-slate-800">{{ $summary['time'] }}</p>
            @endif
            <p class="mt-2 break-words text-sm text-slate-600">Timezone: {{ $summary['timezone'] }}</p>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $summary['rows'] ? 'Weekly total' : 'Duration' }}</p>
            <p class="mt-1 text-lg font-semibold text-slate-900">{{ $summary['duration'] }}</p>
        </div>
    </div>
    @if($summary['range'])
        <p class="mt-3 text-sm font-medium text-slate-700">{{ $summary['range'] }}</p>
    @endif
    @if($summary['rows'])
        <ul class="mt-4 divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white sm:hidden">
            @foreach($summary['rows'] as $slot)
                <li class="px-3 py-3">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <span class="text-sm font-semibold text-slate-900">{{ $slot['day'] }}</span>
                        <span class="text-xs text-slate-600">{{ $slot['duration'] }} / visit</span>
                    </div>
                    <p class="mt-1 whitespace-nowrap text-sm text-slate-700">{{ $slot['time'] }}</p>
                </li>
            @endforeach
        </ul>
        <div class="mt-4 hidden overflow-x-auto rounded-lg border border-slate-200 bg-white sm:block">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-3 py-2 font-semibold">Day</th>
                        <th scope="col" class="px-3 py-2 font-semibold">Care time</th>
                        <th scope="col" class="px-3 py-2 font-semibold">Per visit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($summary['rows'] as $slot)
                        <tr>
                            <th scope="row" class="px-3 py-2 font-medium text-slate-900">{{ $slot['day'] }}</th>
                            <td class="whitespace-nowrap px-3 py-2 text-slate-700">{{ $slot['time'] }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $slot['duration'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    @if($summary['notice'])
        <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">{{ $summary['notice'] }}</p>
    @endif
</section>
