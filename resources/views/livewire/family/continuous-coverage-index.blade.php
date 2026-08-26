<div class="hc-page space-y-6 py-6 sm:py-8">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    <section class="hc-brand-panel overflow-hidden">
        <div class="relative grid gap-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
            <div>
                <p class="hc-brand-kicker text-[#E8E0FF]">Continuous care</p>
                <h1 class="mt-2 max-w-3xl font-display text-3xl font-semibold leading-tight text-white sm:text-4xl">One clear schedule for around-the-clock care.</h1>
                <p class="mt-3 max-w-2xl text-base text-[#F7F1E8]/85">Build a family-approved care team, fill recurring shifts, and spot coverage gaps before they become surprises.</p>
            </div>
            <a href="{{ route('family.continuous-coverage.create') }}" wire:navigate class="inline-flex min-h-12 items-center justify-center rounded-xl bg-white px-5 py-3 text-base font-semibold text-[#0F3D3E] shadow-sm transition hover:bg-[#F7F2EA]">Create coverage plan</a>
        </div>
    </section>

    <x-family-care-nav active="continuous" />

    <div class="rounded-2xl border border-[#D8D0C5] bg-[#FFF9F1] p-4 text-sm text-[#4B5B6B]">
        <p class="font-semibold text-[#17313F]">Your family stays in control.</p>
        <p class="mt-1">You approve each caregiver. Caregivers decide whether to join your care team and which recurring or backup shifts to accept.</p>
    </div>

    <section class="grid gap-5 lg:grid-cols-2">
        @forelse ($activePlans as $plan)
            @php
                $summary = $plan->coverage_summary;
                $next = $plan->getRelation('nextShift');
                $barColor = $summary['required_minutes'] === 0
                    ? 'bg-slate-300'
                    : ($summary['percent'] === 100 && $summary['overlap_minutes'] === 0
                        ? 'bg-emerald-500'
                        : ($summary['percent'] >= 75 ? 'bg-amber-500' : 'bg-rose-500'));
            @endphp
            <article class="rounded-3xl border border-[#DED6CA] bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#2F6F62]">{{ $plan->coverage_pattern === '24_7' ? '24/7 coverage' : ucfirst($plan->coverage_pattern) }}</p>
                        <h2 class="mt-1 font-display text-2xl font-semibold text-[#17313F]">{{ $plan->title }}</h2>
                        <p class="mt-1 text-sm text-[#607080]">Care for {{ $plan->recipientName() }}</p>
                    </div>
                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">{{ strtoupper($plan->status) }}</span>
                </div>

                <div class="mt-5 rounded-2xl bg-[#F7F2EA] p-4">
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-semibold text-[#17313F]">This week</p>
                        <p class="text-sm font-semibold text-[#17313F]">{{ number_format($summary['covered_minutes'] / 60, 0) }} of {{ number_format($summary['required_minutes'] / 60, 0) }} hours covered</p>
                    </div>
                    <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-white" role="progressbar" aria-valuenow="{{ $summary['percent'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Weekly coverage {{ $summary['percent'] }} percent">
                        <div class="h-full rounded-full {{ $barColor }}" style="width: {{ $summary['percent'] }}%"></div>
                    </div>
                    @if ($summary['required_minutes'] === 0)
                        <p class="mt-2 text-sm font-semibold text-[#607080]">No required coverage is scheduled this week.</p>
                    @elseif ($summary['overlap_minutes'] > 0)
                        <p class="mt-2 text-sm font-semibold text-amber-800">{{ number_format($summary['overlap_minutes'] / 60, 1) }} overlapping hours need review.</p>
                    @elseif ($summary['uncovered_minutes'] > 0)
                        <p class="mt-2 text-sm font-semibold text-rose-700">{{ number_format($summary['uncovered_minutes'] / 60, 1) }} hours still need a caregiver.</p>
                    @else
                        <p class="mt-2 text-sm font-semibold text-emerald-700">All required coverage is filled.</p>
                    @endif
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl border border-[#E7E0D8] p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#7B8794]">Next shift</p>
                        <p class="mt-1 font-semibold text-[#17313F]">{{ $next?->scheduled_start_at?->copy()->setTimezone($plan->timezone)->format('D, M j · g:i A') ?: 'None generated' }}</p>
                        <p class="mt-1 text-sm text-[#607080]">{{ $next?->assignedCaregiver?->name ?: 'Caregiver needed' }}</p>
                    </div>
                    <div class="rounded-2xl border border-[#E7E0D8] p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#7B8794]">Shift structure</p>
                        <p class="mt-1 font-semibold text-[#17313F]">{{ $plan->coverage_pattern === '24_7' ? number_format($plan->shift_length_minutes / 60, 0).'-hour rotating shifts' : ($plan->coverage_pattern === 'overnight' ? 'Nightly overnight window' : 'Custom weekly windows') }}</p>
                        <p class="mt-1 text-sm text-[#607080]">{{ $plan->timezone }}</p>
                    </div>
                </div>

                <a href="{{ route('family.continuous-coverage.show', $plan) }}" wire:navigate class="mt-5 inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-[#0F3D3E] px-4 py-3 font-semibold text-white transition hover:bg-[#17313F]">Open coverage plan</a>
            </article>
        @empty
            <div class="rounded-3xl border border-dashed border-[#CFC4B5] bg-white p-8 text-center lg:col-span-2">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-[#EAF6F2] text-2xl" aria-hidden="true">24</div>
                <h2 class="mt-4 font-display text-2xl font-semibold text-[#17313F]">No active continuous-care plan</h2>
                <p class="mx-auto mt-2 max-w-xl text-[#607080]">Use this only for substantial coverage coordinated across several family-approved caregivers, such as 24/7 or overnight care.</p>
                <a href="{{ route('family.continuous-coverage.create') }}" wire:navigate class="mt-5 inline-flex min-h-12 items-center justify-center rounded-xl bg-[#0F3D3E] px-5 py-3 font-semibold text-white">Create a coverage plan</a>
            </div>
        @endforelse
    </section>

    @if($pastPlans->isNotEmpty())
        <details class="rounded-3xl border border-[#DED6CA] bg-white shadow-sm">
            <summary class="flex min-h-16 cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 font-semibold text-[#17313F]">
                <span>Past coverage</span>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-700">{{ $pastPlans->count() }} ended</span>
            </summary>
            <div class="border-t border-[#E7E0D8] p-4 sm:p-5">
                <div class="grid gap-3 lg:grid-cols-2">
                    @foreach($pastPlans as $plan)
                        <article class="rounded-2xl border border-[#E7E0D8] bg-[#F7F5F1] p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h2 class="font-display text-xl font-semibold text-[#17313F]">{{ $plan->title }}</h2>
                                    <p class="mt-1 text-sm text-[#607080]">Care for {{ $plan->recipientName() }} · Ended {{ $plan->ends_on?->format('M j, Y') ?: 'previously' }}</p>
                                </div>
                                <span class="rounded-full bg-slate-200 px-3 py-1 text-xs font-semibold text-slate-700">ENDED</span>
                            </div>
                            <a href="{{ route('family.continuous-coverage.show', ['coveragePlan' => $plan->id, 'tab' => 'history']) }}" wire:navigate class="mt-4 inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-[#2F6F62] px-4 font-semibold text-[#2F6F62] sm:w-auto">View history & billing</a>
                        </article>
                    @endforeach
                </div>
            </div>
        </details>
    @endif
</div>
