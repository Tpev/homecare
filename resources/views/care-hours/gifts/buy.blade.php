@extends('layouts.care-hours-public')
@section('content')
    <header>
        <p class="text-sm font-semibold text-[#485E53]">A gift of care</p>
        <h1 class="mt-2 font-display text-3xl font-semibold">Give hours. Let them choose when.</h1>
        <p class="mt-3 text-[#485E53]">Buy a package and share its gift code. They can create their free LoLo account when they’re ready to claim.</p>
    </header>
    @include('care-hours.partials.messages')
    @if($buyer)
        <p class="text-sm text-[#485E53]">Receipt for {{ $buyer['email'] }} · $31 per paid hour.</p>
        <form action="{{ route('care-hours.gifts.checkout') }}" method="POST" class="space-y-5">
            @csrf
            <input type="hidden" name="request_key" value="{{ $requestKey }}">
            <fieldset>
                <legend class="sr-only">Choose a gift package</legend>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach($packages as $package)
                        <input type="hidden" name="quotes[{{ $package['paid_hours'] }}]" value="{{ $package['quote_key'] }}">
                        <label class="hc-surface flex cursor-pointer items-start gap-3 p-5 has-[:checked]:border-emerald-800 has-[:checked]:ring-1 has-[:checked]:ring-emerald-800">
                            <input type="radio" name="hours" value="{{ $package['paid_hours'] }}" required @checked((int) old('hours') === $package['paid_hours']) class="mt-1 border-[#D8E1D7] text-emerald-800">
                            <span><span class="block font-display text-2xl font-semibold">{{ $package['paid_hours'] }} care hours</span><span class="mt-2 block text-xl font-semibold">${{ number_format($package['amount_cents'] / 100, 0) }}</span></span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            @if(collect($packages)->contains(fn ($package) => ($package['conditional_bonus_minutes'] ?? 0) > 0))
                <p class="text-sm text-[#485E53]">An eligible recipient also gets 1, 2 or 4 welcome hours with a 10-, 20- or 40-hour gift. We confirm their bonus when they claim. The 5-hour gift has no bonus.</p>
            @endif
            <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="terms" value="1" required class="mt-1 rounded border-[#D8E1D7]"><span>I agree this gift is non-refundable, has no expiry and can be claimed once for one person.</span></label>
            <button class="hc-primary-button w-full sm:w-auto" type="submit">Continue to payment</button>
        </form>
    @else
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4" aria-label="Gift packages">
            @foreach($packages as $package)<div class="hc-surface p-4"><p class="font-semibold">{{ $package['paid_hours'] }} hours</p><p class="mt-2">${{ number_format($package['amount_cents'] / 100, 0) }}</p></div>@endforeach
        </div>
        @if($verificationPending)
            <form action="{{ route('care-hours.gifts.confirm') }}" method="POST" class="hc-surface space-y-4 p-5">
                @csrf<h2 class="font-display text-xl font-semibold">Check your email</h2>
                <label for="gift-verification-code" class="block text-sm font-semibold">Your six-digit code</label>
                <input id="gift-verification-code" name="code" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" class="min-h-12 w-full rounded-xl border-[#D8E1D7]">
                <button type="submit" class="hc-primary-button">Verify email</button>
            </form>
        @endif
        <form action="{{ route('care-hours.gifts.verify') }}" method="POST" class="hc-surface space-y-4 p-5">
            @csrf<h2 class="font-display text-xl font-semibold">{{ $verificationPending ? 'Send another code' : 'Where should we send your receipt?' }}</h2>
            <div><label for="gift-buyer-name" class="block text-sm font-semibold">Your name</label><input id="gift-buyer-name" name="name" value="{{ old('name') }}" required maxlength="100" autocomplete="name" class="mt-2 min-h-12 w-full rounded-xl border-[#D8E1D7]"></div>
            <div><label for="gift-buyer-email" class="block text-sm font-semibold">Your email</label><input id="gift-buyer-email" name="email" value="{{ old('email') }}" type="email" required maxlength="254" autocomplete="email" class="mt-2 min-h-12 w-full rounded-xl border-[#D8E1D7]"></div>
            <button type="submit" class="hc-primary-button">Send verification code</button><p class="text-sm text-[#485E53]">No LoLo account needed to buy.</p>
        </form>
    @endif
    <p class="text-sm text-[#485E53]">No expiry. No extra hourly platform fee. Care hours cover standard individual visits. Share your code privately: whoever claims it first chooses the recipient.</p>
    <a href="{{ route('care-hours.claim') }}" class="inline-flex min-h-11 items-center text-sm font-semibold underline">Already have a gift code?</a>
@endsection
