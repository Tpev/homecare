@extends('layouts.care-hours-public')
@section('content')
    <header>
        <p class="text-sm font-semibold text-[#485E53]">A gift of care</p>
        <h1 class="mt-2 font-display text-3xl font-semibold">Give hours. Let them choose when.</h1>
        <p class="mt-3 text-[#485E53]">Buy a package and share its gift code. They can create their free LoLo account when they’re ready to claim.</p>
    </header>
    @include('care-hours.partials.messages')
    <form action="{{ route($buyer ? 'care-hours.gifts.checkout' : ($verificationPending ? 'care-hours.gifts.confirm' : 'care-hours.gifts.verify')) }}" method="POST" class="space-y-5">
        @csrf
        @if($buyer)
            <input type="hidden" name="request_key" value="{{ $requestKey }}">
        @endif
        <fieldset aria-describedby="gift-package-note">
            <legend class="mb-1 font-semibold">Choose your gift</legend>
            <p class="mb-4 text-sm text-[#485E53]">$31 per paid hour. Platform fee included.</p>
            <div class="hc-gift-packages">
                @foreach($packages as $package)
                    @if($buyer)
                        <input type="hidden" name="quotes[{{ $package['paid_hours'] }}]" value="{{ $package['quote_key'] }}">
                    @endif
                    <label class="hc-gift-package">
                        <span class="hc-gift-package-top"><span class="font-display hc-gift-package-hours">{{ $package['paid_hours'] }} hours</span><input type="radio" name="hours" value="{{ $package['paid_hours'] }}" required @checked((int) $selectedHours === $package['paid_hours']) aria-label="{{ $package['paid_hours'] }} care hours" class="border-[#D8E1D7] text-emerald-800"></span>
                        <span class="hc-gift-package-offer">
                            @if(($package['conditional_bonus_minutes'] ?? 0) > 0)
                                <span class="hc-gift-package-bonus">+ {{ $package['conditional_bonus_minutes'] / 60 }} free welcome {{ $package['conditional_bonus_minutes'] === 60 ? 'hour' : 'hours' }}</span>
                            @else
                                <span class="hc-gift-package-standard">{{ $package['paid_hours'] }} hours of care</span>
                            @endif
                        </span>
                        <span class="hc-gift-package-price">${{ number_format($package['amount_cents'] / 100, 0) }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>
        <p id="gift-package-note" class="text-sm text-[#485E53]">@if(collect($packages)->contains(fn ($package) => ($package['conditional_bonus_minutes'] ?? 0) > 0))The welcome bonus applies once per recipient, on their first 10-, 20- or 40-hour package. Confirmed when they claim. A 5-hour gift has no bonus and keeps them eligible.@else Paid hours never expire.@endif</p>
        @if($buyer)
            <p class="text-sm text-[#485E53]">Receipt for {{ $buyer['email'] }}</p>
            <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="terms" value="1" required class="mt-1 rounded border-[#D8E1D7]"><span>I agree this gift is non-refundable, has no expiry and can be claimed once for one person.</span></label>
            <button class="hc-primary-button w-full sm:w-auto" type="submit">Continue to payment</button>
        @elseif($verificationPending)
            <section class="hc-surface space-y-4 p-5">
                <h2 class="font-display text-xl font-semibold">Check your email</h2>
                <label for="gift-verification-code" class="block text-sm font-semibold">Your six-digit code</label>
                <input id="gift-verification-code" name="code" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" class="min-h-12 w-full rounded-xl border-[#D8E1D7]">
                <button type="submit" class="hc-primary-button">Verify email</button>
            </section>
        @endif
        @unless($buyer)
            @if($verificationPending)
                <details class="hc-surface p-5"><summary class="cursor-pointer text-sm font-semibold">Send another code or change email</summary><div class="mt-4 space-y-4">
            @else
                <section class="hc-surface space-y-4 p-5"><h2 class="font-display text-xl font-semibold">Where should we send your receipt?</h2>
            @endif
                <div><label for="gift-buyer-name" class="block text-sm font-semibold">Your name</label><input id="gift-buyer-name" name="name" value="{{ old('name', auth()->user()?->name) }}" @required(! $verificationPending) maxlength="100" autocomplete="name" class="mt-2 min-h-12 w-full rounded-xl border-[#D8E1D7]"></div>
                <div><label for="gift-buyer-email" class="block text-sm font-semibold">Your email</label><input id="gift-buyer-email" name="email" value="{{ old('email', auth()->user()?->email) }}" type="email" @required(! $verificationPending) maxlength="254" autocomplete="email" class="mt-2 min-h-12 w-full rounded-xl border-[#D8E1D7]"></div>
                <button type="submit" @if($verificationPending) formaction="{{ route('care-hours.gifts.verify') }}" formnovalidate @endif class="hc-primary-button">Send verification code</button>
                @unless($verificationPending)<p class="text-sm text-[#485E53]">Verify your email, then pay securely.</p>@endunless
            @if($verificationPending)</div></details>@else</section>@endif
        @endunless
    </form>
    <p class="text-sm text-[#485E53]">No expiry. No extra hourly platform fee. Care hours cover standard individual visits. Share your code privately: whoever claims it first chooses the recipient.</p>
    <a href="{{ route('care-hours.claim') }}" class="inline-flex min-h-11 items-center text-sm font-semibold underline">Already have a gift code?</a>
@endsection
