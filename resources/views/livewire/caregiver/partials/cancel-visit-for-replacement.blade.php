@if ($booking->status === \App\Models\CareBooking::STATUS_SCHEDULED
    && $requestItem->request_type === \App\Models\CareRequest::TYPE_ONE_TIME
    && ! $booking->care_plan_id && ! $booking->started_at)
    <details class="rounded-xl border border-[#D6CCBE] bg-white p-4 text-[#17313F]" @if ($errors->has('cancellationReason')) open @endif>
        <summary class="cursor-pointer font-semibold">I can't make it</summary>
        <div class="mt-3 space-y-3">
            <p class="text-sm">Cancel your visit so the family can hire another caregiver on this request. The cancellation takes effect immediately.</p>
            <x-textarea label="Why can't you make it?" wire:model="cancellationReason" />
            @error('cancellationReason') <p role="alert" class="text-sm text-red-700">{{ $message }}</p> @enderror
            <x-button color="red" wire:click="cancelBookingForReplacement" wire:loading.attr="disabled" wire:target="cancelBookingForReplacement"
                wire:confirm="Cancel your visit? The family will be able to hire another caregiver.">
                Cancel my visit
            </x-button>
        </div>
    </details>
@endif
