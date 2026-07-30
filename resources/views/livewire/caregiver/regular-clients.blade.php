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
        $pricing = app(\App\Support\MarketplacePricing::class);
        $dayOptions = [
            0 => 'Sun',
            1 => 'Mon',
            2 => 'Tue',
            3 => 'Wed',
            4 => 'Thu',
            5 => 'Fri',
            6 => 'Sat',
        ];
        $statusStyles = [
            'active' => 'bg-emerald-100 text-emerald-700',
            'payment_attention' => 'bg-amber-100 text-amber-800',
            'paused' => 'bg-sky-100 text-sky-700',
            'pending_caregiver' => 'bg-indigo-100 text-indigo-700',
            'countered' => 'bg-amber-100 text-amber-800',
        ];
    @endphp

    <section class="hc-brand-panel">
        <div class="relative grid grid-cols-1 gap-5 lg:grid-cols-5">
            <div class="lg:col-span-3">
                <p class="hc-brand-kicker text-[#E8E0FF]">Regular Clients</p>
                <h1 class="mt-1 text-2xl font-display font-semibold leading-tight sm:text-3xl">Direct regular-care offers from families.</h1>
                <p class="mt-2 max-w-2xl text-sm text-[#F7F1E8]/82">
                    Accept a schedule, suggest a better time, or decline. Accepted offers create real booked visits with payment authorization.
                </p>
            </div>
            <div class="grid grid-cols-2 gap-3 lg:col-span-2">
                <div class="hc-brand-stat">
                    <p class="text-[11px] uppercase tracking-[0.16em] text-[#F0E9E1]/70">Needs response</p>
                    <p class="mt-1 text-2xl font-semibold">{{ $offers->where('status', \App\Models\CarePlan::STATUS_PENDING_CAREGIVER)->count() }}</p>
                </div>
                <div class="hc-brand-stat">
                    <p class="text-[11px] uppercase tracking-[0.16em] text-[#F0E9E1]/70">Active clients</p>
                    <p class="mt-1 text-2xl font-semibold">{{ $activePlans->count() }}</p>
                </div>
            </div>
        </div>
    </section>

    @if ($pendingChanges->isNotEmpty())
        <section class="rounded-lg border border-amber-300 bg-amber-50">
            <div class="border-b border-amber-200 px-5 py-4 sm:px-7">
                <p class="text-sm font-bold uppercase tracking-wide text-amber-800">Needs your response</p>
                <h2 class="mt-1 font-display text-2xl font-semibold text-amber-950">Regular-care changes</h2>
                <p class="mt-1 text-base text-amber-900">Review the exact change before accepting. Current visits stay unchanged until you accept.</p>
            </div>
            <div class="divide-y divide-amber-200">
                @foreach ($pendingChanges as $change)
                    @php
                        $proposal = $change->proposed_schedule ?? [];
                        $isExtra = $change->type === \App\Models\CarePlanScheduleChange::TYPE_EXTRA_VISIT;
                        $proposedStart = $isExtra ? \Illuminate\Support\Carbon::parse((string) data_get($proposal, 'start_at')) : null;
                        $proposedEnd = $isExtra ? \Illuminate\Support\Carbon::parse((string) data_get($proposal, 'end_at')) : null;
                        $proposedDays = collect(data_get($proposal, 'days', []))->map(fn ($day) => $dayOptions[(int) $day] ?? null)->filter()->implode(', ');
                    @endphp
                    <article class="px-5 py-5 sm:px-7">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <h3 class="font-display text-xl font-semibold text-[#17313F]">{{ $change->plan?->family?->name }} requests {{ $isExtra ? 'an extra visit' : 'a schedule change' }}</h3>
                                @if ($isExtra)
                                    <p class="mt-2 text-lg font-semibold text-[#324457]">{{ $proposedStart?->format('l, F j, g:i A') }} to {{ $proposedEnd?->format('g:i A') }}</p>
                                @else
                                    <p class="mt-2 text-lg font-semibold text-[#324457]">{{ $proposedDays }}</p>
                                    <p class="text-base text-[#526474]">{{ \Illuminate\Support\Carbon::parse((string) data_get($proposal, 'start_time'))->format('g:i A') }} to {{ \Illuminate\Support\Carbon::parse((string) data_get($proposal, 'end_time'))->format('g:i A') }}, starting {{ $change->effective_on?->format('F j') }}</p>
                                @endif
                                @if ($change->note)<p class="mt-3 rounded-md bg-white px-4 py-3 text-base text-[#526474]">{{ $change->note }}</p>@endif
                            </div>
                            <div class="flex flex-col gap-2 sm:flex-row">
                                <button type="button" wire:click="respondToChange({{ $change->id }}, true)" wire:confirm="Accept this change? Your visit list will update." class="hc-primary-button min-h-12 text-base">Accept</button>
                                <button type="button" wire:click="respondToChange({{ $change->id }}, false)" wire:confirm="Decline this change? The current schedule will stay." class="hc-secondary-button min-h-12 text-base">Decline</button>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <section class="grid grid-cols-1 gap-5 xl:grid-cols-12">
        <div class="space-y-5 xl:col-span-8">
            <x-card>
                <x-slot:header>
                    <div>
                        <h2 class="font-display text-lg font-semibold">Offers to review</h2>
                        <p class="text-sm text-[#607080]">Families who already hired you can request a recurring schedule directly.</p>
                    </div>
                </x-slot:header>

                <div class="space-y-3">
                    @forelse ($offers as $offer)
                        @php
                            $style = $statusStyles[$offer->status] ?? 'bg-slate-100 text-slate-700';
                            $offerStatusLabel = $offer->status === \App\Models\CarePlan::STATUS_PAYMENT_ATTENTION
                                ? 'FAMILY PAYMENT NEEDED'
                                : strtoupper(str_replace('_', ' ', $offer->status));
                            $tasks = collect($offer->task_snapshot ?? []);
                            $visits = $scheduleService->upcomingVisits($offer, 3, $offer->status === \App\Models\CarePlan::STATUS_COUNTERED);
                            $offerRate = $pricing->hourlyRateForFamily($offer->family, (float) $offer->hourly_rate);
                        @endphp
                        <div class="rounded-2xl border border-[#DED6CA] bg-white p-4">
                            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="font-display text-lg font-semibold text-[#17313F]">{{ $offer->title }}</h3>
                                        <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $style }}">{{ $offerStatusLabel }}</span>
                                    </div>
                                    <p class="mt-1 text-sm text-[#607080]">
                                        {{ $offer->family?->name }} - {{ $scheduleService->scheduleLabel($offer, $offer->status === \App\Models\CarePlan::STATUS_COUNTERED) }} - ${{ number_format($offerRate, 2) }}/hr
                                    </p>
                                    @if ($offer->family_message)
                                        <p class="mt-2 rounded-xl border border-[#E4DDD3] bg-[#F7F2EA] px-3 py-2 text-sm text-[#4B5B6B]">{{ $offer->family_message }}</p>
                                    @endif
                                </div>
                                <div class="text-sm text-[#607080] md:text-right">
                                    <p>Recipient</p>
                                    <p class="font-semibold text-[#17313F]">{{ $offer->recipientName() }}</p>
                                    <x-care-recipient-context :snapshot="$offer->recipient_snapshot" class="mt-2 justify-start md:justify-end" />
                                </div>
                            </div>

                            <div class="mt-4 grid grid-cols-1 gap-2 md:grid-cols-3">
                                @foreach ($visits as $visit)
                                    <div class="rounded-xl border border-[#E4DDD3] bg-[#F7F2EA] px-3 py-2 text-xs text-[#4B5B6B]">{{ $visit['label'] }}</div>
                                @endforeach
                            </div>

                            @if ($tasks->count() > 0)
                                <div class="mt-4 flex flex-wrap gap-2">
                                    @foreach ($tasks->take(5) as $task)
                                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{{ $task['name'] ?? 'Care task' }}</span>
                                    @endforeach
                                </div>
                            @endif

                            @if ($offer->status === \App\Models\CarePlan::STATUS_PENDING_CAREGIVER)
                                <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                                    <x-button color="green" wire:click="acceptOffer({{ $offer->id }})">Accept schedule</x-button>
                                    <x-button color="blue" light wire:click="openCounter({{ $offer->id }})">Suggest another time</x-button>
                                    <x-button color="red" light wire:click="declineOffer({{ $offer->id }})">Decline</x-button>
                                </div>
                            @else
                                <p class="mt-4 text-sm text-amber-700">Counter sent. Waiting for the family to accept.</p>
                            @endif

                            @if ($counterPlanId === $offer->id)
                                <form wire:submit="sendCounter" class="mt-4 rounded-2xl border border-[#D8D1F1] bg-[#F5F1FB] p-4">
                                    <p class="font-display text-lg font-semibold text-[#17313F]">Suggest a better schedule</p>
                                    <div class="mt-4 space-y-4">
                                        <div>
                                            <p class="text-sm font-medium text-[#324457]">Care days</p>
                                            <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-7">
                                                @foreach ($dayOptions as $value => $label)
                                                    <label class="flex h-11 cursor-pointer items-center justify-center rounded-xl border text-sm font-semibold transition {{ in_array((string) $value, $counterScheduleDays, true) ? 'border-[#0F3D3E] bg-[#0F3D3E] text-white' : 'border-[#DED6CA] bg-white text-[#0F3D3E] hover:bg-[#F5F1EB]' }}">
                                                        <input type="checkbox" class="sr-only" value="{{ $value }}" wire:model.live="counterScheduleDays">
                                                        {{ $label }}
                                                    </label>
                                                @endforeach
                                            </div>
                                            @error('counterScheduleDays') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                        </div>

                                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                            <x-input type="time" label="Start time" wire:model="counterStartTime" />
                                            <x-input type="time" label="End time" wire:model="counterEndTime" />
                                            <x-input type="date" label="First date" wire:model="counterStartsOn" />
                                        </div>
                                        @error('counterStartTime') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                                        @error('counterEndTime') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                                        @error('counterStartsOn') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                                        <x-textarea label="Note to family" wire:model="counterNote" />
                                    </div>
                                    <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                                        <x-button color="blue" type="submit">Send counter</x-button>
                                        <x-button color="slate" light type="button" wire:click="cancelCounter">Cancel</x-button>
                                    </div>
                                </form>
                            @endif
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-[#D6CCBE] bg-[#F7F2EA] px-4 py-8 text-center">
                            <p class="font-display text-lg font-semibold text-[#17313F]">No regular-care offers right now.</p>
                            <p class="mx-auto mt-2 max-w-xl text-sm text-[#607080]">When a family wants to rebook you repeatedly, the offer appears here and in your work inbox.</p>
                        </div>
                    @endforelse
                </div>
            </x-card>
        </div>

        <aside class="space-y-5 xl:col-span-4">
            <x-card>
                <x-slot:header>
                    <h2 class="font-display text-lg font-semibold">Active regular clients</h2>
                </x-slot:header>
                <div class="space-y-3">
                    @forelse ($activePlans as $plan)
                        @php
                            $style = $statusStyles[$plan->status] ?? 'bg-slate-100 text-slate-700';
                            $planStatusLabel = $plan->status === \App\Models\CarePlan::STATUS_PAYMENT_ATTENTION
                                ? 'FAMILY PAYMENT NEEDED'
                                : strtoupper(str_replace('_', ' ', $plan->status));
                            $upcoming = $scheduleService->upcomingVisits($plan, 1);
                            $nextVisitBooking = data_get($upcoming, '0.booking');
                        @endphp
                        <div class="rounded-2xl border border-[#E4DDD3] bg-white p-3">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="font-semibold text-[#17313F]">{{ $plan->family?->name }}</p>
                                    <p class="mt-1 text-sm text-[#607080]">{{ $plan->recipientName() }}</p>
                                    <x-care-recipient-context :snapshot="$plan->recipient_snapshot" class="mt-2" />
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $style }}">{{ $planStatusLabel }}</span>
                            </div>
                            @if ($plan->status === \App\Models\CarePlan::STATUS_PAYMENT_ATTENTION)
                                <p class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                    The family needs to update payment before the next protected visit can be generated.
                                </p>
                            @endif
                            <p class="mt-2 text-xs text-[#607080]">{{ $scheduleService->scheduleLabel($plan) }}</p>
                            <p class="mt-1 text-xs text-[#4B5B6B]">Next: {{ $upcoming[0]['label'] ?? 'pending' }}</p>
                            @if ($nextVisitBooking || $plan->source_care_request_id)
                                <div class="mt-3 grid gap-2">
                                    @if ($nextVisitBooking)
                                        <a href="{{ route('care-requests.apply', $nextVisitBooking->care_request_id) }}" wire:navigate>
                                            <x-button color="blue" light sm class="w-full">Open next visit</x-button>
                                        </a>
                                    @endif
                                    @if ($plan->source_care_request_id)
                                        <a href="{{ route('care-requests.apply', $plan->source_care_request_id) }}" wire:navigate>
                                            <x-button color="gray" light sm class="w-full">Message family</x-button>
                                        </a>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-[#607080]">Accepted regular-care plans appear here.</p>
                    @endforelse
                </div>
            </x-card>
        </aside>
    </section>
</div>
