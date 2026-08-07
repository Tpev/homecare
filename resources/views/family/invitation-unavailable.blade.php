<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Family invitation · LoLo</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#F7F2EA] text-[#17313F] antialiased">
    <main class="mx-auto flex min-h-screen max-w-xl items-center px-4 py-12">
        <section class="w-full rounded-2xl border border-[#E3D6C5] bg-white p-7 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#B95745]">Family access</p>
            <h1 class="mt-3 font-display text-3xl font-semibold">Invitation unavailable</h1>
            <p class="mt-3 text-sm leading-6 text-[#68756F]">{{ $message }}</p>
            <a href="{{ route('login') }}" class="mt-6 inline-flex min-h-11 items-center justify-center rounded-xl bg-[#173F35] px-5 font-semibold text-white">Go to sign in</a>
        </section>
    </main>
</body>
</html>
