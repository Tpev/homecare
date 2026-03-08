<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'HomeCare'))</title>
    <meta name="description" content="@yield('meta_description', 'HomeCare connects families and caregivers for non-medical home care in Raleigh, NC.')">
    <meta name="robots" content="index,follow">
    <link rel="canonical" href="@yield('canonical', request()->url())">

    <meta property="og:type" content="website">
    <meta property="og:title" content="@yield('title', config('app.name', 'HomeCare'))">
    <meta property="og:description" content="@yield('meta_description', 'HomeCare connects families and caregivers for non-medical home care in Raleigh, NC.')">
    <meta property="og:url" content="@yield('canonical', request()->url())">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=manrope:400,500,600,700,800|outfit:500,600,700&display=swap" rel="stylesheet" />

    {{-- TallStackUI --}}
    <tallstackui:script />

    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased text-slate-900">
    @yield('structured_data')
    <x-toast />

    @yield('content')

    @livewireScripts
</body>
</html>
