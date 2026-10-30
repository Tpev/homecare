@extends('layouts.care-hours-marketing')

@section('title', $pageTitle)
@section('meta_description', $pageDescription)
@section('canonical', $pageUrl)
@section('og_image', asset('images/marketing/prepaid-care/prepaid-care-social.jpg'))
@section('og_image_alt', 'A woman wearing glasses smiling while using her smartphone.')
@section('og_image_width', '1200')
@section('og_image_height', '1800')
@section('og_image_type', 'image/jpeg')

@push('head')
<style>
    .chm .prepaid-hero-photo { object-position: 50% 24%; }
    .chm .prepaid-balance-label { display: flex; align-items: center; justify-content: space-between; gap: 16px; color: #596e62; font-size: 12px; }
    .chm .prepaid-balance-label span:last-child { font-size: 10px; letter-spacing: .06em; text-transform: uppercase; }
    .chm .prepaid-balance-value { margin: 12px 0 8px; font-family: ChmSerif, Georgia, serif; font-size: 38px; line-height: 1; color: #23483f; }
    .chm .prepaid-balance-value span { font-family: ChmInter, sans-serif; font-size: 14px; }
    .chm .prepaid-balance-note { display: flex; gap: 8px; align-items: center; font-size: 12px; color: #596e62; }
    .chm .prepaid-balance-note i { width: 7px; height: 7px; border-radius: 100%; background: #4f7251; }
    .chm .prepaid-step-number { color: #a15b47; font-size: 13px; letter-spacing: .09em; font-weight: 600; }
    .chm .prepaid-step-number + h3 { margin-top: 0; }
    .chm .prepaid-story-section { background: #eef0e7; }
    .chm .prepaid-story-copy h2 { max-width: 470px; }
    .chm .prepaid-story-copy > p:not(.chm-eyebrow) { max-width: 460px; margin-top: 22px; color: #57685e; font-size: 15px; line-height: 1.8; }
    .chm .prepaid-pool { max-width: 440px; margin: 0 auto; background: #fffdf7; border: 1px solid #d8dfd1; border-radius: 22px; padding: 30px; box-shadow: 0 16px 35px #23483f0a; }
    .chm .prepaid-pool-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; padding-bottom: 23px; border-bottom: 1px solid #e5e5d9; }
    .chm .prepaid-pool-heading h3 { font-size: 25px; margin: 4px 0 0; }
    .chm .prepaid-pool-heading small { color: #66746d; font-size: 11px; }
    .chm .prepaid-pool-illustration { margin-top: 4px; padding: 5px 8px; background: #edf0e6; border-radius: 5px; color: #66746d; font-size: 10px; }
    .chm .prepaid-pool-person { display: flex; align-items: center; gap: 12px; margin-top: 22px; font-size: 14px; }
    .chm .prepaid-pool-person > span:first-child { display: grid; place-items: center; flex: 0 0 37px; height: 37px; border-radius: 50%; background: #f2e3d9; color: #8d594a; font-size: 12px; }
    .chm .prepaid-pool-person:nth-child(3) > span:first-child { background: #e6ebdf; color: #526a4d; }
    .chm .prepaid-pool-person:nth-child(4) > span:first-child { background: #e8e5ee; color: #6a5b7e; }
    .chm .prepaid-pool-person strong { margin-left: auto; font-weight: 500; color: #45634f; }
    .chm .prepaid-pool-total { display: flex; align-items: center; justify-content: space-between; gap: 20px; margin: 26px -30px -30px; padding: 24px 30px; border-radius: 0 0 21px 21px; background: #23483f; color: #fff7ea; }
    .chm .prepaid-pool-total span { max-width: 165px; font-size: 13px; line-height: 1.5; }
    .chm .prepaid-pool-total strong { font-family: ChmSerif, Georgia, serif; font-size: 32px; font-weight: 500; white-space: nowrap; }
    .chm .prepaid-pool-caption { max-width: 380px; margin: 15px auto 0; text-align: center; font-size: 11px; color: #68776b; line-height: 1.6; }
    .chm .prepaid-price-intro { margin: 12px auto 0; max-width: 580px; }
    .chm .prepaid-price-line { display: flex; align-items: baseline; gap: 7px; }
    .chm .prepaid-price-line small { font-size: 12px; color: #66746d; }
    .chm .prepaid-package-type { margin: 10px 0 0; min-height: 20px; font-size: 12px; color: #66746d; }
    .chm .prepaid-package-bonus { min-height: 42px; margin: 22px 0; font-size: 13px; line-height: 1.6; }
    .chm .prepaid-package-bonus strong { color: #a15b47; font-weight: 600; }
    .chm .prepaid-package-plain { color: #66746d; }
    .chm .prepaid-package-total { border-top: 1px solid #dedfd3; padding-top: 19px; margin-bottom: 22px; }
    .chm .prepaid-package-total strong { display: block; font-family: ChmSerif, Georgia, serif; font-size: 25px; font-weight: 500; }
    .chm .prepaid-package-total span { color: #66746d; font-size: 11px; }
    .chm .prepaid-pricing-note { max-width: 820px; margin: 22px auto 0; text-align: center; font-size: 12px; line-height: 1.7; color: #66746d; }
    .chm .prepaid-access-note { display: inline-block; margin-top: 22px; border-radius: 8px; padding: 11px 16px; background: #f0eee3; font-size: 12px; line-height: 1.6; color: #596e62; }
    .chm .prepaid-gift-link { display: inline-flex; align-items: center; gap: 12px; border-bottom: 1px solid currentColor; padding-bottom: 5px; margin-top: 25px; font-weight: 600; font-size: 14px; text-decoration: none; }
    .chm .chm-package-featured .prepaid-package-type,
    .chm .chm-package-featured .prepaid-price-line small,
    .chm .chm-package-featured .prepaid-package-total span,
    .chm .chm-package-featured .prepaid-package-plain { color: #d5e1d2; }
    .chm .chm-package-featured .prepaid-package-bonus strong { color: #efbb9e; }
    .chm .chm-package-featured .prepaid-package-total { border-color: #647b6c; }
    @media (max-width: 800px) {
        .chm .chm-hero-visual .chm-balance-card { position: relative; left: auto; bottom: auto; margin: -18px 16px 0; min-width: 0; max-width: 300px; }
    }
    @media (max-width: 640px) {
        .chm .prepaid-pool { padding: 24px; }
        .chm .prepaid-pool-total { margin: 24px -24px -24px; padding: 22px 24px; }
        .chm .prepaid-package-bonus { min-height: 0; margin: 18px 0; }
    }
</style>
@endpush

@section('page_content')
    @php
        $examplePackage = collect($packages)->firstWhere('hours', 10);
    @endphp

    <section class="chm-wrap chm-hero" aria-labelledby="prepaid-heading">
        <div class="chm-hero-copy">
            <p class="chm-eyebrow">Prepaid care hours</p>
            <h1 id="prepaid-heading" class="chm-hero-title">A little planning.<br>A lot of <em>peace of mind.</em></h1>
            <p class="chm-lead">Put care hours aside for yourself or someone you love. They’re ready when your family needs a little more support at home.</p>
            <div class="chm-actions">
                <a class="chm-button" href="#packages">Explore packages <span aria-hidden="true">↗</span></a>
                <a class="chm-button chm-button-secondary" href="#how-it-works">How it works <span aria-hidden="true">↓</span></a>
            </div>
            <p class="chm-microcopy">From $155 · $31 per hour, platform fee included</p>
        </div>
        <div class="chm-hero-visual">
            <img class="chm-hero-photo prepaid-hero-photo"
                 src="{{ asset('images/marketing/prepaid-care/prepaid-care-960.webp') }}"
                 srcset="{{ asset('images/marketing/prepaid-care/prepaid-care-640.webp') }} 640w, {{ asset('images/marketing/prepaid-care/prepaid-care-960.webp') }} 960w, {{ asset('images/marketing/prepaid-care/prepaid-care-1440.webp') }} 1440w"
                 sizes="(max-width: 607px) calc(100vw - 48px), (max-width: 800px) 560px, (max-width: 1100px) calc((100vw - 108px) / 2), (max-width: 1320px) calc((100vw - 185px) / 2), 560px"
                 alt="A woman wearing glasses smiling while using her smartphone." width="960" height="1440" fetchpriority="high">
            <div class="chm-balance-card">
                <div class="prepaid-balance-label"><span>Your family’s care</span><span>Illustration</span></div>
                <p class="prepaid-balance-value">{{ $examplePackage['total_hours'] }} <span>hours ready</span></p>
                <p class="prepaid-balance-note"><i aria-hidden="true"></i> For the days you need a hand</p>
            </div>
        </div>
    </section>

    <div class="chm-wrap">
        <div class="chm-benefit-strip" aria-label="Prepaid care benefits">
            <div class="chm-benefit-item"><span aria-hidden="true">01</span><div><strong>No expiry date</strong><p>Use your hours when you need them.</p></div></div>
            <div class="chm-benefit-item"><span aria-hidden="true">02</span><div><strong>One family balance</strong><p>Keep everyone’s care hours together.</p></div></div>
            <div class="chm-benefit-item"><span aria-hidden="true">03</span><div><strong>An hour stays an hour</strong><p>Even if standard prices rise later.</p></div></div>
        </div>
    </div>

    <section id="how-it-works" class="chm-wrap chm-section" aria-labelledby="how-heading">
        <div class="chm-section-heading">
            <p class="chm-eyebrow">A simpler way to plan ahead</p>
            <h2 id="how-heading">Care on your family’s terms.</h2>
            <p>Choose the hours now. Arrange the visits when you’re ready.</p>
        </div>
        <div class="chm-steps">
            <article class="chm-step"><span class="prepaid-step-number">01</span><h3>Choose your package.</h3><p>Add prepaid hours to the family account you manage. Start with 5 hours or set aside more.</p></article>
            <article class="chm-step"><span class="prepaid-step-number">02</span><h3>Find the right support.</h3><p>Arrange standard individual care through LoLo, from companionship to help with everyday routines.</p></article>
            <article class="chm-step"><span class="prepaid-step-number">03</span><h3>Let your hours do the rest.</h3><p>Apply your balance to eligible visits. See what’s available, reserved and used, all in one place.</p></article>
        </div>
    </section>

    <section class="prepaid-story-section" aria-labelledby="family-heading">
        <div class="chm-wrap chm-section chm-split">
            <div class="prepaid-story-copy">
                <p class="chm-eyebrow">One family. A little help from everyone.</p>
                <h2 id="family-heading">You don’t have to<br>do it all alone.</h2>
                <p>Emma, James and Sophie all want to help their dad, Robert. A private contribution link lets each of them buy care hours for the same family balance.</p>
                <p>No juggling separate gift cards. Just more time for Dad’s care, with one family account keeping track.</p>
                <a href="#packages" class="prepaid-gift-link">Find your family’s starting point <span aria-hidden="true">→</span></a>
            </div>
            <div class="chm-story-visual">
                <div class="prepaid-pool">
                    <div class="prepaid-pool-heading"><div><small>THE BENNETT FAMILY</small><h3>A little help for Dad</h3></div><span class="prepaid-pool-illustration">Illustration</span></div>
                    <div class="prepaid-pool-person"><span aria-hidden="true">EB</span><span>Emma contributed</span><strong>5 hours</strong></div>
                    <div class="prepaid-pool-person"><span aria-hidden="true">JB</span><span>James contributed</span><strong>5 hours</strong></div>
                    <div class="prepaid-pool-person"><span aria-hidden="true">SB</span><span>Sophie contributed</span><strong>10 hours</strong></div>
                    <div class="prepaid-pool-total"><span>Paid hours in one family balance</span><strong>20 hours</strong></div>
                </div>
                <p class="prepaid-pool-caption">An example of family contributions. @if($welcomeEnabled) Any eligible welcome bonus adds extra hours to the same balance. @endif</p>
            </div>
        </div>
    </section>

    <section id="packages" class="chm-wrap chm-section" aria-labelledby="package-heading">
        <div class="chm-section-heading">
            <p class="chm-eyebrow">A little now. Ready for later.</p>
            <h2 id="package-heading">Find your starting point.</h2>
            <p class="prepaid-price-intro">Every paid hour is $31, including the platform fee. Choose the amount of care that feels right for your family.</p>
        </div>
        <div class="chm-packages">
            @foreach($packages as $package)
                <article class="chm-package {{ $package['hours'] === 10 ? 'chm-package-featured' : '' }}">
                    @if($package['hours'] === 10)<span class="chm-badge">A little breathing room</span>@endif
                    <h3>{{ $package['hours'] }} hours</h3>
                    <p class="prepaid-package-type">{{ match ($package['hours']) { 5 => 'A simple start', 10 => 'A little more support', 20 => 'Room for a routine', default => 'More care set aside' } }}</p>
                    <div class="prepaid-price-line"><p class="chm-price">{{ $package['formatted_price'] }}</p><small>USD</small></div>
                    <p class="prepaid-package-bonus">
                        @if($package['bonus_hours'] > 0)
                            <strong>+ {{ $package['bonus_hours'] }} {{ $package['bonus_hours'] === 1 ? 'hour' : 'hours' }} free</strong><br>with your first eligible package
                        @else
                            <span class="prepaid-package-plain">No expiry.<br>Care at your own pace.</span>
                        @endif
                    </p>
                    <div class="prepaid-package-total"><strong>{{ $package['total_hours'] }} hours of care</strong><span>{{ $package['bonus_hours'] > 0 ? 'With your welcome bonus' : 'In your family balance' }}</span></div>
                    <a href="{{ $purchaseUrl }}" class="{{ $package['hours'] === 10 ? 'chm-button' : 'chm-button chm-button-secondary' }}">{{ $canPurchase ? 'Buy prepaid care' : 'Ask about availability' }} <span aria-hidden="true">↗</span></a>
                </article>
            @endforeach
        </div>
        <p class="prepaid-pricing-note">@if($welcomeEnabled) The welcome bonus applies once per family account, on its first eligible 10, 20 or 40-hour package. Buying 5 hours keeps that bonus available for later. @endif Packages are non-refundable. Care is subject to caregiver availability.</p>
        @unless($canPurchase)
            <div style="text-align:center"><p class="prepaid-access-note">Prepaid care is opening gradually. Contact us to check availability for your family.</p></div>
        @endunless
    </section>

    <section id="faq" class="chm-wrap chm-section chm-faq" aria-labelledby="faq-heading">
        <div class="chm-section-heading">
            <p class="chm-eyebrow">A few things to know</p>
            <h2 id="faq-heading">Clear answers.<br>More peace of mind.</h2>
        </div>
        <div>
            @foreach($faqs as $faq)
                <details><summary>{{ $faq['question'] }}</summary><p>{{ $faq['answer'] }}</p></details>
            @endforeach
        </div>
    </section>

    <section class="chm-wrap" aria-labelledby="closing-heading">
        <div class="chm-closing">
            <div><p class="chm-eyebrow">Care is a beautiful thing to give.</p><h2 id="closing-heading">Thinking of someone<br>you love?</h2><p>Give them hours of support, with a gift code they can claim when they’re ready. They don’t need a LoLo account yet.</p></div>
            <div class="chm-actions"><a href="{{ route('care-gift-cards') }}" class="chm-button">Discover care gift cards <span aria-hidden="true">↗</span></a></div>
        </div>
    </section>
@endsection
