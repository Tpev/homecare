<div class="hc-page space-y-5 py-5 sm:space-y-6 sm:py-8">
    @php
        $workedHours = intdiv((int) $summary['worked_minutes'], 60);
        $workedRemainingMinutes = (int) $summary['worked_minutes'] % 60;
        $workedSummary = $workedHours.'h '.str_pad((string) $workedRemainingMinutes, 2, '0', STR_PAD_LEFT).'m';
        $multipleCurrencies = count($summary['money']) > 1;
        $visitToneClasses = [
            'completed' => 'bg-emerald-100 text-emerald-800',
            'awaiting_approval' => 'bg-amber-100 text-amber-900',
            'check_in_missing' => 'bg-amber-100 text-amber-900',
            'disputed' => 'bg-rose-100 text-rose-800',
            'cancelled' => 'bg-slate-100 text-slate-700',
            'no_show' => 'bg-rose-100 text-rose-800',
        ];
        $paymentToneClasses = [
            'green' => 'bg-emerald-100 text-emerald-800',
            'blue' => 'bg-sky-100 text-sky-800',
            'amber' => 'bg-amber-100 text-amber-900',
            'slate' => 'bg-slate-100 text-slate-700',
        ];
    @endphp

    <section class="relative overflow-hidden rounded-3xl bg-[#23483F] p-5 text-white shadow-sm sm:p-7">
        <div class="pointer-events-none absolute -right-16 -top-20 h-64 w-64 rounded-full bg-[#7C5DDC]/20 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-24 -left-10 h-56 w-56 rounded-full bg-[#C96B55]/20 blur-3xl"></div>
        <div class="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#D8E8D4]">Care history</p>
                <h1 class="mt-2 font-display text-3xl font-semibold leading-tight text-white sm:text-4xl">Care history</h1>
                <p class="mt-3 max-w-2xl text-base leading-7 text-[#F7F1E8]">
                    Every previous visit, caregiver, worked hour, and charge in one place.
                </p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                <a href="{{ route('family.requests.index') }}" wire:navigate class="inline-flex min-h-12 items-center justify-center rounded-xl bg-white px-5 text-sm font-semibold text-[#23483F] shadow-sm transition hover:bg-[#F8F0E2]">Back to Care</a>
                <a href="{{ route('family.care.index') }}" wire:navigate class="inline-flex min-h-12 items-center justify-center rounded-xl border border-white/35 px-5 text-sm font-semibold text-white transition hover:bg-white/10">Regular care</a>
            </div>
        </div>
    </section>

    <section aria-labelledby="history-summary-heading" class="space-y-3">
        <div class="flex items-end justify-between gap-3">
            <div>
                <h2 id="history-summary-heading" class="font-display text-xl font-semibold text-[#17313F]">History summary</h2>
                <p class="text-sm text-[#607080]">Totals reflect every matching visit, not only this page.</p>
            </div>
            @if ($multipleCurrencies)
                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-900">Shown by currency</span>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-3">
            <article class="rounded-2xl border border-[#D8E1D7] bg-[#F6FBF8] p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-emerald-700">Care provided</p>
                <p class="mt-2 text-2xl font-semibold text-[#17313F]">{{ (int) $summary['care_provided'] }}</p>
                <p class="mt-1 text-xs text-[#607080]">Visits with recorded care</p>
            </article>
            <article class="rounded-2xl border border-[#D8E1D7] bg-[#F6FBF8] p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-emerald-700">Recorded hours</p>
                <p class="mt-2 text-2xl font-semibold text-[#17313F]">{{ $workedSummary }}</p>
                <p class="mt-1 text-xs text-[#607080]">Submitted worked time only</p>
            </article>
            <article class="col-span-2 rounded-2xl border border-[#D7CCE9] bg-[#FAF8FD] p-4 shadow-sm lg:col-span-1">
                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-[#6A4E9A]">Net billed</p>
                <div class="mt-2 space-y-1">
                    @foreach ($summary['money'] as $money)
                        <p class="text-2xl font-semibold text-[#17313F]">{{ $money['net_billed_label'] }}</p>
                        @if ($multipleCurrencies)<p class="text-xs text-[#607080]">{{ $money['currency'] }}</p>@endif
                    @endforeach
                </div>
                <p class="mt-1 text-xs text-[#607080]">Captured minus refunds</p>
            </article>
        </div>
    </section>

    <section aria-labelledby="history-filters-heading" class="rounded-3xl border border-[#E4DDD3] bg-[#FFFCF8] p-4 shadow-sm sm:p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 id="history-filters-heading" class="font-display text-xl font-semibold text-[#17313F]">Find a visit</h2>
                <p class="text-sm text-[#607080]">Search by booking number, caregiver, recipient, or care title.</p>
            </div>
            @if ($activeFilterCount > 0)
                <button type="button" wire:click="clearFilters" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-[#D6CCBE] bg-white px-4 text-sm font-semibold text-[#0F3D3E] transition hover:bg-[#F5F1EB]">
                    Clear {{ $activeFilterCount }} filter{{ $activeFilterCount === 1 ? '' : 's' }}
                </button>
            @endif
        </div>

        <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <label for="care-history-search" class="block text-sm font-medium text-[#324457]">Search</label>
                <input id="care-history-search" type="search" wire:model.change="search" placeholder="Booking #, person, or title" class="mt-1 h-11 w-full rounded-xl border border-[#DED6CA] bg-white px-3 text-sm text-[#17313F] shadow-sm outline-none transition placeholder:text-[#8A96A3] focus:border-[#4F6FAF] focus:ring-2 focus:ring-[#4F6FAF]/20">
            </div>
            <x-native-select-field label="Date range" wire:model.live="range" :options="$rangeOptions" id="care-history-range" />
            <x-native-select-field label="Care type" wire:model.live="careType" :options="$careTypeOptions" id="care-history-type" />
            <x-native-select-field label="Visit status" wire:model.live="visitStatus" :options="$visitStatusOptions" id="care-history-visit-status" />
            <x-native-select-field label="Payment status" wire:model.live="paymentStatus" :options="$paymentStatusOptions" id="care-history-payment-status" />
            <x-native-select-field label="Recipient" wire:model.live="recipient" :options="$recipientOptions" id="care-history-recipient" />
            <x-native-select-field label="Caregiver" wire:model.live="caregiver" :options="$caregiverOptions" id="care-history-caregiver" />
            <x-native-select-field label="Regular-care plan" wire:model.live="plan" :options="$planOptions" id="care-history-plan" />
        </div>

        @if ($range === 'custom')
            <div class="mt-3 grid gap-3 rounded-2xl border border-[#E4DDD3] bg-white p-3 sm:grid-cols-2">
                <div>
                    <label for="care-history-from" class="block text-sm font-medium text-[#324457]">From</label>
                    <input id="care-history-from" type="date" wire:model.change="from" class="mt-1 h-11 w-full rounded-xl border border-[#DED6CA] bg-white px-3 text-sm text-[#17313F] focus:border-[#4F6FAF] focus:ring-2 focus:ring-[#4F6FAF]/20">
                </div>
                <div>
                    <label for="care-history-to" class="block text-sm font-medium text-[#324457]">To</label>
                    <input id="care-history-to" type="date" wire:model.change="to" class="mt-1 h-11 w-full rounded-xl border border-[#DED6CA] bg-white px-3 text-sm text-[#17313F] focus:border-[#4F6FAF] focus:ring-2 focus:ring-[#4F6FAF]/20">
                </div>
            </div>
        @endif
    </section>

    <section aria-labelledby="history-list-heading" class="space-y-3">
        <div class="flex items-end justify-between gap-3">
            <div>
                <h2 id="history-list-heading" class="font-display text-xl font-semibold text-[#17313F]">Previous care</h2>
                <p class="text-sm text-[#607080]">{{ $historyItems->total() }} matching booking{{ $historyItems->total() === 1 ? '' : 's' }}, newest first.</p>
            </div>
            <div wire:loading class="text-sm font-medium text-[#4F6FAF]" role="status">Updating…</div>
        </div>

        <div class="space-y-3" wire:loading.class="opacity-60">
            @forelse ($historyItems as $item)
                @php
                    $visitClass = $visitToneClasses[$item['visit_status_key']] ?? 'bg-slate-100 text-slate-700';
                    $paymentClass = $paymentToneClasses[$item['payment']['tone']] ?? 'bg-slate-100 text-slate-700';
                @endphp
                <article wire:key="care-history-booking-{{ $item['booking_id'] }}" class="overflow-hidden rounded-3xl border border-[#E4DDD3] bg-white shadow-sm">
                    <div class="p-4 sm:p-5">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <time datetime="{{ optional($item['reference_at'])->toDateString() }}" class="text-sm font-semibold text-[#C96B55]">
                                        {{ optional($item['reference_at'])->format('D, M j, Y') ?: 'Date unavailable' }}
                                    </time>
                                    <span class="rounded-full bg-[#F5F1EB] px-2.5 py-1 text-[11px] font-semibold text-[#4B5B6B]">{{ $item['care_type_label'] }}</span>
                                    <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $visitClass }}">{{ $item['visit_status_label'] }}</span>
                                    @if ($item['adjusted'])
                                        <span class="rounded-full bg-indigo-100 px-2.5 py-1 text-[11px] font-semibold text-indigo-800">Adjusted</span>
                                    @endif
                                    @if ($item['time_correction'])
                                        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-semibold text-amber-900">{{ $item['time_correction']['status_label'] }}</span>
                                    @endif
                                </div>

                                <h3 class="mt-2 font-display text-xl font-semibold text-[#17313F]">{{ $item['recipient_name'] }} with {{ $item['caregiver_name'] }}</h3>
                                <p class="mt-1 text-sm text-[#607080]">{{ $item['request_title'] }} · Booking #{{ $item['booking_id'] }}</p>

                                <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-3">
                                    <div class="rounded-xl border border-[#E4DDD3] bg-[#FFFCF8] px-3 py-2">
                                        <p class="text-[11px] uppercase tracking-[0.12em] text-[#7B8794]">Scheduled</p>
                                        <p class="mt-1 text-sm font-semibold text-[#17313F]">
                                            {{ optional($item['scheduled_start_at'])->format('g:i A') ?: 'Time unavailable' }}
                                            @if ($item['scheduled_end_at'])–{{ $item['scheduled_end_at']->format('g:i A') }}@endif
                                        </p>
                                        <p class="text-xs text-[#607080]">{{ $item['scheduled_duration_label'] ?: 'Duration unavailable' }}</p>
                                    </div>
                                    <div class="rounded-xl border border-[#E4DDD3] bg-[#FFFCF8] px-3 py-2">
                                        <p class="text-[11px] uppercase tracking-[0.12em] text-[#7B8794]">Recorded care</p>
                                        @if ($item['worked_label'])
                                            <p class="mt-1 text-sm font-semibold text-[#17313F]">Worked {{ $item['worked_label'] }}</p>
                                            <p class="text-xs text-[#607080]">Submitted caregiver time</p>
                                        @else
                                            <p class="mt-1 text-sm font-semibold text-[#607080]">No worked time recorded</p>
                                            <p class="text-xs text-[#607080]">Scheduled time is not counted as worked</p>
                                        @endif
                                    </div>
                                    <div class="rounded-xl border border-[#E4DDD3] bg-[#FFFCF8] px-3 py-2">
                                        <p class="text-[11px] uppercase tracking-[0.12em] text-[#7B8794]">Payment</p>
                                        <p class="mt-1 text-sm font-semibold text-[#17313F]">{{ $item['payment']['amount_label'] }}</p>
                                        <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $paymentClass }}">{{ $item['payment']['label'] }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex shrink-0 flex-col gap-2 lg:w-48">
                                <a href="{{ $item['action_url'] }}" wire:navigate class="hc-primary-button min-h-11 w-full">{{ $item['action_label'] }}</a>
                            </div>
                        </div>

                        <details class="mt-4 rounded-2xl border border-[#E4DDD3] bg-[#FFFCF8]" id="booking-{{ $item['booking_id'] }}">
                            <summary class="cursor-pointer list-none px-4 py-3 text-sm font-semibold text-[#17313F] [&::-webkit-details-marker]:hidden">
                                Visit, payment, and help details
                            </summary>
                            <div class="space-y-4 border-t border-[#E4DDD3] p-4">
                                <p class="rounded-xl border border-[#D8E1D7] bg-[#F6FBF8] px-3 py-2 text-sm text-[#2F6B55]">{{ $item['visit_status_help'] }}</p>

                                @if ($item['time_correction'])
                                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-amber-800">Time correction · Version {{ $item['time_correction']['version'] }}</p>
                                                <p class="mt-1 font-semibold text-[#17313F]">{{ $item['time_correction']['status_label'] }}</p>
                                                <p class="mt-1 text-sm text-[#526474]">{{ $item['time_correction']['reason_label'] }} · {{ $item['time_correction']['duration_label'] }}</p>
                                            </div>
                                            <p class="text-sm font-semibold text-[#17313F]">{{ $item['time_correction']['family_amount_label'] }}</p>
                                        </div>
                                    </div>
                                @endif

                                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[#7B8794]">Actual check-in</p>
                                        <p class="mt-1 text-sm font-semibold text-[#17313F]">{{ optional($item['started_at'])->format('M j, g:i A') ?: 'Not recorded' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[#7B8794]">Actual check-out</p>
                                        <p class="mt-1 text-sm font-semibold text-[#17313F]">{{ optional($item['completed_at'])->format('M j, g:i A') ?: 'Not recorded' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[#7B8794]">Break time</p>
                                        <p class="mt-1 text-sm font-semibold text-[#17313F]">{{ $item['break_label'] }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[#7B8794]">Tasks</p>
                                        <p class="mt-1 text-sm font-semibold text-[#17313F]">{{ $item['task_completed'] }} of {{ $item['task_total'] }} completed</p>
                                    </div>
                                </div>

                                <div class="rounded-xl border border-[#D7CCE9] bg-white p-3">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p class="text-sm font-semibold text-[#17313F]">Payment record</p>
                                            <p class="mt-1 text-xs text-[#607080]">{{ $item['payment']['help'] }}</p>
                                            @if ($item['payment']['captured_at'])
                                                <p class="mt-1 text-xs text-[#607080]">Captured {{ $item['payment']['captured_at']->format('M j, Y \a\t g:i A') }}</p>
                                            @elseif ($item['payment']['authorized_at'])
                                                <p class="mt-1 text-xs text-[#607080]">Authorized {{ $item['payment']['authorized_at']->format('M j, Y \a\t g:i A') }}</p>
                                            @endif
                                        </div>
                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $paymentClass }}">{{ $item['payment']['label'] }}</span>
                                    </div>
                                    @if ($item['payment']['gross_cents'] > 0 || $item['payment']['refunded_cents'] > 0)
                                        <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-3">
                                            <div class="rounded-lg bg-[#FAF8FD] px-3 py-2"><dt class="text-xs text-[#7B8794]">Captured</dt><dd class="font-semibold text-[#17313F]">{{ $item['payment']['gross_label'] }}</dd></div>
                                            <div class="rounded-lg bg-[#FAF8FD] px-3 py-2"><dt class="text-xs text-[#7B8794]">Refunded</dt><dd class="font-semibold text-[#17313F]">{{ $item['payment']['refunded_label'] }}</dd></div>
                                            <div class="rounded-lg bg-[#FAF8FD] px-3 py-2"><dt class="text-xs text-[#7B8794]">Net paid</dt><dd class="font-semibold text-[#17313F]">{{ $item['payment']['net_label'] }}</dd></div>
                                        </dl>
                                        @if ($item['payment']['overage_cents'] > 0)
                                            <p class="mt-2 text-xs text-[#607080]">The captured total includes {{ $item['payment']['overage_label'] }} of additional worked time. It is not added twice.</p>
                                        @endif
                                    @endif
                                </div>

                                @if ($item['task_total'] > 0)
                                    <div>
                                        <p class="text-sm font-semibold text-[#17313F]">Task completion snapshot</p>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            @foreach ($item['task_checks'] as $task)
                                                <span class="rounded-full border px-3 py-1 text-xs font-medium {{ $task->is_completed ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-[#E4DDD3] bg-white text-[#607080]' }}">
                                                    {{ $task->is_completed ? 'Completed' : 'Not marked' }} · {{ $task->label }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <div class="flex flex-col gap-2 border-t border-[#E4DDD3] pt-4 sm:flex-row sm:flex-wrap">
                                    <a href="{{ $item['action_url'] }}" wire:navigate class="hc-primary-button min-h-11">{{ $item['action_label'] }}</a>
                                    <a href="{{ route('family.requests.show', $item['care_request_id']) }}" wire:navigate class="hc-secondary-button min-h-11">Open full visit record</a>
                                    <a href="{{ route('family.requests.show', ['careRequest' => $item['care_request_id'], 'tab' => 'support']) }}" wire:navigate class="hc-secondary-button min-h-11">Get help with this visit</a>
                                    @if ($item['caregiver_profile_url'])
                                        <a href="{{ $item['caregiver_profile_url'] }}" wire:navigate class="hc-secondary-button min-h-11">View caregiver profile</a>
                                    @endif
                                    @if ($item['care_plan_id'])
                                        <a href="{{ route('family.care.show', $item['care_plan_id']) }}" wire:navigate class="hc-secondary-button min-h-11">Open regular-care plan</a>
                                    @endif
                                </div>
                            </div>
                        </details>
                    </div>
                </article>
            @empty
                <div class="rounded-3xl border border-dashed border-[#D6CCBE] bg-[#FFFCF8] px-5 py-10 text-center">
                    <p class="font-display text-xl font-semibold text-[#17313F]">No past care matches these filters.</p>
                    <p class="mx-auto mt-2 max-w-xl text-sm text-[#607080]">Clear filters to see all previous visits, or return to Care to review upcoming and open requests.</p>
                    <div class="mt-5 flex flex-col justify-center gap-2 sm:flex-row">
                        @if ($activeFilterCount > 0)
                            <button type="button" wire:click="clearFilters" class="hc-primary-button min-h-11">Clear filters</button>
                        @endif
                        <a href="{{ route('family.requests.index') }}" wire:navigate class="hc-secondary-button min-h-11">Back to Care</a>
                    </div>
                </div>
            @endforelse
        </div>

        @if ($historyItems->hasPages())
            <div class="pt-2">{{ $historyItems->links() }}</div>
        @endif
    </section>
</div>
