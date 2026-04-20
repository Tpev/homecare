@extends('layouts.marketing')

@section('title', 'Home Care HUB | Care when you need it most')
@section('meta_description', 'Home Care HUB helps families get trusted non-medical home support in minutes, from quick check-ins to a few focused hours or broader daytime care.')
@section('canonical', route('landing'))
@section('og_image', asset('images/marketing/homepage/hero-care.jpg'))
@section('og_image_alt', 'Caregiver smiling with an older adult at home in a warm kitchen.')

@section('content')
    <style>
        .hub-home {
            --hub-bg: #faf7f2;
            --hub-surface: rgba(255, 252, 247, 0.92);
            --hub-card: #fffdfa;
            --hub-cream: #f6efe6;
            --hub-deep: #123f40;
            --hub-deep-2: #0d3233;
            --hub-copy: #627079;
            --hub-copy-soft: #7d8890;
            --hub-lavender: #7c5ddc;
            --hub-blue: #6f88ff;
            --hub-border: rgba(18, 63, 64, 0.1);
            --hub-shadow: 0 32px 80px -36px rgba(18, 63, 64, 0.26);
            --hub-shadow-soft: 0 16px 42px -24px rgba(18, 63, 64, 0.18);
            background:
                radial-gradient(circle at top left, rgba(124, 93, 220, 0.14), transparent 22%),
                radial-gradient(circle at top right, rgba(111, 136, 255, 0.12), transparent 28%),
                linear-gradient(180deg, #fffdf9 0%, var(--hub-bg) 34%, #fffdfa 100%);
            color: var(--hub-deep);
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
            border: 1px solid rgba(124, 93, 220, 0.16);
            background: rgba(255, 255, 255, 0.78);
            padding: 0.55rem 0.9rem;
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--hub-lavender);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
        }

        .hub-home .hub-dot {
            height: 0.5rem;
            width: 0.5rem;
            border-radius: 999px;
            background: var(--hub-lavender);
            box-shadow: 0 0 0 0 rgba(124, 93, 220, 0.5);
            animation: hub-pulse 2.2s infinite;
        }

        @keyframes hub-pulse {
            0% { box-shadow: 0 0 0 0 rgba(124, 93, 220, 0.45); }
            70% { box-shadow: 0 0 0 12px rgba(124, 93, 220, 0); }
            100% { box-shadow: 0 0 0 0 rgba(124, 93, 220, 0); }
        }

        .hub-home .hub-nav-link {
            font-size: 0.95rem;
            font-weight: 600;
            color: rgba(18, 63, 64, 0.72);
            transition: color .18s ease;
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
            background: rgba(124, 93, 220, 0.12);
            color: var(--hub-lavender);
            border: 1px solid rgba(124, 93, 220, 0.18);
            padding: 0.9rem 1.3rem;
        }

        .hub-home .hub-button-secondary:hover {
            transform: translateY(-1px);
            background: rgba(124, 93, 220, 0.16);
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
            padding: 1.5rem 0 4.5rem;
        }

        .hub-home .hub-hero-grid {
            display: grid;
            gap: 2rem;
            position: relative;
            padding: 3rem 1.2rem 2.4rem;
            border-radius: 2.5rem;
            overflow: hidden;
            min-height: 40rem;
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
                radial-gradient(circle at left center, rgba(255, 252, 247, 0.76) 0%, rgba(255, 252, 247, 0.36) 28%, rgba(255, 252, 247, 0) 62%),
                linear-gradient(180deg, rgba(18, 63, 64, 0.02) 0%, rgba(18, 63, 64, 0.12) 100%);
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
            max-width: 36rem;
        }

        .hub-home .hub-kicker {
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--hub-lavender);
        }

        .hub-home .hub-hero-title {
            margin-top: 1.8rem;
            max-width: 10ch;
            font-size: clamp(3rem, 10vw, 5.6rem);
            line-height: 0.92;
        }

        .hub-home .hub-hero-title em,
        .hub-home .hub-final-title em,
        .hub-home .hub-section-title em,
        .hub-home .hub-highlight {
            color: var(--hub-lavender);
            font-style: italic;
            font-weight: 400;
        }

        .hub-home .hub-hero-body {
            margin-top: 1.25rem;
            max-width: 30rem;
            font-size: 1.02rem;
            line-height: 1.8;
            color: var(--hub-copy);
        }

        .hub-home .hub-hero-actions {
            margin-top: 1.6rem;
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
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
            color: var(--hub-copy);
        }

        .hub-home .hub-proof-inline::after {
            content: '•';
            margin-left: 0.5rem;
            color: rgba(18, 63, 64, 0.2);
        }

        .hub-home .hub-proof-inline:last-child::after {
            display: none;
        }

        .hub-home .hub-proof-inline strong {
            color: var(--hub-lavender);
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

        .hub-home .hub-hero-visual {
            position: relative;
            z-index: 2;
        }

        .hub-home .hub-quick-wrap {
            position: relative;
            max-width: 23rem;
            margin-inline: auto;
        }

        .hub-home .hub-quick-wrap::before {
            content: '';
            position: absolute;
            inset: -1rem;
            border-radius: 2.5rem;
            background: radial-gradient(circle, rgba(124, 93, 220, 0.2) 0%, rgba(124, 93, 220, 0) 70%);
            z-index: 0;
        }

        .hub-home .hub-quick-wrap > * { position: relative; z-index: 1; }

        .hub-home .hub-mini-toast {
            position: relative;
            width: fit-content;
            max-width: calc(100% - 2rem);
            margin: -1rem auto 0 1rem;
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
            background: rgba(124, 93, 220, 0.14);
            color: var(--hub-lavender);
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
            padding: 4.75rem 0;
        }

        .hub-home .hub-section-alt { background: rgba(255,255,255,0.72); }
        .hub-home .hub-section-soft { background: rgba(246, 239, 230, 0.55); }
        .hub-home .hub-section-dark {
            background: linear-gradient(180deg, #123f40 0%, #0e3334 100%);
            color: rgba(255,255,255,0.92);
        }

        .hub-home .hub-section-title {
            font-size: clamp(2.15rem, 6vw, 4rem);
            line-height: 0.98;
        }

        .hub-home .hub-section-text {
            margin-top: 1rem;
            font-size: 1rem;
            line-height: 1.8;
            color: var(--hub-copy);
            max-width: 40rem;
        }

        .hub-home .hub-live-grid,
        .hub-home .hub-how-layout {
            display: grid;
            gap: 2rem;
        }

        .hub-home .hub-live-stack {
            position: relative;
            min-height: 34rem;
        }

        .hub-home .hub-profile-card {
            position: relative;
            width: min(100%, 21rem);
            padding: 1rem;
        }

        .hub-home .hub-profile-card img {
            width: 100%;
            height: 12rem;
            object-fit: cover;
            border-radius: 1.4rem;
        }

        .hub-home .hub-visit-card,
        .hub-home .hub-week-card {
            padding: 1.35rem;
        }

        .hub-home .hub-visit-card {
            background: linear-gradient(180deg, var(--hub-deep) 0%, #194a4b 100%);
            color: rgba(255,255,255,0.95);
            border-color: rgba(255,255,255,0.08);
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
            background: linear-gradient(180deg, var(--hub-deep) 0%, #173f55 100%);
            color: rgba(255,255,255,0.95);
            border-color: rgba(255,255,255,0.08);
        }

        .hub-home .hub-value-card {
            padding: 1.4rem;
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
            background: rgba(124, 93, 220, 0.1);
            color: var(--hub-lavender);
        }

        .hub-home .hub-human {
            position: relative;
            min-height: 24rem;
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
            padding: 3rem 1.25rem;
            display: flex;
            align-items: flex-end;
            min-height: 24rem;
        }

        .hub-home .hub-human-title {
            font-size: clamp(2.2rem, 7vw, 4.8rem);
            line-height: 0.95;
            color: #fffefb;
        }

        .hub-home .hub-how-card {
            padding: 1.3rem;
            background: rgba(255, 252, 247, 0.8);
        }

        .hub-home .hub-step-no {
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--hub-lavender);
        }

        .hub-home .hub-podcast-card {
            overflow: hidden;
            background: linear-gradient(180deg, var(--hub-deep) 0%, #173f55 100%);
            color: rgba(255,255,255,0.95);
            border-color: rgba(255,255,255,0.08);
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
            padding: 1.3rem 0;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .hub-home .hub-diff-row:last-child {
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .hub-home .hub-plus {
            font-family: 'Source Serif 4', ui-serif, Georgia, serif;
            font-size: 2rem;
            line-height: 1;
            color: rgba(124, 93, 220, 0.82);
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
            font-size: clamp(3rem, 10vw, 6rem);
            line-height: 0.92;
        }

        .hub-home .hub-inline-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.1rem;
            height: 1.1rem;
            border-radius: 999px;
            background: rgba(124, 93, 220, 0.12);
            color: var(--hub-lavender);
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
            background: linear-gradient(90deg, var(--hub-blue) 0%, var(--hub-lavender) 56%, #f3b5c6 100%);
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
            background: rgba(124, 93, 220, 0.12);
            color: var(--hub-lavender);
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
            background: rgba(246, 239, 230, 0.56);
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
            border-color: rgba(124, 93, 220, 0.35);
            box-shadow: 0 0 0 4px rgba(124, 93, 220, 0.1);
            background: #fff;
        }

        .hub-home .hub-home-request .hub-request-label {
            display: block;
            margin-bottom: 0.55rem;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: var(--hub-copy-soft);
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
                padding: 3rem 0 6rem;
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
                grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
                align-items: center;
            }

            .hub-home .hub-podcast-card {
                display: grid;
                grid-template-columns: 18rem minmax(0, 1fr);
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
                grid-template-columns: minmax(0, 1fr) minmax(380px, 0.72fr);
                align-items: center;
                gap: 3rem;
                padding: 4.25rem 3.5rem 5rem;
                min-height: 42rem;
            }

            .hub-home .hub-hero-visual {
                justify-self: end;
            }

            .hub-home .hub-quick-wrap {
                max-width: 22rem;
                margin: 1.5rem 0 0 auto;
            }

            .hub-home .hub-live-stack .hub-profile-card {
                position: absolute;
                left: 0;
                top: 0;
            }

            .hub-home .hub-live-stack .hub-visit-card {
                position: absolute;
                right: 0;
                top: 2.25rem;
                width: 19rem;
            }

            .hub-home .hub-live-stack .hub-week-card {
                position: absolute;
                left: 5rem;
                bottom: 0;
                width: 22rem;
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
                padding: 3.8rem 0;
            }

            .hub-home .hub-human,
            .hub-home .hub-human-copy {
                min-height: 19rem;
            }

            .hub-home .hub-home-request .hub-request-shell {
                padding: 1rem;
            }

            .hub-home .hub-hero-grid {
                padding: 4.4rem 1.1rem 1.35rem;
                min-height: auto;
            }

            .hub-home .hub-hero-grid::before {
                background:
                    linear-gradient(180deg, rgba(255, 252, 247, 0.92) 0%, rgba(255, 252, 247, 0.72) 44%, rgba(255, 252, 247, 0.18) 100%);
            }

            .hub-home .hub-hero-title {
                max-width: 8.5ch;
                font-size: 3.4rem;
            }

            .hub-home .hub-hero-body {
                max-width: 100%;
                font-size: 0.97rem;
                line-height: 1.7;
            }

            .hub-home .hub-hero-proofline {
                gap: 0.6rem 1rem;
            }

            .hub-home .hub-proof-inline {
                font-size: 0.8rem;
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
    </style>

    <livewire:family-landing />
@endsection
