<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow"><meta name="referrer" content="no-referrer">
    <title>Care hours | LoLo Care</title>
    <x-site-icons />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#F7F3ED] text-[#23483F]">
    <header class="border-b border-[#D8E1D7] bg-white"><div class="mx-auto max-w-3xl px-5 py-5"><a href="{{ route('landing') }}" class="font-display text-xl font-semibold">LoLo Care</a></div></header>
    <main class="mx-auto max-w-3xl space-y-6 px-5 py-8">@yield('content')</main>
    <footer class="mx-auto max-w-3xl px-5 py-8 text-sm text-[#485E53]">Care hours · LoLo Care</footer>
</body>
</html>
