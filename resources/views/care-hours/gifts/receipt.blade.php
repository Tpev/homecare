@extends('layouts.care-hours-public')
@section('content')
    @include('care-hours.partials.messages')
    @if($giftCode)
        <header><h1 class="font-display text-3xl font-semibold">Your gift is ready</h1><p class="mt-3 text-[#485E53]">{{ \App\Models\CareHourAccount::formatMinutes($purchase->paid_minutes) }} of care to share with someone you love.</p></header>
        <section class="hc-surface space-y-4 p-6" aria-label="Your gift code" x-data="{ copied: false }">
            <label for="paid-gift-code" class="block text-sm font-semibold">Gift code</label>
            <input id="paid-gift-code" readonly value="{{ $giftCode }}" class="min-h-14 w-full rounded-xl border-[#D8E1D7] bg-[#F7F3ED] p-3 text-center font-mono text-lg font-semibold tracking-wide" onclick="this.select()">
            <button type="button" class="hc-primary-button" x-on:click="navigator.clipboard.writeText($el.parentElement.querySelector('input').value).then(() => copied = true)" x-text="copied ? 'Copied' : 'Copy code'">Copy code</button>
            <p class="text-sm text-[#485E53]">Send this code privately with the link below. They can create an account when they claim it.</p>
            <a href="{{ route('care-hours.claim') }}" class="break-all text-sm font-semibold underline">{{ route('care-hours.claim') }}</a>
        </section>
    @elseif(in_array($purchase->status, ['held', 'recovery', 'corrected', 'correction_pending'], true))
        <header><h1 class="font-display text-3xl font-semibold">This gift needs a payment review</h1><p class="mt-3 text-[#485E53]">Contact LoLo with the purchase reference below.</p></header>
    @elseif($purchase->gift?->claimed_at)
        <header><h1 class="font-display text-3xl font-semibold">Your gift has been claimed</h1><p class="mt-3 text-[#485E53]">The code can no longer be used. Your purchase receipt stays available here.</p></header>
    @elseif(in_array($purchase->status, ['failed', 'expired'], true))
        <header><h1 class="font-display text-3xl font-semibold">This gift was not completed</h1><p class="mt-3 text-[#485E53]">No gift code was issued for this payment attempt.</p></header>
    @else
        <header><h1 class="font-display text-3xl font-semibold">Confirming your gift payment</h1><p class="mt-3 text-[#485E53]">Your code appears here once payment is confirmed. You can safely check again.</p></header>
        @if($purchase->status === 'pending' && ! $purchase->paid_at && $purchase->checkout_url)<a href="{{ $purchase->checkout_url }}" class="hc-primary-button">Continue payment</a>@endif
        <a href="{{ route('care-hours.gifts.receipt', $purchase->public_id) }}" class="hc-secondary-button">Check payment</a>
    @endif
    <section class="hc-surface p-5" aria-label="Gift purchase receipt">
        <h2 class="font-display text-xl font-semibold">Purchase receipt</h2>
        <dl class="mt-4 space-y-3 text-sm">
            <div class="flex flex-wrap justify-between gap-3"><dt>Gift hours</dt><dd class="font-semibold">{{ \App\Models\CareHourAccount::formatMinutes($purchase->paid_minutes) }}</dd></div>
            <div class="flex flex-wrap justify-between gap-3"><dt>Package price</dt><dd class="font-semibold">${{ number_format($purchase->amount_cents / 100, 2) }} USD</dd></div>
            <div class="space-y-1 border-t border-[#D8E1D7] pt-3"><dt>Reference</dt><dd class="break-all">{{ $purchase->public_id }}</dd></div>
        </dl>
    </section>
    <p class="text-sm text-[#485E53]">Non-refundable · No expiry. Any eligible welcome bonus is confirmed when the gift is claimed. Buying a gift does not give access to the recipient’s care account.</p>
@endsection
