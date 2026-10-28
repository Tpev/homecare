@extends('layouts.care-hours-public')
@section('content')
    <div class="mx-auto max-w-lg space-y-6">
        <header>
            <p class="mb-3 text-sm font-semibold text-[#485E53]">A gift for you</p>
            <h1 class="font-display text-3xl font-semibold">Claim your gift of care</h1>
            <p class="mt-3 text-[#485E53]">Enter your code. Your hours will be here whenever you need care.</p>
        </header>
        @include('care-hours.partials.messages')
        @if(auth()->check() && auth()->user()->role !== 'family')
            <section class="hc-surface space-y-4 p-6">
                <p>Use a family account to claim this gift.</p>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="hc-primary-button" type="submit">Sign out</button></form>
            </section>
        @else
            <form method="POST" action="{{ route('care-hours.claim.start') }}" class="hc-surface space-y-5 p-6">
                @csrf
                <div><label for="gift-code" class="block font-semibold">Gift code</label>
                    <input id="gift-code" name="code" required maxlength="80" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="Enter your gift code" class="mt-2 min-h-12 w-full rounded-xl border-[#D8E1D7] font-mono">
                </div>
                <button type="submit" class="hc-primary-button w-full justify-center">Continue</button>
                @guest<p class="text-sm text-[#485E53]">Next, create your free account or sign in.</p>@endguest
            </form>
        @endif
        <p class="text-sm text-[#485E53]">No card needed to claim · Your hours never expire</p>
    </div>
@endsection
