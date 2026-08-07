@php
    $message = trim((string) (($exception ?? null)?->getMessage() ?? ''));
    $accessEnded = str_contains(strtolower($message), 'access to this family account has ended');
    $heading = $accessEnded ? 'Your access to this family account has ended.' : 'You do not have access to this page.';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Access unavailable | LoLo Care</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#FFF9EF] text-[#17313F] antialiased">
    <main class="flex min-h-screen items-center justify-center p-5">
        <section class="w-full max-w-lg rounded-3xl border border-[#E3D6C5] bg-white p-7 text-center shadow-xl sm:p-10" aria-labelledby="access-heading">
            <img src="{{ asset('images/marketing/lolo/lolo-wordmark-evergreen.svg') }}" alt="LoLo Care" class="mx-auto h-9 w-auto">
            <p class="mt-8 text-xs font-bold uppercase tracking-[0.18em] text-[#B95745]">Family access</p>
            <h1 id="access-heading" class="mt-2 font-display text-3xl font-semibold leading-tight" tabindex="-1">{{ $heading }}</h1>
            <p class="mt-4 text-sm leading-6 text-[#5D6D67]">
                {{ $accessEnded
                    ? 'You can no longer see this family’s care, visits, messages, or billing history. The account owner can invite you again if access is needed.'
                    : 'Return to your account or contact LoLo Support if you believe this is a mistake.' }}
            </p>
            <div class="mt-7 grid gap-3 sm:grid-cols-2">
                <a href="{{ route('landing') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-[#D7CEC2] px-4 font-semibold text-[#526474]">LoLo Care home</a>
                @auth
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="min-h-11 w-full rounded-xl bg-[#173F35] px-4 font-semibold text-white">Sign out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#173F35] px-4 font-semibold text-white">Sign in</a>
                @endauth
            </div>
        </section>
    </main>
</body>
</html>
