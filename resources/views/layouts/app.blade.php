<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>LoLo</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Source+Serif+4:wght@500;600;700&display=swap" rel="stylesheet">

        <x-analytics.google-tag />

        <tallstackui:script />

        @livewireStyles
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    @php
        $compactFooter = request()->routeIs('messages.*')
            || request()->routeIs('care-requests.apply')
            || request()->routeIs('family.requests.show')
            || request()->routeIs('family.care.show');
        $shouldTrackFamilySignupConversion = session('google_ads_family_signup_conversion')
            && auth()->check()
            && auth()->user()?->role === 'family';
        $openAiSupportIncidentCount = auth()->user()?->isAdministrator()
            && \Illuminate\Support\Facades\Schema::hasTable('ai_support_incidents')
                ? \App\Models\AiSupportIncident::query()
                    ->where('status', \App\Models\AiSupportIncident::STATUS_OPEN)
                    ->where('severity', \App\Models\AiSupportIncident::SEVERITY_CRITICAL)
                    ->count()
                : 0;
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

            @if($openAiSupportIncidentCount > 0)
                <div class="border-b border-rose-300 bg-rose-700 text-white">
                    <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                        <strong>{{ $openAiSupportIncidentCount }} unresolved AI Support incident{{ $openAiSupportIncidentCount === 1 ? '' : 's' }}. Affected capabilities remain stopped.</strong>
                        <a href="{{ route('admin.ai-support.readiness') }}" class="font-semibold underline underline-offset-2">Review release readiness</a>
                    </div>
                </div>
            @endif

            <main class="flex-1">
                {{ $slot }}
            </main>

            <footer class="border-t border-[#DED6CA]/80 bg-[rgba(255,253,250,0.96)] backdrop-blur">
                <div class="mx-auto max-w-7xl px-4 py-3 sm:px-6 lg:px-8">
                    <div class="sm:hidden">
                        @if ($compactFooter)
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-[11px] text-[#6E746F]">&copy; {{ now()->year }} LoLo</p>
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
                                <p class="text-[11px] text-[#6E746F]">&copy; {{ now()->year }} LoLo Care Inc</p>
                                <x-legal-links class="flex-col items-start gap-x-3 gap-y-2 text-[11px]" />
                            </div>
                        @endif
                    </div>

                    <div class="hidden items-center justify-between gap-4 sm:flex">
                        <p class="text-xs text-[#6E746F]">&copy; {{ now()->year }} LoLo Care Inc</p>
                        <x-legal-links />
                    </div>
                </div>
            </footer>
        </div>

        @if (auth()->check() && in_array(auth()->user()?->role, ['family', 'caregiver'], true))
            <livewire:support.chat-widget
                :origin-route="request()->route()?->getName()"
                :origin-path="request()->getPathInfo()"
            />
        @endif

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

