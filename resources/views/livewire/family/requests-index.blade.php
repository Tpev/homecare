<div>
    <div class="hc-page space-y-5 pb-28 pt-5 sm:space-y-6 sm:pb-24 sm:pt-8">
        @if (session('status'))
            <x-alert color="green">{{ session('status') }}</x-alert>
        @endif

        @php
            $familyFirstName = str(trim((string) auth()->user()?->name))->before(' ')->value() ?: 'there';
            $toneClasses = [
                'green' => 'bg-emerald-100 text-emerald-800',
                'blue' => 'bg-sky-100 text-sky-800',
                'amber' => 'bg-amber-100 text-amber-900',
                'rose' => 'bg-rose-100 text-rose-800',
                'slate' => 'bg-slate-100 text-slate-700',
            ];
            $nextTone = $nextVisit ? ($toneClasses[$nextVisit['status']['tone']] ?? $toneClasses['slate']) : $toneClasses['slate'];
            $isCurrentVisit = $nextVisit && in_array($nextVisit['status']['label'], ['Happening now', 'Paused'], true);
        @endphp

        <section data-ai-target="family.care_requests" tabindex="-1" class="overflow-hidden rounded-[2rem] border border-[#D8D0C5] bg-[#FFFCF8] shadow-sm outline-none">
            <div class="p-5 sm:p-7 lg:p-8">
                <p class="hc-brand-kicker">Care overview</p>
                <h1 class="mt-2 max-w-3xl font-display text-3xl font-semibold leading-tight text-[#17313F] sm:text-4xl">Hi, {{ $familyFirstName }}.</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-[#607080] sm:text-base">Here’s an overview of your care.</p>
                <a href="{{ route('family.requests.create') }}" wire:navigate class="hc-primary-button mt-5 min-h-12 w-full sm:w-auto">Request care</a>
            </div>
        </section>

        <x-family-care-nav active="overview" />

        <section id="care-actions" class="scroll-mt-28 rounded-3xl border border-[#E4DDD3] bg-[#FFFCF8] p-4 shadow-sm sm:p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-[#C96B55]">Do now</p>
                    <h2 class="mt-1 font-display text-2xl font-semibold text-[#17313F]">Needs your attention</h2>
                    <p class="mt-1 text-sm text-[#607080]">The most urgent decisions across all care.</p>
                </div>
                @if ($attentionCount > 0)
                    <a href="{{ route('family.care.actions') }}" wire:navigate class="hc-link shrink-0">View all {{ $attentionCount }}</a>
                @endif
            </div>

            <div class="mt-4 grid gap-3 xl:grid-cols-3">
                @forelse ($familyActions as $action)
                    <div @class(['hidden sm:block' => $loop->index > 0])>
                        <x-family-action-card :item="$action" compact />
                    </div>
                @empty
                    <div class="rounded-2xl border border-[#D8E1D7] bg-[#F2F8F4] p-5 xl:col-span-3">
                        <p class="font-display text-xl font-semibold text-[#17313F]">You’re all caught up.</p>
                        <p class="mt-1 text-sm leading-6 text-[#607080]">Approvals, caregiver replies, and payment issues will appear here when needed.</p>
                    </div>
                @endforelse
            </div>
        </section>

        <div class="grid gap-5 lg:grid-cols-2">
            <section aria-labelledby="next-care-heading" class="rounded-3xl border border-[#D8E1D7] bg-[#F7FBF8] p-4 shadow-sm sm:p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-[#2F6F62]">{{ $isCurrentVisit ? 'Current' : 'Next' }}</p>
                        <h2 id="next-care-heading" class="mt-1 font-display text-2xl font-semibold text-[#17313F]">{{ $isCurrentVisit ? 'Current visit' : 'Next visit' }}</h2>
                    </div>
                    <a href="{{ route('family.care.schedule') }}" wire:navigate class="hc-link shrink-0">Schedule{{ $upcomingCount > 0 ? ' ('.$upcomingCount.')' : '' }}</a>
                </div>

                @if ($nextVisit)
                    <div class="mt-5">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-[11px] font-bold uppercase tracking-[0.14em] text-[#2F6F62]">{{ $nextVisit['type_label'] }}</span>
                            <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $nextTone }}">{{ $nextVisit['status']['label'] }}</span>
                        </div>
                        <h3 class="mt-2 font-display text-2xl font-semibold text-[#17313F]">{{ $nextVisit['headline'] }}</h3>
                        <p class="mt-2 text-base font-semibold text-[#324457]">{{ $nextVisit['starts_at']?->format('g:i A') }}@if($nextVisit['ends_at'])–{{ $nextVisit['ends_at']->format('g:i A') }}@endif</p>
                        <p class="mt-1 text-sm text-[#607080]">{{ $nextVisit['caregiver'] }}@if($nextVisit['location']) · {{ $nextVisit['location'] }}@endif</p>
                        <a href="{{ $nextVisit['details_url'] }}" wire:navigate class="hc-secondary-button mt-5 w-full sm:w-auto">Open visit</a>
                    </div>
                @else
                    <div class="mt-5 rounded-2xl border border-dashed border-[#C7D5CA] bg-white/70 px-4 py-7 text-center">
                        <p class="font-display text-xl font-semibold text-[#17313F]">No confirmed visit yet.</p>
                        <p class="mt-1 text-sm text-[#607080]">Care being arranged remains available in Arrangements.</p>
                    </div>
                @endif
            </section>

            <section id="care-arrangements" aria-labelledby="arrangements-heading" class="rounded-3xl border border-[#E4DDD3] bg-[#FFFCF8] p-4 shadow-sm sm:p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-[#C96B55]">Your care</p>
                        <h2 id="arrangements-heading" class="mt-1 font-display text-2xl font-semibold text-[#17313F]">Arrangements</h2>
                    </div>
                    <a href="{{ route('family.care.index') }}" wire:navigate class="hc-link shrink-0">View all{{ $arrangementCount > 0 ? ' ('.$arrangementCount.')' : '' }}</a>
                </div>

                <div class="mt-4 divide-y divide-[#E4DDD3] overflow-hidden rounded-2xl border border-[#E4DDD3] bg-white">
                    <a href="{{ route('family.care.index', ['view' => 'arranging']) }}" wire:navigate class="flex min-h-20 items-center justify-between gap-4 px-4 py-3 transition hover:bg-[#F8F4ED]">
                        <div>
                            <p class="font-semibold text-[#17313F]">Being arranged</p>
                            <p class="mt-0.5 text-sm text-[#607080]">Open one-time and recurring care requests</p>
                        </div>
                        <span class="text-2xl font-semibold text-[#17313F]">{{ $beingArrangedCount }}</span>
                    </a>
                    <a href="{{ route('family.care.index', ['view' => 'ongoing', 'type' => 'regular']) }}" wire:navigate class="flex min-h-20 items-center justify-between gap-4 px-4 py-3 transition hover:bg-[#F8F4ED]">
                        <div>
                            <p class="font-semibold text-[#17313F]">Ongoing recurring care</p>
                            <p class="mt-0.5 text-sm text-[#607080]">Active or paused schedules</p>
                        </div>
                        <span class="text-2xl font-semibold text-[#17313F]">{{ $ongoingPlanCount }}</span>
                    </a>
                    @if ($continuousCoverageVisible)
                        <a href="{{ route('family.continuous-coverage.index') }}" wire:navigate class="flex min-h-20 items-center justify-between gap-4 px-4 py-3 transition hover:bg-[#F8F4ED]">
                            <div>
                                <p class="font-semibold text-[#17313F]">Continuous care</p>
                                <p class="mt-0.5 text-sm text-[#607080]">Around-the-clock and overnight plans</p>
                            </div>
                            <span class="text-2xl font-semibold text-[#17313F]">{{ $continuousPlanCount }}</span>
                        </a>
                    @endif
                </div>
            </section>
        </div>
    </div>
</div>
