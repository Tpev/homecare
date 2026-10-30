<div class="hc-create-checkout">
    <h2 id="care-review-heading" class="sr-only">Publish your request</h2>

    @if ($errors->any())
        <div class="hc-create-publish-errors" role="alert">
            <p class="font-semibold">A few details need your attention before publishing.</p>
            <ul class="mt-2 list-disc space-y-1 pl-4">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
            <p class="mt-2">Check the highlighted fields above. Your details are still here.</p>
        </div>
    @endif

    <label for="private-care-request" class="hc-create-privacy-choice">
        <input id="private-care-request" type="checkbox" wire:model.live="is_private" class="mt-1 rounded border-[#748176] text-[#23483F] focus:ring-[#23483F]">
        <span>
            <span class="block font-semibold">Make this request private</span>
            <span class="mt-1 block">{{ $is_private ? 'Only caregivers you invite can view and apply. Hidden from the caregiver marketplace.' : 'Otherwise, eligible caregivers can find and apply. You can also invite specific caregivers.' }}</span>
        </span>
    </label>

    @if($careHoursAccount)
        <fieldset class="mt-4 space-y-2 rounded-xl border border-[#D8E1D7] p-4 text-sm">
            <legend class="px-1 font-semibold">Payment when you book</legend>
            <label class="flex items-start gap-2">
                <input type="radio" name="request-care-hour-funding" wire:model.live="careHourAccountId" value="{{ $careHoursAccount->id }}" @checked((int) $careHourAccountId === (int) $careHoursAccount->id) class="mt-1">
                <span>Use care hours first · {{ \App\Models\CareHourAccount::formatMinutes($careHoursAccount->balance()['available_minutes']) }} in your family balance</span>
            </label>
            <label class="flex items-center gap-2"><input type="radio" name="request-care-hour-funding" wire:click="$set('careHourAccountId', null)" value="card" @checked($careHourAccountId === null)>Use card instead</label>
            <p class="text-xs text-[#485E53]">Posting is free. Review any uncovered amount before booking.</p>
            @error('careHourAccountId')<p class="text-rose-800" role="alert">{{ $message }}</p>@enderror
        </fieldset>
    @endif

    <div class="hc-create-checkout-bar">
        <div class="hc-create-estimate">
            @if($careHoursAccount && (int) $careHourAccountId === (int) $careHoursAccount->id)
                @php
                    $estimateMinutes = $this->estimatedHours === null ? null : (int) round($this->estimatedHours * 60);
                    $coveredMinutes = $estimateMinutes === null ? 0 : min($estimateMinutes, $careHoursAccount->balance()['available_minutes']);
                    $uncoveredMinutes = $estimateMinutes === null ? 0 : max(0, $estimateMinutes - $coveredMinutes);
                    $uncoveredTotal = $uncoveredMinutes * $this->standardHourlyTotal / 60;
                @endphp
                <p>{{ $request_type === \App\Models\CareRequest::TYPE_RECURRING ? 'Estimated first week' : 'Estimated payment' }}</p>
                @if($estimateMinutes !== null)
                    <p class="hc-create-estimate-total">{{ \App\Models\CareHourAccount::formatMinutes($coveredMinutes) }}<span>care hours{{ $uncoveredMinutes > 0 ? ' + $'.number_format($uncoveredTotal, 2) : '' }}</span></p>
                    <p class="hc-create-estimate-note">{{ $uncoveredMinutes > 0 ? 'You will review the remaining payment when booking.' : 'Covered by your current care hours.' }}{{ $request_type === \App\Models\CareRequest::TYPE_RECURRING ? ' Your balance is shared across visits.' : '' }}</p>
                @else
                    <p class="hc-create-estimate-note">Add the schedule to see how many hours will be covered.</p>
                @endif
                @if($this->estimateAgreement)
                    <p class="hc-create-estimate-note">Your custom-priced caregiver uses card payment.</p>
                @endif
            @else
            <p>{{ ($request_type === \App\Models\CareRequest::TYPE_RECURRING ? 'Estimated recurring care each week' : 'Estimated one-time cost').($this->estimateAgreement && ! $this->estimateAgreement->all_caregivers ? ' with '.$this->estimateAgreement->caregiver->name : '') }}</p>
            @if ($this->estimateAgreement?->all_caregivers)
                <p class="hc-create-estimate-note">Your agreed total rate: ${{ number_format($this->estimateHourlyRate + $this->processingFeeHourlyRate, 2) }}/hour with any caregiver.</p>
            @elseif ($this->estimateAgreement)
                <p class="hc-create-estimate-note">Your agreed rate with {{ $this->estimateAgreement->caregiver->name }}: ${{ number_format($this->estimateHourlyRate + $this->processingFeeHourlyRate, 2) }}/hour. This estimate applies when you hire {{ $this->estimateAgreement->caregiver->name }}.</p>
                <p class="hc-create-estimate-note">Standard rate for other caregivers: ${{ number_format($this->standardHourlyTotal, 2) }}/hour, including the processing fee.</p>
            @endif
            @if ($this->estimatedTotal !== null && $this->estimatedHours !== null)
                <p class="hc-create-estimate-total">${{ number_format($this->estimatedTotal, 2) }}<span>{{ $request_type === \App\Models\CareRequest::TYPE_RECURRING ? '/ week' : 'total' }}</span></p>
                <p class="hc-create-estimate-note">{{ $this->processingFeeHourlyRate > 0 ? 'Includes the processing fee.' : 'No additional processing fee.' }}{{ $request_type === \App\Models\CareRequest::TYPE_RECURRING ? ' Each visit is charged separately.' : '' }}</p>
                <details class="hc-create-cost-details">
                    <summary>Cost breakdown</summary>
                    <div class="hc-create-cost-breakdown">
                        <dl>
                            <div><dt>Care · {{ number_format($this->estimatedHours, 2) }}h × ${{ number_format($this->estimateHourlyRate, 2) }}/hr</dt><dd>${{ number_format($this->estimatedCost, 2) }}</dd></div>
                            <div><dt>Processing fee · ${{ number_format($this->processingFeeHourlyRate, 2) }}/hr</dt><dd>${{ number_format($this->estimatedProcessingFee, 2) }}</dd></div>
                        </dl>
                        @if ($this->processingFeeHourlyRate > 0)
                            <p>A ${{ number_format($this->processingFeeHourlyRate, 2) }}/hour processing fee is added to the ${{ number_format($this->estimateHourlyRate, 2) }}/hour care rate.</p>
                        @endif
                        @if ($request_type === \App\Models\CareRequest::TYPE_RECURRING)
                            <p>You are not paying for every future visit now. LoLo confirms your card before each visit and charges the final amount after that visit.</p>
                        @endif
                    </div>
                </details>
            @else
                <p class="hc-create-estimate-note">Add the schedule to see the estimate.</p>
            @endif
            @endif
        </div>

        <div class="hc-create-publish">
            <button type="submit" class="hc-primary-button" wire:loading.attr="disabled" wire:target="publish">
                <span wire:loading.remove wire:target="publish">{{ $is_private ? 'Create private request' : 'Publish request' }}</span>
                <span wire:loading wire:target="publish">Creating your request…</span>
            </button>
            <a href="{{ route('family.requests.index') }}" wire:navigate class="hc-create-cancel">Cancel</a>
            <p>{{ $is_private ? 'Next: invite caregivers to get replies.' : 'Next: find caregivers and review applicants.' }}</p>
        </div>
    </div>
</div>
