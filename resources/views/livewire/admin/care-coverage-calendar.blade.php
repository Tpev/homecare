<div class="hc-page space-y-6 py-8">
    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-cyan-700">Analytics · Care operations</p>
                <h1 class="mt-1 text-3xl font-black tracking-tight text-slate-950">Care Coverage Calendar</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                    Every scheduled shift and open care request in one operational view. Each slot identifies the customer, Family Account, caregiver, time, and current status.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button type="button" wire:click="previousMonth" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" aria-label="Previous month">&larr;</button>
                <button type="button" wire:click="goToToday" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Today</button>
                <button type="button" wire:click="nextMonth" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" aria-label="Next month">&rarr;</button>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4">
                <p class="text-xs font-bold uppercase tracking-wide text-sky-700">Shifts in view</p>
                <p class="mt-1 text-3xl font-black text-sky-950">{{ number_format($summary['shifts']) }}</p>
                <p class="mt-1 text-xs text-sky-700">Assigned bookings</p>
            </div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                <p class="text-xs font-bold uppercase tracking-wide text-amber-700">Open slots</p>
                <p class="mt-1 text-3xl font-black text-amber-950">{{ number_format($summary['open_slots']) }}</p>
                <p class="mt-1 text-xs text-amber-700">Unassigned demand in view</p>
            </div>
            <div class="rounded-2xl border border-orange-200 bg-orange-50 p-4">
                <p class="text-xs font-bold uppercase tracking-wide text-orange-700">Open requests</p>
                <p class="mt-1 text-3xl font-black text-orange-950">{{ number_format($summary['open_requests']) }}</p>
                <p class="mt-1 text-xs text-orange-700">Prospective requests in queue</p>
            </div>
            <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4">
                <p class="text-xs font-bold uppercase tracking-wide text-indigo-700">Families in view</p>
                <p class="mt-1 text-3xl font-black text-indigo-950">{{ number_format($summary['families']) }}</p>
                <p class="mt-1 text-xs text-indigo-700">Distinct Family Accounts</p>
            </div>
        </div>
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-6">
            <label class="text-xs font-semibold text-slate-600 xl:col-span-2">
                Search people or requests
                <input type="search" wire:model.live.debounce.300ms="q" placeholder="Customer, family, caregiver…" class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-cyan-500 focus:ring-cyan-500">
            </label>
            <label class="text-xs font-semibold text-slate-600">
                Calendar content
                <select wire:model.live="view" class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-cyan-500 focus:ring-cyan-500">
                    @foreach($viewOptions as $option)
                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-semibold text-slate-600">
                Family Account
                <select wire:model.live="familyAccount" class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-cyan-500 focus:ring-cyan-500">
                    <option value="all">All families</option>
                    @foreach($familyOptions as $option)
                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-semibold text-slate-600">
                Caregiver
                <select wire:model.live="caregiver" class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-cyan-500 focus:ring-cyan-500">
                    <option value="all">All caregivers</option>
                    @foreach($caregiverOptions as $option)
                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-semibold text-slate-600">
                Status
                <select wire:model.live="status" class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-cyan-500 focus:ring-cyan-500">
                    @foreach($statusOptions as $option)
                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500">
            <p>Amber cards are open, unassigned requests. Blue and status-colored cards are confirmed shifts.</p>
            @if($view !== 'all' || $familyAccount !== 'all' || $caregiver !== 'all' || $status !== 'all' || trim($q) !== '')
                <button type="button" wire:click="clearFilters" class="font-bold text-cyan-700 hover:text-cyan-900">Clear filters</button>
            @endif
        </div>
    </section>

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <div>
                <h2 class="text-2xl font-black text-slate-950">{{ $calendarMonth->format('F Y') }}</h2>
                <p class="text-xs text-slate-500">Displaying {{ $gridStart->format('M j') }}–{{ $gridEnd->format('M j, Y') }}</p>
            </div>
            <div class="flex flex-wrap gap-3 text-xs font-semibold text-slate-600">
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-sky-400"></span>Shift</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span>Open request</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>In progress</span>
            </div>
        </div>

        <div class="hidden overflow-x-auto lg:block">
            <div class="min-w-[1280px]">
                <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50">
                    @foreach(['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $weekday)
                        <div class="px-3 py-2 text-center text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">{{ $weekday }}</div>
                    @endforeach
                </div>

                <div class="grid grid-cols-7">
                    @foreach($days as $day)
                        <div class="min-h-56 border-b border-r border-slate-200 p-2 {{ $day['is_current_month'] ? 'bg-white' : 'bg-slate-50/70' }}">
                            <div class="mb-2 flex items-center justify-between">
                                <span class="inline-flex h-7 min-w-7 items-center justify-center rounded-full px-1.5 text-xs font-bold {{ $day['is_today'] ? 'bg-cyan-700 text-white' : ($day['is_current_month'] ? 'text-slate-800' : 'text-slate-400') }}">
                                    {{ $day['date']->day }}
                                </span>
                                @if($day['events']->isNotEmpty())
                                    <span class="text-[10px] font-bold text-slate-400">{{ $day['events']->count() }} slot{{ $day['events']->count() === 1 ? '' : 's' }}</span>
                                @endif
                            </div>
                            <div class="space-y-2">
                                @foreach($day['events'] as $event)
                                    @include('livewire.admin.partials.care-calendar-event', ['event' => $event])
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="divide-y divide-slate-200 lg:hidden">
            @forelse(collect($days)->filter(fn ($day) => $day['events']->isNotEmpty()) as $day)
                <div class="p-4 sm:p-5">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="font-bold text-slate-900">{{ $day['date']->format('l, F j') }}</h3>
                        <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-bold text-slate-600">{{ $day['events']->count() }} slot{{ $day['events']->count() === 1 ? '' : 's' }}</span>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach($day['events'] as $event)
                            @include('livewire.admin.partials.care-calendar-event', ['event' => $event])
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="px-5 py-12 text-center">
                    <p class="font-semibold text-slate-800">No calendar slots match this view.</p>
                    <p class="mt-1 text-sm text-slate-500">Try another month or clear the filters.</p>
                </div>
            @endforelse
        </div>
    </section>

    @if($view !== 'shifts')
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-amber-700">Prospective demand</p>
                    <h2 class="mt-1 text-2xl font-black text-slate-950">Open request queue</h2>
                    <p class="mt-1 text-sm text-slate-600">Every unfulfilled request matching the current family and search filters, including requests without a calendar-ready schedule.</p>
                </div>
                <p class="text-sm font-bold text-slate-700">{{ number_format($openRequests->count()) }} request{{ $openRequests->count() === 1 ? '' : 's' }}</p>
            </div>

            @if($caregiver !== 'all')
                <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    Open requests are unassigned, so they are hidden while a caregiver filter is active. Clear that filter to view the prospective queue.
                </div>
            @else
                <div class="mt-5 space-y-3 md:hidden">
                    @forelse($openRequests as $request)
                        <a href="{{ $request['url'] }}" wire:navigate class="block rounded-2xl border border-amber-200 bg-amber-50 p-4 transition hover:border-amber-400">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-bold text-amber-950">{{ $request['customer'] }}</p>
                                    <p class="text-sm text-amber-900">{{ $request['title'] }}</p>
                                </div>
                                @if($request['is_private'])<span class="rounded-full bg-indigo-700 px-2 py-1 text-[10px] font-bold text-white">Private</span>@endif
                            </div>
                            <dl class="mt-3 space-y-1 text-xs text-amber-900">
                                <div>
                                    <dt class="inline font-bold">Family:</dt>
                                    <dd class="inline">{{ $request['family'] }}@if($request['family_account_id']) · Account #{{ $request['family_account_id'] }}@endif</dd>
                                </div>
                                <div><dt class="inline font-bold">Schedule:</dt> <dd class="inline">{{ $request['schedule'] }}</dd></div>
                                <div><dt class="inline font-bold">Caregiver:</dt> <dd class="inline">Unassigned</dd></div>
                                <div><dt class="inline font-bold">Pipeline:</dt> <dd class="inline">{{ $request['applications'] }} applicants · {{ $request['invitations'] }} invites</dd></div>
                            </dl>
                        </a>
                    @empty
                        <p class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">No open requests match the current filters.</p>
                    @endforelse
                </div>

                <div class="mt-5 hidden overflow-x-auto rounded-2xl border border-slate-200 md:block">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-[11px] font-bold uppercase tracking-wide text-slate-500">
                                <th class="px-4 py-3">Customer / request</th>
                                <th class="px-4 py-3">Family Account</th>
                                <th class="px-4 py-3">Requested schedule</th>
                                <th class="px-4 py-3">Caregiver</th>
                                <th class="px-4 py-3">Pipeline</th>
                                <th class="px-4 py-3 text-right">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            @forelse($openRequests as $request)
                                <tr>
                                    <td class="px-4 py-3 align-top">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="font-bold text-slate-950">{{ $request['customer'] }}</p>
                                            @if($request['is_private'])<span class="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-bold text-indigo-800">Private</span>@endif
                                        </div>
                                        <p class="mt-0.5 text-xs text-slate-600">{{ $request['title'] }} · #{{ $request['id'] }}</p>
                                    </td>
                                    <td class="px-4 py-3 align-top">
                                        <p class="font-semibold text-slate-900">{{ $request['family'] }}</p>
                                        <p class="text-xs text-slate-500">{{ $request['family_account_id'] ? 'Account #'.$request['family_account_id'] : 'Legacy family record' }}</p>
                                    </td>
                                    <td class="px-4 py-3 align-top">
                                        <p class="font-semibold text-slate-900">{{ $request['schedule'] }}</p>
                                        @if($request['location'])<p class="text-xs text-slate-500">{{ $request['location'] }}</p>@endif
                                    </td>
                                    <td class="px-4 py-3 align-top"><span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800">Unassigned</span></td>
                                    <td class="px-4 py-3 align-top text-xs text-slate-600">{{ $request['applications'] }} applicants<br>{{ $request['invitations'] }} invites</td>
                                    <td class="px-4 py-3 text-right align-top"><a href="{{ $request['url'] }}" wire:navigate class="font-bold text-cyan-700 hover:text-cyan-900">Open</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">No open requests match the current filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif
</div>
