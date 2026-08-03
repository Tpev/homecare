@extends('layouts.marketing')

@section('title', 'LoLo Care | Trusted help at home')
@section('meta_description', 'Find and coordinate flexible, non-medical home support for someone you love.')
@section('canonical', route('landing'))
@section('og_image', asset('images/marketing/lolo-hero.jpg'))
@section('og_image_alt', 'LoLo Care guide welcoming families looking for trusted help at home.')
@section('hide_default_footer', 'true')

@push('head')
    <link rel="preload" as="image" href="{{ asset('images/marketing/lolo-hero.jpg') }}">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600&display=swap" rel="stylesheet">
@endpush

@section('structured_data')
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'LoLo Care',
            'url' => route('landing'),
            'description' => 'Flexible, non-medical home support for older adults and their families.',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
@endsection

@section('content')
    @php
        $heroCaregiver = $featuredCaregivers->first();
        $heroCaregiverName = $heroCaregiver?->user?->name ?? 'Caregiver profiles';
        $heroCaregiverRate = $heroCaregiver ? '$'.number_format($heroCaregiver->resolvePlatformHourlyRate(), 0).'/hr' : 'From $30/hr';
        $heroCaregiverMeta = $heroCaregiver && (float) $heroCaregiver->average_rating > 0 && (int) $heroCaregiver->reviews_count > 0
            ? number_format((float) $heroCaregiver->average_rating, 1).' rating · '.(int) $heroCaregiver->reviews_count.' review'.((int) $heroCaregiver->reviews_count === 1 ? '' : 's')
            : ($heroCaregiver?->hasIdentityVerifiedBadge() ? 'Identity verified' : 'Explore available caregivers');
        $heroNameParts = preg_split('/\s+/', trim($heroCaregiverName));
        $heroInitials = collect($heroNameParts)->filter()->map(fn ($part) => strtoupper(substr($part, 0, 1)))->take(2)->implode('') ?: 'LC';
    @endphp

    <style>
        .lolo-home {
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

        .lolo-home *,
        .lolo-home *::before,
        .lolo-home *::after { box-sizing: border-box; }
        .lolo-home button,
        .lolo-home input,
        .lolo-home select { font: inherit; }
        .lolo-home a { color: inherit; text-decoration: none; }
        .lolo-home img { display: block; max-width: 100%; }
        .lolo-home h1,
        .lolo-home h2,
        .lolo-home h3 { color: inherit; font-family: 'Fraunces', serif; font-optical-sizing: auto; }
        .lolo-home h1 {
            margin: 0 0 28px;
            font-size: clamp(3.15rem, 5.15vw, 5.7rem);
            font-weight: 500;
            letter-spacing: -.05em;
            line-height: .95;
        }
        .lolo-home h1 em { color: var(--coral); font-style: normal; }
        .lolo-home h2 {
            margin: 0 0 22px;
            font-size: clamp(2.6rem, 4vw, 4.7rem);
            font-weight: 500;
            letter-spacing: -.04em;
            line-height: 1;
        }
        .lolo-home h3 { margin: 0; font-size: 1.75rem; }
        .lolo-home p { margin-top: 0; }
        .lolo-home .eyebrow {
            margin-bottom: 18px;
            color: var(--coral);
            font-size: .78rem;
            font-weight: 600;
            letter-spacing: .18em;
            text-transform: uppercase;
        }
        .lolo-home .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 999px;
            background: var(--green);
            color: #fff;
            cursor: pointer;
            font-weight: 600;
            padding: 16px 26px;
            transition: background-color .2s, transform .2s;
        }
        .lolo-home .button:hover { background: var(--coral); transform: translateY(-2px); }
        .lolo-home .button.small { padding: 11px 20px; }
        .lolo-home :focus-visible { outline: 3px solid rgba(185, 87, 69, .45); outline-offset: 3px; }

        .lolo-home .nav-shell {
            position: sticky;
            top: 0;
            z-index: 20;
            display: flex;
            align-items: center;
            height: 88px;
            border-bottom: 1px solid rgba(42, 59, 54, .1);
            background: rgba(255, 247, 234, .92);
            padding: 0 max(4vw, 24px);
            backdrop-filter: blur(14px);
        }
        .lolo-home .brand { display: inline-flex; align-items: center; flex: 0 0 auto; }
        .lolo-home .brand img { width: 78px; height: auto; }
        .lolo-home .nav-links { display: flex; gap: 34px; margin: auto; font-size: .96rem; }
        .lolo-home .nav-links a:hover { color: var(--coral); }
        .lolo-home .nav-actions { display: flex; align-items: center; gap: 20px; font-size: .95rem; }
        .lolo-home .caregiver-join {
            display: inline-flex;
            align-items: center;
            min-height: 42px;
            border: 1px solid rgba(185, 87, 69, .36);
            border-radius: 999px;
            color: var(--coral);
            padding: 8px 15px;
            font-weight: 700;
        }
        .lolo-home .caregiver-join:hover { border-color: var(--green); color: var(--green); }
        .lolo-home .mobile-menu { display: none; margin-left: auto; }
        .lolo-home .mobile-menu summary { cursor: pointer; list-style: none; font-size: .82rem; font-weight: 600; }
        .lolo-home .mobile-menu summary::-webkit-details-marker { display: none; }

        .lolo-home .hero {
            display: grid;
            grid-template-columns: 1.1fr .9fr;
            align-items: center;
            gap: 5vw;
            max-width: 1440px;
            min-height: 760px;
            margin: auto;
            overflow: hidden;
            padding: 70px max(5vw, 36px) 120px;
        }
        .lolo-home .hero-copy { max-width: 760px; }
        .lolo-home .lede { max-width: 650px; color: #53605b; font-size: 1.15rem; line-height: 1.72; }
        .lolo-home .hero-actions { display: flex; align-items: center; gap: 30px; margin: 34px 0 22px; }
        .lolo-home .text-link { border-bottom: 1px solid; font-weight: 600; }
        .lolo-home .reassurance { color: var(--muted); font-size: .88rem; }
        .lolo-home .hero-visual { position: relative; display: flex; align-items: center; justify-content: center; height: 610px; }
        .lolo-home .hero-visual::before {
            position: absolute;
            width: 520px;
            height: 520px;
            border-radius: 50%;
            background: var(--oat);
            content: '';
        }
        .lolo-home .hero-visual img {
            z-index: 1;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            object-fit: cover;
            object-position: center;
            mix-blend-mode: multiply;
        }
        .lolo-home .profile-float,
        .lolo-home .update-float {
            position: absolute;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid var(--line);
            border-radius: 18px;
            background: #fffaf1;
            padding: 14px 16px;
            box-shadow: 0 18px 50px rgba(35, 48, 45, .13);
        }
        .lolo-home .profile-float { top: 120px; left: -25px; }
        .lolo-home .profile-float b { margin-left: 18px; }
        .lolo-home .update-float { right: -15px; bottom: 100px; }
        .lolo-home .update-float i {
            display: grid;
            place-items: center;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--green);
            color: #fff;
            font-style: normal;
        }
        .lolo-home .profile-float small,
        .lolo-home .update-float small { display: block; margin-top: 3px; color: var(--muted); font-size: .8rem; }
        .lolo-home .avatar { display: grid; place-items: center; width: 44px; height: 44px; border-radius: 50%; font-weight: 700; }
        .lolo-home .peach { background: #efc0a8; }
        .lolo-home .sage { background: #b9c8b6; }
        .lolo-home .gold { background: #e8cf8b; }

        .lolo-home .booking-wrap { position: relative; z-index: 5; margin-top: -65px; padding: 0 max(4vw, 24px); }
        .lolo-home .booking-card {
            display: grid;
            grid-template-columns: 1.5fr 1.15fr 1.25fr 1fr .8fr;
            align-items: end;
            gap: 15px;
            max-width: 1380px;
            margin: auto;
            border: 1px solid var(--line);
            border-radius: 30px;
            background: #fffaf1;
            padding: 30px;
            box-shadow: 0 24px 70px rgba(35, 48, 45, .11);
        }
        .lolo-home .booking-heading { grid-column: 1 / -1; }
        .lolo-home .booking-heading h2 { margin-bottom: 8px; font-size: 2rem; }
        .lolo-home .booking-card label span {
            display: block;
            margin: 0 0 8px 4px;
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .lolo-home .booking-card input,
        .lolo-home .booking-card select {
            width: 100%;
            height: 54px;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: var(--cream);
            color: var(--ink);
            padding: 0 14px;
        }
        .lolo-home .schedule-toggle { display: flex; grid-column: 1 / 3; border-radius: 999px; background: var(--oat); padding: 5px; }
        .lolo-home .schedule-option { flex: 1; cursor: pointer; border-radius: 999px; padding: 11px; text-align: center; }
        .lolo-home .schedule-option:has(input:checked) { background: var(--green); color: #fff; }
        .lolo-home .schedule-option input { position: absolute; width: 1px; height: 1px; opacity: 0; }
        .lolo-home .button.search { grid-column: 3 / -1; height: 52px; }

        .lolo-home .section { max-width: 1440px; margin: auto; padding: 140px max(5vw, 36px); }
        .lolo-home .section-head { display: grid; grid-template-columns: 1.3fr .7fr; align-items: end; gap: 80px; margin-bottom: 55px; }
        .lolo-home .section-head > p { max-width: 430px; color: var(--muted); line-height: 1.7; }
        .lolo-home .profile-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; }
        .lolo-home .caregiver-card { overflow: hidden; border: 1px solid var(--line); border-radius: 24px; background: #fffaf1; }
        .lolo-home .portrait { position: relative; display: grid; place-items: center; height: 180px; }
        .lolo-home .portrait.has-photo { overflow: hidden; background: #e9dfcf; }
        .lolo-home .portrait.has-photo img { width: 100%; height: 100%; object-fit: cover; object-position: center 28%; }
        .lolo-home .portrait span { color: var(--green); font: 600 3rem 'Fraunces', serif; }
        .lolo-home .portrait b { position: absolute; top: 12px; right: 12px; border-radius: 999px; background: #fff; color: var(--green); padding: 7px 10px; font-size: .68rem; }
        .lolo-home .card-body { padding: 22px; }
        .lolo-home .name-line { display: flex; justify-content: space-between; gap: 12px; }
        .lolo-home .verified { color: var(--green); font-size: .88rem; }
        .lolo-home .skills { min-height: 46px; color: var(--muted); }
        .lolo-home .card-body small { color: var(--coral); font-size: .86rem; font-weight: 700; }
        .lolo-home .card-actions { display: flex; gap: 8px; margin-top: 20px; }
        .lolo-home .card-actions a { flex: 1; border: 1px solid var(--green); border-radius: 999px; padding: 11px 8px; text-align: center; font-size: .86rem; }
        .lolo-home .card-actions .select { background: var(--green); color: #fff; }
        .lolo-home .center-link { display: block; margin-top: 35px; text-align: center; font-weight: 600; }
        .lolo-home .profile-empty { grid-column: 1 / -1; border: 1px dashed var(--line); border-radius: 24px; background: #fffaf1; padding: 42px; text-align: center; }
        .lolo-home .profile-empty p { max-width: 620px; margin: 12px auto 24px; color: var(--muted); }

        .lolo-home .dark-section { background: var(--deep); color: var(--cream); padding: 130px max(5vw, 36px); }
        .lolo-home .dark-section > .section-head,
        .lolo-home .steps { max-width: 1300px; margin-right: auto; margin-left: auto; }
        .lolo-home .section-head.light > p { color: #b8c5bf; }
        .lolo-home .steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .lolo-home .steps > article { border: 1px solid #396057; border-radius: 26px; background: #1d4b40; padding: 28px; }
        .lolo-home .steps > article > span { color: #d48974; font-weight: 700; }
        .lolo-home .steps article p { color: #c7d0cc; line-height: 1.6; }
        .lolo-home .mini-ui,
        .lolo-home .mini-profile,
        .lolo-home .mini-checks {
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 10px;
            height: 190px;
            margin: 28px 0;
            border-radius: 18px;
            background: var(--cream);
            color: var(--ink);
            padding: 22px;
        }
        .lolo-home .mini-ui b,
        .lolo-home .mini-checks b { border: 1px solid var(--line); border-radius: 10px; padding: 9px; }
        .lolo-home .mini-profile { align-items: center; text-align: center; }
        .lolo-home .mini-profile i { display: grid; place-items: center; width: 62px; height: 62px; border-radius: 50%; background: #efc0a8; font-style: normal; font-weight: 700; }

        .lolo-home .family { display: grid; grid-template-columns: .85fr 1.15fr; align-items: center; gap: 8vw; }
        .lolo-home .family-copy > p:not(.eyebrow) { color: var(--muted); font-size: 1.1rem; line-height: 1.7; }
        .lolo-home .family-copy ul { display: grid; grid-template-columns: 1fr 1fr; gap: 13px; margin: 30px 0; padding: 0; list-style: none; }
        .lolo-home .family-copy li::before { margin-right: 9px; color: var(--coral); content: '✓'; }
        .lolo-home .family-board { border: 1px solid var(--line); border-radius: 30px; background: #fffaf1; padding: 30px; box-shadow: 0 20px 60px rgba(35, 48, 45, .1); }
        .lolo-home .family-top,
        .lolo-home .timeline article { display: flex; align-items: center; gap: 15px; }
        .lolo-home .family-top { border-bottom: 1px solid var(--line); padding-bottom: 20px; }
        .lolo-home .family-top div,
        .lolo-home .timeline article div { display: flex; flex-direction: column; }
        .lolo-home .family-top > b { margin-left: auto; }
        .lolo-home .timeline article { border-bottom: 1px solid var(--line); padding: 20px 0; }
        .lolo-home .timeline article i { color: var(--muted); font-style: normal; }
        .lolo-home .timeline article span { display: grid; place-items: center; width: 30px; height: 30px; margin-left: auto; border-radius: 50%; background: var(--green); color: #fff; }
        .lolo-home .note { margin-top: 25px; border-radius: 16px; background: var(--oat); padding: 20px; font-family: 'Fraunces', serif; font-size: 1.2rem; }
        .lolo-home .note small { display: block; margin-top: 12px; font: 400 .75rem 'DM Sans', sans-serif; }

        .lolo-home .comparison { overflow: hidden; border: 1px solid var(--line); border-radius: 26px; background: #fffaf1; }
        .lolo-home .comparison .row { display: grid; grid-template-columns: 1.4fr repeat(3, 1fr); border-bottom: 1px solid var(--line); }
        .lolo-home .comparison .row:last-child { border: 0; }
        .lolo-home .comparison .row span { padding: 18px; }
        .lolo-home .comparison .row.header { font-size: .8rem; font-weight: 700; }
        .lolo-home .comparison .row:not(.header) span:not(:first-child) { color: var(--muted); }
        .lolo-home .comparison .row .lolo-col { background: #e2eadf; color: var(--green) !important; font-weight: 700; }

        .lolo-home .economics { display: grid; grid-template-columns: 1fr 1fr; align-items: center; gap: 12vw; background: var(--oat); padding: 120px max(8vw, 36px); }
        .lolo-home .economics > div:first-child p:not(.eyebrow) { color: var(--muted); line-height: 1.7; }
        .lolo-home .money-card { border-radius: 32px; background: var(--green); color: #fff; padding: 40px; text-align: center; box-shadow: 20px 20px #d4a18d; }
        .lolo-home .money-card > span { display: block; }
        .lolo-home .money-card > strong { font: 500 6rem 'Fraunces', serif; }
        .lolo-home .money-card > strong small { font: 500 1.5rem 'DM Sans', sans-serif; }
        .lolo-home .money-card > div { display: grid; grid-template-columns: 1fr 1fr; margin-top: 20px; border-top: 1px solid #58736d; }
        .lolo-home .money-card p { margin-bottom: 0; padding: 20px 10px 0; }

        .lolo-home .video-section { max-width: 1280px; margin: 140px auto; padding: 0 36px; }
        .lolo-home .video-copy { max-width: 700px; margin: auto; text-align: center; }
        .lolo-home .video-frame {
            position: relative;
            overflow: hidden;
            width: 100%;
            aspect-ratio: 16 / 7;
            margin-top: 40px;
            border-radius: 30px;
            background: linear-gradient(125deg, #d7c09d, #789086);
            box-shadow: 0 24px 70px rgba(35, 48, 45, .14);
        }
        .lolo-home .video-frame iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }

        .lolo-home .trust { display: grid; grid-template-columns: .8fr 1.2fr; gap: 10vw; background: var(--green); color: var(--cream); padding: 120px max(8vw, 36px); }
        .lolo-home .trust-grid { display: grid; grid-template-columns: 1fr 1fr; }
        .lolo-home .trust-grid p { margin: 0; border-top: 1px solid #547069; padding: 20px 0; }
        .lolo-home .trust-grid b { margin-right: 20px; color: #d48974; }

        .lolo-home .faq { max-width: 1100px; }
        .lolo-home .faq > h2 { margin-bottom: 45px; }
        .lolo-home .faq-item { border-top: 1px solid var(--line); }
        .lolo-home .faq-item:last-child { border-bottom: 1px solid var(--line); }
        .lolo-home .faq-item summary { display: flex; justify-content: space-between; width: 100%; cursor: pointer; padding: 22px 0; font-weight: 600; list-style: none; }
        .lolo-home .faq-item summary::-webkit-details-marker { display: none; }
        .lolo-home .faq-item summary::after { content: '+'; font-size: 1.5rem; font-weight: 400; }
        .lolo-home .faq-item[open] summary::after { content: '−'; }
        .lolo-home .faq-item p { max-width: 750px; padding-bottom: 20px; color: var(--muted); line-height: 1.7; }

        .lolo-home .final-cta { margin: 0 max(4vw, 24px) 80px; border-radius: 34px; background: var(--deep); color: var(--cream); padding: 100px 30px; text-align: center; }
        .lolo-home .final-cta > p:not(.eyebrow) { max-width: 620px; margin: 0 auto 30px; color: #c0cbc7; line-height: 1.7; }
        .lolo-home .final-cta div { display: flex; align-items: center; justify-content: center; gap: 28px; margin-bottom: 25px; }
        .lolo-home .button.coral { background: var(--coral); }
        .lolo-home .final-cta small { color: #9eb0a9; }

        .lolo-home .landing-footer { display: grid; grid-template-columns: 2fr repeat(3, 1fr); gap: 50px; border-top: 1px solid var(--line); padding: 70px max(5vw, 36px) 30px; }
        .lolo-home .landing-footer > div { display: flex; flex-direction: column; align-items: flex-start; gap: 12px; font-size: .92rem; }
        .lolo-home .footer-brand .brand img { width: 84px; }
        .lolo-home .footer-brand p { max-width: 300px; color: var(--muted); line-height: 1.6; }
        .lolo-home .copyright { grid-column: 1 / -1; margin-top: 20px; border-top: 1px solid var(--line); color: var(--muted); padding-top: 25px; font-size: .76rem; }

        @media (max-width: 900px) {
            .lolo-home h1 { font-size: clamp(3.15rem, 8vw, 4.4rem); }
            .lolo-home .desktop-nav { display: none; }
            .lolo-home .mobile-menu { display: block; }
            .lolo-home .mobile-menu nav {
                position: absolute;
                top: 88px;
                right: 0;
                left: 0;
                display: flex;
                flex-direction: column;
                gap: 18px;
                border-bottom: 1px solid var(--line);
                background: var(--cream);
                padding: 24px;
                box-shadow: 0 20px 30px rgba(35, 48, 45, .08);
            }
            .lolo-home .nav-actions { margin-left: 18px; }
            .lolo-home .nav-actions .caregiver-join,
            .lolo-home .nav-actions .sign-in { display: none; }
            .lolo-home .hero { grid-template-columns: 1fr; padding-top: 55px; }
            .lolo-home .hero-visual { height: 480px; }
            .lolo-home .booking-card { grid-template-columns: 1fr 1fr; }
            .lolo-home .booking-heading,
            .lolo-home .schedule-toggle,
            .lolo-home .button.search { grid-column: 1 / -1; }
            .lolo-home .profile-grid,
            .lolo-home .steps,
            .lolo-home .section-head,
            .lolo-home .family,
            .lolo-home .economics,
            .lolo-home .trust { grid-template-columns: 1fr; }
            .lolo-home .comparison { overflow-x: auto; }
            .lolo-home .comparison .row { min-width: 760px; }
            .lolo-home .family-board { margin-top: 20px; }
            .lolo-home .landing-footer { grid-template-columns: 1fr 1fr; }
            .lolo-home .footer-brand { grid-column: 1 / -1; }
        }

        @media (max-width: 560px) {
            .lolo-home h1 { font-size: 2.85rem; line-height: .98; }
            .lolo-home .nav-shell { height: 72px; }
            .lolo-home .nav-actions .button { padding: 9px 14px; }
            .lolo-home .mobile-menu nav { top: 72px; }
            .lolo-home .hero { padding: 48px 22px 100px; }
            .lolo-home .hero-actions { flex-direction: column; align-items: flex-start; gap: 18px; }
            .lolo-home .hero-visual { height: 370px; }
            .lolo-home .hero-visual::before { width: 330px; height: 330px; }
            .lolo-home .hero-visual img { width: 320px; height: 320px; }
            .lolo-home .profile-float { top: 35px; left: -5px; transform: scale(.83); transform-origin: left center; }
            .lolo-home .update-float { right: -8px; bottom: 22px; transform: scale(.83); transform-origin: right center; }
            .lolo-home .booking-wrap { padding: 0 12px; }
            .lolo-home .booking-card { grid-template-columns: 1fr; padding: 22px; }
            .lolo-home .booking-card label,
            .lolo-home .booking-heading,
            .lolo-home .schedule-toggle,
            .lolo-home .button.search { grid-column: 1; }
            .lolo-home .section,
            .lolo-home .dark-section { padding: 95px 22px; }
            .lolo-home .section-head { gap: 12px; }
            .lolo-home .section-head h2 { font-size: 2.7rem; }
            .lolo-home .family-copy ul { grid-template-columns: 1fr; }
            .lolo-home .economics,
            .lolo-home .trust { padding: 90px 22px; }
            .lolo-home .money-card { padding: 32px 20px; box-shadow: 12px 12px #d4a18d; }
            .lolo-home .money-card > strong { font-size: 4.4rem; }
            .lolo-home .video-section { margin: 90px auto; padding: 0 22px; }
            .lolo-home .video-frame { aspect-ratio: 16 / 10; border-radius: 20px; }
            .lolo-home .trust-grid { grid-template-columns: 1fr; }
            .lolo-home .final-cta { margin: 0 12px 60px; padding: 70px 22px; }
            .lolo-home .final-cta div { flex-direction: column; }
            .lolo-home .landing-footer { grid-template-columns: 1fr 1fr; padding: 60px 22px; }
            .lolo-home .copyright { grid-column: 1 / -1; }
        }

        @media (prefers-reduced-motion: reduce) {
            .lolo-home *,
            .lolo-home *::before,
            .lolo-home *::after { scroll-behavior: auto !important; transition: none !important; }
        }
    </style>

    <main class="lolo-home">
        <header class="nav-shell">
            <a class="brand" href="{{ route('landing') }}" aria-label="LoLo Care home">
                <img src="{{ asset('images/marketing/lolo/lolo-wordmark-evergreen.svg') }}" alt="LoLo Care" width="652" height="222">
            </a>

            <nav class="nav-links desktop-nav" aria-label="Main navigation">
                <a href="#how">How it works</a>
                <a href="#caregivers">Caregivers</a>
                <a href="#families">For families</a>
                <a href="#safety">Safety</a>
            </nav>

            <details class="mobile-menu">
                <summary aria-label="Open navigation menu">Menu</summary>
                <nav aria-label="Mobile navigation">
                    <a href="#how">How it works</a>
                    <a href="#caregivers">Caregivers</a>
                    <a href="#families">For families</a>
                    <a href="#safety">Safety</a>
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

        <section class="hero" id="top">
            <div class="hero-copy">
                <p class="eyebrow">Flexible support for aging at home</p>
                <h1>Trusted help at home, <em>without becoming</em> the care manager.</h1>
                <p class="lede">Find and book trusted caregivers for companionship, errands, rides, meal preparation, and everyday support. Coordinate everything in one place and stay informed from wherever you are.</p>
                <div class="hero-actions">
                    <a class="button" href="{{ route('register') }}">Find care</a>
                    <a class="text-link" href="#video">Watch how LoLo works <span aria-hidden="true">↗</span></a>
                </div>
                <p class="reassurance">Starting at $30 per hour &nbsp;·&nbsp; Book from one hour &nbsp;·&nbsp; No long-term commitment</p>
            </div>

            <div class="hero-visual">
                <img src="{{ asset('images/marketing/lolo-hero.jpg') }}" alt="LoLo, the warm and reassuring guide to care at home" width="820" height="820">
                <div class="profile-float">
                    <span class="avatar peach">{{ $heroInitials }}</span>
                    <div><strong>{{ $heroCaregiverName }}</strong><small>{{ $heroCaregiverMeta }}</small></div>
                    <b>{{ $heroCaregiverRate }}</b>
                </div>
                <div class="update-float">
                    <i aria-hidden="true">✓</i>
                    <div><strong>Visit summary shared</strong><small>Mom had a great afternoon.</small></div>
                </div>
            </div>
        </section>

        <section class="booking-wrap" id="booking" aria-labelledby="booking-title">
            <form class="booking-card" method="GET" action="{{ route('landing.get-care') }}">
                <div class="booking-heading">
                    <p class="eyebrow">Start here</p>
                    <h2 id="booking-title">What kind of help do you need?</h2>
                </div>

                <label>
                    <span>Where is care needed?</span>
                    <input name="zip" inputmode="numeric" autocomplete="postal-code" placeholder="ZIP code or city" required>
                </label>
                <label>
                    <span>Type of support</span>
                    <select name="service_type">
                        <option value="Companion care">Companionship</option>
                        <option value="Errands and rides">Transportation & errands</option>
                        <option value="Meal prep">Meal preparation</option>
                        <option value="Light housekeeping">Light housekeeping</option>
                        <option value="Not sure yet">Not sure yet</option>
                    </select>
                </label>
                <label>
                    <span>Date</span>
                    <input name="preferred_date" aria-label="Care date" type="date" min="{{ now()->toDateString() }}">
                </label>
                <label>
                    <span>Start time</span>
                    <select name="preferred_time" aria-label="Start time">
                        <option>9:00 AM</option>
                        <option>12:00 PM</option>
                        <option>3:00 PM</option>
                        <option>6:00 PM</option>
                    </select>
                </label>
                <label>
                    <span>Hours</span>
                    <select name="preferred_hours" aria-label="Number of hours">
                        <option value="1">1 hour</option>
                        <option value="2">2 hours</option>
                        <option value="3">3 hours</option>
                        <option value="4">4 hours</option>
                    </select>
                </label>

                <div class="schedule-toggle" aria-label="Care schedule">
                    <label class="schedule-option"><input type="radio" name="care_schedule" value="one_time" checked><span>One-time</span></label>
                    <label class="schedule-option"><input type="radio" name="care_schedule" value="recurring"><span>Recurring</span></label>
                </div>
                <input type="hidden" name="time_preference" value="this_week">
                <button class="button search" type="submit">See available caregivers</button>
            </form>
        </section>

        <section class="section caregivers" id="caregivers">
            <div class="section-head">
                <div>
                    <p class="eyebrow">Choose with confidence</p>
                    <h2>Find someone who fits your family.</h2>
                </div>
                <p>Review experience, availability, ratings, verification, services, and hourly pricing before booking.</p>
            </div>

            <div class="profile-grid">
                @forelse ($featuredCaregivers as $caregiver)
                    @php
                        $photoUrl = $caregiver->profile_photo_path
                            ? \Illuminate\Support\Facades\Storage::disk('public')->url($caregiver->profile_photo_path)
                            : null;
                        $nameParts = preg_split('/\s+/', trim((string) $caregiver->user->name));
                        $initials = collect($nameParts)->filter()->map(fn ($part) => strtoupper(substr($part, 0, 1)))->take(2)->implode('') ?: 'LC';
                        $skills = $caregiver->skills->take(3)->pluck('name')->implode(', ');
                        $hasReviews = (float) $caregiver->average_rating > 0 && (int) $caregiver->reviews_count > 0;
                        $tone = ['peach', 'sage', 'gold'][$loop->index % 3];
                    @endphp
                    <article class="caregiver-card">
                        <div class="portrait {{ $tone }} {{ $photoUrl ? 'has-photo' : '' }}">
                            @if ($photoUrl)
                                <img src="{{ $photoUrl }}" alt="{{ $caregiver->user->name }}" loading="lazy">
                            @else
                                <span>{{ $initials }}</span>
                            @endif
                            <b>{{ $caregiver->hasTopCaregiverBadge() ? 'Top caregiver' : 'Available' }}</b>
                        </div>
                        <div class="card-body">
                            <div class="name-line"><h3>{{ $caregiver->user->name }}</h3><strong>${{ number_format($caregiver->resolvePlatformHourlyRate(), 0) }}/hr</strong></div>
                            <p class="verified">
                                @if ($caregiver->hasIdentityVerifiedBadge())
                                    ● Identity verified
                                @else
                                    ● Active LoLo profile
                                @endif
                                @if ($hasReviews)
                                    &nbsp;·&nbsp; ★ {{ number_format((float) $caregiver->average_rating, 1) }} ({{ (int) $caregiver->reviews_count }})
                                @else
                                    &nbsp;·&nbsp; New to LoLo
                                @endif
                            </p>
                            <p>{{ (int) $caregiver->years_experience }} year{{ (int) $caregiver->years_experience === 1 ? '' : 's' }} of experience</p>
                            <p class="skills">{{ $skills ?: \Illuminate\Support\Str::limit((string) $caregiver->bio, 90) }}</p>
                            <small>{{ collect([$caregiver->user->city, $caregiver->user->state])->filter()->implode(', ') ?: 'Accepting new clients' }}</small>
                            <div class="card-actions">
                                <a href="{{ route('caregivers.show', ['slug' => $caregiver->slug]) }}">View profile</a>
                                <a class="select" href="{{ route('register') }}">Find care</a>
                            </div>
                        </div>
                    </article>
                @empty
                    <article class="profile-empty">
                        <h3>Caregiver profiles are being updated.</h3>
                        <p>Browse the marketplace to see active profiles as caregivers complete review and become available.</p>
                        <a class="button" href="{{ route('caregivers.search') }}">Browse caregivers</a>
                    </article>
                @endforelse
            </div>

            <a class="center-link" href="{{ route('caregivers.search') }}">View all caregivers →</a>
        </section>

        <section class="dark-section" id="how">
            <div class="section-head light">
                <div>
                    <p class="eyebrow">How it works</p>
                    <h2>Care at home, made simple.</h2>
                </div>
                <p>Request help, choose a caregiver, and stay updated — all in one place.</p>
            </div>

            <div class="steps">
                <article>
                    <span>01</span>
                    <div class="mini-ui"><small>What help do you need?</small><b>Rides</b><b>Errands</b><b>Companionship</b></div>
                    <h3>Request</h3>
                    <p>Select the support, date, and time that works for your family.</p>
                </article>
                <article>
                    <span>02</span>
                    <div class="mini-profile"><i>{{ $heroInitials }}</i><strong>{{ $heroCaregiverName }}</strong><small>Available profile · {{ $heroCaregiverRate }}</small></div>
                    <h3>Match</h3>
                    <p>Review profiles, availability, experience, ratings, and pricing.</p>
                </article>
                <article>
                    <span>03</span>
                    <div class="mini-checks"><b>✓ Caregiver arrived</b><b>✓ Visit completed</b><b>✓ Summary shared</b></div>
                    <h3>Stay updated</h3>
                    <p>Follow each visit and keep family informed without repeated calls and texts.</p>
                </article>
            </div>
        </section>

        <section class="section family" id="families">
            <div class="family-copy">
                <p class="eyebrow">One shared view</p>
                <h2>Coordinate care for your parents from wherever you are.</h2>
                <p>Parents, children, siblings, and caregivers can stay connected through one shared care experience.</p>
                <ul>
                    <li>Invite family members</li>
                    <li>Share schedules</li>
                    <li>Receive arrival updates</li>
                    <li>Review visit summaries</li>
                    <li>Message caregivers</li>
                    <li>Keep care records together</li>
                </ul>
                <a class="button" href="{{ route('register') }}">See family coordination</a>
            </div>

            <div class="family-board">
                <div class="family-top">
                    <span class="avatar sage">EP</span>
                    <div><small>Care plan for</small><strong>Eleanor P.</strong></div>
                    <b>Family of 3</b>
                </div>
                <div class="timeline">
                    <article><i>9:00</i><div><strong>{{ $heroCaregiver?->user?->name ?? 'Caregiver' }} arrived</strong><small>Companionship · Today</small></div><span>✓</span></article>
                    <article><i>11:04</i><div><strong>Visit completed</strong><small>Summary is ready to review</small></div><span>✓</span></article>
                </div>
                <div class="note">“We took a short walk, made lunch, and set out tomorrow’s appointment notes.”<small>— {{ $heroCaregiver?->user?->name ?? 'Caregiver' }}</small></div>
            </div>
        </section>

        <section class="section compare">
            <div class="section-head">
                <div>
                    <p class="eyebrow">Why LoLo</p>
                    <h2>A better option for flexible support at home.</h2>
                </div>
                <p>Matching, flexibility, coordination, and trust — together.</p>
            </div>

            <div class="comparison" role="table" aria-label="Home care options comparison">
                <div class="row header" role="row"><span>What families need</span><span>Traditional agencies</span><span>Directories / DIY</span><span class="lolo-col">LoLo</span></div>
                @foreach ([
                    ['Book from one hour', 'Not typical', 'Available'],
                    ['Clear pricing', 'Limited', 'Varies'],
                    ['Caregiver profiles', 'Limited', 'Varies'],
                    ['Family coordination', 'Limited', 'Not available'],
                    ['Visit summaries', 'Limited', 'Not available'],
                    ['Secure payments', 'Included', 'Varies'],
                ] as $row)
                    <div class="row" role="row"><span>{{ $row[0] }}</span><span>{{ $row[1] }}</span><span>{{ $row[2] }}</span><span class="lolo-col">Included</span></div>
                @endforeach
            </div>
        </section>

        <section class="economics">
            <div>
                <p class="eyebrow">A fairer marketplace</p>
                <h2>Lower family cost.<br>Better caregiver earnings.</h2>
                <p>LoLo reduces traditional agency overhead, allowing families to pay less while caregivers retain more of what families spend.</p>
            </div>
            <div class="money-card">
                <span>Care starts at</span>
                <strong>$30<small>/hr</small></strong>
                <div><p><b>Clear rates</b><br>before booking</p><p><b>Secure payment</b><br>through LoLo</p></div>
            </div>
        </section>

        <section class="video-section" id="video">
            <div class="video-copy">
                <p class="eyebrow">60-second overview</p>
                <h2>See how LoLo works for families.</h2>
                <p>From requesting help to reviewing the visit, everything stays connected.</p>
            </div>
            <div class="video-frame">
                <iframe
                    src="https://www.youtube-nocookie.com/embed/_nve3ZnFsGM?rel=0"
                    title="How LoLo Care works for families"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                    referrerpolicy="strict-origin-when-cross-origin"
                    allowfullscreen
                ></iframe>
            </div>
        </section>

        <section class="trust" id="safety">
            <div>
                <p class="eyebrow">Trust & safety</p>
                <h2>Trust should be built into every visit.</h2>
            </div>
            <div class="trust-grid">
                <p><b>01</b>Identity verification</p><p><b>02</b>Background screening</p>
                <p><b>03</b>Reviews and profiles</p><p><b>04</b>Secure payments</p>
                <p><b>05</b>Clear visit details</p><p><b>06</b>Family-visible updates</p>
            </div>
        </section>

        <section class="section faq">
            <p class="eyebrow">Questions, answered</p>
            <h2>What families ask us.</h2>

            <details class="faq-item" open><summary>What types of care does LoLo provide?</summary><p>LoLo connects families with non-medical home support, including companionship, rides, errands, meal preparation, light housekeeping, and respite support.</p></details>
            <details class="faq-item"><summary>Is there a minimum visit length?</summary><p>You can request flexible help starting from a one-hour visit, subject to caregiver availability.</p></details>
            <details class="faq-item"><summary>Can I book care for my parent?</summary><p>Yes. Family members can arrange and coordinate care for an older parent or another loved one.</p></details>
            <details class="faq-item"><summary>Can other family members join the account?</summary><p>Yes. Invite family members so schedules, visit updates, messages, and care history stay in one shared place.</p></details>
            <details class="faq-item"><summary>How are caregivers verified?</summary><p>Caregiver profiles can include identity verification, background screening status, experience, training, certifications, and family reviews.</p></details>
        </section>

        <section class="final-cta">
            <p class="eyebrow">Care that fits your family</p>
            <h2>Find trusted help for someone you love.</h2>
            <p>Choose the support, date, and time. Review available caregivers and book care that fits your family.</p>
            <div><a class="button coral" href="{{ route('register') }}">Find care</a><a href="{{ route('caregivers.search') }}">View caregivers →</a></div>
            <small>Starting at $30 per hour. Book from one hour.</small>
        </section>

        <footer class="landing-footer">
            <div class="footer-brand">
                <a class="brand" href="#top" aria-label="Back to the top">
                    <img src="{{ asset('images/marketing/lolo/lolo-wordmark-evergreen.svg') }}" alt="LoLo Care" width="652" height="222">
                </a>
                <p>The trust-and-coordination layer for aging at home.</p>
            </div>
            <div><strong>Families</strong><a href="{{ route('register') }}">Find care</a><a href="#how">How it works</a><a href="#safety">Safety</a></div>
            <div><strong>Caregivers</strong><a href="{{ route('caregiver.register') }}">Become a caregiver</a><a href="{{ route('login') }}">Caregiver login</a></div>
            <div><strong>Company</strong><a href="{{ route('blog.index') }}">Resources</a><a href="mailto:hello@carelolo.com">Contact</a><a href="{{ route('legal.index') }}">Legal & privacy</a></div>
            <p class="copyright">© {{ now()->year }} LoLo Care Inc. Non-medical home support.</p>
        </footer>
    </main>
@endsection
