<div class="hc-care-workspace mx-auto outline-none" data-ai-target="family.care_requests" tabindex="-1">
    <div class="hc-page space-y-5 pb-28 pt-5 sm:space-y-6 sm:pb-24 sm:pt-8">
        @if (session('status'))
            <x-alert color="green">{{ session('status') }}</x-alert>
        @endif

        @php
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

        <h1 class="sr-only">Care</h1>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <x-family-care-nav active="overview" />
            <a href="{{ route('family.requests.create') }}" wire:navigate class="hc-primary-button min-h-11 self-end shrink-0">Request new care</a>
        </div>

        <section id="care-actions" class="scroll-mt-28 rounded-3xl border border-[#E4DDD3] bg-[#FFFCF8] p-4 shadow-sm sm:p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="font-display text-2xl font-semibold text-[#17313F]">Needs your attention</h2>
                </div>
                @if ($attentionCount > 0)
                    <a href="{{ route('family.care.actions') }}" wire:navigate class="hc-link shrink-0">View all {{ $attentionCount }}</a>
                @endif
            </div>

            <div @class(['mt-4 grid gap-3', 'xl:grid-cols-2' => $familyActions->count() === 2, 'xl:grid-cols-3' => $familyActions->count() >= 3])>
                @forelse ($familyActions as $action)
                    <div @class(['hidden sm:block' => $loop->index > 0])>
                        <x-family-action-card :item="$action" compact show-caregiver-photo />
                    </div>
                @empty
                    <div class="rounded-2xl border border-[#D8E1D7] bg-[#F2F8F4] p-5 xl:col-span-3">
                        <p class="font-display text-xl font-semibold text-[#17313F]">You’re all caught up.</p>
                        <p class="mt-1 text-sm leading-6 text-[#607080]">Approvals, caregiver replies, and payment issues will appear here when needed.</p>
                    </div>
                @endforelse
            </div>
        </section>

        <section aria-labelledby="next-care-heading" class="rounded-3xl border border-[#D8E1D7] bg-[#F7FBF8] p-4 shadow-sm sm:p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 id="next-care-heading" class="font-display text-2xl font-semibold text-[#17313F]">{{ $isCurrentVisit ? 'Current visit' : 'Next visit' }}</h2>
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
                    <div class="mt-3 flex items-center gap-2.5">
                        @if ($nextVisit['caregiver_user'] ?? null)
                            <x-caregiver-identity :caregiver="$nextVisit['caregiver_user']" avatar-only class="shrink-0" />
                        @endif
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-[#17313F]">{{ $nextVisit['caregiver'] }}</p>
                            @if($nextVisit['location'])<p class="text-xs text-[#607080]">{{ $nextVisit['location'] }}</p>@endif
                        </div>
                    </div>
                    <a href="{{ $nextVisit['details_url'] }}" wire:navigate class="hc-secondary-button mt-5 w-full sm:w-auto">Open visit</a>
                </div>
            @else
                <div class="mt-5 rounded-2xl border border-dashed border-[#C7D5CA] bg-white/70 px-4 py-7 text-center">
                    <p class="font-display text-xl font-semibold text-[#17313F]">No confirmed visit yet.</p>
                    <a href="{{ route('family.care.index') }}" wire:navigate class="hc-link mt-1 inline-block text-sm">View care requests</a>
                </div>
            @endif
        </section>

    </div>
</div>
