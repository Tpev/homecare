@php
    $timezone = $booking ? app(\App\Services\Booking\CareBookingTimeCorrectionService::class)->timezoneFor($booking) : config('app.timezone');
    $statusTone = match ($latestTimeCorrection?->status) {
        \App\Models\CareBookingTimeCorrection::STATUS_APPLIED => 'border-emerald-200 bg-emerald-50 text-emerald-950',
        \App\Models\CareBookingTimeCorrection::STATUS_CHANGES_REQUESTED,
        \App\Models\CareBookingTimeCorrection::STATUS_PAYMENT_ACTION_REQUIRED => 'border-amber-300 bg-amber-50 text-amber-950',
        \App\Models\CareBookingTimeCorrection::STATUS_ESCALATED,
        \App\Models\CareBookingTimeCorrection::STATUS_APPROVED_ADMIN_REQUIRED => 'border-indigo-200 bg-indigo-50 text-indigo-950',
        default => 'border-[#C9D9D4] bg-[#F3FAF7] text-[#17313F]',
    };
@endphp

@if ($timeCorrectionsEnabled && ($showTimeCorrectionPanel || $latestTimeCorrection))
    <section
        class="scroll-mt-24 rounded-3xl border p-5 shadow-sm {{ $showTimeCorrectionPanel ? 'border-[#0F6B62] bg-white' : $statusTone }}"
        aria-labelledby="time-correction-heading"
        aria-live="polite"
        x-data
        x-on:time-correction-opened.window="$nextTick(() => $refs.heading?.focus())"
        x-on:time-correction-closed.window="$nextTick(() => document.getElementById('time-correction-trigger')?.focus())"
    >
        @if ($showTimeCorrectionPanel)
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#0F6B62]">Manual time · Family approval required</p>
                    <h2 id="time-correction-heading" x-ref="heading" tabindex="-1" class="mt-1 font-display text-2xl font-semibold text-[#17313F] focus:outline-none">Fix visit time</h2>
                    <p class="mt-2 text-sm leading-6 text-[#607080]">Enter only the care hours you actually worked. The family will review the exact time before anything is finalized.</p>
                </div>
                <button type="button" wire:click="closeTimeCorrection" class="min-h-12 min-w-12 rounded-full border border-[#D6CCBE] text-xl text-[#526474] focus:outline-none focus:ring-2 focus:ring-[#0F6B62]" aria-label="Close time correction">×</button>
            </div>

            @if (! $reviewingTimeCorrection)
                <form wire:submit="reviewTimeCorrection" class="mt-6 space-y-5">
                    @if ($timeCorrectionSupersedesId && $latestTimeCorrection?->family_response_note)
                        <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">
                            <p class="font-semibold">The family asked for a change</p>
                            <p class="mt-1 leading-6">{{ $latestTimeCorrection->family_response_note }}</p>
                        </div>
                    @endif

                    <fieldset>
                        <legend class="text-base font-semibold text-[#17313F]">What happened?</legend>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            @foreach ([
                                \App\Models\CareBookingTimeCorrection::REASON_FORGOT_START => 'I forgot to check in',
                                \App\Models\CareBookingTimeCorrection::REASON_FORGOT_END => 'I forgot to end the visit',
                                \App\Models\CareBookingTimeCorrection::REASON_FORGOT_BOTH => 'I provided care — add my hours',
                                \App\Models\CareBookingTimeCorrection::REASON_BREAK_WRONG => 'The unpaid break is wrong',
                                \App\Models\CareBookingTimeCorrection::REASON_APP_OR_GPS => 'The app or location record is wrong',
                                \App\Models\CareBookingTimeCorrection::REASON_OTHER => 'Something else happened',
                            ] as $value => $label)
                                <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-xl border border-[#D6CCBE] px-3 py-2 text-base text-[#263C48] has-[:checked]:border-[#0F6B62] has-[:checked]:bg-[#EDF8F4]">
                                    <input type="radio" wire:model="timeCorrectionReason" value="{{ $value }}" class="h-5 w-5 border-[#AAB7BF] text-[#0F6B62] focus:ring-[#0F6B62]">
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('timeCorrectionReason') <p class="mt-2 text-sm font-medium text-red-700" role="alert">{{ $message }}</p> @enderror
                    </fieldset>

                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block text-base font-semibold text-[#263C48]" for="time-correction-start">
                            Actual start
                            <input id="time-correction-start" type="datetime-local" wire:model="timeCorrectionStartedAt" aria-describedby="time-correction-timezone @error('timeCorrectionStartedAt') time-correction-start-error @enderror" class="mt-2 min-h-12 w-full rounded-xl border-[#BFC8CE] text-base focus:border-[#0F6B62] focus:ring-[#0F6B62]">
                        </label>
                        <label class="block text-base font-semibold text-[#263C48]" for="time-correction-end">
                            Actual end
                            <input id="time-correction-end" type="datetime-local" wire:model="timeCorrectionCompletedAt" aria-describedby="time-correction-timezone @error('timeCorrectionCompletedAt') time-correction-end-error @enderror" class="mt-2 min-h-12 w-full rounded-xl border-[#BFC8CE] text-base focus:border-[#0F6B62] focus:ring-[#0F6B62]">
                        </label>
                        @error('timeCorrectionStartedAt') <p id="time-correction-start-error" class="text-sm font-medium text-red-700" role="alert">{{ $message }}</p> @enderror
                        @error('timeCorrectionCompletedAt') <p id="time-correction-end-error" class="text-sm font-medium text-red-700" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <p id="time-correction-timezone" class="text-sm text-[#607080]">Times are shown in {{ str_replace('_', ' ', $timezone) }}.</p>

                    <label class="block text-base font-semibold text-[#263C48]" for="time-correction-break">
                        Unpaid break (minutes)
                        <input id="time-correction-break" type="number" min="0" max="960" inputmode="numeric" wire:model="timeCorrectionBreakMinutes" class="mt-2 min-h-12 w-full rounded-xl border-[#BFC8CE] text-base focus:border-[#0F6B62] focus:ring-[#0F6B62] md:max-w-xs">
                    </label>
                    @error('timeCorrectionBreakMinutes') <p class="text-sm font-medium text-red-700" role="alert">{{ $message }}</p> @enderror

                    <label class="block text-base font-semibold text-[#263C48]" for="time-correction-explanation">
                        Explain what happened
                        <textarea id="time-correction-explanation" wire:model="timeCorrectionExplanation" rows="4" maxlength="2000" class="mt-2 w-full rounded-xl border-[#BFC8CE] text-base focus:border-[#0F6B62] focus:ring-[#0F6B62]" placeholder="For example: I arrived at 7:30 AM and provided care until 9:30 AM, but I forgot to check in."></textarea>
                    </label>
                    @error('timeCorrectionExplanation') <p class="text-sm font-medium text-red-700" role="alert">{{ $message }}</p> @enderror

                    <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-[#D6CCBE] bg-[#FFFCF8] p-4 text-base text-[#263C48]">
                        <input type="checkbox" wire:model="timeCorrectionConfirmed" class="mt-0.5 h-5 w-5 rounded border-[#AAB7BF] text-[#0F6B62] focus:ring-[#0F6B62]">
                        <span>I confirm these are the hours I actually provided care.</span>
                    </label>
                    @error('timeCorrectionConfirmed') <p class="text-sm font-medium text-red-700" role="alert">{{ $message }}</p> @enderror
                    @error('timeCorrectionSubmit') <p class="text-sm font-medium text-red-700" role="alert">{{ $message }}</p> @enderror

                    <div class="grid gap-2 sm:flex sm:justify-end">
                        <button type="button" wire:click="closeTimeCorrection" class="min-h-12 rounded-xl border border-[#BFC8CE] px-5 text-base font-semibold text-[#324457] focus:outline-none focus:ring-2 focus:ring-[#0F6B62]">Cancel</button>
                        <button type="submit" class="min-h-12 rounded-xl bg-[#0F6B62] px-5 text-base font-semibold text-white focus:outline-none focus:ring-2 focus:ring-[#0F6B62] focus:ring-offset-2">Review request</button>
                    </div>
                </form>
            @else
                <div class="mt-6 space-y-5">
                    <div class="rounded-2xl border border-[#C9D9D4] bg-[#F3FAF7] p-4">
                        <h3 class="font-display text-xl font-semibold text-[#17313F]">Review before sending</h3>
                        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                            <div><dt class="text-[#607080]">Actual care time</dt><dd class="mt-1 font-semibold text-[#17313F]">{{ \Illuminate\Support\Carbon::parse($timeCorrectionPreview['started_at'])->setTimezone($timezone)->format('M j, g:i A') }} – {{ \Illuminate\Support\Carbon::parse($timeCorrectionPreview['completed_at'])->setTimezone($timezone)->format('g:i A') }}</dd></div>
                            <div><dt class="text-[#607080]">Worked duration</dt><dd class="mt-1 font-semibold text-[#17313F]">{{ $timeCorrectionPreview['worked_label'] }}</dd></div>
                            <div><dt class="text-[#607080]">Unpaid break</dt><dd class="mt-1 font-semibold text-[#17313F]">{{ $timeCorrectionPreview['break_minutes'] }} min</dd></div>
                            <div><dt class="text-[#607080]">Estimated earnings</dt><dd class="mt-1 font-semibold text-[#17313F]">{{ $timeCorrectionPreview['caregiver_amount_label'] }}</dd></div>
                        </dl>
                    </div>
                    <div class="grid gap-3 md:grid-cols-3">
                        <div class="rounded-2xl border border-[#E4DDD3] p-4"><p class="text-xs font-semibold uppercase tracking-wide text-[#7B8794]">Scheduled</p><p class="mt-2 text-sm font-semibold text-[#17313F]">{{ $booking->scheduled_start_at?->copy()->setTimezone($timezone)->format('g:i A') ?: 'Not recorded' }} – {{ $booking->scheduled_end_at?->copy()->setTimezone($timezone)->format('g:i A') ?: 'Not recorded' }}</p></div>
                        <div class="rounded-2xl border border-[#E4DDD3] p-4"><p class="text-xs font-semibold uppercase tracking-wide text-[#7B8794]">App recorded</p><p class="mt-2 text-sm font-semibold text-[#17313F]">{{ $booking->started_at?->copy()->setTimezone($timezone)->format('g:i A') ?: 'No check-in' }} – {{ $booking->completed_at?->copy()->setTimezone($timezone)->format('g:i A') ?: 'No end time' }}</p></div>
                        <div class="rounded-2xl border border-[#0F6B62] bg-[#EDF8F4] p-4"><p class="text-xs font-semibold uppercase tracking-wide text-[#0F6B62]">Requested</p><p class="mt-2 text-sm font-semibold text-[#17313F]">{{ \Illuminate\Support\Carbon::parse($timeCorrectionPreview['started_at'])->setTimezone($timezone)->format('g:i A') }} – {{ \Illuminate\Support\Carbon::parse($timeCorrectionPreview['completed_at'])->setTimezone($timezone)->format('g:i A') }}</p></div>
                    </div>
                    <p class="text-sm leading-6 text-[#607080]">Nothing changes until the family approves this request.</p>
                    <div class="grid gap-2 sm:flex sm:justify-end">
                        <button type="button" wire:click="editTimeCorrection" class="min-h-12 rounded-xl border border-[#BFC8CE] px-5 text-base font-semibold text-[#324457] focus:outline-none focus:ring-2 focus:ring-[#0F6B62]">Edit</button>
                        <button type="button" wire:click="submitTimeCorrection" wire:loading.attr="disabled" class="min-h-12 rounded-xl bg-[#0F6B62] px-5 text-base font-semibold text-white disabled:opacity-60 focus:outline-none focus:ring-2 focus:ring-[#0F6B62] focus:ring-offset-2">Send to family</button>
                    </div>
                </div>
            @endif
        @elseif ($latestTimeCorrection)
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em]">Time correction · Version {{ $latestTimeCorrection->version }}</p>
                    <h2 id="time-correction-heading" class="mt-1 font-display text-2xl font-semibold">{{ $latestTimeCorrection->statusLabel() }}</h2>
                    <p class="mt-2 text-sm leading-6">{{ $latestTimeCorrection->reasonLabel() }} · {{ $latestTimeCorrection->durationLabel() }} · estimated earnings {{ $latestTimeCorrection->caregiverAmountLabel() }}</p>
                    @if ($latestTimeCorrection->family_response_note)
                        <div class="mt-3 rounded-xl border border-current/20 bg-white/60 p-3 text-sm"><span class="font-semibold">Family note:</span> {{ $latestTimeCorrection->family_response_note }}</div>
                    @endif
                    @if ($latestTimeCorrection->last_error && in_array($latestTimeCorrection->status, [\App\Models\CareBookingTimeCorrection::STATUS_PAYMENT_ACTION_REQUIRED, \App\Models\CareBookingTimeCorrection::STATUS_ESCALATED], true))
                        <p class="mt-3 text-sm">{{ $latestTimeCorrection->last_error }}</p>
                    @endif
                </div>
                <div class="grid shrink-0 gap-2 sm:min-w-48">
                    @if ($latestTimeCorrection->status === \App\Models\CareBookingTimeCorrection::STATUS_CHANGES_REQUESTED)
                        <button id="time-correction-trigger" type="button" wire:click="openTimeCorrection" class="min-h-12 rounded-xl bg-[#0F6B62] px-4 text-base font-semibold text-white focus:outline-none focus:ring-2 focus:ring-[#0F6B62] focus:ring-offset-2">Update request</button>
                    @endif
                    @if (in_array($latestTimeCorrection->status, [\App\Models\CareBookingTimeCorrection::STATUS_PENDING_FAMILY, \App\Models\CareBookingTimeCorrection::STATUS_CHANGES_REQUESTED], true))
                        <button type="button" wire:click="withdrawTimeCorrection({{ $latestTimeCorrection->id }})" wire:confirm="Withdraw this time correction?" class="min-h-12 rounded-xl border border-current/30 px-4 text-base font-semibold focus:outline-none focus:ring-2 focus:ring-[#0F6B62]">Withdraw</button>
                        <button type="button" wire:click="escalateTimeCorrection({{ $latestTimeCorrection->id }})" class="min-h-12 rounded-xl px-4 text-base font-semibold underline underline-offset-4 focus:outline-none focus:ring-2 focus:ring-[#0F6B62]">Get help from LoLo</button>
                    @endif
                </div>
            </div>
        @endif
    </section>
@endif
