@extends('layouts.marketing')

@section('title', 'LoLo | Home care and companionship')
@section('meta_description', 'LoLo helps families arrange trusted non-medical home care and companionship, with clear hourly care starting at $30/hr.')
@section('canonical', route('landing'))
@section('og_image', asset('images/marketing/homepage/hero-care.jpg'))
@section('og_image_alt', 'Caregiver smiling with an older adult at home in a warm kitchen.')

@section('content')
    <style>
        .hub-home {
            --hub-bg: #fff7ea;
            --hub-surface: rgba(255, 247, 234, 0.93);
            --hub-card: #fffaf0;
            --hub-cream: #f1e5d2;
            --hub-deep: #23483f;
            --hub-deep-2: #19392f;
            --hub-copy: #53645d;
            --hub-copy-soft: #6f766f;
            --hub-coral: #c96b55;
            --hub-cta: #b95745;
            --hub-coral: #c96b55;
            --hub-cta: #b95745;
            --hub-border: rgba(35, 72, 63, 0.12);
            --hub-shadow: 0 32px 80px -36px rgba(35, 72, 63, 0.3);
            --hub-shadow-soft: 0 16px 42px -24px rgba(35, 72, 63, 0.2);
            background:
                radial-gradient(circle at top left, rgba(201, 107, 85, 0.14), transparent 22%),
                radial-gradient(circle at top right, rgba(158, 216, 198, 0.16), transparent 28%),
                linear-gradient(180deg, #fffaf2 0%, var(--hub-bg) 34%, #fffaf0 100%);
            color: var(--hub-deep);
            overflow-x: clip;
            overflow-y: visible;
        }

        .hub-home,
        .hub-home p,
        .hub-home li,
        .hub-home a,
        .hub-home span,
        .hub-home button,
        .hub-home input,
        .hub-home textarea,
        .hub-home select {
            font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
        }

        .hub-home h1,
        .hub-home h2,
        .hub-home h3,
        .hub-home h4,
        .hub-home .hub-serif {
            font-family: 'Source Serif 4', ui-serif, Georgia, serif;
            letter-spacing: -0.04em;
            color: var(--hub-deep);
        }

        .hub-home .hub-container {
            width: min(1180px, calc(100vw - 2rem));
            margin-inline: auto;
        }

        .hub-home .hub-nav {
            position: sticky;
            top: 0;
            z-index: 50;
            backdrop-filter: blur(22px);
            background: rgba(255, 252, 247, 0.85);
            border-bottom: 1px solid rgba(18, 63, 64, 0.08);
        }

        .hub-home .hub-desktop-nav {
            display: flex;
            align-items: center;
            gap: 2.3rem;
        }

        .hub-home .hub-logo-mark {
            height: 2.9rem;
            width: 2.9rem;
            border-radius: 1rem;
            background: linear-gradient(145deg, var(--hub-deep), #204f50);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 18px 34px -22px rgba(18, 63, 64, 0.42);
        }

        .hub-home .hub-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border-radius: 999px;
            border: 1px solid rgba(201, 107, 85, 0.16);
            background: rgba(255, 255, 255, 0.78);
            padding: 0.55rem 0.9rem;
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--hub-coral);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
        }

        .hub-home .hub-dot {
            height: 0.5rem;
            width: 0.5rem;
            border-radius: 999px;
            background: var(--hub-coral);
            box-shadow: 0 0 0 0 rgba(201, 107, 85, 0.5);
            animation: hub-pulse 2.2s infinite;
        }

        @keyframes hub-pulse {
            0% { box-shadow: 0 0 0 0 rgba(201, 107, 85, 0.45); }
            70% { box-shadow: 0 0 0 12px rgba(201, 107, 85, 0); }
            100% { box-shadow: 0 0 0 0 rgba(201, 107, 85, 0); }
        }

        .hub-home .hub-nav-link {
            font-size: 0.98rem;
            font-weight: 600;
            color: rgba(18, 63, 64, 0.72);
            transition: color .18s ease;
            white-space: nowrap;
        }

        .hub-home .hub-nav-link:hover { color: var(--hub-deep); }

        .hub-home .hub-button-primary,
        .hub-home .hub-button-secondary,
        .hub-home .hub-button-ghost {
            min-height: 3rem;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
            font-size: 0.92rem;
            font-weight: 700;
            transition: transform .18s ease, background-color .18s ease, color .18s ease, border-color .18s ease, box-shadow .18s ease;
        }

        .hub-home .hub-button-primary {
            background: var(--hub-deep);
            color: #fff;
            padding: 0.9rem 1.4rem;
            box-shadow: 0 18px 32px -18px rgba(18, 63, 64, 0.46);
        }

        .hub-home .hub-button-primary:hover {
            transform: translateY(-1px);
            background: var(--hub-deep-2);
        }

        .hub-home .hub-button-secondary {
            background: rgba(201, 107, 85, 0.12);
            color: var(--hub-coral);
            border: 1px solid rgba(201, 107, 85, 0.18);
            padding: 0.9rem 1.3rem;
        }

        .hub-home .hub-button-secondary:hover {
            transform: translateY(-1px);
            background: rgba(201, 107, 85, 0.16);
        }

        .hub-home .hub-button-ghost {
            color: rgba(18, 63, 64, 0.76);
            border: 1px solid rgba(18, 63, 64, 0.1);
            background: rgba(255,255,255,0.7);
            padding: 0.8rem 1.15rem;
        }

        .hub-home .hub-button-ghost:hover {
            background: rgba(255,255,255,0.95);
            transform: translateY(-1px);
        }

        .hub-home .hub-hero {
            padding: 1.5rem 0 4.4rem;
        }

        .hub-home .hub-hero-grid {
            display: grid;
            gap: 2rem;
            position: relative;
            padding: 3rem 1.45rem 2.6rem;
            border-radius: 2.75rem;
            overflow: hidden;
            min-height: 38rem;
            background:
                linear-gradient(90deg, rgba(255, 252, 247, 0.92) 0%, rgba(255, 252, 247, 0.78) 36%, rgba(255, 252, 247, 0.18) 100%),
                linear-gradient(180deg, rgba(255, 252, 247, 0.24) 0%, rgba(255, 252, 247, 0.06) 100%);
            box-shadow: var(--hub-shadow);
        }

        .hub-home .hub-hero-grid::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at left center, rgba(255, 252, 247, 0.92) 0%, rgba(255, 252, 247, 0.6) 30%, rgba(255, 252, 247, 0.12) 62%),
                linear-gradient(180deg, rgba(18, 63, 64, 0.02) 0%, rgba(18, 63, 64, 0.18) 100%);
            z-index: 1;
        }

        .hub-home .hub-hero-grid::after {
            content: '';
            position: absolute;
            inset: 0;
            background-image: url('{{ asset('images/marketing/homepage/hero-care.jpg') }}');
            background-size: cover;
            background-position: center;
            opacity: 0.92;
            z-index: 0;
        }

        .hub-home .hub-hero-copy {
            position: relative;
            z-index: 2;
            max-width: 28rem;
            padding: 0.85rem 0 0.5rem;
        }

        .hub-home .hub-kicker {
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--hub-coral);
        }

        .hub-home .hub-hero-title {
            margin-top: 1.65rem;
            max-width: 6.25ch;
            font-size: clamp(3.35rem, 7vw, 5.35rem);
            line-height: 0.88;
            text-shadow: 0 10px 32px rgba(255, 252, 247, 0.26);
        }

        .hub-home .hub-hero-title em,
        .hub-home .hub-final-title em,
        .hub-home .hub-section-title em,
        .hub-home .hub-highlight {
            color: var(--hub-coral);
            font-style: italic;
            font-weight: 400;
        }

        .hub-home .hub-hero-body {
            margin-top: 1.2rem;
            max-width: 22rem;
            font-size: 1rem;
            line-height: 1.56;
            color: rgba(18, 63, 64, 0.84);
            font-weight: 500;
            text-shadow: 0 2px 10px rgba(255, 252, 247, 0.32);
        }

        .hub-home .hub-hero-actions {
            margin-top: 1.6rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            align-items: flex-start;
        }

        .hub-home .hub-proof-grid,
        .hub-home .hub-range-grid,
        .hub-home .hub-value-grid,
        .hub-home .hub-audience-grid,
        .hub-home .hub-stat-grid,
        .hub-home .hub-how-grid,
        .hub-home .hub-trust-grid {
            display: grid;
            gap: 1rem;
        }

        .hub-home .hub-proof-card,
        .hub-home .hub-range-card,
        .hub-home .hub-value-card,
        .hub-home .hub-audience-card,
        .hub-home .hub-stat-card,
        .hub-home .hub-how-card,
        .hub-home .hub-trust-card,
        .hub-home .hub-podcast-card,
        .hub-home .hub-surface-card,
        .hub-home .hub-floating-card {
            border-radius: 1.8rem;
            border: 1px solid var(--hub-border);
            background: var(--hub-surface);
            box-shadow: var(--hub-shadow-soft);
        }

        .hub-home .hub-hero-proofline {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
        }

        .hub-home .hub-proof-inline {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.86rem;
            color: rgba(18, 63, 64, 0.88);
            background: rgba(255, 252, 247, 0.48);
            border: 1px solid rgba(255, 255, 255, 0.45);
            backdrop-filter: blur(8px);
            border-radius: 999px;
            padding: 0.45rem 0.75rem;
            box-shadow: 0 8px 20px -16px rgba(18, 63, 64, 0.24);
        }

        .hub-home .hub-proof-inline::after {
            display: none;
        }

        .hub-home .hub-proof-inline strong {
            color: var(--hub-coral);
            font-weight: 800;
        }

        .hub-home .hub-range-card {
            padding: 1rem;
        }

        .hub-home .hub-range-card strong {
            display: block;
            font-size: 0.94rem;
            color: var(--hub-deep);
        }

        .hub-home .hub-range-card span {
            display: block;
            margin-top: 0.45rem;
            font-size: 0.84rem;
            line-height: 1.55;
            color: var(--hub-copy-soft);
        }

        .hub-home .hub-trust-strip {
            background: #fff7ea;
            padding-block: 2.5rem 4.75rem;
        }

        .hub-home .hub-trust-grid {
            grid-template-columns: 1fr;
        }

        .hub-home .hub-trust-card {
            padding: 1.2rem;
            border-radius: 1.35rem;
            background: rgba(255, 250, 240, 0.86);
            box-shadow: 0 16px 38px -28px rgba(35, 72, 63, 0.24);
        }

        .hub-home .hub-trust-card strong {
            display: block;
            color: var(--hub-deep);
            font-size: 1rem;
            line-height: 1.2;
        }

        .hub-home .hub-trust-card span {
            display: block;
            margin-top: 0.55rem;
            color: rgba(35, 72, 63, 0.72);
            font-size: 0.92rem;
            line-height: 1.52;
        }

        .hub-home.hub-motion-ready [data-hub-reveal] {
            opacity: 0;
            transform: translate3d(0, 1.6rem, 0);
            transition: opacity .7s ease, transform .7s ease;
        }

        .hub-home.hub-motion-ready [data-hub-reveal].is-visible {
            opacity: 1;
            transform: translate3d(0, 0, 0);
        }

        .hub-home .hub-home-request {
            position: relative;
            scroll-margin-top: 7rem;
            transition: box-shadow .2s ease, transform .2s ease;
        }

        .hub-home .hub-home-request.hub-request-focus {
            animation: hub-request-focus 1.25s ease;
        }

        @keyframes hub-request-focus {
            0% {
                box-shadow: 0 0 0 0 rgba(201, 107, 85, 0);
                transform: translateY(0);
            }
            18% {
                box-shadow: 0 0 0 8px rgba(201, 107, 85, 0.18), 0 30px 80px -26px rgba(35, 72, 63, 0.28);
                transform: translateY(-2px);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(201, 107, 85, 0), 0 30px 80px -26px rgba(35, 72, 63, 0.24);
                transform: translateY(0);
            }
        }

        @keyframes hub-rise-in {
            from {
                opacity: 0;
                transform: translate3d(0, 1.4rem, 0);
            }
            to {
                opacity: 1;
                transform: translate3d(0, 0, 0);
            }
        }

        @keyframes hub-gentle-float {
            0%, 100% { transform: translate3d(0, 0, 0); }
            50% { transform: translate3d(0, -0.45rem, 0); }
        }

        .hub-home .hub-hero-visual {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            justify-content: center;
        }

        .hub-home .hub-quick-wrap {
            position: relative;
            max-width: 22rem;
            margin-inline: 0;
        }

        .hub-home .hub-quick-wrap::before {
            content: '';
            position: absolute;
            inset: -1rem;
            border-radius: 2.5rem;
            background: radial-gradient(circle, rgba(201, 107, 85, 0.18) 0%, rgba(201, 107, 85, 0) 70%);
            z-index: 0;
        }

        .hub-home .hub-quick-wrap > * { position: relative; z-index: 1; }

        .hub-home .hub-mini-toast {
            position: relative;
            width: fit-content;
            max-width: calc(100% - 2rem);
            margin: -0.65rem 4.8rem 0 0;
            padding: 0.8rem 0.95rem;
            border-radius: 1.2rem;
            background: rgba(255, 252, 247, 0.94);
            backdrop-filter: blur(18px);
            border: 1px solid rgba(255,255,255,0.65);
            box-shadow: 0 18px 40px -26px rgba(18, 63, 64, 0.38);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            z-index: 3;
        }

        .hub-home .hub-mini-avatar {
            height: 2.2rem;
            width: 2.2rem;
            border-radius: 999px;
            background: rgba(201, 107, 85, 0.14);
            color: var(--hub-coral);
            font-size: 0.8rem;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
        }

        .hub-home .hub-mini-toast p {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--hub-deep);
        }

        .hub-home .hub-mini-toast span {
            display: block;
            margin-top: 0.12rem;
            font-size: 0.75rem;
            color: var(--hub-copy-soft);
        }

        .hub-home .hub-section {
            padding: 4rem 0;
        }

        .hub-home .hub-section-alt { background: rgba(255,255,255,0.72); }
        .hub-home .hub-section-soft { background: rgba(246, 239, 230, 0.55); }
        .hub-home .hub-section-dark {
            background: linear-gradient(180deg, #123f40 0%, #0e3334 100%);
            color: rgba(255,255,255,0.92);
        }

        .hub-home .hub-section-title {
            font-size: clamp(2.15rem, 4.8vw, 4rem);
            line-height: 0.96;
            letter-spacing: -0.05em;
        }

        .hub-home .hub-section-text {
            margin-top: 1rem;
            font-size: 0.99rem;
            line-height: 1.68;
            color: var(--hub-copy);
            max-width: 36rem;
        }

        .hub-home .hub-support-section .hub-section-title {
            font-size: clamp(2.4rem, 4.5vw, 4.2rem);
            line-height: 0.94;
        }

        .hub-home .hub-support-section .hub-section-text {
            max-width: 34rem;
            font-size: 1rem;
        }

        .hub-home .hub-experience-section .hub-section-title {
            max-width: 7.2ch;
            font-size: clamp(2.45rem, 4.6vw, 4.2rem);
            line-height: 0.92;
        }

        .hub-home .hub-values-section .hub-section-title {
            max-width: 9ch;
            font-size: clamp(2.45rem, 4.6vw, 4.25rem);
        }

        .hub-home .hub-how-section .hub-section-title {
            max-width: 7.4ch;
            font-size: clamp(2.4rem, 4.4vw, 4.15rem);
            line-height: 0.94;
        }

        .hub-home .hub-difference-section .hub-section-title {
            font-size: clamp(2.45rem, 4.6vw, 4.4rem);
            line-height: 0.92;
            max-width: 10.5ch;
        }

        .hub-home .hub-live-grid,
        .hub-home .hub-how-layout {
            display: grid;
            gap: 1.8rem;
        }

        .hub-home .hub-live-stack {
            position: relative;
            min-height: 28rem;
            width: min(100%, 29rem);
            max-width: 29rem;
            margin-left: auto;
            margin-right: auto;
        }

        .hub-home .hub-experience-copy {
            max-width: 21rem;
            padding-top: 1.6rem;
        }

        .hub-home .hub-profile-card {
            position: relative;
            width: min(100%, 11.5rem);
            padding: 0.75rem;
            z-index: 2;
            box-shadow: 0 28px 60px -36px rgba(18, 63, 64, 0.24);
        }

        .hub-home .hub-profile-card img {
            width: 100%;
            height: 7.4rem;
            object-fit: cover;
            border-radius: 1.1rem;
        }

        .hub-home .hub-visit-card,
        .hub-home .hub-week-card {
            padding: 1.35rem;
        }

        .hub-home .hub-visit-card {
            background: linear-gradient(180deg, var(--hub-deep) 0%, #194a4b 100%);
            color: rgba(255,255,255,0.95);
            border-color: rgba(255,255,255,0.08);
            z-index: 3;
            box-shadow: 0 28px 60px -30px rgba(18, 63, 64, 0.42);
        }

        .hub-home .hub-week-card {
            z-index: 1;
            box-shadow: 0 28px 58px -34px rgba(18, 63, 64, 0.18);
        }

        .hub-home .hub-week-item,
        .hub-home .hub-visit-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            border-radius: 1rem;
        }

        .hub-home .hub-week-item {
            background: var(--hub-cream);
            padding: 0.8rem 0.9rem;
            margin-top: 0.75rem;
        }

        .hub-home .hub-audience-card {
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .hub-home .hub-audience-card img {
            width: 100%;
            aspect-ratio: 5 / 4;
            object-fit: cover;
        }

        .hub-home .hub-audience-card-dark {
            background: linear-gradient(180deg, var(--hub-deep) 0%, var(--hub-deep-2) 100%);
            color: rgba(255,255,255,0.95);
            border-color: rgba(255,255,255,0.08);
        }

        .hub-home .hub-audience-card-dark h3,
        .hub-home .hub-audience-card-dark p,
        .hub-home .hub-audience-card-dark li,
        .hub-home .hub-audience-card-dark span {
            color: inherit;
        }

        .hub-home .hub-audience-card-dark h3 {
            color: #fffefb !important;
        }

        .hub-home .hub-value-card {
            padding: 1.35rem;
        }

        .hub-home .hub-value-shift-1,
        .hub-home .hub-value-shift-2 {
            margin-top: 0;
        }

        .hub-home .hub-value-icon {
            height: 3rem;
            width: 3rem;
            border-radius: 1rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            background: rgba(201, 107, 85, 0.1);
            color: var(--hub-coral);
        }

        .hub-home .hub-human {
            position: relative;
            min-height: 19rem;
            overflow: hidden;
        }

        .hub-home .hub-human img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .hub-home .hub-human::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(18, 63, 64, 0.08) 0%, rgba(18, 63, 64, 0.78) 100%);
        }

        .hub-home .hub-human-copy {
            position: relative;
            z-index: 1;
            padding: 2.4rem 1.25rem;
            display: flex;
            align-items: flex-end;
            min-height: 19rem;
        }

        .hub-home .hub-human-title {
            font-size: clamp(2.25rem, 5vw, 4.3rem);
            line-height: 0.94;
            color: #fffefb;
        }

        .hub-home .hub-how-card {
            padding: 1.35rem 1.3rem 1.5rem;
            background: rgba(255, 252, 247, 0.8);
        }

        .hub-home .hub-step-no {
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--hub-coral);
        }

        .hub-home .hub-podcast-card {
            overflow: hidden;
            background: linear-gradient(180deg, var(--hub-deep) 0%, var(--hub-deep-2) 100%);
            color: rgba(255,255,255,0.95);
            border-color: rgba(255,255,255,0.08);
            border-radius: 2rem;
        }

        .hub-home .hub-podcast-image {
            position: relative;
            aspect-ratio: 1 / 1;
            min-height: 14rem;
        }

        .hub-home .hub-podcast-image img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .hub-home .hub-podcast-play {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .hub-home .hub-play-circle {
            height: 4.5rem;
            width: 4.5rem;
            border-radius: 999px;
            background: rgba(255,252,247,0.94);
            color: var(--hub-deep);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 20px 36px -24px rgba(18, 63, 64, 0.5);
        }

        .hub-home .hub-play-circle-disabled {
            opacity: 0.7;
            cursor: default;
        }

        .hub-home .hub-podcast-link {
            color: inherit;
            text-decoration: none;
        }

        .hub-home .hub-podcast-link:hover {
            color: var(--hub-coral);
        }

        .hub-home .hub-dark-title,
        .hub-home .hub-dark-title em,
        .hub-home .hub-section-dark h2,
        .hub-home .hub-section-dark h3,
        .hub-home .hub-section-dark h4,
        .hub-home .hub-section-dark p,
        .hub-home .hub-section-dark li,
        .hub-home .hub-section-dark span {
            color: inherit;
        }

        .hub-home .hub-diff-row {
            display: flex;
            gap: 1rem;
            padding: 1.45rem 0;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .hub-home .hub-diff-row:last-child {
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .hub-home .hub-plus {
            font-family: 'Source Serif 4', ui-serif, Georgia, serif;
            font-size: 2rem;
            line-height: 1;
            color: rgba(201, 107, 85, 0.82);
            flex: 0 0 auto;
        }

        .hub-home .hub-stat-card {
            padding: 1.3rem 0.25rem;
            text-align: center;
            background: transparent;
            box-shadow: none;
            border-color: rgba(18,63,64,0.06);
        }

        .hub-home .hub-stat-card strong {
            display: block;
            font-family: 'Source Serif 4', ui-serif, Georgia, serif;
            font-size: clamp(3rem, 9vw, 5rem);
            line-height: 0.95;
            color: var(--hub-deep);
        }

        .hub-home .hub-final {
            text-align: center;
        }

        .hub-home .hub-final-title {
            font-size: clamp(2.8rem, 6vw, 5rem);
            line-height: 0.92;
        }

        .hub-home .hub-inline-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.1rem;
            height: 1.1rem;
            border-radius: 999px;
            background: rgba(201, 107, 85, 0.12);
            color: var(--hub-coral);
            font-size: 0.7rem;
            font-weight: 800;
        }

        .hub-home .hub-muted { color: var(--hub-copy-soft); }

        .hub-home .hub-home-request {
            position: relative;
            overflow: hidden;
            border-radius: 1.95rem;
            border: 1px solid rgba(18, 63, 64, 0.08);
            background: rgba(255, 252, 247, 0.96);
            box-shadow: var(--hub-shadow);
        }

        .hub-home .hub-home-request::before {
            content: '';
            position: absolute;
            inset: 0 0 auto;
            height: 5px;
            background: linear-gradient(90deg, var(--hub-cta) 0%, var(--hub-coral) 62%, #f1e5d2 100%);
        }

        .hub-home .hub-home-request h2,
        .hub-home .hub-home-request h3 {
            font-family: 'Source Serif 4', ui-serif, Georgia, serif;
            color: var(--hub-deep);
            letter-spacing: -0.035em;
        }

        .hub-home .hub-home-request .hub-request-shell {
            padding: 1.3rem;
        }

        .hub-home .hub-home-request .hub-request-kicker {
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--hub-copy-soft);
        }

        .hub-home .hub-home-request .hub-request-title {
            margin-top: 0.35rem;
            font-size: 2rem;
            line-height: 1;
        }

        .hub-home .hub-home-request .hub-request-heart {
            height: 2.35rem;
            width: 2.35rem;
            border-radius: 999px;
            background: rgba(201, 107, 85, 0.12);
            color: var(--hub-coral);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
        }

        .hub-home .hub-home-request .hub-input,
        .hub-home .hub-home-request .hub-textarea,
        .hub-home .hub-home-request .hub-select {
            width: 100%;
            min-height: 3.2rem;
            border-radius: 1.2rem;
            border: 1px solid rgba(18,63,64,0.08);
            background: rgba(255, 255, 255, 0.96);
            padding: 0.85rem 1rem;
            font-size: 0.96rem;
            font-weight: 600;
            color: var(--hub-deep);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.4);
            transition: border-color .16s ease, box-shadow .16s ease, background-color .16s ease;
        }

        .hub-home .hub-home-request .hub-input:focus,
        .hub-home .hub-home-request .hub-textarea:focus,
        .hub-home .hub-home-request .hub-select:focus {
            outline: none;
            border-color: rgba(201, 107, 85, 0.35);
            box-shadow: 0 0 0 4px rgba(201, 107, 85, 0.1);
            background: #fff;
        }

        .hub-home .hub-home-request .hub-request-label {
            display: block;
            margin-bottom: 0.55rem;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: rgba(18, 63, 64, 0.62);
        }

        .hub-home .hub-home-request .hub-request-grid-two {
            display: grid;
            gap: 0.9rem;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .hub-home .hub-home-request .hub-request-submit {
            width: 100%;
            min-height: 3.45rem;
            border-radius: 999px;
            background: var(--hub-deep);
            color: #fff;
            font-size: 0.96rem;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 14px 28px -18px rgba(18, 63, 64, 0.4);
            transition: transform .16s ease, background-color .16s ease;
        }

        .hub-home .hub-home-request .hub-request-submit:hover {
            transform: translateY(-1px);
            background: var(--hub-deep-2);
        }

        .hub-home .hub-home-request .hub-error {
            margin-top: 0.45rem;
            font-size: 0.84rem;
            color: #c23b55;
            font-weight: 600;
        }

        .hub-home .hub-home-request .hub-request-meta {
            margin-top: 0.7rem;
            text-align: center;
            font-size: 0.76rem;
            color: var(--hub-copy-soft);
        }

        .hub-home .hub-section-anchor { scroll-margin-top: 6rem; }

        @media (min-width: 768px) {
            .hub-home .hub-hero {
                padding: 2.15rem 0 5rem;
            }

            .hub-home .hub-hero-actions {
                flex-direction: row;
                align-items: center;
            }

            .hub-home .hub-value-grid,
            .hub-home .hub-trust-grid,
            .hub-home .hub-how-grid,
            .hub-home .hub-stat-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .hub-home .hub-audience-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .hub-home .hub-live-grid,
            .hub-home .hub-how-layout {
                grid-template-columns: minmax(0, 1fr) minmax(0, 0.94fr);
                align-items: center;
            }

            .hub-home .hub-podcast-card {
                display: grid;
                grid-template-columns: 17rem minmax(0, 1fr);
            }

            .hub-home .hub-value-shift-1 {
                margin-top: 1.1rem;
            }

            .hub-home .hub-value-shift-2 {
                margin-top: 2.2rem;
            }

            .hub-home .hub-home-request .hub-request-shell {
                padding: 1.4rem;
            }
        }

        @media (min-width: 1100px) {
            .hub-home .hub-hero-grid {
                grid-template-columns: minmax(0, 0.95fr) minmax(340px, 0.8fr);
                align-items: center;
                gap: 2.35rem;
                padding: 3.6rem 3.4rem 3.85rem;
                min-height: 39.5rem;
            }

            .hub-home .hub-hero-visual {
                justify-self: end;
                width: 100%;
                max-width: 22.5rem;
            }

            .hub-home .hub-quick-wrap {
                max-width: 22rem;
                margin: 0.25rem 0 0 auto;
            }

            .hub-home .hub-live-stack .hub-profile-card {
                position: absolute;
                right: 1.15rem;
                top: 6.25rem;
                width: 10.5rem;
                padding: 0.65rem;
            }

            .hub-home .hub-live-stack .hub-visit-card {
                position: absolute;
                left: 0;
                top: 0.3rem;
                width: 16.75rem;
            }

            .hub-home .hub-live-stack .hub-week-card {
                position: absolute;
                right: 1.25rem;
                bottom: 0.35rem;
                width: 11.2rem;
            }

            .hub-home .hub-live-stack .hub-profile-card img {
                height: 5.6rem;
                border-radius: 0.95rem;
            }

            .hub-home .hub-live-stack .hub-profile-card .space-y-2 {
                display: none;
            }

            .hub-home .hub-live-stack .hub-profile-card .mt-4 {
                margin-top: 0.65rem;
            }

            .hub-home .hub-live-stack .hub-profile-card .text-lg {
                font-size: 1.05rem;
            }

            .hub-home .hub-live-stack .hub-profile-card .text-sm {
                font-size: 0.78rem;
                line-height: 1.4;
            }
        }

        @media (max-width: 767px) {
            .hub-home .hub-container {
                width: min(100vw - 1.25rem, 1180px);
            }

            .hub-home .hub-nav .hub-desktop-nav,
            .hub-home .hub-nav .hub-nav-cta {
                display: none !important;
            }

            .hub-home .hub-hero-copy {
                order: 1;
            }

            .hub-home .hub-hero-visual {
                order: 2;
                margin-top: 0.5rem;
            }

            .hub-home .hub-section {
                padding: 3rem 0;
            }

            .hub-home .hub-human,
            .hub-home .hub-human-copy {
                min-height: 19rem;
            }

            .hub-home .hub-home-request .hub-request-shell {
                padding: 1rem;
            }

            .hub-home .hub-hero-grid {
                padding: 3.45rem 1rem 1.2rem;
                min-height: auto;
            }

            .hub-home .hub-hero-grid::before {
                background:
                    linear-gradient(180deg, rgba(255, 252, 247, 0.95) 0%, rgba(255, 252, 247, 0.82) 44%, rgba(255, 252, 247, 0.22) 100%);
            }

            .hub-home .hub-hero-title {
                max-width: 6.8ch;
                font-size: 3.15rem;
            }

            .hub-home .hub-hero-body {
                max-width: 100%;
                font-size: 1rem;
                line-height: 1.66;
            }

            .hub-home .hub-hero-proofline {
                gap: 0.6rem 1rem;
            }

            .hub-home .hub-proof-inline {
                font-size: 0.8rem;
                padding: 0.38rem 0.64rem;
            }

            .hub-home .hub-proof-inline + .hub-proof-inline::before {
                display: none;
            }

            .hub-home .hub-proof-inline::after {
                display: none;
            }

            .hub-home .hub-home-request .hub-request-title {
                font-size: 1.65rem;
            }

            .hub-home .hub-home-request .hub-request-grid-two {
                grid-template-columns: 1fr;
            }

            .hub-home .hub-mini-toast {
                margin: 0.85rem auto 0;
                width: 100%;
                max-width: 100%;
            }

            .hub-home .hub-live-stack {
                min-height: auto;
                display: grid;
                gap: 1rem;
                max-width: 100%;
            }

            .hub-home .hub-experience-copy {
                padding-top: 0;
                max-width: 100%;
            }

            .hub-home .hub-profile-card,
            .hub-home .hub-visit-card,
            .hub-home .hub-week-card {
                position: relative !important;
                inset: auto !important;
                width: 100% !important;
            }

            .hub-home .hub-section-title {
                font-size: 2.35rem;
                line-height: 0.98;
            }

            .hub-home .hub-support-section .hub-section-title {
                font-size: 2.55rem;
            }

            .hub-home .hub-experience-section .hub-section-title {
                max-width: 8.5ch;
            }

            .hub-home .hub-how-card h3 {
                font-size: 1.55rem;
                line-height: 1.05;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .hub-home *,
            .hub-home *::before,
            .hub-home *::after {
                animation: none !important;
                transition: none !important;
                scroll-behavior: auto !important;
            }
        }

        /* Target-alignment pass based on the supplied Lovable reference. */
        .hub-home {
            background: #fff7ea;
            overflow-x: clip;
            overflow-y: visible;
        }

        .hub-home .hub-container {
            width: 100%;
            max-width: 1280px;
            padding-inline: 1.5rem;
            margin-inline: auto;
        }

        .hub-home .hub-nav {
            position: absolute;
            inset: 0 0 auto;
            z-index: 80;
            background: transparent;
            border: 0;
            backdrop-filter: none;
        }

        .hub-home .hub-brand-link {
            display: inline-flex;
            align-items: center;
            min-height: 2.75rem;
        }

        .hub-home .hub-logo-image {
            display: block;
            height: 2.15rem;
            width: auto;
            max-width: min(10rem, 40vw);
            object-fit: contain;
            filter: drop-shadow(0 10px 24px rgba(35, 72, 63, 0.08));
        }

        .hub-home .hub-logo-mark {
            height: 2.25rem;
            width: 2.25rem;
            border-radius: 999px;
            background: var(--hub-deep);
            box-shadow: none;
        }

        .hub-home .hub-logo-letter {
            font-family: 'Source Serif 4', ui-serif, Georgia, serif;
            color: #fffdf7;
            font-size: 1rem;
            font-weight: 700;
            line-height: 1;
        }

        .hub-home .hub-brand-text {
            font-family: 'Source Serif 4', ui-serif, Georgia, serif;
            color: var(--hub-deep);
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: -0.04em;
        }

        .hub-home .hub-brand-text span {
            font-family: inherit;
            color: var(--hub-coral);
        }

        .hub-home .hub-nav-link {
            font-size: 0.96rem;
            font-weight: 600;
            color: rgba(18, 63, 64, 0.78);
        }

        .hub-home .hub-phone-chip {
            min-height: 2.75rem;
            border-radius: 999px;
            padding: 0.7rem 1rem;
            border: 1px solid rgba(35, 72, 63, 0.12);
            background: rgba(255, 247, 234, 0.78);
            color: var(--hub-deep);
            font-size: 0.9rem;
            font-weight: 700;
            box-shadow: 0 14px 28px -24px rgba(35, 72, 63, 0.28);
            backdrop-filter: blur(10px);
        }

        .hub-home .hub-hero {
            position: relative;
            min-height: 100svh;
            padding: 0 !important;
            background: var(--hub-cream);
        }

        .hub-home .hub-hero > .hub-container {
            max-width: none;
            padding-inline: max(1.5rem, calc((100vw - 1280px) / 2 + 3rem));
        }

        .hub-home .hub-hero-grid {
            min-height: 100svh !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            overflow: hidden;
            grid-template-columns: 1fr;
            align-items: center;
            gap: 2.5rem;
            padding-block: 9rem 5.5rem !important;
            background: transparent !important;
        }

        .hub-home .hub-hero-grid::before {
            background:
                linear-gradient(90deg, rgba(241, 229, 210, 0.96) 0%, rgba(241, 229, 210, 0.8) 41%, rgba(241, 229, 210, 0.1) 100%),
                linear-gradient(0deg, rgba(255, 247, 234, 0.62) 0%, rgba(255, 247, 234, 0) 40%);
        }

        .hub-home .hub-hero-grid::after {
            background-position: center;
            opacity: 1;
        }

        .hub-home .hub-hero-copy {
            max-width: 42rem;
            padding: 0;
        }

        .hub-home .hub-pill {
            padding: 0.42rem 0.85rem;
            border-radius: 999px;
            background: rgba(255, 247, 234, 0.78);
            border-color: rgba(35, 72, 63, 0.08);
            color: var(--hub-deep);
            letter-spacing: 0;
            text-transform: none;
            font-size: 0.76rem;
            font-weight: 600;
        }

        .hub-home .hub-dot {
            height: 0.45rem;
            width: 0.45rem;
        }

        .hub-home .hub-hero-title {
            margin-top: 1.8rem;
            max-width: 12ch;
            font-size: clamp(3rem, 6vw, 5.75rem);
            line-height: 0.95;
            letter-spacing: -0.045em;
            text-shadow: none;
        }

        .hub-home .hub-hero-body {
            margin-top: 1.75rem;
            max-width: 32rem;
            font-size: 1.2rem;
            line-height: 1.72;
            color: rgba(18, 63, 64, 0.78);
            text-shadow: none;
        }

        .hub-home .hub-hero-phone {
            margin-top: 1rem;
            font-size: 1.04rem;
            font-weight: 700;
            color: rgba(18, 63, 64, 0.78);
        }

        .hub-home .hub-hero-phone a {
            color: var(--hub-deep);
            text-decoration: none;
            border-bottom: 1px solid rgba(18, 63, 64, 0.18);
        }

        .hub-home .hub-hero-actions {
            margin-top: 2.45rem;
            flex-direction: row;
            gap: 1.5rem;
            align-items: center;
        }

        .hub-home .hub-button-primary {
            min-height: 3.1rem;
            padding: 0.85rem 1.65rem;
            font-size: 0.9rem;
            font-weight: 650;
            background: var(--hub-cta);
            color: #fff;
            box-shadow: 0 20px 48px -24px rgba(185, 87, 69, 0.42);
        }

        .hub-home .hub-button-primary:hover {
            background: #a84f3f;
        }

        .hub-home .hub-button-secondary {
            min-height: auto;
            border: 0;
            background: transparent;
            padding: 0;
            color: var(--hub-deep);
            box-shadow: none;
        }

        .hub-home .hub-hero-proofline {
            margin-top: 3.1rem !important;
            gap: 1.6rem;
        }

        .hub-home .hub-proof-inline {
            padding: 0;
            border: 0;
            background: transparent;
            box-shadow: none;
            backdrop-filter: none;
            color: rgba(18, 63, 64, 0.55);
            font-size: 0.92rem;
        }

        .hub-home .hub-proof-inline strong {
            color: var(--hub-coral);
        }

        .hub-home .hub-proof-inline + .hub-proof-inline::before {
            content: '';
            height: 0.22rem;
            width: 0.22rem;
            border-radius: 999px;
            background: rgba(18, 63, 64, 0.25);
            margin-right: 0.75rem;
        }

        .hub-home .hub-hero-visual {
            align-items: stretch;
            justify-content: center;
            width: 100%;
            max-width: 27rem;
            justify-self: end;
        }

        .hub-home .hub-quick-wrap {
            max-width: 27rem;
            width: 100%;
        }

        .hub-home .hub-quick-wrap::before {
            inset: -1rem;
            opacity: 0.65;
        }

        .hub-home .hub-home-request {
            border-radius: 1.75rem;
            background: rgba(255, 247, 234, 0.94);
            box-shadow: 0 30px 80px -26px rgba(35, 72, 63, 0.24);
        }

        .hub-home .hub-home-request .hub-request-shell {
            padding: 1.6rem;
        }

        .hub-home .hub-home-request .hub-request-title {
            font-size: 1.55rem;
            line-height: 1.05;
        }

        .hub-home .hub-home-request .hub-request-price {
            margin-top: 0.5rem;
            color: rgba(35, 72, 63, 0.76);
            font-size: 0.98rem;
            font-weight: 650;
            line-height: 1.35;
        }

        .hub-home .hub-home-request .hub-request-price strong {
            color: var(--hub-coral);
            font-weight: 800;
        }

        .hub-home .hub-home-request .hub-input,
        .hub-home .hub-home-request .hub-select {
            min-height: 3.65rem;
            background: rgba(241, 229, 210, 0.74);
            border: 0;
            color: var(--hub-deep);
            font-size: 1rem;
            font-weight: 650;
        }

        .hub-home .hub-home-request .hub-request-label {
            font-size: 0.82rem;
            color: rgba(18, 63, 64, 0.74);
        }

        .hub-home .hub-home-request .hub-request-submit {
            min-height: 3.45rem;
            border-radius: 1.15rem;
            background: var(--hub-cta);
            font-size: 1rem;
            box-shadow: 0 16px 32px -18px rgba(185, 87, 69, 0.44);
        }

        .hub-home .hub-home-request .hub-request-submit:hover {
            background: #a84f3f;
        }

        .hub-home .hub-audience-card,
        .hub-home .hub-value-card,
        .hub-home .hub-trust-card,
        .hub-home .hub-stat-card,
        .hub-home .hub-how-card {
            transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease;
        }

        .hub-home .hub-audience-card:hover,
        .hub-home .hub-value-card:hover,
        .hub-home .hub-trust-card:hover,
        .hub-home .hub-stat-card:hover {
            transform: translateY(-0.2rem);
            box-shadow: 0 24px 58px -34px rgba(35, 72, 63, 0.28);
        }

        .hub-home .hub-mini-toast {
            position: absolute;
            left: -2.2rem;
            bottom: -1.3rem;
            margin: 0;
            transform: rotate(-3deg);
        }

        .hub-home .hub-trust-strip {
            margin-top: 0;
            padding-block: 3rem 5rem;
            background: #fff7ea;
        }

        .hub-home .hub-trust-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
        }

        .hub-home .hub-trust-card {
            background: rgba(255, 250, 240, 0.92);
            border-color: rgba(35, 72, 63, 0.1);
            padding: 1.4rem;
        }

        @media (prefers-reduced-motion: no-preference) {
            .hub-home .hub-hero-title {
                animation: hub-rise-in .72s ease .08s both;
            }

            .hub-home .hub-hero-body,
            .hub-home .hub-hero-phone {
                animation: hub-rise-in .72s ease .2s both;
            }

            .hub-home .hub-hero-actions {
                animation: hub-rise-in .72s ease .32s both;
            }

            .hub-home .hub-hero-proofline {
                animation: hub-rise-in .72s ease .42s both;
            }

            .hub-home .hub-quick-wrap {
                animation: hub-gentle-float 7s ease-in-out infinite;
            }

            .hub-home .hub-mini-toast {
                animation: hub-gentle-float 6.5s ease-in-out 1s infinite;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .hub-home *,
            .hub-home *::before,
            .hub-home *::after {
                animation-duration: .001ms !important;
                animation-iteration-count: 1 !important;
                scroll-behavior: auto !important;
                transition-duration: .001ms !important;
            }
        }

        .hub-home .hub-section {
            padding-block: 6rem;
        }

        .hub-home .hub-support-section {
            min-height: 36rem;
            display: flex;
            align-items: center;
            background: var(--hub-cream);
        }

        .hub-home .hub-support-section .hub-section-title {
            max-width: 42rem;
            margin-inline: auto;
            font-size: clamp(3rem, 5vw, 5rem);
            line-height: 1;
        }

        .hub-home .hub-support-section .hub-section-text {
            margin-top: 1.5rem;
            font-size: 1rem;
        }

        .hub-home .hub-experience-section {
            background: #fff7ea;
            padding-block: 7rem 8rem;
            overflow: hidden;
        }

        .hub-home .hub-live-grid {
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 4rem;
            align-items: center;
        }

        .hub-home .hub-experience-copy {
            grid-column: span 5 / span 5;
            max-width: 30rem;
            padding-top: 0;
        }

        .hub-home .hub-experience-section .hub-section-title {
            max-width: 12ch;
            font-size: clamp(3rem, 4.5vw, 4.55rem);
            line-height: 1;
        }

        .hub-home .hub-live-stack {
            grid-column: span 7 / span 7;
            width: 100%;
            max-width: none;
            height: 32.5rem;
            min-height: 32.5rem;
            margin: 0;
            position: relative;
        }

        .hub-home .hub-profile-card,
        .hub-home .hub-live-stack .hub-profile-card {
            position: absolute;
            top: 0;
            left: 0;
            right: auto;
            bottom: auto;
            width: 18.75rem;
            padding: 1.25rem;
            border-radius: 1.75rem;
            transform: rotate(-2deg);
            z-index: 20;
        }

        .hub-home .hub-profile-card img,
        .hub-home .hub-live-stack .hub-profile-card img {
            height: 11rem;
            border-radius: 1.25rem;
        }

        .hub-home .hub-profile-card .space-y-2,
        .hub-home .hub-live-stack .hub-profile-card .space-y-2 {
            display: none;
        }

        .hub-home .hub-visit-card,
        .hub-home .hub-live-stack .hub-visit-card {
            position: absolute;
            top: 3rem;
            right: 1rem;
            left: auto;
            bottom: auto;
            width: 17.5rem;
            padding: 1.5rem;
            border-radius: 1.75rem;
            transform: rotate(3deg);
            z-index: 10;
        }

        .hub-home .hub-week-card,
        .hub-home .hub-live-stack .hub-week-card {
            position: absolute;
            bottom: 0;
            left: 3rem;
            top: auto;
            right: auto;
            width: 20rem;
            padding: 1.25rem;
            border-radius: 1.75rem;
            transform: rotate(-1deg);
        }

        .hub-home .hub-audience-card {
            border-radius: 1.75rem;
        }

        .hub-home .hub-audience-card-dark {
            transform: translateY(3rem);
            background: var(--hub-deep);
        }

        .hub-home .hub-values-section {
            background: #fff7ea;
            padding-block: 6rem 8rem;
        }

        .hub-home .hub-values-section .hub-section-title {
            max-width: 13ch;
            font-size: clamp(2.65rem, 4vw, 4rem);
            line-height: 1.05;
        }

        .hub-home .hub-value-grid {
            gap: 1.25rem;
        }

        .hub-home .hub-value-card {
            background: var(--hub-cream);
            border-radius: 1.5rem;
            padding: 2rem;
            box-shadow: 0 18px 46px -34px rgba(18, 63, 64, 0.16);
        }

        .hub-home .hub-value-shift-1 {
            margin-top: 2rem;
        }

        .hub-home .hub-value-shift-2 {
            margin-top: 4rem;
        }

        .hub-home .hub-human,
        .hub-home .hub-human-copy {
            min-height: 80svh;
        }

        .hub-home .hub-human-copy {
            align-items: flex-end;
            padding-bottom: 7rem;
        }

        .hub-home .hub-human-title {
            max-width: 46rem;
            font-size: clamp(2.5rem, 6vw, 5.5rem);
            line-height: 0.95;
        }

        .hub-home .hub-how-section {
            background: var(--hub-cream);
            padding-block: 6rem 8rem;
        }

        .hub-home .hub-how-layout {
            grid-template-columns: minmax(0, 1fr);
            gap: 4rem;
        }

        .hub-home .hub-how-section .hub-section-title {
            max-width: none;
            font-size: clamp(2.6rem, 4vw, 4rem);
            line-height: 1.05;
        }

        .hub-home .hub-how-card {
            background: transparent;
            border: 0;
            box-shadow: none;
            border-radius: 0;
            padding: 0;
        }

        .hub-home .hub-podcast-card {
            display: grid;
            grid-template-columns: 17.5rem minmax(0, 1fr);
            max-width: 45rem;
            margin-inline: auto;
            background: #fff7ea;
            color: var(--hub-deep);
            border-radius: 1.75rem;
            box-shadow: 0 26px 70px -38px rgba(18, 63, 64, 0.28);
        }

        .hub-home .hub-podcast-card h3,
        .hub-home .hub-podcast-card p,
        .hub-home .hub-podcast-card blockquote,
        .hub-home .hub-podcast-card span {
            color: var(--hub-deep) !important;
        }

        .hub-home .hub-podcast-card .hub-podcast-image {
            min-height: 17.5rem;
        }

        .hub-home .hub-section-dark {
            background: var(--hub-deep);
            padding-block: 7rem;
        }

        .hub-home .hub-difference-section .hub-live-grid {
            grid-template-columns: repeat(12, minmax(0, 1fr));
            align-items: start;
            gap: 5rem;
        }

        .hub-home .hub-difference-section .hub-live-grid > div:first-child {
            grid-column: span 5 / span 5;
        }

        .hub-home .hub-difference-section .hub-live-grid > div:last-child {
            grid-column: span 7 / span 7;
        }

        .hub-home .hub-stat-grid {
            gap: 2.5rem;
        }

        .hub-home .hub-stat-card {
            border: 0;
        }

        .hub-home .hub-final-section {
            background: var(--hub-cream);
            padding-block: 8rem 9rem;
        }

        .hub-home .hub-final-title {
            font-size: clamp(3rem, 8vw, 6.5rem);
            line-height: 0.95;
        }

        @media (min-width: 1024px) {
            .hub-home .hub-container {
                padding-inline: 3rem;
            }

            .hub-home .hub-hero-grid {
                grid-template-columns: repeat(12, minmax(0, 1fr));
            }

            .hub-home .hub-hero-copy {
                grid-column: span 7 / span 7;
            }

            .hub-home .hub-hero-visual {
                grid-column: span 5 / span 5;
            }
        }

        @media (max-width: 767px) {
            .hub-home .hub-container {
                width: 100%;
                padding-inline: 1.25rem;
            }

            .hub-home .hub-nav {
                position: absolute;
            }

            .hub-home .hub-brand-text {
                font-size: 1.05rem;
            }

            .hub-home .hub-logo-image {
                height: 1.85rem;
                max-width: 8rem;
            }

            .hub-home .hub-hero-grid {
                padding: 7rem 1.25rem 4rem !important;
                min-height: auto !important;
            }

            .hub-home .hub-hero-title {
                max-width: 9ch;
                font-size: 3.15rem;
            }

            .hub-home .hub-hero-actions {
                flex-direction: column;
                align-items: flex-start;
            }

            .hub-home .hub-hero-phone {
                font-size: 0.9rem;
            }

            .hub-home .hub-hero-visual {
                max-width: 100%;
            }

            .hub-home .hub-mini-toast {
                position: relative;
                left: auto;
                bottom: auto;
                margin-top: 0.9rem;
                width: 100%;
                transform: none;
            }

            .hub-home .hub-support-section {
                min-height: auto;
                padding-block: 5rem;
            }

            .hub-home .hub-support-section .hub-section-title,
            .hub-home .hub-experience-section .hub-section-title,
            .hub-home .hub-values-section .hub-section-title,
            .hub-home .hub-how-section .hub-section-title,
            .hub-home .hub-difference-section .hub-section-title {
                font-size: 2.65rem;
                max-width: none;
            }

            .hub-home .hub-live-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 2.5rem;
            }

            .hub-home .hub-trust-grid {
                grid-template-columns: 1fr;
            }

            .hub-home .hub-experience-copy {
                grid-column: auto;
            }

            .hub-home .hub-live-stack {
                grid-column: auto;
                height: auto;
                min-height: auto;
                display: grid;
                gap: 1rem;
            }

            .hub-home .hub-profile-card,
            .hub-home .hub-visit-card,
            .hub-home .hub-week-card {
                position: relative !important;
                inset: auto !important;
                width: 100% !important;
                transform: none !important;
            }

            .hub-home .hub-audience-card-dark,
            .hub-home .hub-value-shift-1,
            .hub-home .hub-value-shift-2 {
                transform: none;
                margin-top: 0;
            }

            .hub-home .hub-podcast-card {
                grid-template-columns: 1fr;
            }

            .hub-home .hub-difference-section .hub-live-grid,
            .hub-home .hub-difference-section .hub-live-grid > div:first-child,
            .hub-home .hub-difference-section .hub-live-grid > div:last-child {
                display: grid;
                grid-template-columns: 1fr;
                grid-column: auto;
                gap: 2rem;
            }

            .hub-home .hub-human,
            .hub-home .hub-human-copy {
                min-height: 32rem;
            }
        }
    </style>

    <livewire:family-landing />

    <script>
        (() => {
            const home = document.querySelector('.hub-home');
            if (!home) return;

            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            if (reduceMotion || !('IntersectionObserver' in window)) {
                document.querySelectorAll('[data-hub-reveal]').forEach((item) => item.classList.add('is-visible'));
                return;
            }

            home.classList.add('hub-motion-ready');

            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) return;

                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                });
            }, { threshold: 0.16 });

            document.querySelectorAll('[data-hub-reveal]').forEach((item) => observer.observe(item));
        })();
    </script>
@endsection
