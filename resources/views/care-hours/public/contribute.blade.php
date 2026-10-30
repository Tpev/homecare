@extends('layouts.care-hours-public')
@section('content')
    <header><h1 class="font-display text-3xl font-semibold">Give prepaid care hours</h1>@if($link->display_name !== 'Family care balance')<p class="mt-2 font-semibold">For {{ $link->display_name }}</p>@endif<p class="mt-2 text-[#485E53]">Help with future care. Your purchase adds hours to their family’s shared care balance.</p></header>
    @include('care-hours.partials.messages')
    @if($buyer)
        <p class="text-sm text-[#485E53]">Receipt for {{ $buyer['email'] }} · $31 per paid hour, including the hourly platform fee.</p>
        @include('care-hours.partials.packages', ['checkoutUrl' => route('care-hours.contribute.checkout', $token)])
    @else
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4" aria-label="Available care packages">@foreach($packages as $package)<div class="rounded-2xl border border-[#D8E1D7] bg-white p-4"><p class="font-semibold">{{ $package['paid_hours'] }} hours</p>@if($package['bonus_minutes'])<p class="text-sm text-emerald-800">+ {{ \App\Models\CareHourAccount::formatMinutes($package['bonus_minutes']) }} free</p>@endif<p class="mt-2">${{ number_format($package['amount_cents'] / 100, 0) }}</p></div>@endforeach</div>
        @if($verificationPending)
            <form action="{{ route('care-hours.contribute.confirm', $token) }}" method="POST" class="hc-surface space-y-4 p-5">@csrf<h2 class="font-display text-xl font-semibold">Check your email</h2><label for="verification-code" class="block text-sm">Your six-digit code</label><input id="verification-code" name="code" required inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" maxlength="6" class="min-h-12 w-full rounded-xl border-[#D8E1D7]"><button class="hc-primary-button" type="submit">Verify email</button></form>
        @endif
        <form action="{{ route('care-hours.contribute.verify', $token) }}" method="POST" class="hc-surface space-y-4 p-5">@csrf<h2 class="font-display text-xl font-semibold">{{ $verificationPending ? 'Send another code' : 'Where should we send your receipt?' }}</h2><div><label class="block text-sm font-semibold" for="buyer-name">Your name</label><input id="buyer-name" name="name" value="{{ old('name') }}" required maxlength="100" autocomplete="name" class="mt-2 min-h-12 w-full rounded-xl border-[#D8E1D7]"></div><div><label class="block text-sm font-semibold" for="buyer-email">Your email</label><input id="buyer-email" name="email" value="{{ old('email') }}" type="email" required maxlength="254" autocomplete="email" class="mt-2 min-h-12 w-full rounded-xl border-[#D8E1D7]"></div><button type="submit" class="hc-primary-button">Send verification code</button><p class="text-sm text-[#485E53]">No LoLo account needed.</p></form>
    @endif
    <p class="text-sm text-[#485E53]">Your purchase adds to this family’s care balance. It does not give you access to their family account. Packages are non-refundable, and paid and free hours never expire.</p>
@endsection
