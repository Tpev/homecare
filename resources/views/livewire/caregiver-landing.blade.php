@php
    $logo = asset('images/marketing/lolo/lolo-wordmark-evergreen.svg');
    $logoWarm = asset('images/marketing/lolo/lolo-wordmark-warm.svg');
    $heroImage = asset('images/marketing/caregiver-hero-raleigh.jpg');
    $sideImage = asset('images/marketing/homepage/caregivers-side.jpg');
    $profileImage = asset('images/marketing/homepage/caregiver-1.jpg');

    $benefits = [
        [
            'label' => 'Flexible visits',
            'title' => 'Choose work that fits your week.',
            'body' => 'Set your availability, review requests, and accept visits that make sense for your schedule.',
        ],
        [
            'label' => 'Everyday support',
            'title' => 'Help in ways families actually feel.',
            'body' => 'Companionship, errands, meals, light routines, reminders, and calm presence at home.',
        ],
        [
            'label' => 'Clear requests',
            'title' => 'See the details before you say yes.',
            'body' => 'Each request shows timing, location, care notes, tasks, and pay details before you accept.',
        ],
        [
            'label' => 'Trust first',
            'title' => 'A more thoughtful care marketplace.',
            'body' => 'Caregiver profiles, screening, reviews, and family expectations are built into the LoLo experience.',
        ],
    ];

    $steps = [
        ['number' => '01', 'title' => 'Create your profile', 'body' => 'Tell us about your experience, comfort level, service area, and availability.'],
        ['number' => '02', 'title' => 'Complete review', 'body' => 'Finish identity, background, and onboarding steps so families can trust who is arriving.'],
        ['number' => '03', 'title' => 'Review requests', 'body' => 'Get local care opportunities with task details, schedule, location, and pay shown up front.'],
        ['number' => '04', 'title' => 'Support families', 'body' => 'Accept the visits that fit, show up with care, and build your LoLo reputation over time.'],
    ];

    $audiences = [
        'Experienced caregivers who want more schedule control.',
        'Nursing, pre-med, CNA, OT, PT, and social-work students.',
        'Reliable neighbors who are naturally patient and helpful.',
        'Retired professionals who want meaningful part-time work.',
    ];
@endphp

