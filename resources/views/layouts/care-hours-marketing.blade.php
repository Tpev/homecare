@extends('layouts.marketing-lean')

@section('robots', 'index,follow,max-image-preview:large')
@section('disable_marketing_tracking', '1')

@push('head')
    <link rel="preload" href="{{ asset('fonts/SourceSerif4-Variable.woff') }}" as="font" type="font/woff" crossorigin>
    <link rel="preload" href="{{ asset('fonts/Inter-Variable.woff') }}" as="font" type="font/woff" crossorigin>
    <style>@include('marketing.care-hours-styles')</style>
@endpush

@section('structured_data')
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endsection

@section('content')
    <div class="chm">
        <a class="chm-skip" href="#main">Skip to content</a>
        <header class="chm-header chm-wrap">
            <a class="chm-logo" href="{{ route('landing') }}" aria-label="LoLo Care home"><img src="{{ asset('images/marketing/lolo/lolo-wordmark-evergreen.svg') }}" width="102" height="35" alt="LoLo"><span>CARE, WITH YOU.</span></a>
            <nav class="chm-nav" aria-label="Main navigation">
                <a href="{{ route('prepaid-care-hours') }}" @if(request()->routeIs('prepaid-care-hours')) aria-current="page" @endif>Prepaid care hours</a>
                <a href="{{ route('care-gift-cards') }}" @if(request()->routeIs('care-gift-cards')) aria-current="page" @endif>Gift cards</a>
                <a href="#how-it-works">How it works</a>
            </nav>
            <a class="chm-header-login" href="{{ route('login') }}">Log in <span aria-hidden="true">↗</span></a>
            <details class="chm-menu">
                <summary aria-label="Open navigation">Menu <span aria-hidden="true">+</span></summary>
                <nav aria-label="Mobile navigation">
                    <a href="{{ route('prepaid-care-hours') }}">Prepaid care hours</a>
                    <a href="{{ route('care-gift-cards') }}">Gift cards</a>
                    <a href="#how-it-works">How it works</a>
                    <a href="{{ route('care-hours.claim') }}">Claim a gift</a>
                </nav>
            </details>
        </header>
        <main id="main" tabindex="-1">@yield('page_content')</main>
        <footer class="chm-footer">
            <div class="chm-wrap">
                <div class="chm-footer-top">
                    <div><a href="{{ route('landing') }}" aria-label="LoLo Care home"><img src="{{ asset('images/marketing/lolo/lolo-wordmark-ivory.svg') }}" width="102" height="35" alt="LoLo"></a><p>A little help. More life at home.</p><span>Non-medical care, made personal.</span></div>
                    <nav aria-label="Footer navigation"><a href="{{ route('prepaid-care-hours') }}">Prepaid care hours</a><a href="{{ route('care-gift-cards') }}">Gift cards</a><a href="{{ route('care-hours.claim') }}">Claim a gift</a><a href="{{ route('about') }}">About LoLo</a><a href="{{ route('landing.get-care') }}">Talk to us</a></nav>
                </div>
                <div class="chm-footer-bottom"><p>© {{ now()->year }} LoLo Care Inc.</p><nav aria-label="Legal"><a href="{{ route('legal.show', ['slug' => 'platform-terms-of-service']) }}">Terms</a><a href="{{ route('legal.show', ['slug' => 'privacy-policy']) }}">Privacy</a><a href="{{ route('legal.show', ['slug' => 'payment-terms-and-cancellation-policy']) }}">Payments & cancellation</a></nav></div>
            </div>
        </footer>
    </div>
@endsection
