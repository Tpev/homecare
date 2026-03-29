<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'HomeCare'))</title>
    <meta name="description" content="@yield('meta_description', 'HomeCare connects families and caregivers for non-medical home care in Raleigh, NC.')">
    <meta name="robots" content="@yield('robots', 'index,follow')">
    <link rel="canonical" href="@yield('canonical', request()->url())">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('app.name', 'HomeCare') }}">
    <meta property="og:title" content="@yield('title', config('app.name', 'HomeCare'))">
    <meta property="og:description" content="@yield('meta_description', 'HomeCare connects families and caregivers for non-medical home care in Raleigh, NC.')">
    <meta property="og:url" content="@yield('canonical', request()->url())">
    <meta property="og:image" content="@yield('og_image', 'https://images.unsplash.com/photo-1505751172876-fa1923c5c528?auto=format&fit=crop&w=1600&q=80')">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title', config('app.name', 'HomeCare'))">
    <meta name="twitter:description" content="@yield('meta_description', 'HomeCare connects families and caregivers for non-medical home care in Raleigh, NC.')">
    <meta name="twitter:image" content="@yield('og_image', 'https://images.unsplash.com/photo-1505751172876-fa1923c5c528?auto=format&fit=crop&w=1600&q=80')">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=manrope:400,500,600,700,800|outfit:500,600,700&display=swap" rel="stylesheet" />

    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=AW-11325109038"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'AW-11325109038');
    </script>

    {{-- TallStackUI --}}
    <tallstackui:script />

    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased text-slate-900">
    @yield('structured_data')
    <x-toast />

    @yield('content')

    <footer class="border-t border-slate-200 bg-white/95 backdrop-blur">
        <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
            <div class="sm:hidden space-y-2">
                <p class="text-[11px] text-slate-500">© {{ now()->year }} HomeCare / HUB Healthcare, LLC</p>
                <x-legal-links class="gap-x-3 gap-y-2 text-[11px]" />
            </div>
            <div class="hidden sm:flex sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                <p class="text-xs text-slate-500">© {{ now()->year }} HomeCare / HUB Healthcare, LLC</p>
                <x-legal-links />
            </div>
        </div>
    </footer>

    @livewireScripts
</body>
</html>
