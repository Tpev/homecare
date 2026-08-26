@php
    $statusTone = ($journey['status_tone'] ?? 'slate') === 'amber'
        ? 'bg-amber-100 text-amber-900'
        : (($journey['status_tone'] ?? 'slate') === 'green' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700');
    $timelineGrid = match ($journey['timeline']->count()) {
        1 => 'lg:grid-cols-1',
        2 => 'lg:grid-cols-2',
        3 => 'lg:grid-cols-3',
        4 => 'lg:grid-cols-4',
        default => 'lg:grid-cols-5',
    };
@endphp

<div class="hc-page space-y-5 pb-28 pt-5 sm:space-y-6 sm:pb-8 sm:pt-8">
    <section class="overflow-hidden rounded-[2rem] border border-[#D8D0C5] bg-[#FFFCF8] shadow-sm">
        <div class="grid lg:grid-cols-[minmax(0,1fr)_21rem]">
            <div class="p-5 sm:p-7 lg:p-8">
                <a href="{{ route('family.requests.index') }}" wire:navigate class="hc-link text-sm">← Back to Care</a>
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <span class="hc-brand-kicker">{{ $journey['type_label'] }}</span>
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusTone }}">{{ $journey['status'] }}</span>
                </div>
                <h1 class="mt-2 max-w-3xl font-display text-3xl font-semibold leading-tight text-[#17313F] sm:text-4xl">{{ $journey['title'] }}</h1>
                <p class="mt-3 text-base leading-7 text-[#526474]">{{ $journey['subtitle'] }}</p>
                <p class="mt-2 text-xs text-[#7B8794]">{{ $journey['reference'] }}</p>
            </div>
            <aside id="journey-primary-action" class="border-t border-[#E4DDD3] bg-[#F4EEE5] p-5 lg:border-l lg:border-t-0 lg:p-6">
                <p class="text-[11px] font-bold uppercase tracking-[0.16em] {{ $journey['primary_action']['urgent'] ? 'text-amber-800' : 'text-[#2F6F62]' }}">{{ $journey['primary_action']['urgent'] ? 'Needs your attention' : 'Next step' }}</p>
                <p class="mt-2 text-sm leading-6 text-[#526474]">{{ $journey['primary_action']['consequence'] }}</p>
                <a href="{{ $journey['primary_action']['url'] }}" wire:navigate class="{{ $journey['primary_action']['urgent'] ? 'hc-primary-button' : 'hc-secondary-button' }} mt-4 w-full">{{ $journey['primary_action']['label'] }}</a>
            </aside>
        </div>
    </section>

    <x-family-care-nav active="overview" />

    <section class="rounded-3xl border border-[#E4DDD3] bg-[#FFFCF8] p-4 shadow-sm sm:p-6">
        <div class="max-w-2xl">
            <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-[#C96B55]">The complete story</p>
            <h2 class="mt-1 font-display text-2xl font-semibold text-[#17313F]">How this care is progressing</h2>
            <p class="mt-1 text-sm leading-6 text-[#607080]">Requests, caregiver selection, visits, hours, and payment stay connected here without changing how each workflow operates.</p>
        </div>

        <ol class="mt-6 grid gap-0 {{ $timelineGrid }}">
            @foreach ($journey['timeline'] as $stage)
                @php
                    $complete = $stage['state'] === 'complete';
                    $current = $stage['state'] === 'current';
                    $dot = $complete ? 'border-[#2F6F62] bg-[#2F6F62] text-white' : ($current ? 'border-[#C96B55] bg-[#FFF4EA] text-[#9A4938]' : 'border-[#D8D0C5] bg-white text-[#7B8794]');
                @endphp
                <li class="relative grid grid-cols-[2.75rem_minmax(0,1fr)] gap-3 pb-6 last:pb-0 lg:block lg:pr-4 lg:pb-0">
                    @unless ($loop->last)
                        <span aria-hidden="true" class="absolute bottom-0 left-[1.3rem] top-10 w-px bg-[#D8D0C5] lg:left-10 lg:right-0 lg:top-5 lg:h-px lg:w-auto"></span>
                    @endunless
                    <span class="relative z-10 flex h-10 w-10 items-center justify-center rounded-full border-2 text-sm font-bold {{ $dot }}">{{ $complete ? '✓' : $loop->iteration }}</span>
                    <div class="min-w-0 lg:mt-4">
                        <p class="font-display text-lg font-semibold text-[#17313F]">{{ $stage['label'] }}</p>
                        <p class="mt-1 text-sm leading-6 text-[#607080]">{{ $stage['detail'] }}</p>
                        @if ($stage['at'])<p class="mt-2 text-xs font-medium text-[#7B8794]">{{ $stage['at']->format('M j, Y · g:i A') }}</p>@endif
                    </div>
                </li>
            @endforeach
        </ol>
    </section>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1.45fr)_minmax(18rem,.75fr)]">
        <section class="rounded-3xl border border-[#E4DDD3] bg-[#FFFCF8] shadow-sm">
            <div class="border-b border-[#E4DDD3] p-4 sm:p-5">
                <h2 class="font-display text-xl font-semibold text-[#17313F]">Visits connected to this care</h2>
                <p class="mt-1 text-sm text-[#607080]">Scheduled and completed visits remain traceable to the care that created them.</p>
            </div>
            <div class="divide-y divide-[#E4DDD3]">
                @forelse ($journey['visits'] as $visit)
                    <article class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                        <div>
                            <p class="font-display text-lg font-semibold text-[#17313F]">{{ $visit['date'] }}</p>
                            <p class="mt-1 text-sm font-medium text-[#324457]">{{ $visit['time'] }} · {{ $visit['caregiver'] }}</p>
                            <div class="mt-2 flex flex-wrap gap-2 text-xs text-[#607080]"><span>{{ $visit['status'] }}</span><span>·</span><span>Payment: {{ $visit['payment'] }}</span></div>
                        </div>
                        @if ($visit['url'])<a href="{{ $visit['url'] }}" wire:navigate class="hc-secondary-button w-full sm:w-auto">Open visit</a>@endif
                    </article>
                @empty
                    <div class="p-6 text-center sm:p-8">
                        <p class="font-display text-xl font-semibold text-[#17313F]">No confirmed visits yet.</p>
                        <p class="mt-1 text-sm text-[#607080]">This section fills automatically when the care arrangement creates a dated visit.</p>
                    </div>
                @endforelse
            </div>
        </section>

        <aside class="space-y-4">
            <section class="rounded-3xl border border-[#D8E1D7] bg-[#F7FBF8] p-4 shadow-sm sm:p-5">
                <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-[#2F6F62]">Care details</p>
                <dl class="mt-4 space-y-4 text-sm">
                    <div><dt class="text-[#7B8794]">Care recipient</dt><dd class="mt-1 font-semibold text-[#17313F]">{{ $journey['recipient'] }}</dd></div>
                    <div><dt class="text-[#7B8794]">Caregiver</dt><dd class="mt-1 font-semibold text-[#17313F]">{{ $journey['caregiver'] }}</dd></div>
                    <div><dt class="text-[#7B8794]">Schedule</dt><dd class="mt-1 font-semibold leading-6 text-[#17313F]">{{ $journey['schedule'] }}</dd></div>
                </dl>
                <a href="{{ $journey['manage_url'] }}" wire:navigate class="hc-secondary-button mt-5 w-full">Open full management</a>
            </section>

            @if ($journey['tasks']->isNotEmpty())
                <section class="rounded-3xl border border-[#E4DDD3] bg-[#FFFCF8] p-4 shadow-sm sm:p-5">
                    <h2 class="font-display text-lg font-semibold text-[#17313F]">Care tasks</h2>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($journey['tasks'] as $task)<span class="rounded-full border border-[#D8E1D7] bg-[#F7FBF8] px-3 py-1.5 text-xs text-[#526474]">{{ $task }}</span>@endforeach
                    </div>
                </section>
            @endif
        </aside>
    </div>

    <div class="hc-mobile-primary-bar fixed inset-x-0 bottom-0 z-40 border-t border-[#D8D0C5] bg-[#FFFCF8]/95 p-3 shadow-[0_-8px_24px_rgba(23,49,63,0.12)] backdrop-blur sm:hidden">
        <a href="{{ $journey['primary_action']['url'] }}" wire:navigate class="hc-primary-button w-full">{{ $journey['primary_action']['label'] }}</a>
    </div>
</div>
