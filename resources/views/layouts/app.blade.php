<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'HomeCare') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=manrope:400,500,600,700,800|outfit:500,600,700&display=swap" rel="stylesheet" />

        {{-- TallStackUI --}}
        <tallstackui:script />

        @livewireStyles
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    @php
        $compactFooter = request()->routeIs('messages.*')
            || request()->routeIs('care-requests.apply')
            || request()->routeIs('family.requests.show');
    @endphp

    <body class="font-sans antialiased text-slate-900">
        {{-- TallStack Toasts --}}
        <x-toast />

        <div class="min-h-screen flex flex-col">
            <livewire:layout.navigation />

            <!-- Page Heading -->
            @if (isset($header))
                <header class="border-b border-slate-200/80 bg-white/90 backdrop-blur">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endif

            <!-- Page Content -->
            <main class="flex-1">
                {{ $slot }}
            </main>

            <footer class="border-t border-slate-200 bg-white/95 backdrop-blur">
                <div class="mx-auto max-w-7xl px-4 py-3 sm:px-6 lg:px-8">
                    <div class="sm:hidden">
                        @if ($compactFooter)
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-[11px] text-slate-500">© {{ now()->year }} HomeCare</p>
                                <details class="group">
                                    <summary class="list-none rounded-full border border-slate-200 px-3 py-1.5 text-[11px] font-medium text-slate-700">
                                        Legal links
                                    </summary>
                                    <div class="mt-2 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                                        <x-legal-links class="flex-col items-start gap-2 text-[11px]" />
                                    </div>
                                </details>
                            </div>
                        @else
                            <div class="space-y-2">
                                <p class="text-[11px] text-slate-500">© {{ now()->year }} HomeCare / HUB Healthcare, LLC</p>
                                <x-legal-links class="gap-x-3 gap-y-2 text-[11px]" />
                            </div>
                        @endif
                    </div>

                    <div class="hidden sm:flex sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                        <p class="text-xs text-slate-500">© {{ now()->year }} HomeCare / HUB Healthcare, LLC</p>
                        <x-legal-links />
                    </div>
                </div>
            </footer>
        </div>

        {{-- Livewire scripts MUST be present --}}
        @livewireScripts
    </body>
</html>
