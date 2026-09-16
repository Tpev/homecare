<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#fbf8f1">
    <title>Getting started | LoLo Care</title>
    <x-site-icons />
    <link rel="stylesheet" href="{{ asset('css/family-onboarding.css') }}?v={{ filemtime(public_path('css/family-onboarding.css')) }}">
    @livewireStyles
</head>
<body class="family-onboarding-page">
    <header class="site-header">
        <div class="header-inner">
            <img src="{{ asset('images/marketing/lolo/lolo-wordmark-evergreen.svg') }}" width="100" height="34" alt="LoLo" class="logo">
            <nav class="header-actions" aria-label="Account"><a href="{{ route('support.index') }}">Help</a><a href="{{ route('profile') }}">Account</a><form method="post" action="{{ route('logout') }}">@csrf<button type="submit">Sign out</button></form></nav>
        </div>
    </header>
    <main class="onboarding-layout">{{ $slot }}</main>
    @livewireScripts
</body>
</html>
