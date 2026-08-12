@if ($completedExtraVisits->isNotEmpty())
    <section class="rounded-3xl border-2 border-[#9FC7B5] bg-[#F3F9F6] p-5 sm:p-6" aria-labelledby="completed-extra-visits-heading">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-[#0F6B62]">Completed extra visits</p>
        <h2 id="completed-extra-visits-heading" class="mt-1 font-display text-2xl font-semibold text-[#17313F]">Visits reported outside the regular schedule</h2>
        <p class="mt-2 max-w-3xl text-base text-[#526474]">A report never changes your recurring schedule. No new payment is made until you approve the hours.</p>

        @error('extraVisitResponse')<p class="mt-3 rounded-xl border border-rose-300 bg-rose-50 p-3 text-sm font-semibold text-rose-800" role="alert">{{ $message }}</p>@enderror

        <div class="mt-5 space-y-4">
            @foreach ($completedExtraVisits as $report)
                @php
                    $start = $report->proposed_started_at?->copy()->setTimezone($report->timezone);
                    $end = $report->proposed_completed_at?->copy()->setTimezone($report->timezone);
                    $currentFinancial = $report->status === \App\Models\CompletedExtraVisitRequest::STATUS_PENDING_FAMILY
                        ? $completedExtraVisitService->currentFinancialPreview($report)
                        : ($report->final_financial_preview ?: $report->financial_preview);
                    $familyCharge = (int) data_get($currentFinancial, 'amount_captured_cents', data_get($currentFinancial, 'total_charge_cents', 0));
                    $submittedCharge = (int) data_get($report->financial_preview, 'total_charge_cents', 0);
                    $pricingChanged = $report->status === \App\Models\CompletedExtraVisitRequest::STATUS_PENDING_FAMILY
                        && $submittedCharge !== $familyCharge;
                    $tone = match ($report->status) {
                        \App\Models\CompletedExtraVisitRequest::STATUS_PENDING_FAMILY => 'border-amber-300 bg-white',
                        \App\Models\CompletedExtraVisitRequest::STATUS_APPLIED => 'border-emerald-300 bg-white',
                        \App\Models\CompletedExtraVisitRequest::STATUS_PAYMENT_ACTION_REQUIRED => 'border-amber-400 bg-amber-50',
                        \App\Models\CompletedExtraVisitRequest::STATUS_DISPUTED,
                        \App\Models\CompletedExtraVisitRequest::STATUS_ESCALATED,
                        \App\Models\CompletedExtraVisitRequest::STATUS_FAILED => 'border-rose-300 bg-rose-50',
                        default => 'border-sky-200 bg-white',
                    };
                @endphp
                <article
                    id="completed-extra-visit-{{ $report->id }}"
                    class="scroll-mt-24 rounded-2xl border-2 p-4 sm:p-5 {{ $tone }}"
                    x-data
                    x-init="if (window.location.hash === '#completed-extra-visit-{{ $report->id }}') $nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-display text-xl font-semibold text-[#17313F]">{{ $report->caregiver?->name }} reported an extra visit</h3>
                                <span class="rounded-full bg-[#E7F0EC] px-3 py-1 text-xs font-bold text-[#174C43]">{{ $report->statusLabel() }}</span>
                            </div>
                            <p class="mt-2 text-lg font-semibold text-[#324457]">{{ $start?->format('l, F j, Y') }}</p>
                            <p class="text-base text-[#526474]">{{ $start?->format('g:i A') }} to {{ $end?->format('g:i A T') }} · {{ $report->durationLabel() }} worked · {{ $report->proposed_break_minutes }} minute break</p>
                        </div>
                        <div class="rounded-xl border border-[#C9DCD3] bg-[#F8FBF9] px-4 py-3 sm:min-w-44 sm:text-right">
                            <p class="text-xs font-bold uppercase tracking-wide text-[#6A7784]">{{ $report->status === \App\Models\CompletedExtraVisitRequest::STATUS_APPLIED ? 'Net billed' : 'Estimated charge' }}</p>
                            <p class="mt-1 text-2xl font-semibold text-[#17313F]">${{ number_format($familyCharge / 100, 2) }}</p>
                        </div>
                    </div>

                    <dl class="mt-4 grid gap-3 rounded-xl border border-[#E1E7E3] bg-[#FAFCFB] p-4 sm:grid-cols-2">
                        <div><dt class="text-xs font-bold uppercase tracking-wide text-[#6A7784]">Why it was unscheduled</dt><dd class="mt-1 text-base text-[#263C48]">{{ $report->reasonLabel() }}</dd></div>
                        <div><dt class="text-xs font-bold uppercase tracking-wide text-[#6A7784]">Caregiver explanation</dt><dd class="mt-1 whitespace-pre-line text-base text-[#263C48]">{{ $report->explanation }}</dd></div>
                        @if ($report->care_notes)<div class="sm:col-span-2"><dt class="text-xs font-bold uppercase tracking-wide text-[#6A7784]">Optional care notes</dt><dd class="mt-1 whitespace-pre-line text-base text-[#263C48]">{{ $report->care_notes }}</dd></div>@endif
                        @if ($report->family_response_note)<div class="sm:col-span-2"><dt class="text-xs font-bold uppercase tracking-wide text-[#6A7784]">Your response</dt><dd class="mt-1 whitespace-pre-line text-base text-[#263C48]">{{ $report->family_response_note }}</dd></div>@endif
                    </dl>

                    <p class="mt-4 rounded-xl border border-[#D7E2DD] bg-white px-4 py-3 text-sm text-[#526474]">This is a manually reported visit. It is not GPS/check-in verified, and approving it will not add this day to your regular schedule.</p>

                    @if ($pricingChanged)
                        <p class="mt-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-950" role="status">The price changed since this report was submitted. The current estimate of ${{ number_format($familyCharge / 100, 2) }} is shown above and is the amount you are approving.</p>
                    @endif

                    @if ($report->status === \App\Models\CompletedExtraVisitRequest::STATUS_PENDING_FAMILY)
                        <label class="mt-4 block text-base font-semibold text-[#263C48]">Note for changes, a dispute, or LoLo Care support
                            <textarea wire:model="completedExtraVisitResponseNotes.{{ $report->id }}" rows="3" class="mt-2 w-full rounded-xl border-[#BFC8CE] text-base" placeholder="Required if you request changes, dispute the visit, or ask LoLo for help."></textarea>
                        </label>
                        <div class="mt-4 grid gap-3 sm:flex sm:flex-wrap">
                            <button type="button" wire:click="approveCompletedExtraVisit({{ $report->id }})" wire:confirm="Approve {{ $report->durationLabel() }} and authorize the estimated ${{ number_format($familyCharge / 100, 2) }} charge? This will not change your regular schedule." wire:loading.attr="disabled" class="hc-primary-button min-h-12 w-full text-base sm:w-auto">Approve visit and payment</button>
                            <button type="button" wire:click="requestCompletedExtraVisitChanges({{ $report->id }})" wire:loading.attr="disabled" class="hc-secondary-button min-h-12 w-full text-base sm:w-auto">Request changes</button>
                            <button type="button" wire:click="disputeCompletedExtraVisit({{ $report->id }})" wire:confirm="Report that this visit did not happen? The report will be preserved for LoLo Care review and no payment will be made." wire:loading.attr="disabled" class="min-h-12 w-full rounded-xl border-2 border-rose-500 px-4 text-left text-base font-semibold text-rose-800 focus:outline-none focus:ring-2 focus:ring-rose-600 sm:w-auto">This visit did not happen</button>
                            <button type="button" wire:click="escalateCompletedExtraVisit({{ $report->id }})" class="min-h-12 w-full px-3 text-left text-base font-semibold text-[#526474] underline underline-offset-4 sm:w-auto">Ask LoLo for help</button>
                        </div>
                    @elseif ($report->status === \App\Models\CompletedExtraVisitRequest::STATUS_PAYMENT_ACTION_REQUIRED)
                        <div class="mt-4 rounded-xl border border-amber-300 bg-white p-4">
                            <p class="font-semibold text-amber-950">The visit is approved, but payment needs your attention.</p>
                            <p class="mt-1 text-sm text-amber-900">Your approval and the care record are preserved. You do not need to ask the caregiver to submit it again.</p>
                            <div class="mt-3 grid gap-2 sm:flex sm:flex-wrap">
                                <a href="{{ route('family.billing.show') }}" wire:navigate class="hc-primary-button min-h-12 w-full sm:w-auto">Open Billing & Payments</a>
                                <button type="button" wire:click="retryCompletedExtraVisitPayment({{ $report->id }})" wire:loading.attr="disabled" class="hc-secondary-button min-h-12 w-full sm:w-auto">Retry payment</button>
                            </div>
                        </div>
                    @elseif ($report->status === \App\Models\CompletedExtraVisitRequest::STATUS_CHANGES_REQUESTED)
                        <p class="mt-4 rounded-xl border border-sky-200 bg-sky-50 p-4 font-semibold text-sky-950">Waiting for {{ $report->caregiver?->name }} to submit an updated version. No payment has been made.</p>
                    @elseif ($report->status === \App\Models\CompletedExtraVisitRequest::STATUS_APPLIED)
                        <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                            <a href="{{ route('family.care.history', ['plan' => $report->care_plan_id]) }}" wire:navigate class="hc-primary-button min-h-12">View in care history</a>
                            @if ($report->booking)<a href="{{ route('family.requests.show', $report->booking->care_request_id) }}" wire:navigate class="hc-secondary-button min-h-12">Open full visit record</a>@endif
                        </div>
                    @elseif (in_array($report->status, [\App\Models\CompletedExtraVisitRequest::STATUS_DISPUTED, \App\Models\CompletedExtraVisitRequest::STATUS_ESCALATED, \App\Models\CompletedExtraVisitRequest::STATUS_FAILED], true))
                        <p class="mt-4 rounded-xl border border-rose-200 bg-white p-4 font-semibold text-rose-950">No new payment will be made while LoLo Care reviews this report.</p>
                    @endif
                </article>
            @endforeach
        </div>
    </section>
@endif
