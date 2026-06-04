<div class="hc-page space-y-5 py-5 sm:space-y-6 sm:py-8">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    @php
        $pricing = app(\App\Support\MarketplacePricing::class);
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
        $statusStyle = $statusStyles[$plan->status] ?? 'bg-slate-100 text-slate-700';
        $planStatusLabel = $plan->status === \App\Models\CarePlan::STATUS_PAYMENT_ATTENTION
            ? 'PAYMENT NEEDED'
            : strtoupper(str_replace('_', ' ', $plan->status));
        $paymentLabel = $plan->payment_status === \App\Models\CarePlan::PAYMENT_ACTION_REQUIRED
            ? 'ACTION NEEDED'
            : strtoupper(str_replace('_', ' ', $plan->payment_status));
        $tasks = collect($plan->task_snapshot ?? []);
        $address = $plan->address_snapshot ?? [];
        $planRate = $pricing->hourlyRateForFamily($plan->family, (float) $plan->hourly_rate);
    @endphp

    <section class="hc-brand-panel">
        <div class="relative flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="hc-brand-kicker text-[#E8E0FF]">Regular care plan</p>
                <div class="mt-1 flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-display font-semibold leading-tight sm:text-3xl">{{ $plan->title }}</h1>
                    <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $statusStyle }}">{{ $planStatusLabel }}</span>
                </div>
                <p class="mt-2 max-w-2xl text-sm text-[#F7F1E8]/82">
                    {{ $plan->caregiver?->name }} - {{ $scheduleLabel }} - ${{ number_format($planRate, 2) }}/hr
                </p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <a href="{{ route('family.care.index') }}" wire:navigate>
                    <x-button color="white" light class="w-full sm:w-auto">All care plans</x-button>
                </a>
                @if ($plan->nextBooking)
                    <a href="{{ route('family.requests.show', $plan->nextBooking->care_request_id) }}" wire:navigate>
                        <x-button color="white" class="w-full sm:w-auto">Open next booking</x-button>
                    </a>
                @endif
            </div>
        </div>
    </section>

    @if ($plan->status === \App\Models\CarePlan::STATUS_PAYMENT_ATTENTION)
        <x-alert color="amber">
            {{ $plan->last_error ?: 'Payment action is needed before LoLo can keep this regular visit fully protected.' }}
            @if ($plan->nextBooking)
                <a href="{{ route('family.requests.show', $plan->nextBooking->care_request_id) }}" wire:navigate class="ml-1 font-semibold underline underline-offset-2">Open booking payment</a>
            @else
                <a href="{{ route('family.billing.show') }}" wire:navigate class="ml-1 font-semibold underline underline-offset-2">Open billing</a>
            @endif
        </x-alert>
    @endif

    @if ($plan->status === \App\Models\CarePlan::STATUS_COUNTERED)
        <x-card>
            <x-slot:header>
                <div>
                    <h2 class="font-display text-lg font-semibold">Caregiver suggested a different time</h2>
                    <p class="text-sm text-[#607080]">{{ $counterScheduleLabel }}</p>
                </div>
            </x-slot:header>
            <div class="space-y-3">
                @if ($plan->counter_note)
                    <div class="rounded-2xl border border-[#E4DDD3] bg-[#F7F2EA] p-3 text-sm text-[#4B5B6B]">{{ $plan->counter_note }}</div>
                @endif
                <div class="grid grid-cols-1 gap-2 md:grid-cols-3">
                    @foreach ($counterVisits as $visit)
                        <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">{{ $visit['label'] }}</div>
                    @endforeach
                </div>
            </div>
            <x-slot:footer>
                <x-button color="green" wire:click="acceptCounter">Accept counter schedule</x-button>
            </x-slot:footer>
        </x-card>
    @endif

    <section class="grid grid-cols-1 gap-5 xl:grid-cols-12">
        <div class="space-y-5 xl:col-span-8">
            <x-card>
                <x-slot:header>
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="font-display text-lg font-semibold">Upcoming visits</h2>
                            <p class="text-sm text-[#607080]">Generated bookings use the existing request, shift, and payment systems.</p>
                        </div>
                        <span class="rounded-full bg-[#F0E9E1] px-2.5 py-1 text-[11px] font-semibold text-[#4B5B6B]">
                            {{ $paymentLabel }}
                        </span>
                    </div>
                </x-slot:header>

                <div class="space-y-3">
                    @forelse ($upcomingVisits as $visit)
                        @php
                            $isGeneratedBooking = $plan->nextBooking
                                && $plan->nextBooking->scheduled_start_at
                                && $plan->nextBooking->scheduled_start_at->format('Y-m-d H:i') === $visit['start']->format('Y-m-d H:i');
                        @endphp
                        <div class="rounded-2xl border border-[#DED6CA] bg-white p-4">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="font-semibold text-[#17313F]">{{ $visit['label'] }}</p>
                                    <p class="mt-1 text-sm text-[#607080]">{{ $plan->recipientName() }} with {{ $plan->caregiver?->name }}</p>
                                </div>
                                @if ($isGeneratedBooking)
                                    <x-badge color="green" text="BOOKED" />
                                @else
                                    <x-badge color="slate" text="PROJECTED" />
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-[#D6CCBE] px-4 py-6 text-sm text-[#607080]">
                            No future visits are available from this schedule.
                        </div>
                    @endforelse
                </div>
            </x-card>

            <x-card>
                <x-slot:header>
                    <h2 class="font-display text-lg font-semibold">Care instructions</h2>
                </x-slot:header>
                <div class="space-y-4 text-sm">
                    <div>
                        <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Notes</p>
                        <p class="mt-1 text-[#324457]">{{ $plan->care_notes ?: 'No extra notes were added.' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Address</p>
                        <p class="mt-1 text-[#324457]">
                            {{ data_get($address, 'address_line1') }}{{ data_get($address, 'address_line2') ? ', '.data_get($address, 'address_line2') : '' }},
                            {{ data_get($address, 'city') }}, {{ data_get($address, 'state') }} {{ data_get($address, 'zip') }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Tasks</p>
                        <div class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-2">
                            @forelse ($tasks as $task)
                                <div class="rounded-xl border border-[#E4DDD3] bg-white px-3 py-2">
                                    <p class="font-semibold text-[#17313F]">{{ $task['name'] ?? 'Care task' }}</p>
                                    <p class="text-xs text-[#607080]">{{ $task['task_note'] ?? 'No additional note.' }}</p>
                                </div>
                            @empty
                                <p class="text-sm text-[#607080]">No task list was copied from the source request.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </x-card>
        </div>

        <aside class="space-y-5 xl:col-span-4">
            <x-card>
                <x-slot:header>
                    <h2 class="font-display text-lg font-semibold">Caregiver</h2>
                </x-slot:header>
                <div class="space-y-3 text-sm">
                    <div>
                        <p class="font-display text-lg font-semibold text-[#17313F]">{{ $plan->caregiver?->name }}</p>
                        <p class="text-[#607080]">{{ $plan->caregiver?->city }}, {{ $plan->caregiver?->state }}</p>
                    </div>
                    <div class="rounded-2xl border border-[#E4DDD3] bg-[#F7F2EA] p-3">
                        <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Source</p>
                        <p class="mt-1 font-semibold text-[#17313F]">{{ $plan->sourceCareRequest?->title ?: 'Previous request' }}</p>
                    </div>
                    @if ($plan->family_message)
                        <div class="rounded-2xl border border-[#E4DDD3] bg-white p-3">
                            <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Your message</p>
                            <p class="mt-1 text-[#4B5B6B]">{{ $plan->family_message }}</p>
                        </div>
                    @endif
                </div>
            </x-card>

            @if ($plan->nextBooking)
                <x-card>
                    <x-slot:header>
                        <h2 class="font-display text-lg font-semibold">Next generated booking</h2>
                    </x-slot:header>
                    <div class="space-y-2 text-sm">
                        <p>Status: <span class="font-semibold text-[#17313F]">{{ strtoupper($plan->nextBooking->status) }}</span></p>
                        <p>Payment: <span class="font-semibold text-[#17313F]">{{ strtoupper($plan->nextBooking->payment?->status ?? 'pending') }}</span></p>
                        <p>{{ optional($plan->nextBooking->scheduled_start_at)->format('M d, Y g:i A') }} to {{ optional($plan->nextBooking->scheduled_end_at)->format('g:i A') }}</p>
                    </div>
                </x-card>
            @endif

            @if ($plan->isLive())
                <x-card>
                    <x-slot:header>
                        <h2 class="font-display text-lg font-semibold">Plan controls</h2>
                    </x-slot:header>
                    <p class="text-sm text-[#607080]">Ending the plan stops future recurring generation. Existing generated bookings stay in the shift workflow.</p>
                    <x-slot:footer>
                        <x-button color="red" light wire:click="endPlan">End regular care</x-button>
                    </x-slot:footer>
                </x-card>
            @endif
        </aside>
    </section>
</div>
