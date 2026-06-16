@extends('layouts.marketing')

@section('title', 'Private home care callback | LoLo')
@section('meta_description', 'Request a LoLo callback for non-medical home care, companionship, errands, meal support, and flexible everyday help at home.')
@section('canonical', route('landing.get-care'))
@section('og_image', asset('images/marketing/homepage/hero-care.jpg'))
@section('og_image_alt', 'A caregiver and older adult smiling together at home.')

@section('content')
    <style>
        .lolo-callback-page {
            --care-deep: #0f3d3e;
            --care-deep-soft: #23483f;
            --care-blue: #4f6faf;
            --care-lavender: #7c5ddc;
            --care-cream: #f5f1eb;
            --care-white: #faf9f7;
            --care-oat: #f1e5d2;
            --care-coral: #c96b55;
            --care-cta: #b95745;
            --care-ink: #24302d;
            --care-muted: #64736f;
            color: var(--care-deep);
            background: var(--care-cream);
            font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
        }

        .lolo-callback-page *,
        .lolo-callback-page *::before,
        .lolo-callback-page *::after {
            box-sizing: border-box;
        }

        .lolo-callback-page h1,
        .lolo-callback-page h2,
        .lolo-callback-page h3 {
            margin: 0;
            color: var(--care-deep);
            font-family: 'Source Serif 4', ui-serif, Georgia, serif;
            letter-spacing: 0;
        }

        .lolo-callback-page p {
            margin: 0;
        }

        .lolo-callback-container {
            width: min(1160px, calc(100vw - 2rem));
            margin-inline: auto;
        }

        .lolo-callback-hero {
            position: relative;
            isolation: isolate;
            overflow: hidden;
            background-image:
                linear-gradient(90deg, rgba(250, 249, 247, 0.96) 0%, rgba(250, 249, 247, 0.87) 40%, rgba(250, 249, 247, 0.32) 72%),
                linear-gradient(180deg, rgba(15, 61, 62, 0.12) 0%, rgba(15, 61, 62, 0.34) 100%),
                url('{{ asset('images/marketing/homepage/hero-care.jpg') }}');
            background-size: cover;
            background-position: center;
        }

        .lolo-callback-hero::after {
            content: "";
            position: absolute;
            inset: auto 0 0;
            height: 5rem;
            background: var(--care-cream);
            z-index: -1;
        }

        .lolo-callback-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding-block: 1.1rem;
        }

        .lolo-callback-logo {
            width: auto;
            height: 2.15rem;
        }

        .lolo-callback-phone {
            display: inline-flex;
            min-height: 2.75rem;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(15, 61, 62, 0.14);
            border-radius: 8px;
            background: rgba(250, 249, 247, 0.78);
            color: var(--care-deep);
            padding: 0.7rem 0.95rem;
            font-size: 0.9rem;
            font-weight: 750;
            text-decoration: none;
        }

        .lolo-callback-hero-grid {
            display: grid;
            gap: 2rem;
            align-items: center;
            padding-block: 2.2rem 3.2rem;
        }

        .lolo-callback-copy {
            max-width: 36rem;
        }

        .lolo-callback-eyebrow,
        .lolo-callback-kicker {
            color: var(--care-cta);
            font-size: 0.76rem;
            font-weight: 850;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .lolo-callback-copy h1 {
            margin-top: 1rem;
            max-width: 8.4ch;
            font-size: 4.65rem;
            line-height: 0.96;
        }

        .lolo-callback-copy h1 span {
            color: var(--care-cta);
        }

        .lolo-callback-body {
            margin-top: 1.2rem;
            max-width: 31rem;
            color: rgba(36, 48, 45, 0.78);
            font-size: 1.12rem;
            line-height: 1.65;
            font-weight: 520;
        }

        .lolo-callback-signals {
            display: grid;
            gap: 0.8rem;
            margin-top: 1.6rem;
            max-width: 34rem;
        }

        .lolo-callback-signal {
            display: grid;
            grid-template-columns: 1.9rem minmax(0, 1fr);
            gap: 0.75rem;
            align-items: start;
        }

        .lolo-callback-signal-icon {
            display: inline-flex;
            width: 1.9rem;
            height: 1.9rem;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: rgba(79, 111, 175, 0.13);
            color: var(--care-blue);
            font-size: 0.82rem;
            font-weight: 850;
        }

        .lolo-callback-signal strong {
            display: block;
            color: var(--care-deep);
            font-size: 0.96rem;
        }

        .lolo-callback-signal span {
            display: block;
            margin-top: 0.18rem;
            color: rgba(36, 48, 45, 0.68);
            font-size: 0.9rem;
            line-height: 1.45;
        }

        .lolo-callback-availability {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
            margin-top: 1.65rem;
        }

        .lolo-callback-availability span {
            border: 1px solid rgba(124, 93, 220, 0.2);
            border-radius: 8px;
            background: rgba(250, 249, 247, 0.82);
            color: var(--care-deep);
            padding: 0.6rem 0.72rem;
            font-size: 0.84rem;
            font-weight: 760;
        }

        .lolo-callback-card {
            border: 1px solid rgba(15, 61, 62, 0.16);
            border-radius: 8px;
            background: rgba(250, 249, 247, 0.96);
            box-shadow: 0 28px 80px -44px rgba(15, 61, 62, 0.42);
            padding: 1.25rem;
        }

        .lolo-callback-form,
        .lolo-callback-success {
            display: grid;
            gap: 0.95rem;
        }

        .lolo-callback-form h2,
        .lolo-callback-success h2 {
            margin-top: 0.35rem;
            font-size: 2rem;
            line-height: 1.05;
        }

        .lolo-callback-intro,
        .lolo-callback-success p {
            margin-top: 0.55rem;
            color: rgba(36, 48, 45, 0.72);
            font-size: 0.96rem;
            line-height: 1.56;
        }

        .lolo-callback-grid {
            display: grid;
            gap: 0.9rem;
        }

        .lolo-callback-form label {
            display: grid;
            gap: 0.42rem;
            color: rgba(36, 48, 45, 0.78);
            font-size: 0.82rem;
            font-weight: 800;
        }

        .lolo-callback-form input,
        .lolo-callback-form select,
        .lolo-callback-form textarea {
            width: 100%;
            min-height: 3rem;
            border: 1px solid rgba(15, 61, 62, 0.13);
            border-radius: 8px;
            background: #fff;
            color: var(--care-deep);
            font-size: 0.98rem;
            font-weight: 620;
            padding: 0.82rem 0.88rem;
            outline: 0;
        }

        .lolo-callback-form textarea {
            min-height: 6.5rem;
            resize: vertical;
        }

        .lolo-callback-form input:focus,
        .lolo-callback-form select:focus,
        .lolo-callback-form textarea:focus {
            border-color: rgba(79, 111, 175, 0.72);
            box-shadow: 0 0 0 4px rgba(79, 111, 175, 0.13);
        }

        .lolo-callback-form small {
            color: var(--care-cta);
            font-weight: 780;
        }

        .lolo-callback-consent {
            grid-template-columns: 1rem minmax(0, 1fr);
            column-gap: 0.65rem;
            align-items: start;
            font-size: 0.8rem;
            line-height: 1.48;
        }

        .lolo-callback-consent input {
            width: 1rem;
            height: 1rem;
            min-height: 0;
            margin-top: 0.15rem;
            accent-color: var(--care-deep);
        }

        .lolo-callback-consent a {
            color: var(--care-lavender);
            text-decoration: underline;
            text-underline-offset: 0.16rem;
        }

        .lolo-callback-consent small {
            grid-column: 2;
        }

        .lolo-callback-submit,
        .lolo-callback-secondary {
            display: inline-flex;
            min-height: 3.2rem;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 8px;
            background: var(--care-deep);
            color: #fff;
            padding: 0.88rem 1.1rem;
            font-size: 0.96rem;
            font-weight: 820;
            text-decoration: none;
            box-shadow: 0 18px 34px -24px rgba(15, 61, 62, 0.54);
            transition: transform .16s ease, background-color .16s ease;
        }

        .lolo-callback-submit:hover,
        .lolo-callback-secondary:hover {
            background: #0b3334;
            transform: translateY(-1px);
        }

        .lolo-callback-submit:disabled {
            cursor: wait;
            opacity: 0.72;
        }

        .lolo-callback-note {
            color: rgba(36, 48, 45, 0.62);
            font-size: 0.8rem;
            font-weight: 720;
            line-height: 1.45;
            text-align: center;
        }

        .lolo-callback-success {
            min-height: 22rem;
            align-content: center;
            text-align: center;
        }

        .lolo-callback-trust {
            background: var(--care-cream);
            padding-block: 2.2rem 3rem;
        }

        .lolo-callback-trust-grid {
            display: grid;
            gap: 1rem;
        }

        .lolo-callback-trust-item {
            border-left: 3px solid var(--care-blue);
            padding: 0.25rem 0 0.25rem 1rem;
        }

        .lolo-callback-trust-item:nth-child(2) {
            border-color: var(--care-lavender);
        }

        .lolo-callback-trust-item:nth-child(3) {
            border-color: var(--care-coral);
        }

        .lolo-callback-trust-item strong {
            display: block;
            color: var(--care-deep);
            font-size: 1rem;
        }

        .lolo-callback-trust-item span {
            display: block;
            margin-top: 0.42rem;
            color: rgba(36, 48, 45, 0.66);
            font-size: 0.92rem;
            line-height: 1.55;
        }

        @media (min-width: 760px) {
            .lolo-callback-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .lolo-callback-trust-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (min-width: 960px) {
            .lolo-callback-hero-grid {
                grid-template-columns: minmax(0, 0.92fr) minmax(25rem, 0.78fr);
                gap: 4rem;
                padding-block: 3.2rem 4rem;
            }

            .lolo-callback-card {
                padding: 1.5rem;
            }
        }

        @media (max-width: 720px) {
            .lolo-callback-hero {
                background-image:
                    linear-gradient(180deg, rgba(250, 249, 247, 0.96) 0%, rgba(250, 249, 247, 0.9) 52%, rgba(250, 249, 247, 0.74) 100%),
                    url('{{ asset('images/marketing/homepage/hero-care.jpg') }}');
                background-position: center top;
            }

            .lolo-callback-container {
                width: min(100%, calc(100% - 1.25rem));
            }

            .lolo-callback-logo {
                height: 1.85rem;
            }

            .lolo-callback-phone {
                min-height: 2.45rem;
                padding: 0.6rem 0.68rem;
                font-size: 0.78rem;
            }

            .lolo-callback-hero-grid {
                padding-block: 1.5rem 2.25rem;
            }

            .lolo-callback-copy h1 {
                max-width: 10ch;
                font-size: 2.8rem;
                line-height: 1;
            }

            .lolo-callback-body {
                font-size: 1rem;
            }

            .lolo-callback-signals {
                gap: 0.55rem;
                margin-top: 1rem;
            }

            .lolo-callback-signal {
                grid-template-columns: 1.55rem minmax(0, 1fr);
                gap: 0.6rem;
            }

            .lolo-callback-signal-icon {
                width: 1.55rem;
                height: 1.55rem;
                font-size: 0.72rem;
            }

            .lolo-callback-signal span:not(.lolo-callback-signal-icon) {
                display: none;
            }

            .lolo-callback-availability {
                display: none;
            }

            .lolo-callback-form h2,
            .lolo-callback-success h2 {
                font-size: 1.75rem;
            }
        }
    </style>

    <main class="lolo-callback-page">
        <section class="lolo-callback-hero">
            <nav class="lolo-callback-container lolo-callback-nav" aria-label="LoLo callback page">
                <a href="{{ route('landing') }}" aria-label="LoLo homepage">
                    <img src="{{ asset('images/marketing/lolo/lolo-wordmark-evergreen.svg') }}" alt="LoLo" class="lolo-callback-logo">
                </a>

                <a href="tel:9844004008" class="lolo-callback-phone">Call or text (984) 400-4008</a>
            </nav>

            <div class="lolo-callback-container lolo-callback-hero-grid">
                <div class="lolo-callback-copy">
                    <p class="lolo-callback-eyebrow">Private-pay non-medical care</p>
                    <h1>A calmer way to start <span>home care.</span></h1>
                    <p class="lolo-callback-body">
                        Tell us what kind of support your family is arranging. LoLo will review the details and call back with a clear next step for companionship and everyday help at home.
                    </p>

                    <div class="lolo-callback-signals" aria-label="Care highlights">
                        <div class="lolo-callback-signal">
                            <span class="lolo-callback-signal-icon">1</span>
                            <div>
                                <strong>Clear hourly care from $30/hr</strong>
                                <span>Companionship and everyday support without long-term pressure.</span>
                            </div>
                        </div>
                        <div class="lolo-callback-signal">
                            <span class="lolo-callback-signal-icon">2</span>
                            <div>
                                <strong>Vetted caregiver profiles</strong>
                                <span>Families can review fit, availability, and expectations before booking.</span>
                            </div>
                        </div>
                        <div class="lolo-callback-signal">
                            <span class="lolo-callback-signal-icon">3</span>
                            <div>
                                <strong>Flexible first step</strong>
                                <span>Start with one visit, a few hours, or recurring support around real life.</span>
                            </div>
                        </div>
                    </div>

                    <div class="lolo-callback-availability" aria-label="Care options">
                        <span>Companion care</span>
                        <span>Meal support</span>
                        <span>Errands and rides</span>
                        <span>Light housekeeping</span>
                    </div>
                </div>

                <livewire:family.callback-request />
            </div>
        </section>

        <section class="lolo-callback-trust" aria-label="Why families choose LoLo">
            <div class="lolo-callback-container lolo-callback-trust-grid">
                <div class="lolo-callback-trust-item">
                    <strong>Built for the first conversation</strong>
                    <span>The form gathers only the details needed to call back prepared.</span>
                </div>
                <div class="lolo-callback-trust-item">
                    <strong>Local, practical support</strong>
                    <span>Everyday help for home routines, check-ins, meals, and companionship.</span>
                </div>
                <div class="lolo-callback-trust-item">
                    <strong>No medical claims, no pressure</strong>
                    <span>LoLo focuses on non-medical home support and a clear next step.</span>
                </div>
            </div>
        </section>
    </main>

    <script>
        (() => {
            if (window.__loloCallbackTrackingReady) return;

            window.__loloCallbackTrackingReady = true;
            window.__loloTrackedCallbackLeads = window.__loloTrackedCallbackLeads || new Set();

            window.addEventListener('lolo-callback-submitted', (event) => {
                const detail = Array.isArray(event.detail) ? (event.detail[0] || {}) : (event.detail || {});
                const leadId = detail.lead_id ? String(detail.lead_id) : String(Date.now());
                const eventId = `lolo-callback-${leadId}`;

                if (window.__loloTrackedCallbackLeads.has(eventId)) return;
                window.__loloTrackedCallbackLeads.add(eventId);

                const params = {
                    content_name: detail.content_name || 'Family callback request',
                    content_category: detail.content_category || 'home_care_callback',
                    value: Number(detail.value || 45),
                    currency: detail.currency || 'USD',
                };

                if (typeof window.fbq === 'function') {
                    window.fbq('track', detail.event_name || 'Lead', params, { eventID: eventId });
                }

                if (typeof window.gtag === 'function') {
                    window.gtag('event', 'generate_lead', {
                        event_category: 'lead',
                        event_label: 'family_callback',
                    });
                }
            });
        })();
    </script>
@endsection
