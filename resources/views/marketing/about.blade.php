@extends('layouts.marketing-lean')

@section('title', 'About LoLo Care | More human support at home')
@section('meta_description', 'Meet LoLo Care and the values behind our flexible, trusted approach to non-medical support for aging at home.')
@section('canonical', route('about'))
@section('og_image', asset('images/marketing/homepage/human-moment.jpg'))
@section('og_image_alt', 'A caregiver and an older adult sharing a joyful moment at home.')

@section('structured_data')
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'AboutPage',
        'name' => 'About LoLo Care',
        'url' => route('about'),
        'description' => 'The mission and values behind LoLo Care and its approach to flexible, non-medical support for aging at home.',
        'mainEntity' => [
            '@type' => 'Organization',
            'name' => 'LoLo Care',
            'url' => route('landing'),
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endsection

@push('head')
    <link rel="preload" as="image" href="{{ asset('images/marketing/homepage/human-moment.jpg') }}" fetchpriority="high">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600&display=optional">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600&display=optional" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600&display=optional" rel="stylesheet"></noscript>
@endpush

@section('content')
    <style>
        .about-lolo {
            --cream: #fff7ea;
            --oat: #f1e5d2;
            --green: #173f35;
            --deep: #10372f;
            --coral: #b95745;
            --ink: #24302d;
            --muted: #68716c;
            --line: #dfd4c2;
            min-height: 100vh;
            overflow-x: clip;
            background: var(--cream);
            color: var(--ink);
            font-family: 'DM Sans', sans-serif;
            font-size: 16px;
            line-height: 1.6;
        }

        html, body { margin: 0; }
        .about-lolo *, .about-lolo *::before, .about-lolo *::after { box-sizing: border-box; }
        .about-lolo a { color: inherit; text-decoration: none; }
        .about-lolo img { display: block; max-width: 100%; }
        .about-lolo h1, .about-lolo h2, .about-lolo h3 {
            margin-top: 0;
            color: var(--green);
            font-family: 'Fraunces', serif;
            font-optical-sizing: auto;
        }
        .about-lolo p { margin-top: 0; }

        .about-lolo .nav-shell {
            position: relative;
            z-index: 20;
            display: flex;
            align-items: center;
            height: 88px;
            border-bottom: 1px solid rgba(23, 63, 53, .12);
            padding: 0 max(4vw, 26px);
            background: rgba(255, 247, 234, .94);
            backdrop-filter: blur(14px);
        }
        .about-lolo .brand img { width: 102px; height: auto; }
        .about-lolo .nav-links { display: flex; gap: 32px; margin: auto; font-size: .94rem; font-weight: 500; }
        .about-lolo .nav-links a { padding: 10px 0; }
        .about-lolo .nav-links a:hover, .about-lolo .nav-links .active { color: var(--coral); }
        .about-lolo .nav-actions { display: flex; align-items: center; gap: 20px; font-size: .9rem; font-weight: 600; }
        .about-lolo .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 52px;
            border-radius: 999px;
            background: var(--green);
            color: #fffaf1;
            padding: 14px 25px;
            font-weight: 700;
            transition: transform .2s ease, background .2s ease;
        }
        .about-lolo .button:hover { transform: translateY(-2px); background: #225748; }
        .about-lolo .button.small { min-height: 42px; padding: 10px 20px; }
        .about-lolo .mobile-menu { display: none; margin-left: auto; }
        .about-lolo .mobile-menu summary { cursor: pointer; font-weight: 700; list-style: none; }
        .about-lolo .mobile-menu summary::-webkit-details-marker { display: none; }
        .about-lolo .mobile-menu nav {
            position: absolute;
            top: 88px;
            right: 0;
            left: 0;
            display: grid;
            gap: 18px;
            border-bottom: 1px solid var(--line);
            background: var(--cream);
            padding: 24px;
            box-shadow: 0 22px 40px rgba(23, 63, 53, .12);
        }

        .about-lolo .hero {
            position: relative;
            display: grid;
            grid-template-columns: minmax(0, .92fr) minmax(460px, 1.08fr);
            gap: clamp(52px, 7vw, 110px);
            align-items: center;
            min-height: 730px;
            padding: 80px max(5vw, 42px) 96px;
        }
        .about-lolo .hero::before {
            position: absolute;
            top: 70px;
            left: -90px;
            width: 210px;
            height: 210px;
            border: 1px solid rgba(185, 87, 69, .22);
            border-radius: 50%;
            content: '';
        }
        .about-lolo .eyebrow {
            margin-bottom: 20px;
            color: var(--coral);
            font-size: .77rem;
            font-weight: 700;
            letter-spacing: .18em;
            text-transform: uppercase;
        }
        .about-lolo h1 {
            max-width: 780px;
            margin-bottom: 30px;
            font-size: clamp(3.4rem, 5.7vw, 6.7rem);
            font-weight: 500;
            letter-spacing: -.055em;
            line-height: .93;
        }
        .about-lolo h1 em { color: var(--coral); font-style: normal; }
        .about-lolo .hero-copy > p:not(.eyebrow) { max-width: 650px; color: #53605b; font-size: 1.14rem; line-height: 1.75; }
        .about-lolo .hero-actions { display: flex; align-items: center; gap: 26px; margin-top: 34px; }
        .about-lolo .text-link { border-bottom: 1px solid currentColor; font-weight: 700; }

        .about-lolo .hero-image { position: relative; }
        .about-lolo .hero-image::before {
            position: absolute;
            right: -26px;
            bottom: -26px;
            width: 48%;
            height: 44%;
            border-radius: 28px;
            background: var(--coral);
            content: '';
        }
        .about-lolo .hero-image img {
            position: relative;
            z-index: 1;
            width: 100%;
            min-height: 590px;
            border-radius: 34px;
            object-fit: cover;
            object-position: center;
        }
        .about-lolo .image-note {
            position: absolute;
            z-index: 2;
            bottom: 24px;
            left: -34px;
            max-width: 250px;
            border-radius: 18px;
            background: #fffaf1;
            padding: 18px 20px;
            box-shadow: 0 20px 55px rgba(23, 63, 53, .18);
            color: var(--green);
            font-family: 'Fraunces', serif;
            font-size: 1.2rem;
            line-height: 1.25;
        }
        .about-lolo .image-note span { display: block; margin-bottom: 6px; color: var(--coral); font-family: 'DM Sans', sans-serif; font-size: .68rem; font-weight: 700; letter-spacing: .15em; text-transform: uppercase; }

        .about-lolo .why {
            display: grid;
            grid-template-columns: .7fr 1.3fr;
            gap: clamp(50px, 9vw, 150px);
            border-top: 1px solid var(--line);
            padding: 120px max(7vw, 42px);
        }
        .about-lolo .why h2 { margin-bottom: 0; font-size: clamp(2.7rem, 4.5vw, 5.1rem); font-weight: 500; letter-spacing: -.045em; line-height: 1; }
        .about-lolo .why-copy > p { max-width: 820px; color: #53605b; font-size: clamp(1.3rem, 2vw, 1.75rem); line-height: 1.55; }
        .about-lolo .why-points { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 48px; }
        .about-lolo .why-point { border-top: 2px solid var(--coral); padding-top: 20px; }
        .about-lolo .why-point strong { display: block; margin-bottom: 7px; color: var(--green); font-size: 1.03rem; }
        .about-lolo .why-point p { margin-bottom: 0; color: var(--muted); font-size: .93rem; }

        .about-lolo .values {
            position: relative;
            overflow: hidden;
            background: var(--deep);
            color: #fffaf1;
            padding: 120px max(6vw, 42px) 130px;
        }
        .about-lolo .values::after {
            position: absolute;
            right: -170px;
            bottom: -230px;
            width: 520px;
            height: 520px;
            border: 1px solid rgba(255, 250, 241, .15);
            border-radius: 50%;
            content: '';
        }
        .about-lolo .values .eyebrow { color: #efaa96; }
        .about-lolo .values-head { display: grid; grid-template-columns: .82fr 1.18fr; gap: 70px; align-items: end; }
        .about-lolo .values h2 { max-width: 740px; margin-bottom: 0; color: #fffaf1; font-size: clamp(3rem, 5vw, 5.7rem); font-weight: 500; letter-spacing: -.045em; line-height: .97; }
        .about-lolo .values-intro { max-width: 620px; margin: 0 0 8px; color: rgba(255, 250, 241, .72); font-size: 1.08rem; line-height: 1.75; }
        .about-lolo .values-grid { position: relative; z-index: 1; display: grid; grid-template-columns: repeat(2, 1fr); gap: 1px; margin-top: 78px; background: rgba(255, 250, 241, .18); }
        .about-lolo .value-card { min-height: 310px; background: var(--deep); padding: 42px 46px; }
        .about-lolo .value-card .number { display: block; margin-bottom: 62px; color: #efaa96; font-size: .75rem; font-weight: 700; letter-spacing: .14em; }
        .about-lolo .value-card h3 { margin-bottom: 14px; color: #fffaf1; font-size: clamp(1.8rem, 2.6vw, 2.65rem); font-weight: 500; line-height: 1.05; }
        .about-lolo .value-card p { max-width: 520px; margin-bottom: 0; color: rgba(255, 250, 241, .68); }

        .about-lolo .promises {
            display: grid;
            grid-template-columns: minmax(340px, .8fr) minmax(0, 1.2fr);
            gap: clamp(65px, 9vw, 145px);
            align-items: center;
            padding: 130px max(7vw, 42px);
        }
        .about-lolo .promise-photo { position: relative; padding: 0 0 38px 38px; }
        .about-lolo .promise-photo::before {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 74%;
            height: 68%;
            border-radius: 28px;
            background: var(--oat);
            content: '';
        }
        .about-lolo .promise-photo img { position: relative; z-index: 1; width: 100%; max-height: 720px; border-radius: 30px; object-fit: cover; }
        .about-lolo .promises h2 { max-width: 760px; margin-bottom: 28px; font-size: clamp(3rem, 4.7vw, 5.4rem); font-weight: 500; letter-spacing: -.045em; line-height: .98; }
        .about-lolo .promise-intro { max-width: 670px; color: var(--muted); font-size: 1.08rem; line-height: 1.75; }
        .about-lolo .promise-list { margin-top: 54px; }
        .about-lolo .promise-item { display: grid; grid-template-columns: 120px 1fr; gap: 28px; border-top: 1px solid var(--line); padding: 26px 0; }
        .about-lolo .promise-item:last-child { border-bottom: 1px solid var(--line); }
        .about-lolo .promise-item span { color: var(--coral); font-size: .73rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
        .about-lolo .promise-item strong { display: block; margin-bottom: 5px; color: var(--green); font-size: 1.06rem; }
        .about-lolo .promise-item p { margin-bottom: 0; color: var(--muted); font-size: .94rem; }

        .about-lolo .scope {
            display: grid;
            grid-template-columns: .82fr 1.18fr;
            gap: clamp(52px, 9vw, 145px);
            margin: 0 max(4vw, 26px) 120px;
            border-radius: 34px;
            background: var(--oat);
            padding: 90px max(5vw, 44px);
        }
        .about-lolo .scope h2 { margin-bottom: 0; font-size: clamp(2.8rem, 4.3vw, 4.9rem); font-weight: 500; letter-spacing: -.04em; line-height: 1; }
        .about-lolo .scope-copy > p { max-width: 720px; color: #55625c; font-size: 1.08rem; }
        .about-lolo .support-list { display: flex; flex-wrap: wrap; gap: 10px; margin: 32px 0 28px; padding: 0; list-style: none; }
        .about-lolo .support-list li { border: 1px solid rgba(23, 63, 53, .18); border-radius: 999px; background: rgba(255, 250, 241, .62); padding: 9px 15px; color: var(--green); font-size: .88rem; font-weight: 600; }
        .about-lolo .boundary { border-left: 3px solid var(--coral); margin-bottom: 0; padding-left: 18px; color: var(--muted); font-size: .88rem; }

        .about-lolo .north-star { padding: 70px max(7vw, 42px) 135px; text-align: center; }
        .about-lolo .north-star blockquote { max-width: 1180px; margin: 0 auto; color: var(--green); font-family: 'Fraunces', serif; font-size: clamp(2.8rem, 5.2vw, 6.1rem); font-weight: 500; letter-spacing: -.045em; line-height: 1.03; }
        .about-lolo .north-star blockquote em { color: var(--coral); font-style: normal; }

        .about-lolo .final-cta {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 50px;
            align-items: end;
            margin: 0 max(2vw, 16px) 18px;
            border-radius: 34px;
            background: var(--coral);
            padding: 80px max(6vw, 44px);
            color: #fffaf1;
        }
        .about-lolo .final-cta .eyebrow { color: #ffe1d6; }
        .about-lolo .final-cta h2 { max-width: 900px; margin-bottom: 0; color: #fffaf1; font-size: clamp(3rem, 5vw, 5.7rem); font-weight: 500; letter-spacing: -.045em; line-height: .96; }
        .about-lolo .final-actions { display: flex; flex-direction: column; gap: 14px; align-items: stretch; min-width: 210px; }
        .about-lolo .final-actions .button { background: #fffaf1; color: var(--green); }
        .about-lolo .final-actions a:last-child { text-align: center; font-weight: 700; }

        .about-lolo .landing-footer {
            display: grid;
            grid-template-columns: 2fr repeat(3, 1fr);
            gap: 50px;
            border-top: 1px solid var(--line);
            padding: 70px max(5vw, 36px) 30px;
        }
        .about-lolo .landing-footer > div { display: flex; flex-direction: column; align-items: flex-start; gap: 12px; font-size: .92rem; }
        .about-lolo .footer-brand .brand img { width: 84px; }
        .about-lolo .footer-brand p { max-width: 300px; color: var(--muted); line-height: 1.6; }
        .about-lolo .copyright {
            grid-column: 1 / -1;
            margin-top: 20px;
            border-top: 1px solid var(--line);
            color: var(--muted);
            padding-top: 25px;
            font-size: .82rem;
        }

        @media (max-width: 1050px) {
            .about-lolo .nav-links, .about-lolo .nav-actions .caregiver-join { display: none; }
            .about-lolo .mobile-menu { display: block; }
            .about-lolo .nav-actions { margin-left: 22px; }
            .about-lolo .hero { grid-template-columns: 1fr; padding-top: 68px; }
            .about-lolo .hero-copy { max-width: 860px; }
            .about-lolo .hero-image { margin-left: 44px; }
            .about-lolo .hero-image img { min-height: 520px; }
            .about-lolo .why { grid-template-columns: 1fr; }
            .about-lolo .values-head, .about-lolo .scope { grid-template-columns: 1fr; }
            .about-lolo .promises { grid-template-columns: .85fr 1.15fr; gap: 55px; padding-right: 42px; padding-left: 42px; }
            .about-lolo .final-cta { grid-template-columns: 1fr; }
            .about-lolo .final-actions { align-items: flex-start; min-width: 0; }
            .about-lolo .landing-footer { grid-template-columns: 1fr 1fr; }
            .about-lolo .footer-brand { grid-column: 1 / -1; }
        }

        @media (max-width: 620px) {
            .about-lolo .nav-shell { height: 72px; padding: 0 20px; }
            .about-lolo .brand img { width: 86px; }
            .about-lolo .nav-actions .sign-in { display: none; }
            .about-lolo .nav-actions { margin-left: 16px; }
            .about-lolo .button.small { min-height: 38px; padding: 8px 15px; }
            .about-lolo .mobile-menu nav { top: 72px; }
            .about-lolo .hero { gap: 48px; min-height: auto; padding: 54px 22px 78px; }
            .about-lolo h1 { font-size: 3.25rem; }
            .about-lolo .hero-actions { align-items: flex-start; flex-direction: column; gap: 18px; }
            .about-lolo .hero-image { margin-left: 0; }
            .about-lolo .hero-image::before { right: -12px; bottom: -16px; border-radius: 20px; }
            .about-lolo .hero-image img { min-height: 430px; border-radius: 24px; }
            .about-lolo .image-note { bottom: 18px; left: 14px; max-width: 215px; }
            .about-lolo .why { gap: 38px; padding: 82px 22px; }
            .about-lolo .why-points { grid-template-columns: 1fr; }
            .about-lolo .values { padding: 82px 22px; }
            .about-lolo .values-grid { grid-template-columns: 1fr; margin-top: 52px; }
            .about-lolo .value-card { min-height: 0; padding: 34px 2px; }
            .about-lolo .value-card .number { margin-bottom: 35px; }
            .about-lolo .promises { grid-template-columns: 1fr; gap: 62px; padding: 85px 22px; }
            .about-lolo .promise-photo { padding: 0 0 24px 24px; }
            .about-lolo .promise-photo img { max-height: 540px; }
            .about-lolo .promise-item { grid-template-columns: 1fr; gap: 8px; }
            .about-lolo .scope { margin: 0 12px 82px; border-radius: 24px; padding: 62px 22px; }
            .about-lolo .north-star { padding: 30px 22px 92px; }
            .about-lolo .final-cta { margin: 0 10px 10px; border-radius: 24px; padding: 62px 22px; }
            .about-lolo .landing-footer { grid-template-columns: 1fr 1fr; padding: 60px 22px; }
            .about-lolo .copyright { grid-column: 1 / -1; }
        }

        @media (prefers-reduced-motion: reduce) {
            .about-lolo *, .about-lolo *::before, .about-lolo *::after { transition: none !important; }
        }
    </style>

    <main class="about-lolo">
        <header class="nav-shell">
            <a class="brand" href="{{ route('landing') }}" aria-label="LoLo Care home">
                <img src="{{ asset('images/marketing/lolo/lolo-wordmark-evergreen.svg') }}" alt="LoLo Care" width="652" height="222">
            </a>

            <nav class="nav-links" aria-label="Main navigation">
                <a href="{{ route('landing') }}#how">How it works</a>
                <a href="{{ route('landing') }}#caregivers">Caregivers</a>
                <a href="{{ route('landing') }}#families">For families</a>
                <a class="active" href="{{ route('about') }}" aria-current="page">About</a>
            </nav>

            <details class="mobile-menu">
                <summary aria-label="Open navigation menu">Menu</summary>
                <nav aria-label="Mobile navigation">
                    <a href="{{ route('landing') }}">Home</a>
                    <a href="{{ route('landing') }}#how">How it works</a>
                    <a href="{{ route('landing') }}#caregivers">Caregivers</a>
                    <a href="{{ route('landing') }}#families">For families</a>
                    <a href="{{ route('about') }}" aria-current="page">About</a>
                    <a href="{{ route('caregiver.register') }}">Become a caregiver</a>
                    <a href="{{ route('login') }}">Sign in</a>
                </nav>
            </details>

            <div class="nav-actions">
                <a class="caregiver-join" href="{{ route('caregiver.register') }}">Caregiver? Join LoLo</a>
                <a class="sign-in" href="{{ route('login') }}">Sign in</a>
                <a class="button small" href="{{ route('register') }}">Find care</a>
            </div>
        </header>

        <section class="hero">
            <div class="hero-copy">
                <p class="eyebrow">About LoLo Care</p>
                <h1>Care should make life feel more like <em>life.</em></h1>
                <p>LoLo helps families find trusted, non-medical support for someone they love—and makes the everyday work of coordinating care feel simpler, clearer, and more human.</p>
                <div class="hero-actions">
                    <a class="button" href="{{ route('register') }}">Find care</a>
                    <a class="text-link" href="{{ route('caregiver.register') }}">Become a caregiver →</a>
                </div>
            </div>

            <div class="hero-image">
                <img src="{{ asset('images/marketing/homepage/human-moment.jpg') }}" alt="A caregiver and older adult laughing together while making tea at home" width="1920" height="1080" fetchpriority="high" decoding="async">
                <p class="image-note"><span>What matters</span>The conversation. The familiar routine. The good afternoon.</p>
            </div>
        </section>

        <section class="why" aria-labelledby="why-title">
            <div>
                <p class="eyebrow">Why LoLo exists</p>
                <h2 id="why-title">A little help can change the whole week.</h2>
            </div>
            <div class="why-copy">
                <p>Families do not always need a full-time care plan. Often, they need a trusted person for a few meaningful hours—and a dependable way to make that support happen.</p>
                <div class="why-points">
                    <div class="why-point"><strong>Flexible by design</strong><p>Book support that fits real life, from one visit to an ongoing rhythm.</p></div>
                    <div class="why-point"><strong>Easy to coordinate</strong><p>Keep care details, communication, and updates together in one calm place.</p></div>
                    <div class="why-point"><strong>Centered on people</strong><p>Support is shaped around the person, their preferences, and their home.</p></div>
                    <div class="why-point"><strong>Built on trust</strong><p>Clear expectations and reliable follow-through help everyone feel informed.</p></div>
                </div>
            </div>
        </section>

        <section class="values" aria-labelledby="values-title">
            <div class="values-head">
                <div>
                    <p class="eyebrow">Our values</p>
                    <h2 id="values-title">How we want care to feel.</h2>
                </div>
                <p class="values-intro">Values matter most when they shape the small decisions: how clearly we communicate, how thoughtfully support is arranged, and whether each person feels respected throughout.</p>
            </div>
            <div class="values-grid">
                <article class="value-card"><span class="number">01</span><h3>Dignity comes first.</h3><p>Support should add freedom and comfort—never make someone feel managed. The person receiving care stays at the center of every decision.</p></article>
                <article class="value-card"><span class="number">02</span><h3>Trust lives in the details.</h3><p>Clear expectations, honest communication, and reliable follow-through are not extras. They are the foundation of good support.</p></article>
                <article class="value-card"><span class="number">03</span><h3>Flexibility is a form of care.</h3><p>Needs change from day to day. Support should adapt, whether that means one useful hour or a dependable weekly rhythm.</p></article>
                <article class="value-card"><span class="number">04</span><h3>Caregivers deserve respect.</h3><p>Meaningful work starts with clear information, thoughtful matches, and respect for a caregiver’s time, judgment, and contribution.</p></article>
            </div>
        </section>

        <section class="promises" aria-labelledby="promises-title">
            <div class="promise-photo">
                <img src="{{ asset('images/marketing/homepage/families.jpg') }}" alt="A caregiver walking outdoors with an older woman" width="1000" height="1250" loading="lazy" decoding="async">
            </div>
            <div>
                <p class="eyebrow">One service, three promises</p>
                <h2 id="promises-title">Built for everyone care touches.</h2>
                <p class="promise-intro">Good support has to work for the older adult, the people who love them, and the caregiver showing up at the door. LoLo is designed around all three.</p>
                <div class="promise-list">
                    <div class="promise-item"><span>Older adults</span><div><strong>Choice, comfort, and familiar routines.</strong><p>Care fits around the person—not the other way around.</p></div></div>
                    <div class="promise-item"><span>Families</span><div><strong>Visibility without micromanaging.</strong><p>Stay informed and organized without becoming the full-time care coordinator.</p></div></div>
                    <div class="promise-item"><span>Caregivers</span><div><strong>Clear expectations and meaningful work.</strong><p>Know what matters before the visit and focus on giving thoughtful support.</p></div></div>
                </div>
            </div>
        </section>

        <section class="scope" aria-labelledby="scope-title">
            <div>
                <p class="eyebrow">What we do</p>
                <h2 id="scope-title">Practical help.<br>Human impact.</h2>
            </div>
            <div class="scope-copy">
                <p>LoLo connects families with flexible, everyday support that helps older adults stay comfortable, connected, and confident at home.</p>
                <ul class="support-list" aria-label="Types of support">
                    <li>Companionship</li>
                    <li>Rides & errands</li>
                    <li>Meal preparation</li>
                    <li>Light housekeeping</li>
                    <li>Respite support</li>
                    <li>Everyday routines</li>
                </ul>
                <p class="boundary"><strong>Clear boundaries build trust.</strong> LoLo provides non-medical home support. It is not a medical provider or an emergency service.</p>
            </div>
        </section>

        <section class="north-star" aria-label="Our north star">
            <p class="eyebrow">Our north star</p>
            <blockquote>More independence for older adults. More confidence for families. More <em>meaningful work</em> for caregivers.</blockquote>
        </section>

        <section class="final-cta">
            <div>
                <p class="eyebrow">Care that fits real life</p>
                <h2>A little support can make room for a lot more life.</h2>
            </div>
            <div class="final-actions">
                <a class="button" href="{{ route('register') }}">Find care</a>
                <a href="{{ route('caregiver.register') }}">Join as a caregiver →</a>
            </div>
        </section>

        <footer class="landing-footer">
            <div class="footer-brand">
                <a class="brand" href="{{ route('landing') }}" aria-label="LoLo Care home">
                    <img src="{{ asset('images/marketing/lolo/lolo-wordmark-evergreen.svg') }}" alt="LoLo Care" width="652" height="222">
                </a>
                <p>The trust-and-coordination layer for aging at home.</p>
            </div>
            <div><strong>Families</strong><a href="{{ route('register') }}">Find care</a><a href="{{ route('landing') }}#how">How it works</a><a href="{{ route('landing') }}#safety">Safety</a></div>
            <div><strong>Caregivers</strong><a href="{{ route('caregiver.register') }}">Become a caregiver</a><a href="{{ route('login') }}">Caregiver login</a></div>
            <div><strong>Company</strong><a href="{{ route('about') }}">About LoLo</a><a href="{{ route('blog.index') }}">Resources</a><a href="mailto:hello@carelolo.com">Contact</a><a href="{{ route('legal.index') }}">Legal & privacy</a></div>
            <p class="copyright">© {{ now()->year }} LoLo Care Inc. Non-medical home support.</p>
        </footer>
    </main>
@endsection
