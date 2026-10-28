@extends('layouts.care-hours-public')
@section('content')
    <h1 class="font-display text-3xl font-semibold">View your receipt</h1>
    <p class="text-[#485E53]">{{ $canVerifyEmail ? 'Verify the email used for this purchase.' : 'Sign in with the LoLo account used for this purchase.' }}</p>
    @include('care-hours.partials.messages')
    @if($canVerifyEmail)
    @if($verificationPending)<form action="{{ route('care-hours.receipt.confirm', $purchaseId) }}" method="POST" class="hc-surface space-y-4 p-5">@csrf<label for="receipt-code" class="block font-semibold">Your six-digit code</label><input name="code" id="receipt-code" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" class="min-h-12 w-full rounded-xl border-[#D8E1D7]"><button class="hc-primary-button" type="submit">View receipt</button></form>@endif
    <form action="{{ route('care-hours.receipt.verify', $purchaseId) }}" method="POST" class="hc-surface space-y-4 p-5">@csrf<label for="receipt-email" class="block font-semibold">Your email</label><input name="email" id="receipt-email" type="email" required maxlength="254" autocomplete="email" class="min-h-12 w-full rounded-xl border-[#D8E1D7]"><button class="hc-primary-button" type="submit">Send verification code</button></form>
    @else
        <a href="{{ route('family.care-hours.purchase', $purchaseId) }}" class="hc-primary-button">Sign in to view receipt</a>
    @endif
@endsection
