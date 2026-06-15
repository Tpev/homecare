<div class="hc-page space-y-5 py-5 sm:py-6">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    @php
        $statusStyles = [
            'paid' => 'bg-emerald-100 text-emerald-700',
            'scheduled_payout' => 'bg-indigo-100 text-indigo-700',
            'eligible' => 'bg-[#EAF6F6] text-[#7C5DDC]',
            'pending_confirmation' => 'bg-amber-100 text-amber-800',
            'in_progress' => 'bg-emerald-100 text-emerald-700',
            'paused' => 'bg-amber-100 text-amber-800',
            'scheduled' => 'bg-[#F0E9E1] text-[#4B5B6B]',
            'disputed' => 'bg-rose-100 text-rose-700',
            'cancelled' => 'bg-[#E9E1D5] text-[#607080]',
        ];
        $maxTrend = max(1, (float) collect($trend)->max('amount'));
    @endphp

    <section class="hc-brand-panel">
        <div class="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-[#7C5DDC]/20 blur-2xl"></div>
        <div class="pointer-events-none absolute -left-10 -bottom-14 h-40 w-40 rounded-full bg-[#4F6FAF]/20 blur-2xl"></div>

        <div class="relative space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="hc-brand-kicker text-[#E8E0FF]">Earnings</p>
                    <h1 class="mt-1 text-3xl font-display font-semibold leading-tight">${{ number_format((float) ($summary['week_gross'] ?? 0), 2) }}</h1>
                    <p class="mt-1 text-sm text-[#F7F1E8]/82">This week gross</p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                    <a href="{{ route('caregiver.shifts.index') }}" wire:navigate>
                        <x-button color="white" light class="w-full sm:w-auto" sm>My visits</x-button>
                    </a>
                    <a href="{{ route('caregiver.work-inbox.index') }}" wire:navigate>
                        <x-button color="white" light class="w-full sm:w-auto" sm>Find work</x-button>
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                <div class="hc-brand-stat">
                    <p class="text-[11px] uppercase tracking-[0.16em] text-[#F0E9E1]/70">Today</p>
                    <p class="mt-1 text-lg font-semibold">${{ number_format((float) ($summary['today_gross'] ?? 0), 2) }}</p>
                </div>
                <div class="hc-brand-stat">
                    <p class="text-[11px] uppercase tracking-[0.16em] text-[#F0E9E1]/70">Available</p>
                    <p class="mt-1 text-lg font-semibold text-emerald-300">${{ number_format((float) ($summary['available_balance'] ?? 0), 2) }}</p>
                </div>
                <div class="hc-brand-stat">
                    <p class="text-[11px] uppercase tracking-[0.16em] text-[#F0E9E1]/70">Pending</p>
                    <p class="mt-1 text-lg font-semibold text-amber-200">${{ number_format((float) ($summary['pending_balance'] ?? 0), 2) }}</p>
                </div>
                <div class="hc-brand-stat">
                    <p class="text-[11px] uppercase tracking-[0.16em] text-[#F0E9E1]/70">Paid this month</p>
                    <p class="mt-1 text-lg font-semibold">${{ number_format((float) ($summary['paid_this_month'] ?? 0), 2) }}</p>
                </div>
            </div>

            <div class="rounded-[1.4rem] border border-[#BFD6CE] bg-[rgba(255,255,255,0.08)] px-3 py-3">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs uppercase tracking-[0.14em] text-emerald-100">{{ ($nextPayout['type'] ?? 'estimated') === 'scheduled' ? 'Next payout' : 'Estimated next payout' }}</p>
                    <span class="rounded-full bg-[rgba(255,253,250,0.98)]/15 px-2 py-0.5 text-[11px] text-emerald-100">{{ $nextPayout['date']?->format('D, M d') }}</span>
                </div>
                <p class="mt-1 text-2xl font-semibold">${{ number_format((float) ($nextPayout['amount'] ?? 0), 2) }}</p>
                <p class="text-xs text-emerald-100/80">{{ $nextPayout['subtitle'] ?? '' }}</p>
            </div>
        </div>
    </section>

    <div class="grid grid-cols-3 gap-2 rounded-2xl border border-[#DED6CA] bg-[rgba(255,253,250,0.96)] p-1 shadow-sm">
        <button type="button" wire:click="setActiveTab('overview')" class="rounded-xl px-3 py-2 text-sm font-medium transition {{ $activeTab === 'overview' ? 'bg-[#0F3D3E] text-[#FAF9F7]' : 'text-[#6E746F] hover:text-[#0F3D3E]' }}">
            Overview
        </button>
        <button type="button" wire:click="setActiveTab('shifts')" class="rounded-xl px-3 py-2 text-sm font-medium transition {{ $activeTab === 'shifts' ? 'bg-[#0F3D3E] text-[#FAF9F7]' : 'text-[#6E746F] hover:text-[#0F3D3E]' }}">
            Visits
        </button>
        <button type="button" wire:click="setActiveTab('payouts')" class="rounded-xl px-3 py-2 text-sm font-medium transition {{ $activeTab === 'payouts' ? 'bg-[#0F3D3E] text-[#FAF9F7]' : 'text-[#6E746F] hover:text-[#0F3D3E]' }}">
            Payouts
        </button>
    </div>

    @if ($activeTab === 'overview')
        <section class="space-y-4">
            <x-card>
                <x-slot:header>
                    <h2 class="font-display text-lg font-semibold">Best next action</h2>
                </x-slot:header>
                <div class="rounded-xl border border-[#D8D1F1] bg-[#F5F1FB] px-4 py-3">
                    <p class="font-semibold text-[#0F3D3E]">{{ $nextAction['title'] }}</p>
                    <p class="mt-1 text-sm text-[#5B4CB5]">{{ $nextAction['description'] }}</p>
                    <div class="mt-3">
                        <a href="{{ $nextAction['cta_href'] }}" wire:navigate>
                            <span class="hc-primary-button">{{ $nextAction['cta_label'] }}</span>
                        </a>
                    </div>
                </div>
            </x-card>

            <x-card>
                <x-slot:header>
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="font-display text-lg font-semibold">Weekly goal</h2>
                        <span class="text-sm text-[#7B8794]">${{ number_format((float) ($goal['current'] ?? 0), 2) }} / ${{ number_format((float) ($goal['target'] ?? 0), 2) }}</span>
                    </div>
                </x-slot:header>
                <div class="space-y-2">
                    <div class="h-3 w-full overflow-hidden rounded-full bg-[#F0E9E1]">
                        <div class="h-full rounded-full bg-gradient-to-r from-[#0F3D3E] via-[#4F6FAF] to-[#7C5DDC] transition-all" style="width: {{ (int) ($goal['progress'] ?? 0) }}%"></div>
                    </div>
                    <p class="text-xs text-[#607080]">
                        @if (($goal['remaining'] ?? 0) > 0)
                            ${{ number_format((float) $goal['remaining'], 2) }} to hit this week's goal.
                        @else
                            Goal reached. Keep momentum going.
                        @endif
                    </p>
                </div>
            </x-card>

            <x-card>
                <x-slot:header>
                    <h2 class="font-display text-lg font-semibold">8-week trend</h2>
                </x-slot:header>
                @if ((float) collect($trend)->sum('amount') <= 0)
                    <div class="rounded-xl border border-dashed border-[#D6CCBE] bg-[#F5F1EB] px-4 py-8 text-center text-sm text-[#607080]">
                        No earnings trend yet. Once you complete paid visits, your weekly history will appear here.
                    </div>
                @else
                    <div class="grid grid-cols-8 items-end gap-2">
                        @foreach ($trend as $point)
                            @php
                                $height = max(10, (int) round(($point['amount'] / $maxTrend) * 112));
                            @endphp
                            <div class="space-y-1 text-center">
                                <div class="mx-auto flex h-32 w-full max-w-[28px] items-end rounded-md bg-[#F0E9E1]">
                                    <div class="w-full rounded-md bg-gradient-to-t from-[#0F3D3E] via-[#4F6FAF] to-[#7C5DDC]" style="height: {{ $height }}px"></div>
                                </div>
                                <p class="text-[10px] text-[#7B8794]">{{ $point['label'] }}</p>
                                <p class="text-[10px] font-medium text-[#4B5B6B]">${{ number_format((float) $point['amount'], 0) }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>
        </section>
    @endif

    @if ($activeTab === 'shifts')
        <section class="space-y-4">
            <div class="grid grid-cols-4 gap-2 rounded-2xl border border-[#DED6CA] bg-[rgba(255,253,250,0.96)] p-1 shadow-sm">
                <button type="button" wire:click="setRange('today')" class="rounded-xl px-2 py-2 text-xs font-medium transition {{ $range === 'today' ? 'bg-[#0F3D3E] text-[#FAF9F7]' : 'text-[#607080]' }}">Today</button>
                <button type="button" wire:click="setRange('week')" class="rounded-xl px-2 py-2 text-xs font-medium transition {{ $range === 'week' ? 'bg-[#0F3D3E] text-[#FAF9F7]' : 'text-[#607080]' }}">Week</button>
                <button type="button" wire:click="setRange('month')" class="rounded-xl px-2 py-2 text-xs font-medium transition {{ $range === 'month' ? 'bg-[#0F3D3E] text-[#FAF9F7]' : 'text-[#607080]' }}">Month</button>
                <button type="button" wire:click="setRange('all')" class="rounded-xl px-2 py-2 text-xs font-medium transition {{ $range === 'all' ? 'bg-[#0F3D3E] text-[#FAF9F7]' : 'text-[#607080]' }}">All</button>
            </div>

            <div class="space-y-3">
                @forelse ($shiftItems as $item)
                    <article class="rounded-2xl border border-[#DED6CA] bg-[rgba(255,253,250,0.98)] p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-display text-lg font-semibold text-[#17313F]">{{ $item['title'] }}</p>
                                <p class="text-xs text-[#7B8794]">{{ $item['city'] }}, {{ $item['state'] }}</p>
                            </div>
                            <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $statusStyles[$item['status_key']] ?? 'bg-[#F0E9E1] text-[#4B5B6B]' }}">
                                {{ $item['status_label'] }}
                            </span>
                        </div>

                        <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                            <div class="rounded-lg border border-[#DED6CA] bg-[#F5F1EB] px-3 py-2">
                                <p class="text-[11px] uppercase tracking-[0.12em] text-[#7B8794]">Worked</p>
                                <p class="font-semibold text-[#17313F]">{{ $item['worked_label'] }}</p>
                            </div>
                            <div class="rounded-lg border border-[#DED6CA] bg-[#F5F1EB] px-3 py-2">
                                <p class="text-[11px] uppercase tracking-[0.12em] text-[#7B8794]">Gross</p>
                                <p class="font-semibold text-[#17313F]">${{ number_format((float) $item['gross_amount'], 2) }}</p>
                            </div>
                            <div class="rounded-lg border border-[#DED6CA] bg-[#F5F1EB] px-3 py-2">
                                <p class="text-[11px] uppercase tracking-[0.12em] text-[#7B8794]">Rate</p>
                                <p class="font-semibold text-[#17313F]">${{ number_format((float) $item['hourly_rate'], 2) }}/hr</p>
                            </div>
                            <div class="rounded-lg border border-[#DED6CA] bg-[#F5F1EB] px-3 py-2">
                                <p class="text-[11px] uppercase tracking-[0.12em] text-[#7B8794]">Window</p>
                                <p class="font-semibold text-[#17313F] text-xs">
                                    {{ optional($item['scheduled_start_at'])->format('M d, H:i') ?: '-' }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-3 flex items-center justify-between">
                            <p class="text-xs text-[#7B8794]">
                                @if ($item['status_key'] === 'paid')
                                    Paid {{ optional($item['paid_at'])->format('M d, Y') ?: '' }}
                                @elseif ($item['status_key'] === 'scheduled_payout')
                                    Included in upcoming payout
                                @elseif ($item['status_key'] === 'pending_confirmation')
                                    Waiting family confirmation
                                @elseif ($item['status_key'] === 'eligible')
                                    Ready for payout
                                @else
                                    Last update {{ optional($item['reference_at'])->format('M d, H:i') ?: '-' }}
                                @endif
                            </p>

                            @if (! empty($item['care_request_id']))
                                <a href="{{ route('care-requests.apply', $item['care_request_id']) }}" wire:navigate class="text-xs font-medium text-[#7C5DDC] underline underline-offset-2">
                                    Open visit
                                </a>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-[#D6CCBE] bg-[rgba(255,253,250,0.98)] px-4 py-8 text-center text-sm text-[#607080]">
                        No visits in this range yet.
                    </div>
                @endforelse
            </div>
        </section>
    @endif

    @if ($activeTab === 'payouts')
        <section class="space-y-4">
            <x-card>
                <x-slot:header>
                    <h2 class="font-display text-lg font-semibold">Next payout</h2>
                </x-slot:header>
                <div class="rounded-xl border border-[#CFE1D8] bg-[#F2F8F4] px-4 py-3">
                    <p class="text-xs uppercase tracking-[0.12em] text-emerald-700">{{ ($nextPayout['type'] ?? 'estimated') === 'scheduled' ? 'Scheduled' : 'Estimated' }}</p>
                    <p class="mt-1 text-2xl font-semibold text-[#0F3D3E]">${{ number_format((float) ($nextPayout['amount'] ?? 0), 2) }}</p>
                    <p class="text-sm text-[#2F6B55]">{{ $nextPayout['date']?->format('l, M d \\a\\t H:i') }}</p>
                    <p class="mt-1 text-xs text-emerald-700">{{ $nextPayout['subtitle'] ?? '' }}</p>
                </div>
            </x-card>

            <x-card>
                <x-slot:header>
                    <h2 class="font-display text-lg font-semibold">Payout history</h2>
                </x-slot:header>
                <div class="space-y-3">
                    @forelse ($payouts as $payout)
                        @php
                            $payoutStyle = match ($payout['status']) {
                                'paid' => 'bg-emerald-100 text-emerald-700',
                                'processing' => 'bg-indigo-100 text-indigo-700',
                                'scheduled' => 'bg-[#EAF6F6] text-[#7C5DDC]',
                                'failed' => 'bg-rose-100 text-rose-700',
                                default => 'bg-[#F0E9E1] text-[#4B5B6B]',
                            };
                        @endphp
                        <div class="rounded-xl border border-[#DED6CA] bg-[rgba(255,253,250,0.98)] px-4 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-medium text-[#17313F]">
                                        {{ optional($payout['period_start_on'])->format('M d') }} - {{ optional($payout['period_end_on'])->format('M d, Y') }}
                                    </p>
                                    <p class="text-xs text-[#7B8794] mt-1">
                                        {{ $payout['items_count'] }} visit(s)
                                        @if ($payout['scheduled_for'])
                                             -  Scheduled {{ $payout['scheduled_for']->format('M d, H:i') }}
                                        @endif
                                    </p>
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $payoutStyle }}">
                                    {{ strtoupper($payout['status']) }}
                                </span>
                            </div>

                            <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                                <div class="rounded-lg border border-[#DED6CA] bg-[#F5F1EB] px-3 py-2">
                                    <p class="text-[11px] uppercase tracking-[0.12em] text-[#7B8794]">Net</p>
                                    <p class="font-semibold text-[#17313F]">${{ number_format((float) $payout['net_amount'], 2) }}</p>
                                </div>
                                <div class="rounded-lg border border-[#DED6CA] bg-[#F5F1EB] px-3 py-2">
                                    <p class="text-[11px] uppercase tracking-[0.12em] text-[#7B8794]">Gross</p>
                                    <p class="font-semibold text-[#17313F]">${{ number_format((float) $payout['gross_amount'], 2) }}</p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-[#D6CCBE] bg-[rgba(255,253,250,0.98)] px-4 py-8 text-center text-sm text-[#607080]">
                            No payout batches yet. Completed confirmed visits will appear here automatically.
                        </div>
                    @endforelse
                </div>
            </x-card>
        </section>
    @endif
</div>


