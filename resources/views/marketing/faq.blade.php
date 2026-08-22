@extends('layouts.marketing-lean')

@php
    $faqTotal = collect($faqCategories)->sum(fn (array $category) => count($category['faqs']));
    $faqItems = collect($faqCategories)->flatMap(fn (array $category) => $category['faqs'])->values();
@endphp

@section('title', 'Frequently Asked Questions | LoLo Care')
@section('meta_description', 'Clear answers about LoLo care, caregivers, booking, payments, visit updates, family coordination, and caregiver opportunities.')
@section('canonical', route('faq'))
@section('og_image', asset('images/marketing/homepage/human-moment.jpg'))
@section('og_image_alt', 'A caregiver and older adult sharing a warm moment at home.')

@section('structured_data')
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => $faqItems->map(fn (array $faq) => [
            '@type' => 'Question',
            'name' => $faq['question'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $faq['answer'],
            ],
        ])->all(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endsection

@push('head')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600&display=optional">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600&display=optional" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600&display=optional" rel="stylesheet"></noscript>
@endpush

@section('content')
    <style>
        .lolo-faq {
            --cream: #fff7ea;
            --oat: #f1e5d2;
            --green: #173f35;
            --deep: #10372f;
            --coral: #b95745;
            --ink: #24302d;
            --muted: #6f766f;
            --line: #dfd4c2;
            min-height: 100vh;
            overflow-x: clip;
            background: var(--cream);
            color: var(--ink);
            font-family: 'DM Sans', sans-serif;
            font-size: 16px;
            line-height: 1.55;
        }
        html, body { margin: 0; scroll-behavior: smooth; }
        .lolo-faq *, .lolo-faq *::before, .lolo-faq *::after { box-sizing: border-box; }
        .lolo-faq a { color: inherit; text-decoration: none; }
        .lolo-faq button, .lolo-faq input { font: inherit; }
        .lolo-faq img { display: block; max-width: 100%; }
        .lolo-faq h1, .lolo-faq h2, .lolo-faq h3 { color: var(--green); font-family: 'Fraunces', serif; font-optical-sizing: auto; }
        .lolo-faq h1 { max-width: 980px; margin: 0 auto 28px; font-size: clamp(3.4rem, 6.4vw, 7rem); font-weight: 500; letter-spacing: -.055em; line-height: .93; }
        .lolo-faq h1 em { color: var(--coral); font-style: normal; }
        .lolo-faq h2 { margin: 0; font-size: clamp(2.55rem, 4.2vw, 4.8rem); font-weight: 500; letter-spacing: -.04em; line-height: .98; }
        .lolo-faq h3 { margin: 0; font-size: 1.8rem; font-weight: 500; }
        .lolo-faq p { margin-top: 0; }
        .lolo-faq .eyebrow { margin-bottom: 18px; color: var(--coral); font-size: .78rem; font-weight: 600; letter-spacing: .18em; text-transform: uppercase; }
        .lolo-faq .button { display: inline-flex; align-items: center; justify-content: center; border: 0; border-radius: 999px; background: var(--green); color: #fff; cursor: pointer; font-weight: 600; padding: 16px 26px; transition: background-color .2s, transform .2s; }
        .lolo-faq .button:hover { background: var(--coral); transform: translateY(-2px); }
        .lolo-faq .button.small { padding: 11px 20px; }
        .lolo-faq .button.coral { background: var(--coral); }
        .lolo-faq :focus-visible { outline: 3px solid rgba(185, 87, 69, .45); outline-offset: 3px; }

        .lolo-faq .nav-shell { position: sticky; top: 0; z-index: 20; display: flex; align-items: center; height: 88px; border-bottom: 1px solid rgba(42, 59, 54, .1); background: rgba(255, 247, 234, .92); padding: 0 max(4vw, 24px); backdrop-filter: blur(14px); }
        .lolo-faq .brand { display: inline-flex; align-items: center; flex: 0 0 auto; }
        .lolo-faq .brand img { width: 78px; height: auto; }
        .lolo-faq .nav-links { display: flex; gap: 34px; margin: auto; font-size: .96rem; }
        .lolo-faq .nav-links a:hover, .lolo-faq .nav-links .active { color: var(--coral); }
        .lolo-faq .nav-actions { display: flex; align-items: center; gap: 20px; font-size: .95rem; }
        .lolo-faq .caregiver-join { display: inline-flex; align-items: center; min-height: 42px; border: 1px solid rgba(185, 87, 69, .36); border-radius: 999px; color: var(--coral); padding: 8px 15px; font-weight: 700; }
        .lolo-faq .mobile-menu { display: none; margin-left: auto; }
        .lolo-faq .mobile-menu summary { cursor: pointer; font-size: .82rem; font-weight: 600; list-style: none; }
        .lolo-faq .mobile-menu summary::-webkit-details-marker { display: none; }

        .lolo-faq .hero { position: relative; overflow: hidden; padding: 105px max(5vw, 30px) 145px; text-align: center; }
        .lolo-faq .hero::before, .lolo-faq .hero::after { position: absolute; border: 1px solid rgba(185, 87, 69, .18); border-radius: 50%; content: ''; }
        .lolo-faq .hero::before { top: 70px; left: -100px; width: 260px; height: 260px; }
        .lolo-faq .hero::after { right: -70px; bottom: 15px; width: 190px; height: 190px; }
        .lolo-faq .hero-lede { max-width: 720px; margin: 0 auto; color: var(--muted); font-size: 1.12rem; line-height: 1.75; }
        .lolo-faq .search-shell { position: relative; z-index: 2; display: flex; align-items: center; max-width: 800px; margin: 40px auto 15px; border: 1px solid var(--line); border-radius: 999px; background: #fffaf1; padding: 8px 8px 8px 24px; box-shadow: 0 22px 65px rgba(35, 48, 45, .11); }
        .lolo-faq .search-shell::before { margin-right: 14px; color: var(--coral); content: '⌕'; font-size: 1.7rem; line-height: 1; transform: rotate(-15deg); }
        .lolo-faq .search-shell input { min-width: 0; flex: 1; border: 0; outline: 0; background: transparent; color: var(--ink); font-size: 1.05rem; }
        .lolo-faq .search-shell input::placeholder { color: #979b95; }
        .lolo-faq .search-clear { border: 0; border-radius: 999px; background: var(--oat); color: var(--green); cursor: pointer; padding: 12px 18px; font-weight: 700; }
        .lolo-faq .search-clear[hidden] { display: none; }
        .lolo-faq .result-count { min-height: 24px; margin-bottom: 0; color: var(--muted); font-size: .84rem; }

        .lolo-faq .quick-paths { position: relative; z-index: 4; max-width: 1380px; margin: -70px auto 0; padding: 0 max(4vw, 24px); }
        .lolo-faq .quick-card { border: 1px solid var(--line); border-radius: 30px; background: #fffaf1; padding: 30px; box-shadow: 0 24px 70px rgba(35, 48, 45, .1); }
        .lolo-faq .quick-card .eyebrow { margin-bottom: 12px; }
        .lolo-faq .quick-links { display: grid; grid-template-columns: repeat(4, 1fr); border-top: 1px solid var(--line); }
        .lolo-faq .quick-links a { display: flex; justify-content: space-between; gap: 14px; padding: 22px 16px; color: var(--green); font-weight: 700; }
        .lolo-faq .quick-links a + a { border-left: 1px solid var(--line); }
        .lolo-faq .quick-links a span { color: var(--coral); }

        .lolo-faq .faq-shell { display: grid; grid-template-columns: 260px minmax(0, 1fr); gap: clamp(55px, 8vw, 120px); max-width: 1380px; margin: auto; padding: 125px max(5vw, 36px) 150px; }
        .lolo-faq .topic-nav { position: sticky; top: 118px; align-self: start; }
        .lolo-faq .topic-nav strong { display: block; margin-bottom: 18px; color: var(--green); font-family: 'Fraunces', serif; font-size: 1.45rem; font-weight: 500; }
        .lolo-faq .topic-nav a { display: flex; align-items: center; justify-content: space-between; gap: 18px; border-top: 1px solid var(--line); color: var(--muted); padding: 13px 0; font-size: .88rem; }
        .lolo-faq .topic-nav a:hover { color: var(--coral); }
        .lolo-faq .topic-nav small { display: grid; place-items: center; min-width: 26px; height: 26px; border-radius: 50%; background: var(--oat); color: var(--green); font-size: .68rem; }
        .lolo-faq .topic-help { margin-top: 28px; border-left: 2px solid var(--coral); color: var(--muted); padding-left: 15px; font-size: .82rem; line-height: 1.6; }
        .lolo-faq .topic-help a { display: inline; border: 0; color: var(--green); padding: 0; font-weight: 700; }

        .lolo-faq .faq-category { scroll-margin-top: 120px; padding-bottom: 100px; }
        .lolo-faq .faq-category:last-of-type { padding-bottom: 0; }
        .lolo-faq .category-head { display: grid; grid-template-columns: 1fr .7fr; align-items: end; gap: 45px; margin-bottom: 34px; }
        .lolo-faq .category-head p { margin-bottom: 5px; color: var(--muted); line-height: 1.7; }
        .lolo-faq .faq-item { border-top: 1px solid var(--line); }
        .lolo-faq .faq-item:last-child { border-bottom: 1px solid var(--line); }
        .lolo-faq .faq-item summary { position: relative; cursor: pointer; padding: 24px 50px 24px 0; color: var(--green); font-weight: 700; list-style: none; }
        .lolo-faq .faq-item summary::-webkit-details-marker { display: none; }
        .lolo-faq .faq-item summary::after { position: absolute; top: 19px; right: 4px; color: var(--coral); content: '+'; font-size: 1.55rem; font-weight: 400; }
        .lolo-faq .faq-item[open] summary::after { content: '−'; }
        .lolo-faq .faq-item p { max-width: 790px; margin-bottom: 0; color: var(--muted); padding: 0 45px 25px 0; line-height: 1.75; }
        .lolo-faq .no-results { border: 1px dashed var(--line); border-radius: 24px; padding: 45px; text-align: center; }
        .lolo-faq .no-results[hidden] { display: none; }
        .lolo-faq .no-results p { color: var(--muted); }

        .lolo-faq .final-cta { margin: 0 max(4vw, 24px) 80px; border-radius: 34px; background: var(--deep); color: var(--cream); padding: 100px 30px; text-align: center; }
        .lolo-faq .final-cta h2 { color: var(--cream); }
        .lolo-faq .final-cta > p:not(.eyebrow) { max-width: 650px; margin: 0 auto 30px; color: #c0cbc7; line-height: 1.7; }
        .lolo-faq .final-cta div { display: flex; align-items: center; justify-content: center; gap: 24px; }

        .lolo-faq .landing-footer { display: grid; grid-template-columns: 2fr repeat(3, 1fr); gap: 50px; border-top: 1px solid var(--line); padding: 70px max(5vw, 36px) 30px; }
        .lolo-faq .landing-footer > div { display: flex; flex-direction: column; align-items: flex-start; gap: 12px; font-size: .92rem; }
        .lolo-faq .footer-brand .brand img { width: 84px; }
        .lolo-faq .footer-brand p { max-width: 300px; color: var(--muted); line-height: 1.6; }
        .lolo-faq .copyright { grid-column: 1 / -1; margin-top: 20px; border-top: 1px solid var(--line); color: var(--muted); padding-top: 25px; font-size: .76rem; }

        @media (max-width: 980px) {
            .lolo-faq .desktop-nav { display: none; }
            .lolo-faq .mobile-menu { display: block; }
            .lolo-faq .mobile-menu nav { position: absolute; top: 88px; right: 0; left: 0; display: flex; flex-direction: column; gap: 18px; border-bottom: 1px solid var(--line); background: var(--cream); padding: 24px; box-shadow: 0 20px 30px rgba(35, 48, 45, .08); }
            .lolo-faq .nav-actions { margin-left: 18px; }
            .lolo-faq .nav-actions .caregiver-join, .lolo-faq .nav-actions .sign-in { display: none; }
            .lolo-faq .quick-links { grid-template-columns: 1fr 1fr; }
            .lolo-faq .quick-links a:nth-child(3) { border-top: 1px solid var(--line); border-left: 0; }
            .lolo-faq .quick-links a:nth-child(4) { border-top: 1px solid var(--line); }
            .lolo-faq .faq-shell { grid-template-columns: 1fr; }
            .lolo-faq .topic-nav { position: static; }
            .lolo-faq .topic-nav > div { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 8px; scrollbar-width: thin; }
            .lolo-faq .topic-nav a { flex: 0 0 auto; border: 1px solid var(--line); border-radius: 999px; padding: 9px 13px; }
            .lolo-faq .topic-nav small { display: none; }
            .lolo-faq .topic-help { display: none; }
            .lolo-faq .landing-footer { grid-template-columns: 1fr 1fr; }
            .lolo-faq .footer-brand { grid-column: 1 / -1; }
        }

        @media (max-width: 560px) {
            .lolo-faq h1 { font-size: 3.1rem; }
            .lolo-faq .nav-shell { height: 72px; }
            .lolo-faq .nav-actions .button { padding: 9px 14px; }
            .lolo-faq .mobile-menu nav { top: 72px; }
            .lolo-faq .hero { padding: 75px 22px 130px; }
            .lolo-faq .search-shell { border-radius: 24px; padding-left: 16px; }
            .lolo-faq .search-clear { padding: 10px 12px; }
            .lolo-faq .quick-paths { padding: 0 12px; }
            .lolo-faq .quick-card { padding: 22px; }
            .lolo-faq .quick-links { grid-template-columns: 1fr; }
            .lolo-faq .quick-links a + a, .lolo-faq .quick-links a:nth-child(4) { border-top: 1px solid var(--line); border-left: 0; }
            .lolo-faq .faq-shell { gap: 70px; padding: 90px 22px 110px; }
            .lolo-faq .category-head { grid-template-columns: 1fr; gap: 10px; }
            .lolo-faq .faq-category { padding-bottom: 80px; }
            .lolo-faq .final-cta { margin: 0 12px 60px; padding: 70px 22px; }
            .lolo-faq .final-cta div { flex-direction: column; }
            .lolo-faq .landing-footer { grid-template-columns: 1fr 1fr; padding: 60px 22px; }
            .lolo-faq .copyright { grid-column: 1 / -1; }
        }

        @media (prefers-reduced-motion: reduce) { html, body { scroll-behavior: auto; } .lolo-faq *, .lolo-faq *::before, .lolo-faq *::after { transition: none !important; } }
    </style>

    <main class="lolo-faq">
        <header class="nav-shell">
            <a class="brand" href="{{ route('landing') }}" aria-label="LoLo Care home"><img src="{{ asset('images/marketing/lolo/lolo-wordmark-evergreen.svg') }}" alt="LoLo Care" width="652" height="222"></a>
            <nav class="nav-links desktop-nav" aria-label="Main navigation"><a href="{{ route('landing') }}#how">How it works</a><a href="{{ route('caregivers.search') }}">Caregivers</a><a href="{{ route('landing.family') }}">For families</a><a href="{{ route('landing') }}#safety">Safety</a><a href="{{ route('about') }}">About</a><a class="active" href="{{ route('faq') }}" aria-current="page">FAQs</a></nav>
            <details class="mobile-menu"><summary aria-label="Open navigation menu">Menu</summary><nav aria-label="Mobile navigation"><a href="{{ route('landing') }}#how">How it works</a><a href="{{ route('caregivers.search') }}">Caregivers</a><a href="{{ route('landing.family') }}">For families</a><a href="{{ route('landing') }}#safety">Safety</a><a href="{{ route('about') }}">About</a><a href="{{ route('faq') }}" aria-current="page">FAQs</a><a href="{{ route('caregiver.register') }}">Become a caregiver</a><a href="{{ route('login') }}">Sign in</a></nav></details>
            <div class="nav-actions"><a class="caregiver-join" href="{{ route('caregiver.register') }}">Caregiver? Join LoLo</a><a class="sign-in" href="{{ route('login') }}">Sign in</a><a class="button small" href="{{ route('register') }}">Find care</a></div>
        </header>

        <section class="hero" id="top">
            <p class="eyebrow">LoLo help center</p>
            <h1>Questions about care? <em>Start here.</em></h1>
            <p class="hero-lede">Search clear answers for families, older adults, and caregivers—from choosing support to completing a visit.</p>
            <label class="search-shell" for="faq-search"><input id="faq-search" type="search" autocomplete="off" placeholder="Try “booking”, “background checks”, or “getting paid”"><button class="search-clear" type="button" hidden>Clear</button></label>
            <p class="result-count" aria-live="polite">Browse {{ $faqTotal }} answers across {{ count($faqCategories) }} topics.</p>
        </section>

        <section class="quick-paths" aria-label="Popular FAQ topics">
            <div class="quick-card"><p class="eyebrow">Popular paths</p><div class="quick-links">@foreach(collect($faqCategories)->take(4) as $category)<a href="#{{ $category['slug'] }}">{{ $category['title'] }}<span>↓</span></a>@endforeach</div></div>
        </section>

        <div class="faq-shell">
            <aside class="topic-nav" aria-label="FAQ topics"><strong>Browse topics</strong><div>@foreach($faqCategories as $category)<a href="#{{ $category['slug'] }}">{{ $category['title'] }}<small>{{ count($category['faqs']) }}</small></a>@endforeach</div><p class="topic-help">Still unsure? Call or text <a href="tel:9844004008">(984) 400-4008</a>.</p></aside>
            <div class="faq-content">
                @foreach($faqCategories as $category)
                    <section class="faq-category" id="{{ $category['slug'] }}" data-faq-category>
                        <div class="category-head"><div><p class="eyebrow">Topic {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</p><h2>{{ $category['title'] }}</h2></div><p>{{ $category['description'] }}</p></div>
                        <div>@foreach($category['faqs'] as $faq)<details class="faq-item" data-faq-item><summary>{{ $faq['question'] }}</summary><p>{{ $faq['answer'] }}</p></details>@endforeach</div>
                    </section>
                @endforeach
                <div class="no-results" hidden><h3>No exact answer found.</h3><p>Try a shorter search, or contact LoLo and we’ll point you in the right direction.</p><a class="button" href="mailto:hello@carelolo.com">Email LoLo</a></div>
            </div>
        </div>

        <section class="final-cta"><p class="eyebrow">Still have a question?</p><h2>Let’s find the right next step.</h2><p>Tell us whether you are looking for care or interested in caregiving, and we’ll guide you from there.</p><div><a class="button coral" href="{{ route('register') }}">Find care</a><a href="{{ route('caregiver.register') }}">Become a caregiver →</a></div></section>

        <footer class="landing-footer"><div class="footer-brand"><a class="brand" href="#top" aria-label="Back to the top"><img src="{{ asset('images/marketing/lolo/lolo-wordmark-evergreen.svg') }}" alt="LoLo Care" width="652" height="222"></a><p>The trust-and-coordination layer for aging at home.</p></div><div><strong>Families</strong><a href="{{ route('register') }}">Find care</a><a href="{{ route('landing') }}#how">How it works</a><a href="{{ route('landing') }}#safety">Safety</a></div><div><strong>Caregivers</strong><a href="{{ route('caregiver.register') }}">Become a caregiver</a><a href="{{ route('landing.caregiver') }}">Caregiver opportunities</a><a href="{{ route('login') }}">Caregiver login</a></div><div><strong>Company</strong><a href="{{ route('about') }}">About LoLo</a><a href="{{ route('faq') }}">FAQs</a><a href="{{ route('blog.index') }}">Resources</a><a href="mailto:hello@carelolo.com">Contact</a><a href="{{ route('legal.index') }}">Legal & privacy</a></div><p class="copyright">© {{ now()->year }} LoLo Care Inc. Non-medical home support.</p></footer>
    </main>

    <script>
        (() => {
            const input = document.getElementById('faq-search');
            const clearButton = document.querySelector('.search-clear');
            const resultCount = document.querySelector('.result-count');
            const categories = [...document.querySelectorAll('[data-faq-category]')];
            const items = [...document.querySelectorAll('[data-faq-item]')];
            const noResults = document.querySelector('.no-results');
            const total = items.length;

            const filterFaqs = () => {
                const query = input.value.trim().toLocaleLowerCase();
                let visible = 0;

                items.forEach((item) => {
                    const matches = query === '' || item.textContent.toLocaleLowerCase().includes(query);
                    item.hidden = !matches;
                    visible += matches ? 1 : 0;
                });

                categories.forEach((category) => {
                    category.hidden = !category.querySelector('[data-faq-item]:not([hidden])');
                });

                clearButton.hidden = query === '';
                noResults.hidden = visible !== 0;
                resultCount.textContent = query === ''
                    ? `Browse ${total} answers across ${categories.length} topics.`
                    : `${visible} ${visible === 1 ? 'answer' : 'answers'} found for “${input.value.trim()}”.`;
            };

            input.addEventListener('input', filterFaqs);
            input.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    input.value = '';
                    filterFaqs();
                }
            });
            clearButton.addEventListener('click', () => {
                input.value = '';
                filterFaqs();
                input.focus();
            });
        })();
    </script>
@endsection
