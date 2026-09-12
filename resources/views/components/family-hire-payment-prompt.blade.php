@props(['careRequest', 'application', 'unavailable' => false, 'id'])

<div id="{{ $id }}" class="rounded-2xl border border-[#D5E1D8] bg-[#F3F7F3] p-4 text-left" data-testid="hire-payment-prompt">
    <div class="flex items-start gap-2">
        <svg class="mt-0.5 h-4 w-4 shrink-0 text-[#426755]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/><path d="M12 14v3"/></svg>
        <div>
            <p class="text-sm font-semibold text-[#234536]">{{ $unavailable ? 'Let’s check your payment method' : 'Add a card to hire' }}</p>
            <p class="mt-1 text-xs leading-5 text-[#50665A]">{{ $unavailable ? 'We couldn’t check your saved card. Try again or update it securely.' : 'Secure setup with Stripe. No charge now. We’ll bring you back to confirm your hire.' }}</p>
        </div>
    </div>
    <form method="POST" action="{{ route('family.billing.checkout') }}" class="mt-3">
        @csrf
        <input type="hidden" name="care_request_id" value="{{ $careRequest->id }}">
        <input type="hidden" name="application_id" value="{{ $application->id }}">
        <button type="submit" class="hc-primary-button w-full" data-testid="hire-payment-setup">{{ $unavailable ? 'Update card securely' : 'Add card securely' }}</button>
    </form>
    @if ($unavailable)
        <button type="button" wire:click="$refresh" wire:loading.attr="disabled" class="hc-care-text-link mt-2">Check again</button>
    @endif
</div>
