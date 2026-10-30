@if ($reviewingApplicationId || $reviewingCompletion)
<section id="care-decision-review" x-ref="decisionReview" x-init="$nextTick(() => { $el.focus(); $el.scrollIntoView({ block: 'start' }); })" tabindex="-1" class="hc-surface border-2 border-hc-primary p-5 sm:p-7" aria-labelledby="decision-title">
    @if ($reviewingApplicationId)
        @php
            $decisionApplication = $requestItem->applications->firstWhere('id', $reviewingApplicationId);
            $chosenCareHours = $careHourAccountId ? collect($careHourAccounts)->firstWhere('id', (int) $careHourAccountId) : null;
            $coveredCareMinutes = $chosenCareHours && $hireDecisionQuote ? min($hireDecisionQuote['minutes'], $chosenCareHours['available_minutes']) : 0;
            $uncoveredCareMinutes = $chosenCareHours && $hireDecisionQuote ? max(0, $hireDecisionQuote['minutes'] - $coveredCareMinutes) : 0;
            $uncoveredCareCents = $chosenCareHours ? app(\App\Services\CareHours\PrepaidBookingFundingService::class)->amountForMinutes(new \App\Models\CareBooking, $uncoveredCareMinutes) : 0;
        @endphp
        <div class="hc-hire-review">
            <div class="hc-hire-heading">
                <p class="hc-care-eyebrow">Get started · Review before hiring</p>
                <h2 id="decision-title">Care with {{ $decisionApplication?->caregiver?->name }}</h2>
                <p>One last look at the care, schedule, and price for {{ $recordRecipientName }}.</p>
            </div>
            <div class="hc-hire-grid">
                <div class="hc-hire-terms">
                    <dl>
                        <div><dt>Care</dt><dd>{{ $plainRequestType }} for {{ $recordRecipientName }}</dd></div>
                        <div><dt>Schedule</dt><dd>{{ $decisionApplication?->offered_schedule ? \App\Support\WeeklySchedule::label($decisionApplication->offered_schedule['slots']) : $recruitmentSchedule }}</dd></div>
                        @if ($requestItem->request_type === 'recurring' && $hireDecisionStart && $hireDecisionEnd)
                            <div><dt>First visit</dt><dd>{{ $hireDecisionStart->format('D, M j, Y, g:i A') }}–{{ $hireDecisionEnd->format($hireDecisionStart->isSameDay($hireDecisionEnd) ? 'g:i A T' : 'D, M j, g:i A T') }}</dd></div>
                        @endif
                        <div><dt>Location</dt><dd>{{ $serviceAddress }}</dd></div>
                    </dl>
                    <div><h3>Agreed care</h3><p class="whitespace-pre-line">{{ $requestItem->scope_of_work }}</p><p>{{ $requestItem->tasks->pluck('name')->join(' · ') }}</p><p>{{ $requestItem->time_expectations }}</p></div>
                    @if($requestItem->request_type === 'recurring')
                        <div class="hc-recruit-notice"><strong>One arrangement, individual visits.</strong><p>Your recurring care home keeps the weekly schedule and caregiver together. Open any booked visit there to see its hours, updates, and payment.</p></div>
                    @endif
                </div>
                @if($hireDecisionQuote)
                    @if($chosenCareHours)
                    <aside class="hc-hire-price" aria-label="Care hours coverage">
                        <h3>{{ $requestItem->request_type === 'recurring' ? 'First visit coverage' : 'Visit coverage' }}</h3>
                        <dl>
                            <div><dt>Scheduled care</dt><dd>{{ \App\Models\CareHourAccount::formatMinutes($hireDecisionQuote['minutes']) }}</dd></div>
                            <div><dt>From care hours</dt><dd>{{ \App\Models\CareHourAccount::formatMinutes($coveredCareMinutes) }}</dd></div>
                            <div class="hc-hire-total"><dt>Remaining payment</dt><dd>${{ number_format($uncoveredCareCents / 100, 2) }}</dd></div>
                        </dl>
                        <p>{{ $uncoveredCareMinutes > 0 ? \App\Models\CareHourAccount::formatMinutes($uncoveredCareMinutes).' needs a separate payment before the visit is ready.' : 'Nothing to pay for scheduled care.' }}</p>
                    </aside>
                    @else
                    <aside class="hc-hire-price" aria-label="Care price estimate">
                        <h3>{{ $requestItem->request_type === 'recurring' ? 'First visit estimate' : 'Visit estimate' }}</h3>
                        <p>{{ intdiv($hireDecisionQuote['minutes'], 60) }}h {{ $hireDecisionQuote['minutes'] % 60 ? ($hireDecisionQuote['minutes'] % 60).'m' : '' }} scheduled · {{ $hireDecisionQuote['currency'] }}</p>
                        <dl>
                            <div><dt>Care · ${{ number_format($hireDecisionQuote['rate_cents'] / 100, 2) }}/hr</dt><dd>${{ number_format($hireDecisionQuote['care_cents'] / 100, 2) }}</dd></div>
                            <div><dt>{{ $hireDecisionQuote['fee_rate_cents'] !== null ? 'Processing' : 'Platform fee' }}@if($hireDecisionQuote['fee_rate_cents'] !== null) · ${{ number_format($hireDecisionQuote['fee_rate_cents'] / 100, 2) }}/hr
                                @endif
                            </dt><dd>${{ number_format($hireDecisionQuote['fee_cents'] / 100, 2) }}</dd></div>
                            <div class="hc-hire-total"><dt>Estimated total</dt><dd>${{ number_format($hireDecisionQuote['total_cents'] / 100, 2) }}</dd></div>
                        </dl>
                        <p>Based on scheduled hours and the pricing that applies to this caregiver. Final charges depend on approved hours.</p>
                    </aside>
                    @endif
                @endif
            </div>
            <div class="hc-hire-actions">
                @error('hire') <div role="alert"><x-alert color="red">{{ $message }}</x-alert></div> @enderror
                @if(!empty($careHourAccounts))
                    <fieldset class="space-y-2 rounded-xl border border-[#D8E1D7] p-4"><legend class="px-1 text-sm font-semibold">Payment for this visit</legend>
                        @foreach($careHourAccounts as $hoursAccount)
                            <label class="flex items-center gap-2 text-sm"><input type="radio" name="care-hour-funding" wire:click="$set('careHourAccountId', {{ $hoursAccount['id'] }})" value="{{ $hoursAccount['id'] }}" @checked((int) $careHourAccountId === (int) $hoursAccount['id'])>Use care hours · {{ \App\Models\CareHourAccount::formatMinutes($hoursAccount['available_minutes']) }} in your family balance</label>
                        @endforeach
                        <label class="flex items-center gap-2 text-sm"><input type="radio" name="care-hour-funding" wire:click="$set('careHourAccountId', null)" value="card" @checked($careHourAccountId === null)>Usual card payment</label>
                    @error('careHourAccountId')<p class="text-sm text-rose-800" role="alert">{{ $message }}</p>@enderror</fieldset>
                @endif
                <p>Confirming selects this caregiver, saves the care agreement and {{ $careHourAccountId ? 'reserves care hours' : 'prepares card authorization' }}. {{ $decisionApplication?->selected_days !== null ? 'Only their selected days will be assigned. Your request stays open for the remaining days; other applications stay available.' : 'Other open applications will be marked not selected.' }} {{ $requestItem->request_type === 'recurring' ? 'Your care schedule will show booked visits, missing care and any payment action needed.' : 'Your visit will show any payment action needed.' }}</p>
                @if(! $hirePayment['ready'] && $decisionApplication)
                    <x-family-hire-payment-prompt :care-request="$requestItem" :application="$decisionApplication" :unavailable="$hirePayment['unavailable']" id="hire-review-payment" />
                @endif
                <div class="hc-candidate-actions">
                    <button type="button" wire:click="confirmReviewedHire" wire:loading.attr="disabled" @disabled(! $hirePayment['ready'])
                        @if(! $hirePayment['ready']) aria-describedby="hire-review-payment" @endif
                        class="{{ $hirePayment['ready'] ? 'hc-primary-button' : 'hc-secondary-button !border-slate-200 !bg-slate-100 !text-slate-400 !shadow-none cursor-not-allowed' }}">Confirm hire</button>
                    <button type="button" wire:click="closeDecisionReview" class="hc-secondary-button">Back to caregivers</button>
                </div>
            </div>
        </div>
    @elseif ($reviewingCompletion)
        <p class="hc-care-eyebrow">{{ $isLiveVisit ? 'Finish this visit' : 'Review this visit’s hours' }}</p>
        <h2 id="decision-title" class="mt-1 text-2xl font-semibold">{{ $recordHeadline }}</h2>
        <p class="hc-care-subtitle mt-2">{{ $booking?->caregiver?->name ?: $hiredCaregiverName }} · {{ $plainSchedule }}</p>
        @if ($isLiveVisit)
            <p class="mt-5">Confirm that care has ended. This records the end of this visit; its hours can then be reviewed.</p>
        @else
            <div data-ai-target="family.request.timesheet" class="mt-5 grid gap-4 sm:grid-cols-2">
                <div class="hc-care-soft p-4"><p class="hc-care-meta">Scheduled</p><p class="mt-1 font-semibold">{{ $booking?->expected_minutes ?? '—' }} minutes</p><p class="mt-3 hc-care-meta">Submitted</p><p class="mt-1 text-2xl font-semibold">{{ $workedLabel }}</p><p class="mt-2 text-sm">Check-in {{ $booking?->started_at?->format('M j, g:i A') ?: 'Not recorded' }}<br>Check-out {{ $booking?->completed_at?->format('M j, g:i A') ?: 'Not recorded' }}</p></div>
                @if($prepaidBookingSummary)
                    @php($careApproval = $prepaidBookingSummary['approval_preview'])
                    <dl class="space-y-3 rounded-2xl border border-hc-border p-4"><div class="flex justify-between gap-3"><dt>Care hours to use</dt><dd class="font-semibold">{{ \App\Models\CareHourAccount::formatMinutes($careApproval['prepaid_minutes']) }}</dd></div>@if($careApproval['restore_minutes'] > 0)<div class="flex justify-between gap-3"><dt>Hours to return</dt><dd>{{ \App\Models\CareHourAccount::formatMinutes($careApproval['restore_minutes']) }}</dd></div>@endif<div class="flex justify-between gap-3 border-t border-hc-border pt-3"><dt>Separate payment for uncovered time</dt><dd class="font-semibold">${{ number_format($careApproval['card_cents'] / 100, 2) }}</dd></div><p class="text-sm">Approving confirms these hours. Any remaining payment action will appear on this visit.</p></dl>
                @else
                <dl class="space-y-3 rounded-2xl border border-hc-border p-4">
                    <div class="flex justify-between gap-3"><dt>Care · ${{ number_format($shiftRate, 2) }}/hour</dt><dd>${{ number_format($shiftEarnings, 2) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt>{{ $usesPricingV2 ? 'Processing fee' : 'Platform fee' }}</dt><dd>${{ number_format($estimatedPaymentTotal - $shiftEarnings, 2) }}</dd></div>
                    <div class="flex justify-between gap-3 border-t border-hc-border pt-3 text-lg font-semibold"><dt>Estimated total</dt><dd>${{ number_format($estimatedPaymentTotal, 2) }}</dd></div>
                    <p class="text-sm">Your approval starts payment processing. The visit will show the actual payment result.</p>
                </dl>
                @endif
            </div>
            <label class="mt-5 block text-sm font-semibold" for="care-confirmation-note">Approval note (optional)</label>
            <textarea id="care-confirmation-note" wire:model="confirmationNote" rows="3" class="mt-2 w-full rounded-xl border-hc-border"></textarea>
            @error('confirmationNote')<p class="mt-2 text-sm text-red-800">{{ $message }}</p>@enderror
        @endif
        <div class="mt-5 flex flex-col gap-2 sm:flex-row"><button type="button" wire:click="confirmReviewedCompletion" wire:loading.attr="disabled" class="hc-primary-button">{{ $isLiveVisit ? 'Confirm visit has ended' : ($prepaidBookingSummary ? 'Approve hours' : 'Approve hours and pay $'.number_format($estimatedPaymentTotal, 2)) }}</button><button type="button" wire:click="closeDecisionReview" class="hc-secondary-button">Back to visit</button>@if (! $isLiveVisit)<button type="button" wire:click="setActiveTab('support')" class="hc-care-text-link">Question these hours</button>@endif</div>
    @endif
</section>
@endif
