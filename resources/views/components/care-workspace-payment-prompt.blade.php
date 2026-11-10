@props(['careRequest', 'application', 'unavailable' => false, 'id', 'staffWorkspace' => false])

@if($staffWorkspace)
    <div id="{{ $id }}" class="rounded-2xl border border-[#D5E1D8] bg-[#F3F7F3] p-4 text-left" data-testid="staff-payment-prompt">
        <p class="text-sm font-semibold">{{ $unavailable ? 'Let’s check the family’s payment method' : 'No saved card for this family' }}</p>
        <p class="mt-1 text-sm">{{ $unavailable ? 'We couldn’t check the family’s saved card. Try again.' : 'This family needs a saved card or prepaid hours before you can confirm the hire.' }}</p>
        @if($unavailable)<button type="button" wire:click="$refresh" wire:loading.attr="disabled" class="hc-care-text-link mt-2">Check again</button>@endif
        <a class="hc-primary-button mt-3" href="{{ route('customer-care.customer', $careRequest->family_user_id) }}">Manage prepaid hours</a>
    </div>
@else
    <x-family-hire-payment-prompt :care-request="$careRequest" :application="$application" :unavailable="$unavailable" :id="$id" />
@endif
