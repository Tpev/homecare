<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'HomeCare') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=manrope:400,500,600,700,800|outfit:500,600,700&display=swap" rel="stylesheet" />

    <tallstackui:script />

    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased text-slate-900">
    <x-toast />

    <div class="min-h-screen grid lg:grid-cols-2">
        <aside class="hidden lg:flex relative bg-gradient-to-br from-[#08345e] via-[#0d5f8d] to-[#17b879] text-white p-12">
            <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_20%_20%,white_0,transparent_40%),radial-gradient(circle_at_80%_80%,white_0,transparent_35%)]"></div>
            <div class="relative max-w-md my-auto space-y-6">
                <a href="/" wire:navigate class="inline-flex items-center gap-3">
                    <x-application-logo class="w-10 h-10 fill-current text-white" />
                    <span class="text-2xl font-semibold tracking-tight">HomeCare</span>
                </a>
                <h1 class="text-4xl font-display font-semibold leading-tight">Find trusted non-medical care, faster.</h1>
                <p class="text-blue-100 leading-relaxed">Families connect directly with independent caregivers for companionship, daily support, and flexible scheduling.</p>
            </div>
        </aside>

        <main class="flex items-center justify-center p-4 md:p-8">
            <div class="w-full max-w-3xl">
                <div class="lg:hidden mb-6 text-center">
                    <a href="/" wire:navigate class="inline-flex items-center gap-2 text-slate-700">
                        <x-application-logo class="w-8 h-8 fill-current text-blue-700" />
                        <span class="text-xl font-semibold">HomeCare</span>
                    </a>
                </div>
                <div class="bg-white border border-slate-200 shadow-xl shadow-slate-200/60 rounded-2xl p-6 md:p-8">
                    {{ $slot }}
                </div>

                <div class="mt-4 rounded-xl border border-slate-200 bg-white/70 p-3">
                    <x-legal-links />
                </div>
            </div>
        </main>
    </div>

    @livewireScripts
</body>
</html>
