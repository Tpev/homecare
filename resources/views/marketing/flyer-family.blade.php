@extends('layouts.marketing')

@section('title', 'Family Care Flyer | Hub Home Care Raleigh')
@section('meta_description', 'Need help caring for your mom or dad in Raleigh? Hub Home Care is like Uber for home care: trusted help for meals, companionship, errands, and more.')
@section('canonical', route('marketing.flyer.family'))

@section('content')
    <style>
        .flyer-page {
            padding: 2rem 1rem 3rem;
        }

        .flyer-sheet {
            max-width: 1060px;
            margin: 0 auto;
            border-radius: 26px;
            overflow: hidden;
            background: #ffffff;
            border: 1px solid #d8e6f2;
            box-shadow: 0 26px 70px rgba(8, 36, 58, 0.2);
        }

        .flyer-top {
            padding: 0.8rem 1.1rem;
            font-size: 0.8rem;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #deeff7;
            background: linear-gradient(120deg, #0a4566 0%, #0f8fb3 60%, #18b77c 100%);
        }

        .flyer-hero {
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
            gap: 1.2rem;
            align-items: stretch;
            padding: 1.5rem 1.4rem 1.25rem;
            background:
                radial-gradient(circle at 10% 10%, rgba(15, 143, 179, 0.14), transparent 34%),
                radial-gradient(circle at 90% 20%, rgba(24, 183, 124, 0.14), transparent 30%),
                #f5fbff;
        }

        .flyer-hero-copy {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 0.9rem;
        }

        .flyer-title {
            margin: 0;
            font-family: 'Outfit', sans-serif;
            font-size: clamp(2rem, 3vw, 2.85rem);
            line-height: 1.03;
            letter-spacing: -0.03em;
            color: #0a2840;
        }

        .flyer-subtitle {
            margin: 0.85rem 0 0;
            color: #355269;
            font-size: 1.03rem;
            line-height: 1.55;
            max-width: 580px;
        }

        .flyer-heartline {
            margin: 0.65rem 0 0;
            font-size: 0.95rem;
            line-height: 1.5;
            color: #214760;
            font-weight: 600;
        }

        .flyer-emphasis {
            display: inline-block;
            border-radius: 999px;
            padding: 0.1rem 0.48rem;
            font-weight: 800;
            letter-spacing: 0.01em;
            color: #08324d;
            background: linear-gradient(120deg, #d9f0fb 0%, #eaf8ff 100%);
            border: 1px solid #badbeb;
        }

        .flyer-hero-flow {
            margin-top: 0.9rem;
            border: 1px solid #cfe4f1;
            background: linear-gradient(145deg, #fafdff 0%, #edf7fc 100%);
            border-radius: 16px;
            padding: 0.6rem 0.6rem 0.75rem;
            max-width: 360px;
        }

        .flyer-hero-flow h2 {
            margin: 0 0 0.55rem;
            font-family: 'Outfit', sans-serif;
            font-size: 0.98rem;
            color: #0b3653;
            text-align: center;
        }

        .flyer-flow-circle {
            position: relative;
            width: min(100%, 320px);
            aspect-ratio: 1 / 1;
            margin: 0 auto;
        }

        .flyer-flow-ring {
            position: absolute;
            inset: 12% 11%;
            border-radius: 999px;
            border: 2px dashed #a9cfdf;
            background: radial-gradient(circle at center, rgba(22, 159, 200, 0.08), rgba(255, 255, 255, 0));
        }

        .flyer-flow-center {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 36%;
            aspect-ratio: 1 / 1;
            border-radius: 999px;
            color: #ffffff;
            background: linear-gradient(135deg, #0f5b84 0%, #0f8fb3 62%, #1cb67f 100%);
            border: 2px solid #ffffff;
            box-shadow: 0 10px 22px rgba(10, 70, 102, 0.24);
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 0.86rem;
            line-height: 1.18;
            letter-spacing: 0.02em;
        }

        .flyer-flow-node {
            position: absolute;
            width: 41%;
            min-height: 21%;
            border-radius: 12px;
            border: 1px solid #cbe2f0;
            background: #ffffff;
            padding: 0.48rem 0.5rem;
            text-align: center;
            box-shadow: 0 8px 16px rgba(10, 70, 102, 0.08);
        }

        .flyer-flow-node-1 {
            top: 0.5%;
            left: 50%;
            transform: translateX(-50%);
        }

        .flyer-flow-node-2 {
            right: 1%;
            bottom: 6%;
        }

        .flyer-flow-node-3 {
            left: 1%;
            bottom: 6%;
        }

        .flyer-flow-node strong {
            display: block;
            color: #0a3451;
            font-size: 0.78rem;
            line-height: 1.2;
        }

        .flyer-flow-node span {
            display: block;
            margin-top: 0.2rem;
            color: #3d5b70;
            font-size: 0.75rem;
            line-height: 1.2;
        }

        .flyer-flow-arrow {
            position: absolute;
            width: 1.3rem;
            height: 2px;
            background: #0f8fb3;
            border-radius: 999px;
        }

        .flyer-flow-arrow::after {
            content: "";
            position: absolute;
            right: -5px;
            top: 50%;
            transform: translateY(-50%);
            border-top: 4px solid transparent;
            border-bottom: 4px solid transparent;
            border-left: 6px solid #0f8fb3;
        }

        .flyer-flow-arrow-1 {
            right: 18%;
            top: 29%;
            transform: rotate(54deg);
        }

        .flyer-flow-arrow-2 {
            left: 50%;
            bottom: 20%;
            transform: translateX(-50%) rotate(180deg);
        }

        .flyer-flow-arrow-3 {
            left: 18%;
            top: 29%;
            transform: rotate(-54deg);
        }

        .flyer-hero-image {
            position: relative;
            border-radius: 18px;
            overflow: hidden;
            height: clamp(380px, 42vw, 530px);
            box-shadow: 0 16px 36px rgba(7, 33, 54, 0.18);
            border: 1px solid #c8deec;
        }

        .flyer-hero-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center top;
            display: block;
        }

        .flyer-emotion {
            margin: 0.8rem 0;
            border-radius: 14px;
            border: 1px solid #d3e6f2;
            background: linear-gradient(120deg, #ffffff 0%, #f2f9fd 100%);
            padding: 0.8rem 0.85rem;
        }

        .flyer-emotion h2 {
            margin: 0;
            font-family: 'Outfit', sans-serif;
            font-size: 1rem;
            color: #0b3653;
        }

        .flyer-emotion p {
            margin: 0.35rem 0 0;
            color: #355269;
            font-size: 0.9rem;
            line-height: 1.45;
        }

        .flyer-image-badge {
            position: absolute;
            left: 0.7rem;
            bottom: 0.7rem;
            font-size: 0.76rem;
            color: #ffffff;
            background: rgba(8, 38, 59, 0.82);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 999px;
            padding: 0.35rem 0.6rem;
            backdrop-filter: blur(2px);
        }

        .flyer-main {
            padding: 0 1.4rem 1.4rem;
            background: #f5fbff;
        }

        .flyer-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.8rem;
            margin-bottom: 0.8rem;
        }

        .flyer-card {
            background: #ffffff;
            border: 1px solid #d8e6f2;
            border-radius: 16px;
            padding: 0.9rem;
        }

        .flyer-card h2 {
            margin: 0 0 0.45rem;
            font-family: 'Outfit', sans-serif;
            font-size: 1.03rem;
            color: #0b2f49;
        }

        .flyer-card p,
        .flyer-card li {
            margin: 0;
            color: #385268;
            line-height: 1.45;
            font-size: 0.9rem;
        }

        .flyer-list {
            margin: 0;
            padding-left: 1rem;
            display: grid;
            gap: 0.35rem;
        }

        .flyer-trust {
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 1rem;
            color: #ffffff;
            background: linear-gradient(132deg, #0a3554 0%, #0f5b84 52%, #0f8fb3 100%);
            margin-bottom: 0.8rem;
        }

        .flyer-trust h2 {
            margin: 0 0 0.6rem;
            font-family: 'Outfit', sans-serif;
            font-size: 1.13rem;
            color: #ffffff;
        }

        .flyer-trust-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.45rem;
        }

        .flyer-trust-item {
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.18);
            padding: 0.5rem 0.55rem;
            background: rgba(255, 255, 255, 0.1);
            font-size: 0.86rem;
            color: #e8f7ff;
        }

        .flyer-cta {
            border-radius: 16px;
            border: 2px dashed #94bed4;
            padding: 0.9rem;
            text-align: center;
            background: #ffffff;
        }

        .flyer-cta h2 {
            margin: 0;
            font-family: 'Outfit', sans-serif;
            font-size: 1.16rem;
            color: #0b3653;
        }

        .flyer-cta p {
            margin: 0.35rem 0 0;
            color: #3b5a71;
            font-size: 0.92rem;
        }

        .flyer-contact {
            margin-top: 0.65rem;
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .flyer-contact-chip {
            border-radius: 999px;
            background: #0f8fb3;
            color: #ffffff;
            font-size: 0.84rem;
            font-weight: 700;
            padding: 0.42rem 0.8rem;
        }

        @media (max-width: 960px) {
            .flyer-hero {
                grid-template-columns: 1fr;
            }

            .flyer-hero-image {
                height: 360px;
            }

            .flyer-grid {
                grid-template-columns: 1fr;
            }

            .flyer-trust-grid {
                grid-template-columns: 1fr;
            }

            .flyer-flow-circle {
                width: 100%;
                aspect-ratio: auto;
                display: grid;
                grid-template-columns: 1fr;
                gap: 0.42rem;
            }

            .flyer-flow-ring {
                display: none;
            }

            .flyer-flow-center {
                position: static;
                transform: none;
                width: 100%;
                aspect-ratio: auto;
                border-radius: 12px;
                padding: 0.45rem 0.55rem;
                font-size: 0.82rem;
            }

            .flyer-flow-node {
                position: static;
                width: 100%;
                min-height: 0;
            }

            .flyer-flow-arrow {
                display: none;
            }

        }

        @media print {
            body {
                background: #ffffff !important;
            }

            footer {
                display: none !important;
            }

            .flyer-page {
                padding: 0 !important;
            }

            .flyer-sheet {
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
                max-width: none !important;
            }

            .flyer-top,
            .flyer-trust {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>

    <section class="flyer-page">
        <article class="flyer-sheet">
            <div class="flyer-top">Hub Home Care | Trusted home support in Raleigh, NC</div>

            <header class="flyer-hero">
                <div class="flyer-hero-copy">
                    <div>
                    <h1 class="flyer-title">Need help caring for your mom or dad?</h1>
                    <p class="flyer-subtitle">
                        Need support <span class="flyer-emphasis">now</span>? Hub Home Care helps Raleigh families
                        find <span class="flyer-emphasis">trusted</span> caregivers quickly, with
                        <span class="flyer-emphasis">affordable</span> options for meal prep, companionship, errands,
                        and daily support.
                    </p>
                    <p class="flyer-heartline">
                        When you are juggling work, family, and worry, we help bring calm, dignity, and reliable support at home.
                    </p>
                    <section class="flyer-hero-flow">
                        <h2>How It Works</h2>
                        <div class="flyer-flow-circle">
                            <div class="flyer-flow-ring"></div>
                            <div class="flyer-flow-center">How It<br>Works</div>
                            <div class="flyer-flow-node flyer-flow-node-1">
                                <strong>1. Tell Us</strong>
                                <span>Share needs and schedule</span>
                            </div>
                            <div class="flyer-flow-node flyer-flow-node-2">
                                <strong>2. Match</strong>
                                <span>Review and chat with caregivers</span>
                            </div>
                            <div class="flyer-flow-node flyer-flow-node-3">
                                <strong>3. Start Care</strong>
                                <span>Book securely and get support</span>
                            </div>
                            <span class="flyer-flow-arrow flyer-flow-arrow-1" aria-hidden="true"></span>
                            <span class="flyer-flow-arrow flyer-flow-arrow-2" aria-hidden="true"></span>
                            <span class="flyer-flow-arrow flyer-flow-arrow-3" aria-hidden="true"></span>
                        </div>
                    </section>
                    </div>
                </div>

                <figure class="flyer-hero-image">
                    <img
                        src="{{ asset('images/marketing/flyer.png') }}"
                        alt="Young caregiver helping an older adult at home"
                    >
                    <figcaption class="flyer-image-badge">Real support. Real peace of mind.</figcaption>
                </figure>
            </header>

            <div class="flyer-main">
                <section class="flyer-grid">
                    <article class="flyer-card">
                        <h2>What We Help With</h2>
                        <ul class="flyer-list">
                            <li>Meal prep and kitchen support</li>
                            <li>Companionship and social check-ins</li>
                            <li>Grocery shopping and errands</li>
                            <li>Light housekeeping help</li>
                            <li>Rides to appointments and activities</li>
                        </ul>
                    </article>

                    <article class="flyer-card">
                        <h2>Why Families Choose Us</h2>
                        <p>Raleigh-based, faster than traditional agencies, and built around trusted matches.</p>
                    </article>

                    <article class="flyer-card">
                        <h2>Flexible Care</h2>
                        <p>Book one-time help or ongoing weekly support based on your loved one's schedule and needs.</p>
                    </article>
                </section>

                <section class="flyer-emotion">
                    <h2>Care is not just tasks. It is trust, comfort, and presence.</h2>
                    <p>Our goal is to make sure your loved one feels safe and seen, and you feel less alone in the process.</p>
                </section>

                <section class="flyer-trust">
                    <h2>Trust & Security Are Our Priority</h2>
                    <div class="flyer-trust-grid">
                        <div class="flyer-trust-item">Caregiver identity verification before care begins</div>
                        <div class="flyer-trust-item">Secure encrypted checkout for every payment</div>
                        <div class="flyer-trust-item">Private in-platform messaging to protect your family</div>
                        <div class="flyer-trust-item">Transparent profiles and accountability standards</div>
                        <div class="flyer-trust-item">Responsive support team for urgent help</div>
                        <div class="flyer-trust-item">Clear safety and quality policies across the platform</div>
                    </div>
                </section>

                <section class="flyer-cta">
                    <h2>Call Hub Home Care and get trusted help today.</h2>
                    <p>Because your loved one deserves warm, reliable care and you deserve peace of mind.</p>
                    <div class="flyer-contact">
                        <span class="flyer-contact-chip">Call: (555) 123-4567</span>
                        <span class="flyer-contact-chip">Visit: hubhomecare.example</span>
                    </div>
                </section>
            </div>
        </article>
    </section>
@endsection
