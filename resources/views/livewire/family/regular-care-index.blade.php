<div class="hc-page space-y-5 py-5 sm:space-y-6 sm:py-8">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    @php
        $pricing = app(\App\Support\MarketplacePricing::class);
        $activePlans = $plans->filter(fn ($plan) => in_array($plan->status, [
            \App\Models\CarePlan::STATUS_ACTIVE,
            \App\Models\CarePlan::STATUS_PAYMENT_ATTENTION,
            \App\Models\CarePlan::STATUS_PAUSED,
        ], true));
        $pendingPlans = $plans->filter(fn ($plan) => in_array($plan->status, [
            \App\Models\CarePlan::STATUS_PENDING_CAREGIVER,
            \App\Models\CarePlan::STATUS_COUNTERED,
        ], true));
        $statusStyles = [
            'active' => 'bg-emerald-100 text-emerald-700',
            'payment_attention' => 'bg-amber-100 text-amber-800',
            'paused' => 'bg-sky-100 text-sky-700',
            'pending_caregiver' => 'bg-indigo-100 text-indigo-700',
            'countered' => 'bg-amber-100 text-amber-800',
            'declined' => 'bg-rose-100 text-rose-700',
            'ended' => 'bg-slate-100 text-slate-700',
            'cancelled' => 'bg-slate-100 text-slate-700',
        ];
    @endphp

    <section class="hc-brand-panel">
        <div class="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="hc-brand-kicker text-[#E8E0FF]">Regular care</p>
                <h1 class="mt-1 text-2xl font-display font-semibold leading-tight sm:text-3xl">Your regular care</h1>
                <p class="mt-2 max-w-2xl text-base text-[#F7F1E8]/82">
                    Keep the same caregiver and see every confirmed visit in one place.
                </p>
                <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                    <a href="{{ route('family.requests.index') }}" wire:navigate>
                        <x-button color="white" class="w-full sm:w-auto">Back to Care</x-button>
                    </a>
                    <a href="{{ route('caregivers.search') }}" wire:navigate>
                        <x-button color="white" light class="w-full sm:w-auto">Find caregivers</x-button>
                    </a>
                </div>
            </div>
            <div class="rounded-lg border border-white/20 bg-white/10 px-5 py-4 lg:min-w-80">
                <p class="text-sm font-semibold text-[#F0E9E1]/80">Next regular visit</p>
                <p class="mt-1 text-lg font-semibold text-white">
                    {{ optional($nextPlan?->nextBooking?->scheduled_start_at)->format('l, F j \a\t g:i A') ?: 'No upcoming visit yet' }}
                </p>
                @if($nextPlan?->caregiver)
                    <p class="mt-1 text-base text-[#F0E9E1]/80">{{ $nextPlan->caregiver->name }} is coming.</p>
                @elseif($pendingPlans->isNotEmpty())
                    <p class="mt-1 text-base text-[#F0E9E1]/80">Waiting for a caregiver response.</p>
                @endif
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-5 xl:grid-cols-12">
        <div class="space-y-5 xl:col-span-8">
            <x-card>
                <x-slot:header>
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="font-display text-xl font-semibold">Regular care</h2>
                            <p class="text-base text-[#607080]">Repeating visits with caregivers you know.</p>
                        </div>
                        <a href="{{ route('family.requests.index') }}" wire:navigate class="hc-link">All care</a>
                    </div>
                </x-slot:header>

                <div class="space-y-3">
                    @forelse ($plans as $plan)
                        @php
                            $style = $statusStyles[$plan->status] ?? 'bg-slate-100 text-slate-700';
                            $upcoming = $scheduleService->upcomingVisits($plan, 1);
                            $planStatusLabel = $plan->status === \App\Models\CarePlan::STATUS_PAYMENT_ATTENTION
                                ? 'PAYMENT NEEDED'
                                : strtoupper(str_replace('_', ' ', $plan->status));
                            $paymentLabel = $plan->payment_status === \App\Models\CarePlan::PAYMENT_ACTION_REQUIRED
                                ? 'ACTION NEEDED'
                                : strtoupper(str_replace('_', ' ', $plan->payment_status));
                            $planRate = $pricing->hourlyRateForFamily(auth()->user(), (float) $plan->hourly_rate);
                        @endphp
                        <a href="{{ route('family.care.show', $plan->id) }}" wire:navigate class="block rounded-2xl border border-[#DED6CA] bg-white p-4 transition hover:border-[#B7ADA0] hover:shadow-sm">
                            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="font-display text-lg font-semibold text-[#17313F]">{{ $plan->title }}</h3>
                                        <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $style }}">{{ $planStatusLabel }}</span>
                                    </div>
                                    <p class="mt-1 text-sm text-[#607080]">
                                        {{ $plan->caregiver?->name }} - {{ $scheduleService->scheduleLabel($plan) }} - ${{ number_format($planRate, 2) }}/hr
                                    </p>
                                    <p class="mt-2 text-sm text-[#4B5B6B]">
                                        Next: {{ $upcoming[0]['label'] ?? optional($plan->nextBooking?->scheduled_start_at)->format('M d, g:i A') ?? 'waiting for schedule activation' }}
                                    </p>
                                </div>
                                <div class="text-sm text-[#607080] md:text-right">
                                    <p>Payment: <span class="font-semibold text-[#17313F]">{{ $paymentLabel }}</span></p>
                                    @if ($plan->nextBooking)
                                        <p class="mt-1">Visit: {{ strtoupper(str_replace('_', ' ', $plan->nextBooking->status)) }}</p>
                                    @endif
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="rounded-2xl border border-dashed border-[#D6CCBE] bg-[#F7F2EA] px-4 py-8 text-center">
                            <p class="font-display text-lg font-semibold text-[#17313F]">No regular care yet.</p>
                            <p class="mx-auto mt-2 max-w-xl text-base text-[#607080]">
                                After an approved visit, choose regular care to make it repeat.
                            </p>
                        </div>
                    @endforelse
                </div>
            </x-card>
        </div>

        <aside class="space-y-5 xl:col-span-4">
            <x-card>
                <x-slot:header>
                    <h2 class="font-display text-lg font-semibold">Start regular care</h2>
                </x-slot:header>
                <div class="space-y-3">
                    @forelse ($rebookableRequests as $request)
                        @php
                            $hired = $request->applications->first();
                            $caregiverName = trim((string) ($hired?->caregiver?->name ?? ''));
                            $caregiverFirstName = $caregiverName !== ''
                                ? \Illuminate\Support\Str::of($caregiverName)->before(' ')
                                : 'caregiver';
                        @endphp
                        <div class="rounded-2xl border border-[#E4DDD3] bg-white p-3">
                            <p class="font-semibold text-[#17313F]">{{ $hired?->caregiver?->name ?: 'Hired caregiver' }}</p>
                            <p class="mt-1 text-sm text-[#607080]">{{ $request->title }}</p>
                            <p class="mt-1 text-xs text-[#7B8794]">
                                {{ $request->recipient?->full_name ?: 'Recipient' }} - {{ optional($request->booking?->scheduled_start_at)->format('M d, Y') ?: 'Recent care' }}
                            </p>
                            <div class="mt-3">
                                <a href="{{ route('family.care.compose', $request->id) }}" wire:navigate>
                                    <x-button color="blue" light sm class="w-full">Set up regular care with {{ $caregiverFirstName }}</x-button>
                                </a>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-[#607080]">A caregiver appears here after you complete and approve a visit.</p>
                    @endforelse
                </div>
            </x-card>
        </aside>
    </section>
</div>
