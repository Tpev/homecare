<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Home Care HUB') }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Source+Serif+4:wght@500;600;700&display=swap" rel="stylesheet">

        <!-- Google tag (gtag.js) -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=AW-11325109038"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', 'AW-11325109038');
        </script>

        <tallstackui:script />

        @livewireStyles
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    @php
        $compactFooter = request()->routeIs('messages.*')
            || request()->routeIs('care-requests.apply')
            || request()->routeIs('family.requests.show');
        $shouldTrackFamilySignupConversion = session('google_ads_family_signup_conversion')
            && auth()->check()
            && auth()->user()?->role === 'family';
    @endphp

    <body class="overflow-x-hidden antialiased text-[#17313F]">
        <x-toast />

        <div class="hc-app-shell min-h-screen flex flex-col">
            <livewire:layout.navigation />

            @if (isset($header))
                <header class="border-b border-[#DED6CA]/80 bg-[rgba(255,253,250,0.92)] backdrop-blur">
                    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endif

            <main class="flex-1">
                {{ $slot }}
            </main>

            <footer class="border-t border-[#DED6CA]/80 bg-[rgba(255,253,250,0.96)] backdrop-blur">
                <div class="mx-auto max-w-7xl px-4 py-3 sm:px-6 lg:px-8">
                    <div class="sm:hidden">
                        @if ($compactFooter)
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-[11px] text-[#6E746F]">(c) {{ now()->year }} Home Care HUB</p>
                                <details class="group">
                                    <summary class="list-none rounded-full border border-[#DED6CA] px-3 py-1.5 text-[11px] font-medium text-[#0F3D3E]">
                                        Legal links
                                    </summary>
                                    <div class="mt-2 rounded-2xl border border-[#DED6CA] bg-[rgba(255,253,250,0.98)] p-3 shadow-sm">
                                        <x-legal-links class="flex-col items-start gap-2 text-[11px]" />
                                    </div>
                                </details>
                            </div>
                        @else
                            <div class="space-y-2">
                                <p class="text-[11px] text-[#6E746F]">(c) {{ now()->year }} Home Care HUB / HUB Healthcare, LLC</p>
                                <x-legal-links class="flex-col items-start gap-x-3 gap-y-2 text-[11px]" />
                            </div>
                        @endif
                    </div>

                    <div class="hidden items-center justify-between gap-4 sm:flex">
                        <p class="text-xs text-[#6E746F]">(c) {{ now()->year }} Home Care HUB / HUB Healthcare, LLC</p>
                        <x-legal-links />
                    </div>
                </div>
            </footer>
        </div>

        @livewireScripts

        @if ($shouldTrackFamilySignupConversion)
            <script>
                gtag('event', 'conversion', {
                    'send_to': 'AW-11325109038/3SLjCLvtwpUcEK7mm2gq',
                    'value': 1.0,
                    'currency': 'USD',
                });
            </script>
        @endif
    </body>
</html>

