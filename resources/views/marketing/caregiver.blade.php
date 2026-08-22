@extends('layouts.marketing-lean')

@php
    $caregiverFaqs = [
        ['question' => 'How are earnings shown?', 'answer' => 'Every eligible shift shows the expected visit length and estimated payout before you claim it. After a completed visit, LoLo shows the payout status as it moves to your account.'],
        ['question' => 'Can I choose which visits I accept?', 'answer' => 'Yes. You set your availability and service area, review each eligible request, and decide which visits fit your schedule, location, and comfort level.'],
        ['question' => 'What kind of support do LoLo caregivers provide?', 'answer' => 'LoLo caregivers provide non-medical support such as companionship, errands, rides where appropriate, meal preparation, light household routines, reminders, and respite support.'],
        ['question' => 'What do I need before I can be matched?', 'answer' => 'You will complete your profile, service area, availability, task preferences, identity verification, background screening, and any other required onboarding review before matching.'],
        ['question' => 'Do I need professional caregiving experience?', 'answer' => 'Relevant experience and training can strengthen your profile, but reliability, good judgment, patience, and clear communication matter too. Your profile should accurately describe your experience and the tasks you are comfortable providing.'],
        ['question' => 'Does creating a profile guarantee visits?', 'answer' => 'No. Visit availability depends on family demand, location, timing, task fit, and completion of required onboarding. You remain free to accept or decline each eligible request.'],
    ];
@endphp

@section('title', 'Caregiver Opportunities | Flexible Shifts with LoLo')
@section('meta_description', 'Choose local caregiver shifts that fit your schedule. Claim a visit, track your time in LoLo, complete the work, and follow your payout in one place.')
@section('canonical', route('landing.caregiver'))
@section('og_image', asset('images/marketing/caregiver-hero-raleigh.jpg'))
@section('og_image_alt', 'A LoLo caregiver enjoying time with an older adult at home.')

