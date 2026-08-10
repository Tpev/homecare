<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'LoLo')</title>
    <meta name="description" content="@yield('meta_description', 'LoLo helps families arrange trusted, non-medical home care and companionship for an older adult at home.')">
    <meta name="robots" content="@yield('robots', 'index,follow')">
    <link rel="canonical" href="@yield('canonical', request()->url())">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="LoLo">
    <meta property="og:title" content="@yield('title', 'LoLo')">
    <meta property="og:description" content="@yield('meta_description', 'LoLo helps families arrange trusted, non-medical home care and companionship for an older adult at home.')">
    <meta property="og:url" content="@yield('canonical', request()->url())">
    <meta property="og:image" content="@yield('og_image', asset('images/marketing/flyer.png'))">
    <meta property="og:image:alt" content="@yield('og_image_alt', 'LoLo helps families find trusted support for an older parent at home.')">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title', 'LoLo')">
    <meta name="twitter:description" content="@yield('meta_description', 'LoLo helps families arrange trusted, non-medical home care and companionship for an older adult at home.')">
    <meta name="twitter:image" content="@yield('og_image', asset('images/marketing/flyer.png'))">
    <meta name="twitter:image:alt" content="@yield('og_image_alt', 'LoLo helps families find trusted support for an older parent at home.')">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Source+Serif+4:wght@500;600;700&display=swap" rel="stylesheet">

    @stack('head')

    <x-analytics.google-tag />

    <!-- Meta Pixel Code -->
    <script>
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', '1842558893085096');
        fbq('track', 'PageView');
    </script>
    <!-- End Meta Pixel Code -->

    {{-- TallStackUI --}}
    <tallstackui:script />

    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="antialiased text-slate-900 [font-family:'Inter',ui-sans-serif,system-ui,sans-serif]" style="background:#FFF7EA;">
    <noscript>
        <img height="1" width="1" style="display:none"
             src="https://www.facebook.com/tr?id=1842558893085096&ev=PageView&noscript=1"
             alt="">
    </noscript>
    @yield('structured_data')
    <x-toast />

    @yield('content')

    @if (! trim($__env->yieldContent('hide_default_footer')))
        <footer class="border-t border-[#DED6CA] bg-[#FFF7EA]/95 backdrop-blur">
            <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
                <div class="space-y-2 sm:hidden">
                    <p class="text-[11px] text-slate-500">&copy; {{ now()->year }} LoLo Care Inc</p>
                    <x-legal-links class="gap-x-3 gap-y-2 text-[11px]" />
                </div>
                <div class="hidden sm:flex sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                    <p class="text-xs text-slate-500">&copy; {{ now()->year }} LoLo Care Inc</p>
                    <x-legal-links />
                </div>
            </div>
        </footer>
    @endif

    @livewireScripts
</body>
</html>

