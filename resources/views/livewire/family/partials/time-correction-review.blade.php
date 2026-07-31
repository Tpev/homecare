@if (config('marketplace.time_corrections.enabled', false) && $correction)
    @php
        $timezone = app(\App\Services\Booking\CareBookingTimeCorrectionService::class)->timezoneFor($booking);
        $isPending = $correction->status === \App\Models\CareBookingTimeCorrection::STATUS_PENDING_FAMILY;
        $statusTone = match ($correction->status) {
            \App\Models\CareBookingTimeCorrection::STATUS_APPLIED => 'border-emerald-200 bg-emerald-50',
            \App\Models\CareBookingTimeCorrection::STATUS_CHANGES_REQUESTED,
            \App\Models\CareBookingTimeCorrection::STATUS_PAYMENT_ACTION_REQUIRED => 'border-amber-300 bg-amber-50',
            \App\Models\CareBookingTimeCorrection::STATUS_ESCALATED,
            \App\Models\CareBookingTimeCorrection::STATUS_APPROVED_ADMIN_REQUIRED => 'border-indigo-200 bg-indigo-50',
            default => 'border-[#0F6B62] bg-[#F3FAF7]',
        };
    @endphp

    <section
        class="rounded-3xl border-2 p-4 sm:p-5 {{ $statusTone }}"
        aria-labelledby="family-time-correction-heading"
        aria-live="polite"
        x-data
        x-on:time-correction-approval-opened.window="$nextTick(() => $refs.confirmation?.focus())"
        x-on:time-correction-approval-closed.window="$nextTick(() => $refs.approveTrigger?.focus())"
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#0F6B62]">Time correction · Visit #{{ $booking->id }}</p>
                <h3 id="family-time-correction-heading" class="mt-1 font-display text-2xl font-semibold text-[#17313F]">
                    {{ $isPending ? $caregiverFirstName.' asked you to review visit time' : $correction->statusLabel() }}
                </h3>
                <p class="mt-2 text-sm text-[#607080]">{{ $booking->scheduled_start_at?->copy()->setTimezone($timezone)->format('l, F j, Y') }} · {{ str_replace('_', ' ', $timezone) }}</p>
            </div>
            <span class="inline-flex w-fit rounded-full border border-current/15 bg-white px-3 py-1 text-xs font-semibold text-[#0F6B62]">{{ $correction->statusLabel() }}</span>
        </div>

        <div class="mt-5 grid gap-3 lg:grid-cols-3">
            <div class="rounded-2xl border border-[#E4DDD3] bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-[#7B8794]">Scheduled</p>
                <p class="mt-2 font-semibold text-[#17313F]">{{ $booking->scheduled_start_at?->copy()->setTimezone($timezone)->format('g:i A') ?: 'Not recorded' }} – {{ $booking->scheduled_end_at?->copy()->setTimezone($timezone)->format('g:i A') ?: 'Not recorded' }}</p>
            </div>
            <div class="rounded-2xl border border-[#E4DDD3] bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-[#7B8794]">App recorded</p>
                <p class="mt-2 font-semibold text-[#17313F]">{{ $booking->started_at?->copy()->setTimezone($timezone)->format('g:i A') ?: 'No check-in' }} – {{ $booking->completed_at?->copy()->setTimezone($timezone)->format('g:i A') ?: 'No end time' }}</p>
            </div>
            <div class="rounded-2xl border border-[#0F6B62] bg-[#E7F5F0] p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-[#0F6B62]">Requested</p>
                <p class="mt-2 font-semibold text-[#17313F]">{{ $correction->proposed_started_at?->copy()->setTimezone($timezone)->format('g:i A') }} – {{ $correction->proposed_completed_at?->copy()->setTimezone($timezone)->format('g:i A') }}</p>
                <p class="mt-1 text-sm text-[#526474]">{{ $correction->durationLabel() }} after {{ $correction->proposed_break_minutes }} min unpaid break</p>
            </div>
        </div>

        <div class="mt-4 grid gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(230px,0.35fr)]">
            <div class="rounded-2xl border border-[#E4DDD3] bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-[#7B8794]">What happened</p>
                <p class="mt-2 font-semibold text-[#17313F]">{{ $correction->reasonLabel() }}</p>
                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-[#526474]">{{ $correction->explanation }}</p>
                @if ($correction->family_response_note)
                    <p class="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-950"><span class="font-semibold">Your note:</span> {{ $correction->family_response_note }}</p>
                @endif
            </div>
            <div class="rounded-2xl border border-[#0F6B62]/30 bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-[#7B8794]">Final amount if approved</p>
                <p class="mt-2 font-display text-3xl font-semibold text-[#17313F]">{{ $correction->familyAmountLabel() }}</p>
                <p class="mt-1 text-sm text-[#607080]">For {{ $correction->durationLabel() }} of care.</p>
            </div>
        </div>

        @if ($isPending)
            @if ((int) $confirmingTimeCorrectionId === (int) $correction->id)
                <div class="mt-5 rounded-2xl border-2 border-[#0F6B62] bg-white p-4 sm:p-5" tabindex="-1" x-ref="confirmation">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#0F6B62]">Confirm approval</p>
                    <h4 class="mt-1 font-display text-xl font-semibold text-[#17313F]">Approve {{ $correction->durationLabel() }} and pay {{ $correction->familyAmountLabel() }}?</h4>
                    <p class="mt-2 text-sm leading-6 text-[#607080]">This confirms the caregiver’s actual care hours. The visit record and payment will be updated together.</p>
                    @error('timeCorrectionResponse') <p class="mt-3 text-sm font-medium text-red-700" role="alert">{{ $message }}</p> @enderror
                    <div class="mt-4 grid gap-2 sm:flex sm:justify-end">
                        <button type="button" wire:click="cancelTimeCorrectionApproval" class="min-h-12 rounded-xl border border-[#BFC8CE] px-5 text-base font-semibold text-[#324457] focus:outline-none focus:ring-2 focus:ring-[#0F6B62]">Go back</button>
                        <button type="button" wire:click="approveTimeCorrection({{ $correction->id }})" wire:loading.attr="disabled" class="min-h-12 rounded-xl bg-[#0F6B62] px-5 text-base font-semibold text-white disabled:opacity-60 focus:outline-none focus:ring-2 focus:ring-[#0F6B62] focus:ring-offset-2">Confirm approval and payment</button>
                    </div>
                </div>
            @else
                <div class="mt-5 grid gap-3">
                    <button x-ref="approveTrigger" type="button" wire:click="beginTimeCorrectionApproval({{ $correction->id }})" class="min-h-12 w-full rounded-xl bg-[#0F6B62] px-5 text-base font-semibold text-white focus:outline-none focus:ring-2 focus:ring-[#0F6B62] focus:ring-offset-2 sm:w-auto sm:justify-self-start">
                        Approve {{ $correction->durationLabel() }} and pay {{ $correction->familyAmountLabel() }}
                    </button>
                    <div class="rounded-2xl border border-[#E4DDD3] bg-white p-4">
                        <label for="time-correction-response-note" class="text-base font-semibold text-[#17313F]">Ask {{ $caregiverFirstName }} to change this</label>
                        <textarea id="time-correction-response-note" wire:model="timeCorrectionResponseNote" rows="3" maxlength="2000" class="mt-2 w-full rounded-xl border-[#BFC8CE] text-base focus:border-[#0F6B62] focus:ring-[#0F6B62]" placeholder="Explain what time or detail needs to change."></textarea>
                        @error('timeCorrectionResponseNote') <p class="mt-2 text-sm font-medium text-red-700" role="alert">{{ $message }}</p> @enderror
                        <button type="button" wire:click="requestTimeCorrectionChanges({{ $correction->id }})" class="mt-3 min-h-12 w-full rounded-xl border border-[#0F6B62] px-5 text-base font-semibold text-[#0F6B62] focus:outline-none focus:ring-2 focus:ring-[#0F6B62] sm:w-auto">Send change request</button>
                    </div>
                    <button type="button" wire:click="escalateTimeCorrection({{ $correction->id }})" class="min-h-12 w-full rounded-xl px-4 text-left text-base font-semibold text-[#526474] underline underline-offset-4 focus:outline-none focus:ring-2 focus:ring-[#0F6B62] sm:w-auto">Get help from LoLo</button>
                </div>
            @endif
        @elseif ($correction->status === \App\Models\CareBookingTimeCorrection::STATUS_PAYMENT_ACTION_REQUIRED)
            <div class="mt-5 rounded-2xl border border-amber-300 bg-white p-4">
                <p class="font-semibold text-amber-950">Hours approved — payment confirmation needed.</p>
                <p class="mt-1 text-sm leading-6 text-[#607080]">Your time approval is saved. The caregiver does not need to submit again.</p>
                <button type="button" wire:click="continueTimeCorrectionPayment({{ $correction->id }})" class="mt-3 min-h-12 w-full rounded-xl bg-[#0F6B62] px-5 text-base font-semibold text-white focus:outline-none focus:ring-2 focus:ring-[#0F6B62] focus:ring-offset-2 sm:w-auto">Confirm payment</button>
            </div>
        @elseif (in_array($correction->status, [\App\Models\CareBookingTimeCorrection::STATUS_CHANGES_REQUESTED, \App\Models\CareBookingTimeCorrection::STATUS_APPROVED_ADMIN_REQUIRED, \App\Models\CareBookingTimeCorrection::STATUS_ESCALATED], true))
            <p class="mt-5 rounded-2xl border border-current/10 bg-white p-4 text-sm leading-6 text-[#526474]">
                @if ($correction->status === \App\Models\CareBookingTimeCorrection::STATUS_CHANGES_REQUESTED)
                    {{ $caregiverFirstName }} can revise this request. You will review the new version before anything changes.
                @else
                    The approved hours and original visit evidence are saved. LoLo will review the visit and existing payment.
                @endif
            </p>
        @endif
    </section>
@endif
