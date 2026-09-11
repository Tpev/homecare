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

    <div class="hc-create-checkout-bar">
        <div class="hc-create-estimate">
            <p>{{ $request_type === \App\Models\CareRequest::TYPE_RECURRING ? 'Estimated recurring care each week' : 'Estimated one-time cost' }}</p>
            @if ($this->estimatedTotal !== null && $this->estimatedHours !== null)
                <p class="hc-create-estimate-total">${{ number_format($this->estimatedTotal, 2) }}<span>{{ $request_type === \App\Models\CareRequest::TYPE_RECURRING ? '/ week' : 'total' }}</span></p>
                <p class="hc-create-estimate-note">Includes the processing fee.{{ $request_type === \App\Models\CareRequest::TYPE_RECURRING ? ' Each visit is charged separately.' : '' }}</p>
                <details class="hc-create-cost-details">
                    <summary>Cost breakdown</summary>
                    <div class="hc-create-cost-breakdown">
                        <dl>
                            <div><dt>Care · {{ number_format($this->estimatedHours, 2) }}h × ${{ number_format($this->estimateHourlyRate, 2) }}/hr</dt><dd>${{ number_format($this->estimatedCost, 2) }}</dd></div>
                            <div><dt>Processing fee · ${{ number_format($this->processingFeeHourlyRate, 2) }}/hr</dt><dd>${{ number_format($this->estimatedProcessingFee, 2) }}</dd></div>
                        </dl>
                        <p>A ${{ number_format($this->processingFeeHourlyRate, 2) }}/hour processing fee is added to the ${{ number_format($this->estimateHourlyRate, 2) }}/hour care rate.</p>
                        @if ($request_type === \App\Models\CareRequest::TYPE_RECURRING)
                            <p>You are not paying for every future visit now. LoLo confirms your card before each visit and charges the final amount after that visit.</p>
                        @endif
                    </div>
                </details>
            @else
                <p class="hc-create-estimate-note">Add the schedule to see the estimate.</p>
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
