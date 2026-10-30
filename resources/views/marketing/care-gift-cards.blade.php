@extends('layouts.care-hours-marketing')

@section('title', $pageTitle)
@section('meta_description', $pageDescription)
@section('canonical', $pageUrl)
@section('og_image', asset('images/marketing/care-gifts/care-gifts-social.jpg'))
@section('og_image_alt', 'An older woman and a girl sharing tea and conversation at a table.')
@section('og_image_width', '1200')
@section('og_image_height', '800')
@section('og_image_type', 'image/jpeg')

@push('head')
    <style>
        .gift-hero { padding-top: 64px; padding-bottom: 66px; }
        .gift-hero .chm-hero-title { max-width: 660px; }
        .gift-hero .chm-hero-title em { color: #b7634d; font-style: italic; }
        .gift-stage { position: relative; min-height: 455px; display: grid; place-items: center; isolation: isolate; }
        .gift-stage::before { content: ''; position: absolute; width: 415px; height: 415px; border-radius: 50%; background: #e8e4d4; z-index: -2; }
        .gift-stage::after { content: ''; position: absolute; width: 300px; height: 300px; border: 1px solid #d9c7b1; border-radius: 50%; right: -16px; top: 13px; z-index: -3; }
        .gift-envelope { position: absolute; width: 85%; height: 285px; background: #e8d6ba; border: 1px solid #d7c4a8; border-radius: 8px; transform: rotate(9deg) translate(7px, 25px); box-shadow: 0 18px 35px #23483f0a; }
        .gift-envelope::after { content: ''; position: absolute; inset: 0; background: #eddec8; clip-path: polygon(0 0, 50% 65%, 100% 0); border-radius: 8px; }
        .gift-card { position: relative; width: 87%; min-height: 300px; padding: 28px 32px; border: 1px solid #537064; background: #23483f; color: #fff7ea; transform: rotate(-6deg); border-radius: 16px; box-shadow: 0 24px 45px #183e3529; overflow: hidden; }
        .gift-card::before { content: ''; position: absolute; width: 66px; height: 120%; top: -10%; right: 36px; background: #c88369; opacity: .98; }
        .gift-card::after { content: ''; position: absolute; width: 130px; height: 130px; right: 3px; top: 60px; border: 1px solid #ffffff20; border-radius: 50%; }
        .gift-card-brand { width: 80px; height: auto; position: relative; }
        .chm .gift-card-label { position: relative; margin-top: 28px; color: #e3e7d8; font-size: 10px; letter-spacing: .16em; text-transform: uppercase; }
        .gift-card-hours { position: relative; display: block; margin-top: 10px; font: 500 52px/1.05 ChmSerif, Georgia, serif; letter-spacing: -.04em; }
        .chm .gift-card-description { position: relative; margin-top: 6px; font-size: 17px; }
        .gift-card-ribbon { position: absolute; right: 18px; top: 33px; width: 100px; height: 60px; }
        .gift-card-ribbon::before, .gift-card-ribbon::after { content: ''; position: absolute; width: 50px; height: 32px; border: 9px solid #d8a187; border-radius: 48% 48% 48% 12%; top: 5px; transform: rotate(23deg); }
        .gift-card-ribbon::after { right: 0; transform: rotate(154deg); }
        .gift-note { position: absolute; left: 1%; bottom: 2px; width: 245px; background: #fffdf6; padding: 19px 23px; border: 1px solid #dfd6c5; box-shadow: 0 12px 30px #23483f0b; transform: rotate(3deg); border-radius: 3px; }
        .gift-note p { margin: 0; font: italic 22px/1.25 ChmSerif, Georgia, serif; color: #23483f; }
        .gift-note small { display: block; margin-top: 10px; font-size: 11px; color: #68746b; }
        .gift-stage-caption { position: absolute; right: 8px; bottom: -20px; font-size: 10px; color: #64746b; letter-spacing: .03em; }
        .gift-benefit-symbol { display: block; font: 500 28px/1 ChmSerif, Georgia, serif; color: #b7634d; margin-bottom: 11px; }
        .gift-story-wrap { background: #f1ecdf; }
        .gift-story-photo { position: relative; max-width: 500px; }
        .chm .gift-story-photo img { display: block; width: 100%; height: auto; aspect-ratio: 3 / 2; object-fit: cover; object-position: center; border-radius: 100px 100px 12px 12px; }
        .chm .gift-story-caption { position: absolute; bottom: 24px; left: 24px; right: 24px; padding: 17px 22px; border-radius: 8px; background: #fffaf0ed; font: 500 23px/1.3 ChmSerif, Georgia, serif; text-align: center; color: #23483f; }
        .gift-occasions { display: flex; flex-wrap: wrap; gap: 9px; margin: 25px 0; }
        .gift-occasions span { border: 1px solid #d7cfbd; border-radius: 100px; padding: 7px 13px; font-size: 12px; color: #4b5e53; }
        .gift-story .chm-lead { font-size: 17px; }
        .gift-story-link { display: inline-flex; gap: 10px; margin-top: 10px; font-size: 14px; font-weight: 600; text-decoration: underline; text-underline-offset: 5px; }
        .gift-steps-intro { max-width: 650px; }
        .chm .chm-step .gift-step-number { display: grid; width: 45px; height: 45px; place-items: center; border: 1px solid #d9cdbb; border-radius: 50%; font: 500 23px/1 ChmSerif, Georgia, serif; color: #a95543; margin-bottom: 22px; }
        .chm .gift-steps-note { text-align: center; margin-top: 34px; color: #66756b; font-size: 14px; }
        .gift-pricing { background: #f2ede3; border-top: 1px solid #e2d9c9; border-bottom: 1px solid #e2d9c9; }
        .gift-package-label { font-size: 12px; letter-spacing: .01em; color: #647168; min-height: 20px; }
        .gift-pricing .chm-package h3.gift-package-hours { margin: 14px 0 0; font: 500 36px/1.1 ChmSerif, Georgia, serif; color: #23483f; }
        .gift-pricing .chm-package .gift-package-price { margin-top: 12px; font-size: 22px; font-weight: 600; color: #23483f; }
        .gift-package-bonus { min-height: 55px; margin: 20px 0 22px; padding-top: 18px; border-top: 1px solid #ded8cb; font-size: 13px; color: #53695b; }
        .gift-package-bonus strong { font-weight: 600; display: block; }
        .gift-package-bonus small { display: block; margin-top: 4px; color: #68746b; font-size: 11px; }
        .gift-pricing .chm-package-featured { border-color: #23483f; background: #fffaf1; box-shadow: inset 0 3px #23483f; }
        .gift-pricing .chm-package-featured .chm-button { background: #23483f; color: #fff7ea!important; border-color: #23483f; }
        .gift-pricing .chm-package .chm-button { width: 100%; justify-content: center; padding-inline: 12px; font-size: 13px; }
        .chm .gift-pricing-note { max-width: 800px; margin: 24px auto 0; text-align: center; font-size: 12px; color: #5f7065; line-height: 1.7; }
        .chm .gift-early-access { display: inline-block; margin-top: 16px; border-radius: 100px; padding: 8px 16px; border: 1px solid #d9cdbb; background: #fffaf0; color: #52665a; font-size: 12px; }
        .gift-faq-layout { display: grid; grid-template-columns: .8fr 1.2fr; gap: 80px; }
        .gift-faq-layout .chm-section-heading { text-align: left; margin: 0; }
        .gift-faq-layout .chm-section-heading h2 { max-width: 350px; }
        .gift-closing .chm-actions { justify-content: center; flex-direction: column; }
        .gift-closing .gift-redeem-link { display: inline-block; color: #e9e1d2; font-size: 12px; text-decoration: underline; text-underline-offset: 5px; }
        @media(max-width: 960px) {
            .gift-hero { padding-top: 42px; }
            .gift-stage { min-height: 420px; max-width: 550px; width: 100%; margin-inline: auto; }
            .gift-faq-layout { grid-template-columns: 1fr; gap: 30px; }
            .gift-faq-layout .chm-section-heading h2 { max-width: none; }
        }
        @media(max-width: 600px) {
            .gift-hero { padding-bottom: 42px; }
            .gift-stage { min-height: 355px; margin-top: 5px; }
            .gift-stage::before { width: 300px; height: 300px; }
            .gift-stage::after { width: 235px; height: 235px; right: 0; }
            .gift-envelope { height: 225px; width: 87%; }
            .gift-card { min-height: 240px; padding: 22px 24px; width: 87%; }
            .gift-card::before { width: 48px; right: 27px; }
            .gift-card-brand { width: 65px; }
            .chm .gift-card-label { margin-top: 20px; font-size: 9px; }
            .gift-card-hours { font-size: 42px; }
            .chm .gift-card-description { font-size: 15px; }
            .gift-card-ribbon { transform: scale(.75); transform-origin: right top; top: 28px; right: 11px; }
            .gift-note { width: 190px; padding: 14px 17px; bottom: -1px; left: 1px; }
            .gift-note p { font-size: 18px; }
            .gift-note small { margin-top: 7px; font-size: 9px; }
            .gift-stage-caption { bottom: -27px; right: 0; font-size: 9px; }
            .chm .chm-step .gift-step-number { width: 28px; height: 28px; font-size: 17px; }
            .chm .gift-story-photo img { border-radius: 70px 70px 12px 12px; }
            .chm .gift-story-caption { left: 12px; right: 12px; bottom: 12px; padding: 12px 16px; font-size: 16px; }
            .chm .gift-pricing-note { text-align: left; }
            .chm .gift-early-access { border-radius: 10px; line-height: 1.6; }
        }
    </style>
@endpush

@section('page_content')
    <section class="chm-wrap chm-hero gift-hero" aria-labelledby="gift-title">
        <div class="chm-hero-copy">
            <p class="chm-eyebrow">LoLo care gift cards</p>
            <h1 class="chm-hero-title" id="gift-title">A thoughtful gift.<br>A little more <em>care.</em></h1>
            <p class="chm-lead">For someone who has always been there for you. Give hours of help at home, with the freedom to choose when they need it.</p>
            <div class="chm-actions">
                <a class="chm-button" href="#packages">Explore gift cards <span aria-hidden="true">→</span></a>
                <a class="chm-button chm-button-secondary" href="{{ route('care-hours.claim') }}">Redeem a gift code</a>
            </div>
            <p class="chm-microcopy">From $155 · No expiry · They can create an account later</p>
        </div>
        <div class="chm-hero-visual gift-stage" role="img" aria-label="A LoLo gift for Mum from Jane, with ten hours of care and a note saying a little help, whenever you need it.">
            <div class="gift-envelope"></div>
            <div class="gift-card">
                <img class="gift-card-brand" src="{{ asset('images/marketing/lolo/lolo-wordmark-ivory.svg') }}" alt="" width="80" height="35">
                <p class="gift-card-label">A gift of care</p>
                <span class="gift-card-hours">10 hours</span>
                <p class="gift-card-description">of a little extra help.</p>
                <span class="gift-card-ribbon" aria-hidden="true"></span>
            </div>
            <div class="gift-note"><p>A little help,<br>whenever you need it.</p><small>To Mum. With love, Jane.</small></div>
            <span class="gift-stage-caption">Gifts are shared with a private code</span>
        </div>
    </section>

    <section class="chm-wrap chm-benefit-strip" aria-label="A gift that fits their life">
        <div class="chm-benefit-item"><span class="gift-benefit-symbol" aria-hidden="true">01</span><div><strong>Useful in real life</strong><p>Companionship, everyday help and a little breathing room.</p></div></div>
        <div class="chm-benefit-item"><span class="gift-benefit-symbol" aria-hidden="true">02</span><div><strong>Their timing. Their choice.</strong><p>They arrange care when they’re ready. The hours never expire.</p></div></div>
        <div class="chm-benefit-item"><span class="gift-benefit-symbol" aria-hidden="true">03</span><div><strong>A simple code to share</strong><p>No recipient account needed now. They can create one to claim.</p></div></div>
    </section>

    <section class="chm-section gift-story-wrap">
        <div class="chm-wrap chm-split gift-story">
            <div class="chm-story-visual gift-story-photo">
                <img src="{{ asset('images/marketing/care-gifts/care-gifts-960.webp') }}"
                     srcset="{{ asset('images/marketing/care-gifts/care-gifts-640.webp') }} 640w, {{ asset('images/marketing/care-gifts/care-gifts-960.webp') }} 960w, {{ asset('images/marketing/care-gifts/care-gifts-1440.webp') }} 1440w"
                     sizes="(max-width: 548px) calc(100vw - 48px), (max-width: 800px) 500px, (max-width: 1100px) calc((100vw - 117px) / 2), 500px"
                     alt="An older woman and a girl sharing tea and conversation at a table." width="960" height="640" loading="lazy" decoding="async">
                <p class="gift-story-caption">More support for the everyday.</p>
            </div>
            <div>
                <p class="chm-eyebrow">For the people who mean everything</p>
                <h2>For the parent who says,<br>“I don’t need anything.”</h2>
                <p class="chm-lead">A familiar face over a cup of tea. Help preparing lunch. Company for a gentle walk. Sometimes the most thoughtful gift is making an ordinary day a little easier.</p>
                <div class="gift-occasions" aria-label="Gift occasions"><span>Just because</span><span>A birthday</span><span>Thanksgiving</span><span>A thank-you</span></div>
                <p>Give them the hours. Let them choose the care.</p>
                <a class="gift-story-link" href="#how-it-works">See how gifting works <span aria-hidden="true">↗</span></a>
            </div>
        </div>
    </section>

    <section class="chm-section chm-wrap" id="how-it-works" aria-labelledby="gift-steps-title">
        <div class="chm-section-heading gift-steps-intro">
            <p class="chm-eyebrow">From you, with care</p>
            <h2 id="gift-steps-title">A gift now. Help when it’s needed.</h2>
        </div>
        <div class="chm-steps">
            <article class="chm-step"><span class="gift-step-number" aria-hidden="true">1</span><h3>Choose their hours</h3><p>Pick a gift of 5, 10, 20 or 40 hours. Pay once, with the platform fee included.</p></article>
            <article class="chm-step"><span class="gift-step-number" aria-hidden="true">2</span><h3>Share the private code</h3><p>Add it to a card or send it in a personal message. Keep the code private: it can be claimed once.</p></article>
            <article class="chm-step"><span class="gift-step-number" aria-hidden="true">3</span><h3>They claim. They choose.</h3><p>They enter the code, sign in or create a free family account, and add the hours to their balance.</p></article>
        </div>
        <p class="gift-steps-note">New to LoLo? They can create their account when they’re ready to claim.</p>
    </section>

    <section class="chm-section gift-pricing" id="packages" aria-labelledby="gift-packages-title">
        <div class="chm-wrap">
            <div class="chm-section-heading">
                <p class="chm-eyebrow">Choose a little extra care</p>
                <h2 id="gift-packages-title">One gift. So many ways to help.</h2>
                <p>$31 per paid hour, including the platform fee. No subscription.</p>
                @unless($canPurchase)
                    <p class="gift-early-access">Gift cards are opening gradually. Ask us about availability before buying.</p>
                @endunless
            </div>
            <div class="chm-packages">
                @foreach($packages as $package)
                    <article class="chm-package {{ (int) $package['hours'] === 10 ? 'chm-package-featured' : '' }}">
                        <p class="gift-package-label">{{ [5 => 'A little support', 10 => 'A thoughtful gift', 20 => 'More breathing room', 40 => 'Care to look forward to'][$package['hours']] ?? 'A gift of care' }}</p>
                        <h3 class="gift-package-hours">{{ $package['hours'] }} hours</h3>
                        <p class="gift-package-price">{{ $package['formatted_price'] }}</p>
                        <div class="gift-package-bonus">
                            @if($package['bonus_hours'] > 0)
                                <strong>Up to +{{ $package['bonus_hours'] }} welcome {{ (int) $package['bonus_hours'] === 1 ? 'hour' : 'hours' }}</strong>
                                <small>If the receiving family is eligible.</small>
                            @else
                                <strong>{{ $package['hours'] }} hours of care</strong>
                                <small>No welcome bonus on this package.</small>
                            @endif
                        </div>
                        <a class="chm-button {{ (int) $package['hours'] === 10 ? '' : 'chm-button-secondary' }}" href="{{ $purchaseUrl }}">{{ $canPurchase ? 'Buy a gift card' : 'Ask about availability' }} <span aria-hidden="true">↗</span></a>
                    </article>
                @endforeach
            </div>
            <p class="gift-pricing-note">
                @if($welcomeEnabled)
                    Welcome hours apply once per family account, on its first eligible 10, 20 or 40-hour package. Eligibility is confirmed when the gift is claimed. A 5-hour package keeps that eligibility intact.
                @endif
                Hours cover eligible standard individual care, subject to caregiver availability. No expiry. Purchases are non-refundable.
            </p>
        </div>
    </section>

    <section class="chm-section chm-wrap gift-faq-layout" id="faq" aria-labelledby="gift-faq-title">
        <div class="chm-section-heading">
            <p class="chm-eyebrow">Good to know</p>
            <h2 id="gift-faq-title">A few questions,<br>before you give.</h2>
            <p>Want to plan ahead for your own family?</p>
            <a class="gift-story-link" href="{{ route('prepaid-care-hours') }}">Explore prepaid care hours <span aria-hidden="true">↗</span></a>
        </div>
        <div class="chm-faq">
            @foreach($faqs as $faq)
                <details><summary>{{ $faq['question'] }}</summary><p>{{ $faq['answer'] }}</p></details>
            @endforeach
        </div>
    </section>

    <section class="chm-wrap chm-section">
        <div class="chm-closing gift-closing">
            <div>
                <p class="chm-eyebrow">A little help goes a long way</p>
                <h2>Give them something<br>they can feel.</h2>
                <p>More company. More support. More care, on their terms.</p>
            </div>
            <div class="chm-actions">
                <a class="chm-button" href="#packages">Explore gift cards <span aria-hidden="true">→</span></a>
                <a class="gift-redeem-link" href="{{ route('care-hours.claim') }}">Already have a gift code? Redeem it here.</a>
            </div>
        </div>
    </section>
@endsection