@section('structured_data')
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => collect($caregiverFaqs)->map(fn (array $faq) => [
            '@type' => 'Question',
            'name' => $faq['question'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $faq['answer'],
            ],
        ])->values()->all(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endsection

@push('head')
    <link rel="preload" as="image" href="{{ asset('images/marketing/caregiver-hero-raleigh.jpg') }}" fetchpriority="high">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600&display=optional">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600&display=optional" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600&display=optional" rel="stylesheet"></noscript>
@endpush

@section('content')
    <style>
        .lolo-caregiver {
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

        html, body { margin: 0; }
        .lolo-caregiver *, .lolo-caregiver *::before, .lolo-caregiver *::after { box-sizing: border-box; }
        .lolo-caregiver a { color: inherit; text-decoration: none; }
        .lolo-caregiver img { display: block; max-width: 100%; }
        .lolo-caregiver h1, .lolo-caregiver h2, .lolo-caregiver h3 { color: var(--green); font-family: 'Fraunces', serif; font-optical-sizing: auto; }
        .lolo-caregiver h1 { margin: 0 0 28px; font-size: clamp(3.15rem, 5.15vw, 5.7rem); font-weight: 500; letter-spacing: -.05em; line-height: .95; }
        .lolo-caregiver h1 em { color: var(--coral); font-style: normal; }
        .lolo-caregiver h2 { margin: 0 0 22px; font-size: clamp(2.6rem, 4vw, 4.7rem); font-weight: 500; letter-spacing: -.04em; line-height: 1; }
        .lolo-caregiver h3 { margin: 0; font-size: 1.75rem; font-weight: 500; line-height: 1.08; }
        .lolo-caregiver p { margin-top: 0; }
        .lolo-caregiver .eyebrow { margin-bottom: 18px; color: var(--coral); font-size: .78rem; font-weight: 600; letter-spacing: .18em; text-transform: uppercase; }
        .lolo-caregiver .button { display: inline-flex; align-items: center; justify-content: center; border: 0; border-radius: 999px; background: var(--green); color: #fff; cursor: pointer; font-weight: 600; padding: 16px 26px; transition: background-color .2s, transform .2s; }
        .lolo-caregiver .button:hover { background: var(--coral); transform: translateY(-2px); }
        .lolo-caregiver .button.small { padding: 11px 20px; }
        .lolo-caregiver .button.coral { background: var(--coral); }
        .lolo-caregiver .text-link { border-bottom: 1px solid; font-weight: 600; }
        .lolo-caregiver :focus-visible { outline: 3px solid rgba(185, 87, 69, .45); outline-offset: 3px; }

        .lolo-caregiver .nav-shell { position: sticky; top: 0; z-index: 20; display: flex; align-items: center; height: 88px; border-bottom: 1px solid rgba(42, 59, 54, .1); background: rgba(255, 247, 234, .92); padding: 0 max(4vw, 24px); backdrop-filter: blur(14px); }
        .lolo-caregiver .brand { display: inline-flex; align-items: center; flex: 0 0 auto; }
        .lolo-caregiver .brand img { width: 78px; height: auto; }
        .lolo-caregiver .nav-links { display: flex; gap: 34px; margin: auto; font-size: .96rem; }
        .lolo-caregiver .nav-links a:hover { color: var(--coral); }
        .lolo-caregiver .nav-actions { display: flex; align-items: center; gap: 20px; font-size: .95rem; }
        .lolo-caregiver .family-switch { display: inline-flex; align-items: center; min-height: 42px; border: 1px solid rgba(185, 87, 69, .36); border-radius: 999px; color: var(--coral); padding: 8px 15px; font-weight: 700; }
        .lolo-caregiver .family-switch:hover { border-color: var(--green); color: var(--green); }
        .lolo-caregiver .mobile-menu { display: none; margin-left: auto; }
        .lolo-caregiver .mobile-menu summary { cursor: pointer; font-size: .82rem; font-weight: 600; list-style: none; }
        .lolo-caregiver .mobile-menu summary::-webkit-details-marker { display: none; }

        .lolo-caregiver .hero { display: grid; grid-template-columns: 1.1fr .9fr; align-items: center; gap: 5vw; max-width: 1440px; min-height: 760px; margin: auto; overflow: hidden; padding: 70px max(5vw, 36px) 120px; }
        .lolo-caregiver .hero-copy { max-width: 760px; }
        .lolo-caregiver .lede { max-width: 650px; color: #53605b; font-size: 1.15rem; line-height: 1.72; }
        .lolo-caregiver .hero-actions { display: flex; align-items: center; gap: 30px; margin: 34px 0 22px; }
        .lolo-caregiver .reassurance { color: var(--muted); font-size: .88rem; }
        .lolo-caregiver .reassurance strong { color: var(--green); }
        .lolo-caregiver .hero-visual { position: relative; display: flex; align-items: center; justify-content: center; height: 610px; }
        .lolo-caregiver .hero-visual::before { position: absolute; width: 520px; height: 520px; border-radius: 50%; background: var(--oat); content: ''; }
        .lolo-caregiver .hero-visual::after { position: absolute; right: 5%; bottom: 8%; width: 86px; height: 86px; border: 1px solid rgba(185, 87, 69, .32); border-radius: 50%; content: ''; }
        .lolo-caregiver .hero-visual img { position: relative; z-index: 1; width: 500px; height: 500px; border-radius: 50%; object-fit: cover; object-position: center 25%; box-shadow: 0 24px 65px rgba(35, 48, 45, .1); }

        .lolo-caregiver .snapshot-wrap { position: relative; z-index: 5; margin-top: -65px; padding: 0 max(4vw, 24px); }
        .lolo-caregiver .snapshot-card { display: grid; grid-template-columns: repeat(6, 1fr); gap: 0; max-width: 1380px; margin: auto; border: 1px solid var(--line); border-radius: 30px; background: #fffaf1; padding: 30px; box-shadow: 0 24px 70px rgba(35, 48, 45, .11); }
        .lolo-caregiver .snapshot-heading { grid-column: 1 / -1; margin-bottom: 20px; }
        .lolo-caregiver .snapshot-heading .eyebrow { margin-bottom: 7px; }
        .lolo-caregiver .snapshot-heading h2 { margin-bottom: 0; font-size: 2rem; }
        .lolo-caregiver .snapshot-intro { max-width: 760px; margin: 10px 0 0; color: var(--muted); line-height: 1.65; }
        .lolo-caregiver .snapshot-item { min-height: 235px; padding: 20px 16px; }
        .lolo-caregiver .snapshot-item + .snapshot-item { border-left: 1px solid var(--line); }
        .lolo-caregiver .snapshot-item span { display: block; margin-bottom: 8px; color: var(--coral); font-size: .72rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
        .lolo-caregiver .snapshot-item strong { display: block; color: var(--green); font-family: 'Fraunces', serif; font-size: 1.45rem; font-weight: 500; line-height: 1.05; }
        .lolo-caregiver .snapshot-item p { margin: 9px 0 0; color: var(--muted); font-size: .88rem; line-height: 1.6; }
        .lolo-caregiver .shift-state { display: grid; place-items: center; width: 70px; min-height: 44px; margin: 22px 0 24px; border: 1px solid var(--line); border-radius: 999px; background: var(--cream); color: var(--green); font-size: .72rem; font-weight: 800; letter-spacing: .05em; }
        .lolo-caregiver .snapshot-item.start .shift-state { border-color: var(--green); background: var(--green); color: #fff; }
        .lolo-caregiver .snapshot-item.care .shift-state { width: 92px; background: var(--oat); font-variant-numeric: tabular-nums; }
        .lolo-caregiver .snapshot-item.stop .shift-state { border: 2px solid var(--coral); color: var(--coral); }
        .lolo-caregiver .snapshot-item.paid .shift-state { border-color: #b9c8b6; background: #e2eadf; }
        .lolo-caregiver .snapshot-note { grid-column: 1 / -1; margin: 14px 26px 0; border-top: 1px solid var(--line); color: var(--muted); padding-top: 16px; font-size: .75rem; }

        .lolo-caregiver .section { max-width: 1440px; margin: auto; padding: 140px max(5vw, 36px); }
        .lolo-caregiver .section-head { display: grid; grid-template-columns: 1.3fr .7fr; align-items: end; gap: 80px; margin-bottom: 55px; }
        .lolo-caregiver .section-head > p { max-width: 470px; color: var(--muted); line-height: 1.7; }
        .lolo-caregiver .value-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; }
        .lolo-caregiver .value-grid article { border-top: 2px solid var(--coral); padding: 22px 4px 0; }
        .lolo-caregiver .value-grid h3 { margin-bottom: 12px; font-size: 1.55rem; }
        .lolo-caregiver .value-grid p { margin-bottom: 0; color: var(--muted); font-size: .92rem; line-height: 1.65; }

        .lolo-caregiver .dark-section { background: var(--deep); color: var(--cream); padding: 130px max(5vw, 36px); }
        .lolo-caregiver .dark-section > .section-head, .lolo-caregiver .steps { max-width: 1300px; margin-right: auto; margin-left: auto; }
        .lolo-caregiver .dark-section h2, .lolo-caregiver .dark-section h3 { color: var(--cream); }
        .lolo-caregiver .section-head.light > p { color: #b8c5bf; }
        .lolo-caregiver .steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .lolo-caregiver .steps > article { border: 1px solid #396057; border-radius: 26px; background: #1d4b40; padding: 28px; }
        .lolo-caregiver .steps > article > span { color: #d48974; font-weight: 700; }
        .lolo-caregiver .steps article > p { margin-bottom: 0; color: #c7d0cc; line-height: 1.6; }
        .lolo-caregiver .step-preview { display: flex; flex-direction: column; justify-content: center; gap: 10px; height: 190px; margin: 28px 0; border-radius: 18px; background: var(--cream); color: var(--ink); padding: 22px; }
        .lolo-caregiver .profile-preview { align-items: center; text-align: center; }
        .lolo-caregiver .profile-preview i { display: grid; place-items: center; width: 62px; height: 62px; border-radius: 50%; background: #efc0a8; color: var(--green); font-style: normal; font-weight: 700; }
        .lolo-caregiver .profile-preview small, .lolo-caregiver .visit-preview small { color: var(--muted); }
        .lolo-caregiver .check-preview b { border: 1px solid var(--line); border-radius: 10px; padding: 9px; font-size: .87rem; }
        .lolo-caregiver .visit-preview { justify-content: flex-start; }
        .lolo-caregiver .visit-preview strong { color: var(--green); font-family: 'Fraunces', serif; font-size: 1.35rem; }
        .lolo-caregiver .visit-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 4px; }
        .lolo-caregiver .visit-meta span { border: 1px solid var(--line); border-radius: 10px; padding: 8px; font-size: .78rem; }
        .lolo-caregiver .visit-meta b { display: block; color: var(--green); }

        .lolo-caregiver .earnings { display: grid; grid-template-columns: 1fr 1fr; align-items: center; gap: 12vw; background: var(--oat); padding: 120px max(8vw, 36px); }
        .lolo-caregiver .earnings-copy > p:not(.eyebrow) { max-width: 620px; color: var(--muted); line-height: 1.7; }
        .lolo-caregiver .earnings-copy .text-link { display: inline-block; margin-top: 14px; }
        .lolo-caregiver .money-card { border-radius: 32px; background: var(--green); color: #fff; padding: 40px; text-align: center; box-shadow: 20px 20px #d4a18d; }
        .lolo-caregiver .money-card > span { display: block; color: #efaa96; font-size: .72rem; font-weight: 700; letter-spacing: .15em; text-transform: uppercase; }
        .lolo-caregiver .money-card > strong { display: block; margin: 18px 0 12px; font: 500 clamp(3.35rem, 5.4vw, 5.8rem) 'Fraunces', serif; letter-spacing: -.04em; line-height: .95; }
        .lolo-caregiver .money-card > p { color: rgba(255, 255, 255, .72); }
        .lolo-caregiver .money-card > div { display: grid; grid-template-columns: repeat(2, 1fr); margin-top: 20px; border-top: 1px solid #58736d; border-bottom: 1px solid #58736d; }
        .lolo-caregiver .money-card > div p { margin: 0; padding: 20px 10px; }
        .lolo-caregiver .money-card > div p + p { border-left: 1px solid #58736d; }
        .lolo-caregiver .money-card > div small { display: block; color: #aebfba; font-size: .65rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        .lolo-caregiver .money-card .fee-note { margin: 16px 0 0; color: #9eb0a9; font-size: .75rem; text-align: left; }

        .lolo-caregiver .work { display: grid; grid-template-columns: .85fr 1.15fr; align-items: center; gap: 8vw; }
        .lolo-caregiver .work-image { position: relative; padding: 0 0 34px 34px; }
        .lolo-caregiver .work-image::before { position: absolute; bottom: 0; left: 0; width: 72%; height: 65%; border-radius: 28px; background: var(--oat); content: ''; }
        .lolo-caregiver .work-image img { position: relative; z-index: 1; width: 100%; max-height: 660px; border-radius: 30px; object-fit: cover; }
        .lolo-caregiver .work-copy > p:not(.eyebrow, .boundary) { color: var(--muted); font-size: 1.08rem; line-height: 1.7; }
        .lolo-caregiver .task-list { display: flex; flex-wrap: wrap; gap: 10px; margin: 30px 0 26px; padding: 0; list-style: none; }
        .lolo-caregiver .task-list li { border: 1px solid var(--line); border-radius: 999px; background: #fffaf1; padding: 9px 15px; color: var(--green); font-size: .88rem; font-weight: 600; }
        .lolo-caregiver .boundary { margin-bottom: 0; border-left: 3px solid var(--coral); color: var(--muted); padding-left: 18px; font-size: .88rem; }

        .lolo-caregiver .trust { display: grid; grid-template-columns: .8fr 1.2fr; gap: 10vw; background: var(--green); color: var(--cream); padding: 120px max(8vw, 36px); }
        .lolo-caregiver .trust h2 { color: var(--cream); }
        .lolo-caregiver .trust-copy > p:not(.eyebrow) { max-width: 520px; color: #b8c5bf; line-height: 1.7; }
        .lolo-caregiver .trust-grid { display: grid; grid-template-columns: 1fr 1fr; align-content: center; }
        .lolo-caregiver .trust-grid p { margin: 0; border-top: 1px solid #547069; padding: 20px 0; }
        .lolo-caregiver .trust-grid b { display: inline-block; width: 45px; color: #d48974; }

        .lolo-caregiver .faq { max-width: 1100px; }
        .lolo-caregiver .faq > h2 { margin-bottom: 45px; }
        .lolo-caregiver .faq-item { border-top: 1px solid var(--line); }
        .lolo-caregiver .faq-item:last-child { border-bottom: 1px solid var(--line); }
        .lolo-caregiver .faq-item summary { display: flex; justify-content: space-between; width: 100%; cursor: pointer; padding: 22px 0; font-weight: 600; list-style: none; }
        .lolo-caregiver .faq-item summary::-webkit-details-marker { display: none; }
        .lolo-caregiver .faq-item summary::after { content: '+'; font-size: 1.5rem; font-weight: 400; }
        .lolo-caregiver .faq-item[open] summary::after { content: '−'; }
        .lolo-caregiver .faq-item p { max-width: 750px; padding-bottom: 20px; color: var(--muted); line-height: 1.7; }

        .lolo-caregiver .final-cta { margin: 0 max(4vw, 24px) 80px; border-radius: 34px; background: var(--deep); color: var(--cream); padding: 100px 30px; text-align: center; }
        .lolo-caregiver .final-cta h2 { color: var(--cream); }
        .lolo-caregiver .final-cta > p:not(.eyebrow) { max-width: 620px; margin: 0 auto 30px; color: #c0cbc7; line-height: 1.7; }
        .lolo-caregiver .final-cta div { display: flex; align-items: center; justify-content: center; gap: 28px; margin-bottom: 25px; }
        .lolo-caregiver .final-cta small { color: #9eb0a9; }

        .lolo-caregiver .landing-footer { display: grid; grid-template-columns: 2fr repeat(3, 1fr); gap: 50px; border-top: 1px solid var(--line); padding: 70px max(5vw, 36px) 30px; }
        .lolo-caregiver .landing-footer > div { display: flex; flex-direction: column; align-items: flex-start; gap: 12px; font-size: .92rem; }
        .lolo-caregiver .footer-brand .brand img { width: 84px; }
        .lolo-caregiver .footer-brand p { max-width: 300px; color: var(--muted); line-height: 1.6; }
        .lolo-caregiver .copyright { grid-column: 1 / -1; margin-top: 20px; border-top: 1px solid var(--line); color: var(--muted); padding-top: 25px; font-size: .76rem; }

        @media (max-width: 1000px) { .lolo-caregiver .value-grid { grid-template-columns: 1fr 1fr; } }

        @media (max-width: 900px) {
            .lolo-caregiver h1 { font-size: clamp(3.15rem, 8vw, 4.4rem); }
            .lolo-caregiver .desktop-nav { display: none; }
            .lolo-caregiver .mobile-menu { display: block; }
            .lolo-caregiver .mobile-menu nav { position: absolute; top: 88px; right: 0; left: 0; display: flex; flex-direction: column; gap: 18px; border-bottom: 1px solid var(--line); background: var(--cream); padding: 24px; box-shadow: 0 20px 30px rgba(35, 48, 45, .08); }
            .lolo-caregiver .nav-actions { margin-left: 18px; }
            .lolo-caregiver .nav-actions .family-switch, .lolo-caregiver .nav-actions .sign-in { display: none; }
            .lolo-caregiver .hero { grid-template-columns: 1fr; padding-top: 55px; }
            .lolo-caregiver .hero-visual { height: 480px; }
            .lolo-caregiver .snapshot-card { grid-template-columns: 1fr 1fr; }
            .lolo-caregiver .snapshot-item { border-top: 1px solid var(--line); }
            .lolo-caregiver .snapshot-item + .snapshot-item { border-left: 0; }
            .lolo-caregiver .snapshot-item:nth-of-type(odd) { border-left: 1px solid var(--line); }
            .lolo-caregiver .section-head, .lolo-caregiver .steps, .lolo-caregiver .earnings, .lolo-caregiver .work, .lolo-caregiver .trust { grid-template-columns: 1fr; }
            .lolo-caregiver .work-image { max-width: 620px; }
            .lolo-caregiver .landing-footer { grid-template-columns: 1fr 1fr; }
            .lolo-caregiver .footer-brand { grid-column: 1 / -1; }
        }

        @media (max-width: 560px) {
            .lolo-caregiver h1 { font-size: 2.85rem; line-height: .98; }
            .lolo-caregiver .nav-shell { height: 72px; }
            .lolo-caregiver .nav-actions .button { padding: 9px 14px; }
            .lolo-caregiver .mobile-menu nav { top: 72px; }
            .lolo-caregiver .hero { padding: 48px 22px 100px; }
            .lolo-caregiver .hero-actions { flex-direction: column; align-items: flex-start; gap: 18px; }
            .lolo-caregiver .hero-visual { height: 370px; }
            .lolo-caregiver .hero-visual::before { width: 330px; height: 330px; }
            .lolo-caregiver .hero-visual::after { right: -4px; bottom: 16px; width: 64px; height: 64px; }
            .lolo-caregiver .hero-visual img { width: 320px; height: 320px; }
            .lolo-caregiver .snapshot-wrap { padding: 0 12px; }
            .lolo-caregiver .snapshot-card { grid-template-columns: 1fr; padding: 22px; }
            .lolo-caregiver .snapshot-heading, .lolo-caregiver .snapshot-note { grid-column: 1; }
            .lolo-caregiver .snapshot-item { min-height: 0; padding: 20px 4px; }
            .lolo-caregiver .snapshot-item + .snapshot-item, .lolo-caregiver .snapshot-item:nth-of-type(odd) { border-top: 1px solid var(--line); border-left: 0; }
            .lolo-caregiver .snapshot-note { margin: 10px 4px 0; }
            .lolo-caregiver .section, .lolo-caregiver .dark-section { padding: 95px 22px; }
            .lolo-caregiver .section-head { gap: 12px; }
            .lolo-caregiver .section-head h2 { font-size: 2.7rem; }
            .lolo-caregiver .value-grid { grid-template-columns: 1fr; }
            .lolo-caregiver .earnings, .lolo-caregiver .trust { padding: 90px 22px; }
            .lolo-caregiver .money-card { padding: 32px 20px; box-shadow: 12px 12px #d4a18d; }
            .lolo-caregiver .money-card > strong { font-size: 3.8rem; }
            .lolo-caregiver .money-card > div { grid-template-columns: 1fr; }
            .lolo-caregiver .money-card > div p + p { border-top: 1px solid #58736d; border-left: 0; }
            .lolo-caregiver .work-image { padding: 0 0 24px 24px; }
            .lolo-caregiver .trust-grid { grid-template-columns: 1fr; }
            .lolo-caregiver .final-cta { margin: 0 12px 60px; padding: 70px 22px; }
            .lolo-caregiver .final-cta div { flex-direction: column; }
            .lolo-caregiver .landing-footer { grid-template-columns: 1fr 1fr; padding: 60px 22px; }
            .lolo-caregiver .copyright { grid-column: 1 / -1; }
        }

        @media (prefers-reduced-motion: reduce) { .lolo-caregiver *, .lolo-caregiver *::before, .lolo-caregiver *::after { transition: none !important; } }
    </style>

    <main class="lolo-caregiver">
        <header class="nav-shell">
            <a class="brand" href="{{ route('landing') }}" aria-label="LoLo Care home">
                <img src="{{ asset('images/marketing/lolo/lolo-wordmark-evergreen.svg') }}" alt="LoLo Care" width="652" height="222">
            </a>
            <nav class="nav-links desktop-nav" aria-label="Main navigation">
                <a href="#why">Why LoLo</a><a href="#how">How it works</a><a href="#earnings">Pay & visits</a><a href="#work">The work</a><a href="#faq">FAQs</a>
            </nav>
            <details class="mobile-menu">
                <summary aria-label="Open navigation menu">Menu</summary>
                <nav aria-label="Mobile navigation">
                    <a href="#why">Why LoLo</a><a href="#how">How it works</a><a href="#earnings">Pay & visits</a><a href="#work">The work</a><a href="#requirements">Trust & requirements</a><a href="#faq">FAQs</a><a href="{{ route('landing') }}">For families</a><a href="{{ route('login') }}">Sign in</a>
                </nav>
            </details>
            <div class="nav-actions">
                <a class="family-switch" href="{{ route('landing') }}">For families</a><a class="sign-in" href="{{ route('login') }}">Sign in</a><a class="button small" href="{{ route('caregiver.register') }}">Create profile</a>
            </div>
        </header>

        <section class="hero" id="top" aria-labelledby="caregiver-hero-title">
            <div class="hero-copy">
                <p class="eyebrow">Caregiver shifts on your schedule</p>
                <h1 id="caregiver-hero-title">Meaningful care work, <em>on your terms.</em></h1>
                <p class="lede">Choose a nearby shift, show up, tap Start in LoLo, provide thoughtful care, and tap Stop when the visit is done. We keep the timing, visit details, and payout status together.</p>
                <div class="hero-actions"><a class="button" href="{{ route('caregiver.register') }}">Create your caregiver profile</a><a class="text-link" href="#shift-flow">See a shift from start to paid <span aria-hidden="true">↓</span></a></div>
                <p class="reassurance">Choose every shift &nbsp;·&nbsp; Built-in visit timer &nbsp;·&nbsp; Payouts handled for you</p>
            </div>
            <div class="hero-visual"><img src="{{ asset('images/marketing/caregiver-hero-raleigh.jpg') }}" alt="A LoLo caregiver sharing a warm moment with an older adult" width="1024" height="1536" fetchpriority="high" decoding="async"></div>
        </section>

        <section class="snapshot-wrap" id="shift-flow" aria-labelledby="snapshot-title">
            <div class="snapshot-card">
                <div class="snapshot-heading"><p class="eyebrow">One shift with LoLo</p><h2 id="snapshot-title">From available shift to money in your account.</h2><p class="snapshot-intro">The whole visit follows one clear sequence. You always know what to do next, and LoLo keeps the record for you.</p></div>
                <div class="snapshot-item"><span>01 · Choose</span><b class="shift-state">CLAIM</b><strong>Tap to claim</strong><p>Pick a nearby shift that fits your schedule and skills.</p></div>
                <div class="snapshot-item"><span>02 · Arrive</span><b class="shift-state">10:00</b><strong>Show up</strong><p>Arrive at the agreed time with the visit details in LoLo.</p></div>
                <div class="snapshot-item start"><span>03 · Begin</span><b class="shift-state">START</b><strong>Tap Start</strong><p>Start the visit in LoLo so your time is recorded.</p></div>
                <div class="snapshot-item care"><span>04 · Support</span><b class="shift-state">01:42:18</b><strong>Do the visit</strong><p>Follow the notes and provide the agreed non-medical support.</p></div>
                <div class="snapshot-item stop"><span>05 · Finish</span><b class="shift-state">STOP</b><strong>Tap Stop</strong><p>Confirm the visit is complete and stop the timer.</p></div>
                <div class="snapshot-item paid"><span>06 · Payout</span><b class="shift-state">PAID</b><strong>Get paid</strong><p>Your payout is sent after completion and typically arrives within a few days.</p></div>
                <p class="snapshot-note">Shift details, recorded time, completion, and payout status stay together in LoLo. Banking and processor timing can vary.</p>
            </div>
        </section>

        <section class="section" id="why" aria-labelledby="why-title">
            <div class="section-head"><div><p class="eyebrow">Why LoLo</p><h2 id="why-title">Care work with freedom built in.</h2></div><p>LoLo brings together kind, reliable caregivers and older adults who need companionship and everyday help at home. You choose the opportunities that match your time, location, and strengths.</p></div>
            <div class="value-grid">
                <article><h3>Local opportunities</h3><p>Review nearby visits that fit the service area you choose.</p></article><article><h3>Clear expectations</h3><p>Know what a family needs before you make a commitment.</p></article><article><h3>Everyday support</h3><p>Companionship, errands, meals, light routines, and calm presence.</p></article><article><h3>Respect on both sides</h3><p>Profiles, screening, reviews, and clear visit details help build trust.</p></article>
            </div>
        </section>

        <section class="dark-section" id="how" aria-labelledby="how-title">
            <div class="section-head light"><div><p class="eyebrow">Getting started</p><h2 id="how-title">Get approved once. Then claim the shifts that fit.</h2></div><p>Tell families what you do best, complete LoLo’s trust checks, and start choosing from eligible shifts near you.</p></div>
            <div class="steps">
                <article><span>01</span><div class="step-preview profile-preview"><i>YOU</i><strong>Your caregiver profile</strong><small>Strengths · Availability · Service area</small></div><h3>Create your profile</h3><p>Share your experience, strengths, availability, service area, and the tasks you are comfortable providing.</p></article>
                <article><span>02</span><div class="step-preview check-preview"><b>✓ Profile complete</b><b>✓ Identity verified</b><b>✓ Background review</b></div><h3>Complete onboarding</h3><p>Finish the required profile, identity, background, and onboarding review before matching.</p></article>
                <article><span>03</span><div class="step-preview visit-preview"><small>Available near you</small><strong>Companionship & errands</strong><div class="visit-meta"><span>Tuesday<b>10:00 AM</b></span><span>3 miles away<b>Claim shift</b></span></div></div><h3>Claim your first shift</h3><p>Review the person, location, timing, tasks, and estimated payout. Claim it only when everything feels like a fit.</p></article>
            </div>
        </section>

        <section class="earnings" id="earnings" aria-labelledby="earnings-title">
            <div class="earnings-copy"><p class="eyebrow">Clear earnings</p><h2 id="earnings-title">See the pay before you claim the shift.</h2><p>Every eligible shift shows its expected length and estimated payout up front. After you tap Stop and complete the visit, LoLo tracks the payout through to your account.</p><a class="text-link" href="{{ route('caregiver.register') }}">Start your profile →</a></div>
            <div class="money-card" aria-label="LoLo caregiver earnings"><span>Your caregiver rate</span><strong>Earn $27/hr*</strong><p>Estimated shift pay is shown before you claim.</p><div><p><small>Before the visit</small><b>Pay shown up front</b></p><p><small>After the visit</small><b>Payout tracked in LoLo</b></p></div><p class="fee-note">*Payment-processing fees may apply.</p></div>
        </section>

        <section class="section work" id="work" aria-labelledby="work-title">
            <div class="work-image"><img src="{{ asset('images/marketing/homepage/human-moment.jpg') }}" alt="A caregiver and older adult laughing together while making tea" width="1920" height="1080" loading="lazy" decoding="async"></div>
            <div class="work-copy"><p class="eyebrow">The work</p><h2 id="work-title">Practical help. Real human moments.</h2><p>LoLo visits help older adults stay connected and comfortable at home. Sometimes that means getting things done. Sometimes it means being the steady, friendly person who makes the afternoon better.</p><ul class="task-list" aria-label="Examples of caregiver support"><li>Companionship</li><li>Errands & outings</li><li>Meal preparation</li><li>Light household routines</li><li>Reminders</li><li>Respite support</li></ul><p class="boundary"><strong>LoLo support is non-medical.</strong> You choose the tasks you are comfortable providing, and each request shows the expected support before you accept.</p></div>
        </section>

        <section class="trust" id="requirements" aria-labelledby="requirements-title">
            <div class="trust-copy"><p class="eyebrow">Trust & requirements</p><h2 id="requirements-title">Families count on you. LoLo helps make expectations clear.</h2><p>Experience can strengthen your profile. Just as important are good judgment, patience, honest communication, and doing what you said you would do.</p></div>
            <div class="trust-grid"><p><b>01</b>Identity verification</p><p><b>02</b>Background screening</p><p><b>03</b>Accurate availability</p><p><b>04</b>Clear communication</p><p><b>05</b>Respectful support</p><p><b>06</b>Dependable visits</p></div>
        </section>

        <section class="section faq" id="faq" aria-labelledby="faq-title">
            <p class="eyebrow">Questions, answered</p><h2 id="faq-title">What caregivers ask us.</h2>
            @foreach ($caregiverFaqs as $faq)
                <details class="faq-item" @if ($loop->first) open @endif><summary>{{ $faq['question'] }}</summary><p>{{ $faq['answer'] }}</p></details>
            @endforeach
        </section>

        <section class="final-cta">
            <p class="eyebrow">Work that fits real life</p><h2>Bring your care. Keep your flexibility.</h2><p>Create your profile, tell us what you do best, and choose opportunities that work for your schedule.</p><div><a class="button coral" href="{{ route('caregiver.register') }}">Create your profile</a><a href="#how">Review how it works →</a></div><small>Free to start · Choose every visit</small>
        </section>

        <footer class="landing-footer">
            <div class="footer-brand"><a class="brand" href="#top" aria-label="Back to the top"><img src="{{ asset('images/marketing/lolo/lolo-wordmark-evergreen.svg') }}" alt="LoLo Care" width="652" height="222"></a><p>The trust-and-coordination layer for aging at home.</p></div>
            <div><strong>Caregivers</strong><a href="{{ route('caregiver.register') }}">Create a profile</a><a href="#how">How it works</a><a href="#requirements">Requirements</a><a href="{{ route('login') }}">Caregiver login</a></div>
            <div><strong>Families</strong><a href="{{ route('landing') }}">Find care</a><a href="{{ route('caregivers.search') }}">View caregivers</a><a href="{{ route('landing') }}#safety">Safety</a></div>
            <div><strong>Company</strong><a href="{{ route('about') }}">About LoLo</a><a href="{{ route('blog.index') }}">Resources</a><a href="mailto:hello@carelolo.com">Contact</a><a href="{{ route('legal.index') }}">Legal & privacy</a></div>
            <p class="copyright">© {{ now()->year }} LoLo Care Inc. Non-medical home support.</p>
        </footer>
    </main>
@endsection
