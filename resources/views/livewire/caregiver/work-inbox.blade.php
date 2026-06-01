<div class="hc-page space-y-5 py-5 sm:space-y-6 sm:py-8">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    @if (!empty($prelaunchMode))
        <x-alert color="yellow">
            Matching opens soon in your area. Complete your profile now and we will notify you when matching opens.
        </x-alert>
    @endif

    @php
        $statusStyles = [
            'success' => 'bg-emerald-100 text-emerald-700',
            'warning' => 'bg-amber-100 text-amber-800',
            'danger' => 'bg-rose-100 text-rose-700',
            'info' => 'bg-[#E8F0FF] text-[#4F6FAF]',
            'neutral' => 'bg-[#F0E9E1] text-[#4B5B6B]',
        ];
        $visibleShiftValue = collect($items)->sum(fn ($item) => (float) data_get($item, 'compensation.total', 0));
        $responsiveValue = collect($items)->where('scope', 'needs_response')->sum(fn ($item) => (float) data_get($item, 'compensation.total', 0));

        $primaryActionClasses = 'inline-flex h-11 w-full items-center justify-center rounded-[1rem] bg-[#0F3D3E] px-4 text-sm font-semibold text-[#FAF9F7] shadow-sm transition hover:bg-[#123f40] focus:outline-none focus:ring-2 focus:ring-[#4F6FAF]/30 sm:w-auto';
        $successActionClasses = 'inline-flex h-11 w-full items-center justify-center rounded-[1rem] bg-[#4F6FAF] px-4 text-sm font-semibold text-[#FAF9F7] shadow-sm transition hover:bg-[#44639f] focus:outline-none focus:ring-2 focus:ring-[#4F6FAF]/30 sm:w-auto';
        $dangerActionClasses = 'inline-flex h-11 w-full items-center justify-center rounded-[1rem] border border-rose-200 bg-[rgba(255,253,250,0.98)] px-4 text-sm font-semibold text-rose-700 transition hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-500/30 sm:w-auto';
        $secondaryActionClasses = 'inline-flex h-11 w-full items-center justify-center rounded-[1rem] border border-[#DED6CA] bg-[rgba(255,253,250,0.98)] px-4 text-sm font-semibold text-[#0F3D3E] transition hover:bg-[#F5F1EB] focus:outline-none focus:ring-2 focus:ring-[#4F6FAF]/20 sm:w-auto';
    @endphp

    <section class="hc-brand-panel p-4 sm:p-5">
        <div class="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-[#7C5DDC]/20 blur-2xl"></div>
        <div class="pointer-events-none absolute -left-10 -bottom-14 h-40 w-40 rounded-full bg-[#4F6FAF]/20 blur-2xl"></div>

        <div class="relative space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-start sm:justify-between">
                <div>
                    <p class="hc-brand-kicker text-[#E8E0FF]">Caregiver Work Inbox</p>
                    <h1 class="mt-1 text-2xl font-display font-semibold leading-tight sm:text-3xl">Stay on top of new opportunities.</h1>
                    <p class="mt-1 text-sm text-[#F7F1E8]/82">Offers, applications, hired shifts, and recaps in one place.</p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                    <a href="{{ route('caregiver.shifts.index') }}" wire:navigate>
                        <x-button color="white" light class="w-full sm:w-auto" sm>My shifts</x-button>
                    </a>
                    <a href="{{ route('caregiver.earnings.index') }}" wire:navigate>
                        <x-button color="white" light class="w-full sm:w-auto" sm>Earnings</x-button>
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2 lg:grid-cols-6">
                <div class="hc-brand-stat">
                    <p class="text-[11px] uppercase tracking-[0.14em] text-[#D7DEE6]">Needs response</p>
                    <p class="mt-1 text-lg font-semibold">{{ $counts['needs_response'] ?? 0 }}</p>
                </div>
                <div class="hc-brand-stat">
                    <p class="text-[11px] uppercase tracking-[0.14em] text-[#D7DEE6]">New requests</p>
                    <p class="mt-1 text-lg font-semibold">{{ $counts['new_requests'] ?? $counts['recommended'] ?? 0 }}</p>
                </div>
                <div class="hc-brand-stat">
                    <p class="text-[11px] uppercase tracking-[0.14em] text-[#D7DEE6]">Applied</p>
                    <p class="mt-1 text-lg font-semibold">{{ $counts['applied'] ?? 0 }}</p>
                </div>
                <div class="hc-brand-stat">
                    <p class="text-[11px] uppercase tracking-[0.14em] text-[#D7DEE6]">Hired</p>
                    <p class="mt-1 text-lg font-semibold">{{ $counts['hired'] ?? 0 }}</p>
                </div>
                <div class="hc-brand-stat">
                    <p class="text-[11px] uppercase tracking-[0.14em] text-[#D7DEE6]">Completed</p>
                    <p class="mt-1 text-lg font-semibold">{{ $counts['completed'] ?? 0 }}</p>
                </div>
                <div class="hc-brand-stat">
                    <p class="text-[11px] uppercase tracking-[0.14em] text-[#D7DEE6]">Total</p>
                    <p class="mt-1 text-lg font-semibold">{{ $counts['all'] ?? 0 }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-2 lg:grid-cols-2">
                <div class="rounded-[1.4rem] border border-[#BFD6CE] bg-[rgba(255,255,255,0.08)] px-4 py-3">
                    <p class="text-[11px] uppercase tracking-[0.14em] text-emerald-100">Visible shift value</p>
                    <p class="mt-1 text-2xl font-display font-semibold text-white">${{ number_format($visibleShiftValue, 2) }}</p>
                    <p class="text-xs text-emerald-100/90">Estimated total from jobs currently on this screen.</p>
                </div>
                <div class="rounded-[1.4rem] border border-[#D3CBF0] bg-[rgba(255,255,255,0.08)] px-4 py-3">
                    <p class="text-[11px] uppercase tracking-[0.14em] text-[#CFC6F7]">Ready-to-respond value</p>
                    <p class="mt-1 text-2xl font-display font-semibold text-white">${{ number_format($responsiveValue, 2) }}</p>
                    <p class="text-xs text-[#CFC6F7]/90">Fast opportunities waiting for your response.</p>
                </div>
            </div>
        </div>
    </section>

    <div class="sticky top-16 z-20 -mx-1 px-1">
        <div class="grid grid-cols-1 gap-2 rounded-2xl border border-[#E4DDD3] bg-white/95 p-2 shadow-sm backdrop-blur lg:grid-cols-4">
            <div class="overflow-x-auto lg:col-span-3">
                <div class="grid min-w-full grid-cols-2 gap-1 sm:flex sm:min-w-max lg:min-w-0 lg:grid lg:grid-cols-6 lg:gap-1">
                @foreach ($scopeOptions as $option)
                    <button
                        type="button"
                        wire:click="$set('scope', '{{ $option['value'] }}')"
                            class="h-11 rounded-xl px-3 text-sm font-medium transition {{ $scope === $option['value'] ? 'bg-[#0F3D3E] text-[#FAF9F7] shadow-sm' : 'text-[#6E746F] hover:bg-[#F5F1EB] hover:text-[#0F3D3E]' }}"
                    >
                        {{ $option['label'] }}
                    </button>
                @endforeach
                </div>
            </div>
            <div class="rounded-[1rem] border border-[#DED6CA] bg-[rgba(255,253,250,0.96)] p-2">
                <label for="work-inbox-sort" class="sr-only">Sort inbox</label>
                <select
                    id="work-inbox-sort"
                    wire:model.live="sort"
                    class="h-10 w-full rounded-xl border-0 bg-white px-3 text-sm font-medium text-[#0F3D3E] shadow-sm outline-none ring-1 ring-[#DED6CA] transition focus:ring-2 focus:ring-[#4F6FAF]/40"
                >
                    @foreach ($sortOptions as $option)
                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <section class="space-y-3 pt-1">
        @forelse ($items as $item)
            <article class="group overflow-hidden rounded-[1.8rem] border border-[#DED6CA] bg-[rgba(255,253,250,0.98)] p-4 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-lg sm:p-5">
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_17rem]">
                    <div class="min-w-0">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-display text-lg font-semibold leading-snug text-[#17313F]">{{ $item['title'] }}</p>
                                <p class="mt-1 text-sm text-[#607080]">{{ $item['location'] }} - {{ $item['schedule'] }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $statusStyles[$item['status_tone'] ?? 'neutral'] ?? $statusStyles['neutral'] }}">
                                {{ strtoupper((string) $item['status_label']) }}
                            </span>
                        </div>

                        <p class="mt-2 text-xs font-medium text-[#7B8794]">{{ $item['meta'] }}</p>
                        @if (!empty($item['recipient_context']))
                            <x-care-recipient-context :context="$item['recipient_context']" :show-name="true" class="mt-3" />
                        @endif
                        <p class="mt-2 text-sm text-[#4B5B6B]">{{ $item['fit_reason'] }}</p>

                        @if (!empty($item['request_details']))
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($item['request_details'] as $detail)
                                    <span class="inline-flex rounded-full border border-[#DED6CA] bg-[#FFFCF8] px-3 py-1 text-xs font-medium text-[#4B5B6B]">
                                        {{ $detail }}
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        @if (!empty($item['note']))
                            <div class="mt-3 rounded-lg border border-[#E4DDD3] bg-[#F7F2EA] px-3 py-2 text-sm text-[#4B5B6B]">
                                {{ $item['note'] }}
                            </div>
                        @endif

                        @if (!empty($item['compensation_line']))
                            <p class="mt-3 text-xs font-semibold uppercase tracking-[0.12em] text-[#7B8794]">{{ $item['compensation_line'] }}</p>
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
                            <div class="rounded-[1.4rem] border border-[#D7CCE9] bg-gradient-to-br from-[#0F3D3E] via-[#174A52] to-[#4F6FAF] p-4 text-white shadow-sm">
                                <p class="text-[11px] uppercase tracking-[0.14em] text-emerald-200">Estimated earnings</p>
                                <p class="mt-1 text-3xl font-display font-semibold text-emerald-300">
                                    ${{ number_format((float) data_get($item, 'compensation.total', 0), 2) }}
                                </p>
                                <div class="mt-3 space-y-1 text-sm">
                                    <p class="text-[#E7ECF1]">
                                        {{ data_get($item, 'compensation.hours_label') }}h shift
                                    </p>
                                    <p class="text-[#E7ECF1]">
                                        @ ${{ number_format((float) data_get($item, 'compensation.hourly_rate', 0), 2) }}/hr
                                    </p>
                                </div>
                            </div>
                        @else
                            <div class="rounded-[1.4rem] border border-[#DED6CA] bg-[#F5F1EB] p-4">
                                <p class="text-[11px] uppercase tracking-[0.14em] text-[#7B8794]">Earnings</p>
                                <p class="mt-2 text-sm text-[#607080]">Compensation estimate appears when duration is defined.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-[#D6CCBE] bg-white px-4 py-8 text-center text-sm text-[#607080]">
                No items for this filter yet.
            </div>
        @endforelse
    </section>
</div>



