<div class="hc-page py-8 space-y-6">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    @if (!empty($prelaunchMode))
        <x-alert color="yellow">
            Caregiver pre-launch mode is active. Complete your profile now and we will notify you when matching opens.
        </x-alert>
    @endif

    @php
        $statusStyles = [
            'success' => 'bg-emerald-100 text-emerald-700',
            'warning' => 'bg-amber-100 text-amber-800',
            'danger' => 'bg-rose-100 text-rose-700',
            'info' => 'bg-sky-100 text-sky-700',
            'neutral' => 'bg-slate-100 text-slate-700',
        ];
        $visibleShiftValue = collect($items)->sum(fn ($item) => (float) data_get($item, 'compensation.total', 0));
        $responsiveValue = collect($items)->where('scope', 'needs_response')->sum(fn ($item) => (float) data_get($item, 'compensation.total', 0));

        $primaryActionClasses = 'inline-flex h-11 w-full items-center justify-center rounded-xl bg-blue-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500/40 sm:w-auto';
        $successActionClasses = 'inline-flex h-11 w-full items-center justify-center rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500/40 sm:w-auto';
        $dangerActionClasses = 'inline-flex h-11 w-full items-center justify-center rounded-xl border border-rose-200 bg-white px-4 text-sm font-semibold text-rose-700 transition hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-500/30 sm:w-auto';
        $secondaryActionClasses = 'inline-flex h-11 w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400/30 sm:w-auto';
    @endphp

    <section class="relative overflow-hidden rounded-3xl border border-slate-900/80 bg-slate-950 p-4 text-white shadow-xl sm:p-5">
        <div class="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-emerald-500/20 blur-2xl"></div>
        <div class="pointer-events-none absolute -left-10 -bottom-14 h-40 w-40 rounded-full bg-cyan-500/20 blur-2xl"></div>

        <div class="relative space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-[11px] uppercase tracking-[0.18em] text-slate-300">Caregiver Work Inbox</p>
                    <h1 class="mt-1 text-2xl font-display font-semibold leading-tight sm:text-3xl">Know what to do next, fast.</h1>
                    <p class="mt-1 text-sm text-slate-300">Offers, applications, hired shifts, and recaps in one place.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('caregiver.shifts.index') }}" wire:navigate>
                        <x-button color="white" light sm>My shifts</x-button>
                    </a>
                    <a href="{{ route('caregiver.earnings.index') }}" wire:navigate>
                        <x-button color="white" light sm>Earnings</x-button>
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
                <div class="rounded-xl border border-white/15 bg-white/5 px-3 py-2">
                    <p class="text-[11px] uppercase tracking-[0.14em] text-slate-300">Needs response</p>
                    <p class="mt-1 text-lg font-semibold">{{ $counts['needs_response'] ?? 0 }}</p>
                </div>
                <div class="rounded-xl border border-white/15 bg-white/5 px-3 py-2">
                    <p class="text-[11px] uppercase tracking-[0.14em] text-slate-300">Recommended</p>
                    <p class="mt-1 text-lg font-semibold">{{ $counts['recommended'] ?? 0 }}</p>
                </div>
                <div class="rounded-xl border border-white/15 bg-white/5 px-3 py-2">
                    <p class="text-[11px] uppercase tracking-[0.14em] text-slate-300">Applied</p>
                    <p class="mt-1 text-lg font-semibold">{{ $counts['applied'] ?? 0 }}</p>
                </div>
                <div class="rounded-xl border border-white/15 bg-white/5 px-3 py-2">
                    <p class="text-[11px] uppercase tracking-[0.14em] text-slate-300">Hired</p>
                    <p class="mt-1 text-lg font-semibold">{{ $counts['hired'] ?? 0 }}</p>
                </div>
                <div class="rounded-xl border border-white/15 bg-white/5 px-3 py-2">
                    <p class="text-[11px] uppercase tracking-[0.14em] text-slate-300">Completed</p>
                    <p class="mt-1 text-lg font-semibold">{{ $counts['completed'] ?? 0 }}</p>
                </div>
                <div class="rounded-xl border border-white/15 bg-white/5 px-3 py-2">
                    <p class="text-[11px] uppercase tracking-[0.14em] text-slate-300">Total</p>
                    <p class="mt-1 text-lg font-semibold">{{ $counts['all'] ?? 0 }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-2 lg:grid-cols-2">
                <div class="rounded-2xl border border-emerald-300/35 bg-emerald-500/10 px-4 py-3">
                    <p class="text-[11px] uppercase tracking-[0.14em] text-emerald-100">Visible shift value</p>
                    <p class="mt-1 text-2xl font-display font-semibold text-white">${{ number_format($visibleShiftValue, 2) }}</p>
                    <p class="text-xs text-emerald-100/90">Estimated total from jobs currently on this screen.</p>
                </div>
                <div class="rounded-2xl border border-cyan-300/35 bg-cyan-500/10 px-4 py-3">
                    <p class="text-[11px] uppercase tracking-[0.14em] text-cyan-100">Ready-to-respond value</p>
                    <p class="mt-1 text-2xl font-display font-semibold text-white">${{ number_format($responsiveValue, 2) }}</p>
                    <p class="text-xs text-cyan-100/90">Fast opportunities waiting for your response.</p>
                </div>
            </div>
        </div>
    </section>

    <div class="sticky top-16 z-20 -mx-1 px-1">
        <div class="grid grid-cols-1 gap-2 rounded-2xl border border-slate-200 bg-white/95 p-2 shadow-sm backdrop-blur lg:grid-cols-4">
            <div class="overflow-x-auto lg:col-span-3">
                <div class="flex min-w-max gap-1 lg:min-w-0 lg:grid lg:grid-cols-6 lg:gap-1">
                @foreach ($scopeOptions as $option)
                    <button
                        type="button"
                        wire:click="$set('scope', '{{ $option['value'] }}')"
                            class="h-11 rounded-xl px-3 text-sm font-medium transition {{ $scope === $option['value'] ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                    >
                        {{ $option['label'] }}
                    </button>
                @endforeach
                </div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-2">
                <x-select.styled wire:model.live="sort" :options="$sortOptions" />
            </div>
        </div>
    </div>

    <section class="space-y-3 pt-1">
        @forelse ($items as $item)
            <article class="group overflow-hidden rounded-3xl border border-slate-200 bg-white p-4 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-lg sm:p-5">
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_17rem]">
                    <div class="min-w-0">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-display text-lg font-semibold leading-snug text-slate-900">{{ $item['title'] }}</p>
                                <p class="mt-1 text-sm text-slate-600">{{ $item['location'] }} · {{ $item['schedule'] }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $statusStyles[$item['status_tone'] ?? 'neutral'] ?? $statusStyles['neutral'] }}">
                                {{ strtoupper((string) $item['status_label']) }}
                            </span>
                        </div>

                        <p class="mt-2 text-xs font-medium text-slate-500">{{ $item['meta'] }}</p>
                        <p class="mt-2 text-sm text-slate-700">{{ $item['fit_reason'] }}</p>

                        @if (!empty($item['note']))
                            <div class="mt-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                {{ $item['note'] }}
                            </div>
                        @endif

                        @if (!empty($item['compensation_line']))
                            <p class="mt-3 text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">{{ $item['compensation_line'] }}</p>
                        @endif

                        <div class="mt-4 grid grid-cols-1 gap-2 sm:flex sm:flex-wrap sm:items-center">
                            @if (($item['primary_action']['kind'] ?? null) === 'inline')
                                <button
                                    type="button"
                                    wire:click="{{ $item['primary_action']['method'] }}({{ $item['primary_action']['id'] }})"
                                    class="{{ $item['primary_action']['method'] === 'acceptInvitation' ? $successActionClasses : $primaryActionClasses }}"
                                    aria-label="{{ $item['primary_action']['label'] }}"
                                >
                                    {{ $item['primary_action']['label'] }}
                                </button>
                            @elseif (($item['primary_action']['kind'] ?? null) === 'link')
                                <a href="{{ $item['primary_action']['href'] }}" wire:navigate class="{{ $primaryActionClasses }}" aria-label="{{ $item['primary_action']['label'] }}">
                                    {{ $item['primary_action']['label'] }}
                                </a>
                            @endif

                            @if (!empty($item['secondary_action']))
                                @if (($item['secondary_action']['kind'] ?? null) === 'inline')
                                    <button
                                        type="button"
                                        wire:click="{{ $item['secondary_action']['method'] }}({{ $item['secondary_action']['id'] }})"
                                        class="{{ $item['secondary_action']['method'] === 'declineInvitation' ? $dangerActionClasses : $secondaryActionClasses }}"
                                        aria-label="{{ $item['secondary_action']['label'] }}"
                                    >
                                        {{ $item['secondary_action']['label'] }}
                                    </button>
                                @elseif (($item['secondary_action']['kind'] ?? null) === 'link')
                                    <a href="{{ $item['secondary_action']['href'] }}" wire:navigate class="{{ $secondaryActionClasses }}" aria-label="{{ $item['secondary_action']['label'] }}">
                                        {{ $item['secondary_action']['label'] }}
                                    </a>
                                @endif
                            @endif
                        </div>
                    </div>

                    <div class="lg:pl-1">
                        @if (!empty($item['compensation']))
                            <div class="rounded-2xl border border-emerald-300/35 bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 p-4 text-white shadow-sm">
                                <p class="text-[11px] uppercase tracking-[0.14em] text-emerald-200">Estimated earnings</p>
                                <p class="mt-1 text-3xl font-display font-semibold text-emerald-300">
                                    ${{ number_format((float) data_get($item, 'compensation.total', 0), 2) }}
                                </p>
                                <div class="mt-3 space-y-1 text-sm">
                                    <p class="text-slate-200">
                                        {{ data_get($item, 'compensation.hours_label') }}h shift
                                    </p>
                                    <p class="text-slate-200">
                                        @ ${{ number_format((float) data_get($item, 'compensation.hourly_rate', 0), 2) }}/hr
                                    </p>
                                </div>
                            </div>
                        @else
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-[11px] uppercase tracking-[0.14em] text-slate-500">Earnings</p>
                                <p class="mt-2 text-sm text-slate-600">Compensation estimate appears when duration is defined.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-8 text-center text-sm text-slate-600">
                No items for this filter yet.
            </div>
        @endforelse
    </section>
</div>
