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
            'ended' => 'bg-slate-100 text-slate-700',
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
                        $proposedScheduleLabel = $isExtra ? '' : \App\Support\WeeklySchedule::label(
                            \App\Support\WeeklySchedule::normalize(
                                data_get($proposal, 'slots'),
                                data_get($proposal, 'days', []),
                                data_get($proposal, 'start_time'),
                                data_get($proposal, 'end_time'),
                            )
                        );
                    @endphp
                    <article class="px-5 py-5 sm:px-7">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <h3 class="font-display text-xl font-semibold text-[#17313F]">{{ $change->plan?->family?->name }} requests {{ $isExtra ? 'an extra visit' : 'a schedule change' }}</h3>
                                @if ($isExtra)
                                    <p class="mt-2 text-lg font-semibold text-[#324457]">{{ $proposedStart?->format('l, F j, g:i A') }} to {{ $proposedEnd?->format('g:i A') }}</p>
                                @else
                                    <p class="mt-2 text-lg font-semibold text-[#324457]">{{ $proposedScheduleLabel ?: $proposedDays }}</p>
                                    <p class="text-base text-[#526474]">Starting {{ $change->effective_on?->format('F j') }}</p>
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
                            $offerRate = $pricing->caregiverGrossHourlyCents() / 100;
                        @endphp
                        <div class="rounded-2xl border border-[#DED6CA] bg-white p-4">
                            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="font-display text-lg font-semibold text-[#17313F]">{{ $offer->title }}</h3>
                                        <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $style }}">{{ $offerStatusLabel }}</span>
                                    </div>
                                    <p class="mt-1 text-sm text-[#607080]">
                                        {{ $offer->family?->name }} - {{ $scheduleService->scheduleLabel($offer, $offer->status === \App\Models\CarePlan::STATUS_COUNTERED) }} - ${{ number_format($offerRate, 2) }}/hr*
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
                                @if (isset($careProfileSnapshots[$offer->id]))
                                    <div class="mt-4"><x-care-recipient-profile-summary :snapshot="$careProfileSnapshots[$offer->id]" /></div>
                                @endif
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

                                        <div class="space-y-3">
                                            @foreach (collect($counterScheduleDays)->map(fn ($day) => (int) $day)->unique()->sort() as $day)
                                                <div wire:key="counter-day-{{ $day }}" class="rounded-xl border border-[#D8D1F1] bg-white p-3">
                                                    <div class="grid grid-cols-1 gap-3 md:grid-cols-[6rem_1fr_1fr] md:items-end">
                                                        <p class="font-display font-semibold text-[#17313F]">{{ $dayOptions[$day] ?? 'Day' }}</p>
                                                        <div>
                                                            <x-input type="time" label="Starts at" wire:model="counterScheduleSlots.{{ $day }}.start_time" />
                                                            @error('counterScheduleSlots.'.$day.'.start_time') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                                        </div>
                                                        <div>
                                                            <x-input type="time" label="Ends at" wire:model="counterScheduleSlots.{{ $day }}.end_time" />
                                                            @error('counterScheduleSlots.'.$day.'.end_time') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                        <x-input type="date" label="First date" wire:model="counterStartsOn" />
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
                    <h2 class="font-display text-lg font-semibold">Established regular clients</h2>
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
                            @if (isset($careProfileSnapshots[$plan->id]))
                                <div class="mt-4"><x-care-recipient-profile-summary :snapshot="$careProfileSnapshots[$plan->id]" /></div>
                            @endif
                            @if ($plan->status === \App\Models\CarePlan::STATUS_PAYMENT_ATTENTION)
                                <p class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                    The family needs to update payment before the next protected visit can be generated.
                                </p>
                            @endif
                            <p class="mt-2 text-xs text-[#607080]">{{ $scheduleService->scheduleLabel($plan) }}</p>
                            <p class="mt-1 text-xs text-[#4B5B6B]">Next: {{ $upcoming[0]['label'] ?? 'pending' }}</p>

                            @if ($plan->completedExtraVisitRequests->isNotEmpty())
                                <div class="mt-3 space-y-2" aria-label="Completed extra visit reports">
                                    @foreach ($plan->completedExtraVisitRequests as $report)
                                        @php
                                            $reportStart = $report->proposed_started_at?->copy()->setTimezone($report->timezone);
                                            $reportTone = match ($report->status) {
                                                \App\Models\CompletedExtraVisitRequest::STATUS_APPLIED => 'border-emerald-200 bg-emerald-50 text-emerald-950',
                                                \App\Models\CompletedExtraVisitRequest::STATUS_CHANGES_REQUESTED,
                                                \App\Models\CompletedExtraVisitRequest::STATUS_PAYMENT_ACTION_REQUIRED => 'border-amber-300 bg-amber-50 text-amber-950',
                                                \App\Models\CompletedExtraVisitRequest::STATUS_DISPUTED,
                                                \App\Models\CompletedExtraVisitRequest::STATUS_ESCALATED,
                                                \App\Models\CompletedExtraVisitRequest::STATUS_FAILED => 'border-rose-200 bg-rose-50 text-rose-950',
                                                default => 'border-sky-200 bg-sky-50 text-sky-950',
                                            };
                                        @endphp
                                        <div class="rounded-xl border p-3 text-sm {{ $reportTone }}">
                                            <p class="font-semibold">{{ $reportStart?->format('M j, g:i A') }} · {{ $report->durationLabel() }}</p>
                                            <p class="mt-1">{{ $report->statusLabel() }}</p>
                                            @if ($report->family_response_note && $report->status === \App\Models\CompletedExtraVisitRequest::STATUS_CHANGES_REQUESTED)
                                                <p class="mt-2 rounded-lg bg-white/80 p-2"><strong>Family note:</strong> {{ $report->family_response_note }}</p>
                                            @endif
                                            <div class="mt-2 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                                                @if ($report->status === \App\Models\CompletedExtraVisitRequest::STATUS_CHANGES_REQUESTED)
                                                    <button type="button" wire:click="openCompletedExtraVisit({{ $plan->id }}, {{ $report->id }})" class="min-h-11 rounded-lg bg-[#0F6B62] px-3 font-semibold text-white">Update report</button>
                                                @endif
                                                @if (in_array($report->status, [\App\Models\CompletedExtraVisitRequest::STATUS_PENDING_FAMILY, \App\Models\CompletedExtraVisitRequest::STATUS_CHANGES_REQUESTED], true))
                                                    <button type="button" wire:click="withdrawCompletedExtraVisit({{ $report->id }})" wire:confirm="Withdraw this report? The family will be notified and no payment will be made." class="min-h-11 rounded-lg border border-[#B85C4A] px-3 font-semibold text-[#913E31]">Withdraw</button>
                                                @endif
                                                @if ($report->booking)
                                                    <a href="{{ route('care-requests.apply', $report->booking->care_request_id) }}" wire:navigate class="inline-flex min-h-11 items-center rounded-lg border border-[#B9CDC5] px-3 font-semibold text-[#174C43]">View visit</a>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if ($completedExtraVisitService->canReport($plan, auth()->user()))
                                <button type="button" wire:click="openCompletedExtraVisit({{ $plan->id }})" class="mt-3 min-h-12 w-full rounded-xl border-2 border-[#0F6B62] px-3 text-left text-sm font-semibold text-[#0F6B62] transition hover:bg-[#F1F8F4] focus:outline-none focus:ring-2 focus:ring-[#0F6B62] focus:ring-offset-2">
                                    Report a completed extra visit
                                </button>
                            @endif

                            @if ($reportPlanId === $plan->id)
                                <div class="mt-3 rounded-2xl border-2 border-[#0F6B62] bg-[#F8FBF9] p-4" id="completed-extra-visit-form-{{ $plan->id }}">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-xs font-bold uppercase tracking-[0.14em] text-[#0F6B62]">Completed extra visit</p>
                                            <h3 class="mt-1 font-display text-xl font-semibold text-[#17313F]">Care already provided for {{ $plan->recipientName() }}</h3>
                                        </div>
                                        <button type="button" wire:click="closeCompletedExtraVisit" class="min-h-11 px-2 font-semibold text-[#526474] underline">Close</button>
                                    </div>

                                    @if (!$reviewingReport)
                                        <form wire:submit="reviewCompletedExtraVisit" class="mt-4 space-y-4">
                                            @if ($reportSupersedesId)
                                                <x-alert color="yellow">The family requested changes. This creates a new version and preserves the original report.</x-alert>
                                            @endif
                                            <div class="grid gap-3 sm:grid-cols-2">
                                                <label class="text-sm font-semibold text-[#263C48]">Visit date<input type="date" wire:model="reportDate" class="mt-1 min-h-12 w-full rounded-lg border-[#BFC8CE] text-base"></label>
                                                <label class="text-sm font-semibold text-[#263C48]">Unpaid break (minutes)<input type="number" min="0" max="480" step="5" inputmode="numeric" wire:model="reportBreakMinutes" class="mt-1 min-h-12 w-full rounded-lg border-[#BFC8CE] text-base"></label>
                                                <label class="text-sm font-semibold text-[#263C48]">Start time<input type="time" wire:model="reportStartTime" class="mt-1 min-h-12 w-full rounded-lg border-[#BFC8CE] text-base"></label>
                                                <label class="text-sm font-semibold text-[#263C48]">End time<input type="time" wire:model="reportEndTime" class="mt-1 min-h-12 w-full rounded-lg border-[#BFC8CE] text-base"><span class="mt-1 block text-xs font-normal text-[#607080]">For an overnight visit, choose an end time earlier than the start time.</span></label>
                                            </div>
                                            <label class="block text-sm font-semibold text-[#263C48]">Why was this visit not scheduled?
                                                <select wire:model="reportReason" class="mt-1 min-h-12 w-full rounded-lg border-[#BFC8CE] text-base">
                                                    <option value="family_requested">The family requested an additional visit</option>
                                                    <option value="informal_schedule_change">The schedule changed informally</option>
                                                    <option value="forgot_to_request">We forgot to request the visit in advance</option>
                                                    <option value="other">Something else happened</option>
                                                </select>
                                            </label>
                                            <label class="block text-sm font-semibold text-[#263C48]">What happened?<textarea wire:model="reportExplanation" rows="4" class="mt-1 w-full rounded-lg border-[#BFC8CE] text-base" placeholder="Explain why this care was provided outside the regular schedule."></textarea></label>
                                            <label class="block text-sm font-semibold text-[#263C48]">Optional care notes<textarea wire:model="reportCareNotes" rows="3" class="mt-1 w-full rounded-lg border-[#BFC8CE] text-base" placeholder="Do not include private information that is not needed for the visit record."></textarea></label>
                                            <label class="flex min-h-12 items-start gap-3 rounded-xl border border-[#BFC8CE] bg-white p-3 text-sm font-semibold text-[#263C48]"><input type="checkbox" wire:model="reportAttested" class="mt-1 h-5 w-5 shrink-0 rounded border-[#80909B]">I confirm that I personally provided this care and that these hours are accurate.</label>
                                            <div aria-live="polite" class="space-y-1 text-sm text-red-700">
                                                @foreach (['reportDate','reportStartTime','reportEndTime','reportBreakMinutes','reportReason','reportExplanation','reportCareNotes','reportAttested','reportSubmit'] as $field)
                                                    @error($field)<p>{{ $message }}</p>@enderror
                                                @endforeach
                                            </div>
                                            <div class="grid gap-2 sm:flex sm:flex-wrap">
                                                <button type="submit" wire:loading.attr="disabled" class="hc-primary-button min-h-12 w-full sm:w-auto">Review visit and amounts</button>
                                                <button type="button" wire:click="closeCompletedExtraVisit" class="hc-secondary-button min-h-12 w-full sm:w-auto">Cancel</button>
                                            </div>
                                        </form>
                                    @else
                                        <div class="mt-4 space-y-4" aria-live="polite">
                                            <div class="rounded-xl border border-[#C9DCD3] bg-white p-4">
                                                <p class="font-semibold text-[#17313F]">{{ data_get($reportPreview, 'when') }}</p>
                                                <p class="mt-1 text-[#526474]">{{ data_get($reportPreview, 'time') }}</p>
                                                <dl class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                                                    <div><dt class="text-xs font-semibold uppercase text-[#6A7784]">Worked time</dt><dd class="mt-1 font-semibold">{{ intdiv((int) data_get($reportPreview, 'worked_minutes'), 60) }}h {{ (int) data_get($reportPreview, 'worked_minutes') % 60 }}m</dd></div>
                                                    <div><dt class="text-xs font-semibold uppercase text-[#6A7784]">Family estimate</dt><dd class="mt-1 font-semibold">${{ data_get($reportPreview, 'family_charge') }}</dd></div>
                                                    <div><dt class="text-xs font-semibold uppercase text-[#6A7784]">Your estimated payout</dt><dd class="mt-1 font-semibold">${{ data_get($reportPreview, 'caregiver_payout') }}</dd></div>
                                                </dl>
                                            </div>
                                            <x-alert color="blue">The family must approve this report. No payment or payout happens now, and their recurring schedule will not change. This visit will be marked as manually reported.</x-alert>
                                            <div class="grid gap-2 sm:flex sm:flex-wrap">
                                                <button type="button" wire:click="submitCompletedExtraVisit" wire:loading.attr="disabled" class="hc-primary-button min-h-12 w-full sm:w-auto">Send for family approval</button>
                                                <button type="button" wire:click="editCompletedExtraVisit" class="hc-secondary-button min-h-12 w-full sm:w-auto">Edit details</button>
                                                <button type="button" wire:click="closeCompletedExtraVisit" class="min-h-12 px-3 font-semibold text-[#526474] underline">Cancel</button>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endif
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