<div class="lolo-caregivers">
    <style>
        .lolo-caregivers {
            --evergreen: #23483F;
            --evergreen-deep: #173A33;
            --ivory: #FFF7EA;
            --oat: #F1E5D2;
            --coral: #C96B55;
            --cta: #B95745;
            --ink: #24302D;
            --stone: #6F766F;
            background: var(--ivory);
            color: var(--ink);
            font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
            overflow: hidden;
        }

        .cg-display {
            font-family: 'Source Serif 4', Georgia, serif;
            letter-spacing: 0;
        }

        .cg-shell {
            width: min(1160px, calc(100% - 40px));
            margin: 0 auto;
        }

        .cg-nav {
            position: sticky;
            top: 0;
            z-index: 50;
            border-bottom: 1px solid rgba(35, 72, 63, 0.12);
            background: rgba(255, 247, 234, 0.92);
            backdrop-filter: blur(18px);
        }

        .cg-nav-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 22px;
            min-height: 74px;
        }

        .cg-logo {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            color: var(--evergreen);
            text-decoration: none;
        }

        .cg-logo img {
            width: 86px;
            height: auto;
            display: block;
        }

        .cg-logo span {
            display: block;
            border-left: 1px solid rgba(35, 72, 63, 0.24);
            padding-left: 12px;
            color: var(--stone);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.09em;
            text-transform: uppercase;
        }

        .cg-nav-links {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 24px;
            font-size: 14px;
            font-weight: 700;
        }

        .cg-nav-links a {
            color: rgba(36, 48, 45, 0.78);
            text-decoration: none;
            transition: color 180ms ease;
        }

        .cg-nav-links a:hover {
            color: var(--evergreen);
        }

        .cg-button {
            display: inline-flex;
            min-height: 46px;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border-radius: 8px;
            border: 1px solid transparent;
            padding: 0 18px;
            font-size: 14px;
            font-weight: 800;
            text-decoration: none;
            transition: transform 180ms ease, box-shadow 180ms ease, border-color 180ms ease, background 180ms ease;
            white-space: nowrap;
        }

        .cg-button:hover {
            transform: translateY(-1px);
        }

        .cg-button-primary {
            background: var(--cta);
            color: #fff;
            box-shadow: 0 16px 34px rgba(185, 87, 69, 0.24);
        }

        .cg-nav-links a.cg-button-primary {
            color: #fff;
        }

        .cg-button-primary:hover {
            background: #A94E3F;
            box-shadow: 0 20px 42px rgba(185, 87, 69, 0.28);
        }

        .cg-button-secondary {
            border-color: rgba(35, 72, 63, 0.22);
            background: rgba(255, 247, 234, 0.9);
            color: var(--evergreen);
        }

        .cg-button-secondary:hover {
            border-color: rgba(35, 72, 63, 0.38);
            background: #fff;
        }

        .cg-hero {
            position: relative;
            min-height: 720px;
            display: flex;
            align-items: stretch;
            background-image:
                linear-gradient(90deg, rgba(255, 247, 234, 0.98) 0%, rgba(255, 247, 234, 0.88) 36%, rgba(255, 247, 234, 0.16) 70%),
                linear-gradient(0deg, rgba(35, 72, 63, 0.62), rgba(35, 72, 63, 0.04) 45%),
                url('{{ $heroImage }}');
            background-size: cover;
            background-position: center right;
        }

        .cg-hero::after {
            content: "";
            position: absolute;
            inset: auto 0 0;
            height: 180px;
            background: linear-gradient(180deg, rgba(255, 247, 234, 0), var(--ivory));
            pointer-events: none;
        }

        .cg-hero-inner {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: minmax(0, 0.98fr) minmax(300px, 0.7fr);
            gap: 48px;
            align-items: center;
            padding: 86px 0 120px;
        }

        .cg-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 0 0 18px;
            color: var(--cta);
            font-size: 12px;
            font-weight: 900;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .cg-eyebrow::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: var(--coral);
            box-shadow: 0 0 0 5px rgba(201, 107, 85, 0.12);
        }

        .cg-hero h1 {
            margin: 0;
            max-width: 660px;
            color: var(--evergreen);
            font-size: clamp(58px, 8vw, 104px);
            font-weight: 700;
            line-height: 0.9;
        }

        .cg-hero h1 em,
        .cg-section-title em {
            color: var(--coral);
            font-style: italic;
        }

        .cg-hero-copy {
            animation: cgFadeUp 640ms ease both;
        }

        .cg-hero-copy > p:not(.cg-eyebrow):not(.cg-fine-print) {
            max-width: 610px;
            margin: 24px 0 0;
            color: rgba(36, 48, 45, 0.78);
            font-size: 19px;
            line-height: 1.65;
        }

        .cg-hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 32px;
        }

        .cg-fine-print {
            max-width: 560px;
            margin: 18px 0 0;
            color: rgba(36, 48, 45, 0.62);
            font-size: 13px;
            line-height: 1.6;
        }

        .cg-hero-stack {
            align-self: end;
            display: grid;
            gap: 16px;
            justify-items: end;
            padding-top: 220px;
        }

        .cg-request-card,
        .cg-profile-card {
            width: min(100%, 360px);
            border: 1px solid rgba(255, 247, 234, 0.28);
            border-radius: 8px;
            background: rgba(255, 247, 234, 0.92);
            box-shadow: 0 26px 70px rgba(23, 58, 51, 0.28);
            backdrop-filter: blur(16px);
            animation: cgFloat 6s ease-in-out infinite;
        }

        .cg-request-card {
            padding: 22px;
        }

        .cg-card-label {
            margin: 0;
            color: var(--cta);
            font-size: 11px;
            font-weight: 900;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .cg-request-card h2 {
            margin: 9px 0 0;
            color: var(--evergreen);
            font-size: 27px;
            line-height: 1.05;
        }

        .cg-request-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-top: 18px;
        }

        .cg-mini-stat {
            border-radius: 8px;
            background: rgba(241, 229, 210, 0.72);
            padding: 12px;
        }

        .cg-mini-stat span {
            display: block;
            color: var(--stone);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .cg-mini-stat strong {
            display: block;
            margin-top: 4px;
            color: var(--ink);
            font-size: 14px;
        }

        .cg-request-note {
            margin: 16px 0 0;
            border-left: 3px solid var(--coral);
            padding-left: 12px;
            color: rgba(36, 48, 45, 0.72);
            font-size: 13px;
            line-height: 1.5;
        }

        .cg-profile-card {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px;
            animation-delay: 800ms;
        }

        .cg-profile-card img {
            width: 72px;
            height: 72px;
            border-radius: 999px;
            object-fit: cover;
        }

        .cg-profile-card strong {
            display: block;
            color: var(--evergreen);
            font-size: 16px;
        }

        .cg-profile-card span {
            display: block;
            margin-top: 4px;
            color: var(--stone);
            font-size: 13px;
            line-height: 1.4;
        }

        .cg-proof {
            position: relative;
            z-index: 2;
            margin-top: -72px;
        }

        .cg-proof-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            overflow: hidden;
            border: 1px solid rgba(35, 72, 63, 0.12);
            border-radius: 8px;
            background: rgba(255, 247, 234, 0.96);
            box-shadow: 0 24px 80px rgba(23, 58, 51, 0.12);
        }

        .cg-proof-item {
            padding: 28px;
            border-right: 1px solid rgba(35, 72, 63, 0.1);
        }

        .cg-proof-item:last-child {
            border-right: 0;
        }

        .cg-proof-item strong {
            display: block;
            color: var(--evergreen);
            font-size: clamp(34px, 4vw, 54px);
            line-height: 0.95;
        }

        .cg-proof-item span {
            display: block;
            max-width: 250px;
            margin-top: 8px;
            color: rgba(36, 48, 45, 0.68);
            font-size: 14px;
            line-height: 1.45;
        }

        .cg-section {
            padding: 96px 0;
        }

        .cg-section-muted {
            background: var(--oat);
        }

        .cg-section-header {
            display: grid;
            grid-template-columns: minmax(0, 0.9fr) minmax(280px, 0.55fr);
            gap: 40px;
            align-items: end;
            margin-bottom: 42px;
        }

        .cg-section-kicker {
            margin: 0 0 12px;
            color: var(--cta);
            font-size: 12px;
            font-weight: 900;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .cg-section-title {
            margin: 0;
            color: var(--evergreen);
            font-size: clamp(44px, 5.6vw, 76px);
            font-weight: 700;
            line-height: 0.94;
        }

        .cg-section-copy {
            margin: 0;
            color: rgba(36, 48, 45, 0.72);
            font-size: 17px;
            line-height: 1.65;
        }

        .cg-benefits-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

        .cg-benefit-card,
        .cg-step-card,
        .cg-audience-item {
            border: 1px solid rgba(35, 72, 63, 0.12);
            border-radius: 8px;
            background: rgba(255, 247, 234, 0.74);
            box-shadow: 0 18px 44px rgba(23, 58, 51, 0.08);
        }

        .cg-benefit-card {
            min-height: 260px;
            padding: 22px;
            transition: transform 180ms ease, box-shadow 180ms ease;
        }

        .cg-benefit-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 24px 60px rgba(23, 58, 51, 0.12);
        }

        .cg-benefit-card small {
            color: var(--coral);
            font-size: 11px;
            font-weight: 900;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .cg-benefit-card h3 {
            margin: 48px 0 0;
            color: var(--evergreen);
            font-size: 24px;
            line-height: 1.1;
        }

        .cg-benefit-card p {
            margin: 14px 0 0;
            color: rgba(36, 48, 45, 0.68);
            font-size: 14px;
            line-height: 1.55;
        }

        .cg-dark {
            background: var(--evergreen);
            color: var(--ivory);
        }

        .cg-dark-layout {
            display: grid;
            grid-template-columns: minmax(0, 0.85fr) minmax(360px, 1fr);
            gap: 54px;
            align-items: center;
        }

        .cg-dark h2 {
            margin: 0;
            color: var(--ivory);
            font-size: clamp(44px, 5vw, 76px);
            line-height: 0.95;
        }

        .cg-dark h2 em {
            color: #F0A18E;
            font-style: italic;
        }

        .cg-dark p {
            color: rgba(255, 247, 234, 0.74);
        }

        .cg-dark-list {
            display: grid;
            gap: 14px;
            margin-top: 30px;
        }

        .cg-dark-list p {
            margin: 0;
            border-top: 1px solid rgba(255, 247, 234, 0.16);
            padding-top: 14px;
            font-size: 15px;
        }

        .cg-photo-frame {
            overflow: hidden;
            border: 1px solid rgba(255, 247, 234, 0.18);
            border-radius: 8px;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.22);
        }

        .cg-photo-frame img {
            width: 100%;
            height: 520px;
            object-fit: cover;
            display: block;
        }

        .cg-pay-layout {
            display: grid;
            grid-template-columns: minmax(0, 0.85fr) minmax(320px, 0.65fr);
            gap: 32px;
            align-items: stretch;
        }

        .cg-pay-card {
            border-radius: 8px;
            border: 1px solid rgba(35, 72, 63, 0.12);
            background: rgba(255, 247, 234, 0.78);
            padding: 34px;
            box-shadow: 0 22px 54px rgba(23, 58, 51, 0.08);
        }

        .cg-pay-card h2 {
            margin: 0;
            color: var(--evergreen);
            font-size: clamp(42px, 5vw, 72px);
            line-height: 0.92;
        }

        .cg-pay-card p {
            margin: 18px 0 0;
            color: rgba(36, 48, 45, 0.72);
            font-size: 16px;
            line-height: 1.65;
        }

        .cg-rate-box {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border-radius: 8px;
            background: var(--evergreen);
            color: var(--ivory);
            padding: 34px;
            box-shadow: 0 24px 60px rgba(23, 58, 51, 0.16);
        }

        .cg-rate-box span {
            color: rgba(255, 247, 234, 0.72);
            font-size: 12px;
            font-weight: 900;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .cg-rate-box strong {
            display: block;
            margin-top: 28px;
            color: #fff;
            font-size: clamp(58px, 8vw, 96px);
            line-height: 0.9;
        }

        .cg-rate-box p {
            margin: 16px 0 0;
            color: rgba(255, 247, 234, 0.74);
            font-size: 14px;
            line-height: 1.55;
        }

        .cg-steps-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

        .cg-step-card {
            min-height: 230px;
            padding: 22px;
        }

        .cg-step-card span {
            color: var(--coral);
            font-size: 13px;
            font-weight: 900;
            letter-spacing: 0.1em;
        }

        .cg-step-card h3 {
            margin: 46px 0 0;
            color: var(--evergreen);
            font-size: 23px;
            line-height: 1.1;
        }

        .cg-step-card p {
            margin: 12px 0 0;
            color: rgba(36, 48, 45, 0.66);
            font-size: 14px;
            line-height: 1.55;
        }

        .cg-audience-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-top: 34px;
        }

        .cg-audience-item {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 18px;
            color: rgba(36, 48, 45, 0.74);
            font-size: 15px;
            line-height: 1.5;
        }

        .cg-audience-item::before {
            content: "";
            flex: 0 0 auto;
            width: 10px;
            height: 10px;
            margin-top: 6px;
            border-radius: 999px;
            background: var(--coral);
            box-shadow: 0 0 0 5px rgba(201, 107, 85, 0.12);
        }

        .cg-final {
            padding: 110px 0;
            text-align: center;
            background:
                linear-gradient(180deg, rgba(255, 247, 234, 0.1), rgba(255, 247, 234, 0.98)),
                radial-gradient(circle at 50% 10%, rgba(201, 107, 85, 0.16), rgba(201, 107, 85, 0) 34%),
                var(--oat);
        }

        .cg-final img {
            width: 118px;
            margin: 0 auto 20px;
            display: block;
        }

        .cg-final h2 {
            max-width: 780px;
            margin: 0 auto;
            color: var(--evergreen);
            font-size: clamp(44px, 6vw, 86px);
            line-height: 0.92;
        }

        .cg-final p {
            max-width: 620px;
            margin: 22px auto 0;
            color: rgba(36, 48, 45, 0.72);
            font-size: 17px;
            line-height: 1.65;
        }

        .cg-final-actions {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 32px;
        }

        .cg-privacy {
            margin-top: 18px !important;
            color: rgba(36, 48, 45, 0.52) !important;
            font-size: 12px !important;
        }

        .cg-privacy a {
            color: var(--evergreen);
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        @keyframes cgFadeUp {
            from {
                opacity: 0;
                transform: translateY(18px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes cgFloat {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-10px);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .cg-hero-copy,
            .cg-request-card,
            .cg-profile-card {
                animation: none;
            }

            .cg-button,
            .cg-benefit-card {
                transition: none;
            }
        }

        @media (max-width: 980px) {
            .cg-nav-links a:not(.cg-button) {
                display: none;
            }

            .cg-hero {
                min-height: auto;
                background-image:
                    linear-gradient(180deg, rgba(255, 247, 234, 0.97) 0%, rgba(255, 247, 234, 0.88) 42%, rgba(255, 247, 234, 0.22) 100%),
                    url('{{ $heroImage }}');
                background-position: center top;
            }

            .cg-hero-inner,
            .cg-section-header,
            .cg-dark-layout,
            .cg-pay-layout {
                grid-template-columns: 1fr;
            }

            .cg-hero-inner {
                padding-top: 64px;
            }

            .cg-hero-stack {
                justify-items: start;
                padding-top: 20px;
            }

            .cg-proof-grid,
            .cg-benefits-grid,
            .cg-steps-grid {
                grid-template-columns: 1fr;
            }

            .cg-proof {
                margin-top: 0;
            }

            .cg-proof-item {
                border-right: 0;
                border-bottom: 1px solid rgba(35, 72, 63, 0.1);
            }

            .cg-proof-item:last-child {
                border-bottom: 0;
            }

            .cg-benefit-card,
            .cg-step-card {
                min-height: auto;
            }

            .cg-benefit-card h3,
            .cg-step-card h3 {
                margin-top: 30px;
            }
        }

        @media (max-width: 640px) {
            .cg-shell {
                width: min(100% - 28px, 1160px);
            }

            .cg-nav-inner {
                min-height: 66px;
            }

            .cg-logo img {
                width: 72px;
            }

            .cg-logo span {
                display: none;
            }

            .cg-nav-links {
                gap: 8px;
            }

            .cg-nav-links .cg-button {
                width: auto;
                min-height: 40px;
                padding: 0 12px;
                font-size: 12px;
            }

            .cg-hero h1 {
                font-size: clamp(48px, 18vw, 68px);
            }

            .cg-hero-copy > p:not(.cg-eyebrow):not(.cg-fine-print) {
                font-size: 16px;
            }

            .cg-button {
                width: 100%;
            }

            .cg-request-grid,
            .cg-audience-grid {
                grid-template-columns: 1fr;
            }

            .cg-section {
                padding: 74px 0;
            }

            .cg-photo-frame img {
                height: 360px;
            }

            .cg-pay-card,
            .cg-rate-box {
                padding: 24px;
            }
        }
    </style>

    <header class="cg-nav">
        <div class="cg-shell cg-nav-inner">
            <a href="{{ route('landing.family') }}" class="cg-logo" aria-label="LoLo home">
                <img src="{{ $logo }}" alt="LoLo">
                <span>Caregivers</span>
            </a>

            <nav class="cg-nav-links" aria-label="Caregiver page navigation">
                <a href="#why-lolo">Why LoLo</a>
                <a href="#pay">Pay</a>
                <a href="#how-it-works">How it works</a>
                <a href="{{ route('landing.family') }}">Families</a>
                <a class="cg-button cg-button-secondary cg-nav-login" href="{{ route('login') }}">Log in</a>
                <a class="cg-button cg-button-primary" href="{{ route('caregiver.register') }}">Create profile</a>
            </nav>
        </div>
    </header>

    <main>
        <section class="cg-hero" aria-labelledby="caregiver-hero-title">
            <div class="cg-shell cg-hero-inner">
                <div class="cg-hero-copy">
                    <p class="cg-eyebrow">Now open for caregivers</p>
                    <h1 id="caregiver-hero-title" class="cg-display">Caregiving work that fits your <em>life.</em></h1>
                    <p>
                        LoLo connects kind, reliable caregivers with older adults who need companionship and everyday non-medical support at home.
                    </p>

                    <div class="cg-hero-actions">
                        <a class="cg-button cg-button-primary" href="{{ route('caregiver.register') }}">Create your caregiver profile</a>
                        <a class="cg-button cg-button-secondary" href="#how-it-works">See how it works</a>
                    </div>

                    <p class="cg-fine-print">
                        Experience helps, reliability matters most. Profile review, identity verification, and background check are required before matching.
                    </p>
                </div>

                <div class="cg-hero-stack" aria-label="Example caregiver request">
                    <div class="cg-request-card">
                        <p class="cg-card-label">Request preview</p>
                        <h2 class="cg-display">Companionship and errands</h2>
                        <div class="cg-request-grid">
                            <div class="cg-mini-stat">
                                <span>Where</span>
                                <strong>Cary</strong>
                            </div>
                            <div class="cg-mini-stat">
                                <span>When</span>
                                <strong>Tue, 10 AM</strong>
                            </div>
                            <div class="cg-mini-stat">
                                <span>Length</span>
                                <strong>3 hours</strong>
                            </div>
                            <div class="cg-mini-stat">
                                <span>Rate</span>
                                <strong>$30/hr</strong>
                            </div>
                        </div>
                        <p class="cg-request-note">
                            You see the schedule, tasks, location, and pay details before accepting any visit.
                        </p>
                    </div>

                    <div class="cg-profile-card">
                        <img src="{{ $profileImage }}" alt="Smiling LoLo caregiver">
                        <div>
                            <strong>Build a trusted profile</strong>
                            <span>Families can see your strengths, availability, and reviews as you grow.</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="cg-proof" aria-label="Caregiver page highlights">
            <div class="cg-shell">
                <div class="cg-proof-grid">
                    <div class="cg-proof-item">
                        <strong class="cg-display">$30/hr</strong>
                        <span>starting family rate shown on care requests.</span>
                    </div>
                    <div class="cg-proof-item">
                        <strong class="cg-display">Flexible</strong>
                        <span>choose visits that fit your week, your location, and your comfort level.</span>
                    </div>
                    <div class="cg-proof-item">
                        <strong class="cg-display">Local</strong>
                        <span>care opportunities near you, matched around schedule and fit.</span>
                    </div>
                </div>
            </div>
        </section>

        <section id="why-lolo" class="cg-section">
            <div class="cg-shell">
                <div class="cg-section-header">
                    <div>
                        <p class="cg-section-kicker">Why LoLo</p>
                        <h2 class="cg-section-title cg-display">Good care starts with good <em>matches.</em></h2>
                    </div>
                    <p class="cg-section-copy">
                        Families want someone warm, dependable, and clear. Caregivers want work that is respectful, flexible, and worth their time. LoLo is built around both sides.
                    </p>
                </div>

                <div class="cg-benefits-grid">
                    @foreach ($benefits as $benefit)
                        <article class="cg-benefit-card">
                            <small>{{ $benefit['label'] }}</small>
                            <h3 class="cg-display">{{ $benefit['title'] }}</h3>
                            <p>{{ $benefit['body'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="cg-section cg-dark">
            <div class="cg-shell cg-dark-layout">
                <div>
                    <p class="cg-section-kicker">The work</p>
                    <h2 class="cg-display">Non-medical support, real-life <em>moments.</em></h2>
                    <p class="cg-section-copy" style="margin-top: 22px;">
                        LoLo visits are about helping older adults stay connected and comfortable at home. That can mean a walk outside, a grocery trip, a simple meal, a reminder, or a steady conversation.
                    </p>
                    <div class="cg-dark-list">
                        <p>Companionship and conversation</p>
                        <p>Errands, outings, and transportation support where appropriate</p>
                        <p>Meal prep, light routines, reminders, and household help</p>
                    </div>
                </div>

                <div class="cg-photo-frame">
                    <img src="{{ $sideImage }}" alt="LoLo caregiver spending time with an older adult">
                </div>
            </div>
        </section>

        <section id="pay" class="cg-section cg-section-muted">
            <div class="cg-shell cg-pay-layout">
                <div class="cg-pay-card">
                    <p class="cg-section-kicker">Pay clarity</p>
                    <h2 class="cg-display">Know the request before you accept it.</h2>
                    <p>
                        Families see a clear starting rate, and caregivers see the visit details before committing. You should know what the family needs, how long the visit is expected to take, where it is, and what the pay details look like before you say yes.
                    </p>
                    <p>
                        Payout timing, fees, and onboarding requirements are shown during caregiver setup so there are no surprises later.
                    </p>
                </div>

                <aside class="cg-rate-box" aria-label="LoLo family starting rate">
                    <div>
                        <span>Starting family rate</span>
                        <strong class="cg-display">$30/hr</strong>
                    </div>
                    <p>
                        A strong public price point for families, with request details visible to caregivers before accepting a visit.
                    </p>
                </aside>
            </div>
        </section>

        <section id="how-it-works" class="cg-section">
            <div class="cg-shell">
                <div class="cg-section-header">
                    <div>
                        <p class="cg-section-kicker">How it works</p>
                        <h2 class="cg-section-title cg-display">Four steps, no mystery.</h2>
                    </div>
                    <p class="cg-section-copy">
                        The process is simple, but it still protects trust. LoLo asks for the details families need before anyone is matched.
                    </p>
                </div>

                <div class="cg-steps-grid">
                    @foreach ($steps as $step)
                        <article class="cg-step-card">
                            <span>{{ $step['number'] }}</span>
                            <h3 class="cg-display">{{ $step['title'] }}</h3>
                            <p>{{ $step['body'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="cg-section cg-section-muted">
            <div class="cg-shell">
                <div class="cg-section-header">
                    <div>
                        <p class="cg-section-kicker">Who fits</p>
                        <h2 class="cg-section-title cg-display">Care work for people who already care.</h2>
                    </div>
                    <p class="cg-section-copy">
                        LoLo is not only for traditional agency caregivers. It is also for capable, patient people who want meaningful local work and understand the responsibility of showing up for a family.
                    </p>
                </div>

                <div class="cg-audience-grid">
                    @foreach ($audiences as $audience)
                        <p class="cg-audience-item">{{ $audience }}</p>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="cg-final">
            <div class="cg-shell">
                <img src="{{ $logoWarm }}" alt="LoLo">
                <h2 class="cg-display">Join the LoLo caregiver network.</h2>
                <p>
                    Create your profile now. We will review your information and guide you through the next onboarding steps.
                </p>
                <div class="cg-final-actions">
                    <a class="cg-button cg-button-primary" href="{{ route('caregiver.register') }}">Create your caregiver profile</a>
                    <a class="cg-button cg-button-secondary" href="{{ route('landing.family') }}">View family page</a>
                </div>
                <p class="cg-privacy">
                    We use first-party analytics cookies to understand page performance and improve caregiver onboarding.
                    See our <a href="{{ route('legal.show', ['slug' => 'privacy-policy']) }}">Privacy Policy</a>.
                </p>
            </div>
        </section>
    </main>
</div>
