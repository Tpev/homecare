@extends('layouts.care-hours-public')
@section('content')
    <h1 class="font-display text-3xl font-semibold">Open your gift receipt</h1>
    <p class="text-[#485E53]">Verify the email used to buy this gift.</p>
    @include('care-hours.partials.messages')
    @if($verificationPending)
        <form action="{{ route('care-hours.gifts.receipt.confirm', $purchaseId) }}" method="POST" class="hc-surface space-y-4 p-5">
            @csrf<label for="gift-receipt-code" class="block font-semibold">Your six-digit code</label>
            <input id="gift-receipt-code" name="code" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" class="min-h-12 w-full rounded-xl border-[#D8E1D7]">
            <button type="submit" class="hc-primary-button">View gift receipt</button>
        </form>
    @endif
    <form action="{{ route('care-hours.gifts.receipt.verify', $purchaseId) }}" method="POST" class="hc-surface space-y-4 p-5">
        @csrf<label for="gift-receipt-email" class="block font-semibold">Your email</label>
        <input id="gift-receipt-email" name="email" type="email" required maxlength="254" autocomplete="email" class="min-h-12 w-full rounded-xl border-[#D8E1D7]">
        <button type="submit" class="hc-primary-button">Send verification code</button>
    </form>
@endsection
