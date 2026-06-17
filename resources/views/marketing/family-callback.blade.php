@extends('layouts.marketing')

@section('title', 'Find trusted home care support | LoLo')
@section('meta_description', 'Answer a few quick questions and request a LoLo callback for non-medical home care, companionship, errands, meals, and flexible support at home.')
@section('canonical', route('landing.get-care'))
@section('og_image', asset('images/marketing/homepage/hero-care.jpg'))
@section('og_image_alt', 'A caregiver and older adult smiling together at home.')

@section('content')
    <style>
        .lolo-getcare-page {
            --care-deep: #0f3d3e;
            --care-ink: #24302d;
            --care-cream: #f5f1eb;
            --care-ivory: #faf9f7;
            --care-oat: #f1e5d2;
            --care-blue: #4f6faf;
            --care-lavender: #7c5ddc;
            --care-coral: #c96b55;
            --care-cta: #b95745;
            --care-muted: #64736f;
            min-height: 100svh;
            background:
                linear-gradient(180deg, rgba(250, 249, 247, 0.86), rgba(245, 241, 235, 0.98)),
                var(--care-cream);
            color: var(--care-deep);
            font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
        }

        .lolo-getcare-page *,
        .lolo-getcare-page *::before,
        .lolo-getcare-page *::after {
            box-sizing: border-box;
        }

        .lolo-getcare-page h1,
        .lolo-getcare-page h2,
        .lolo-getcare-page h3 {
            margin: 0;
            color: var(--care-deep);
            font-family: 'Source Serif 4', ui-serif, Georgia, serif;
            letter-spacing: 0;
        }

        .lolo-getcare-page p {
            margin: 0;
        }

        .lolo-getcare-container {
            width: min(1180px, calc(100vw - 2rem));
            margin-inline: auto;
        }

        .lolo-getcare-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding-block: 1rem;
        }

        .lolo-getcare-logo {
            display: block;
            width: auto;
            height: 2rem;
        }

        .lolo-getcare-phone {
            display: inline-flex;
            min-height: 2.75rem;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(15, 61, 62, 0.12);
            border-radius: 8px;
            background: rgba(250, 249, 247, 0.78);
            color: var(--care-deep);
            padding: 0.68rem 0.9rem;
            font-size: 0.88rem;
            font-weight: 780;
            text-decoration: none;
            white-space: nowrap;
        }

        .lolo-getcare-hero {
            position: relative;
            overflow: hidden;
            padding-bottom: 3rem;
        }

        .lolo-getcare-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: 0;
            background-image:
                linear-gradient(90deg, rgba(245, 241, 235, 0.98) 0%, rgba(245, 241, 235, 0.92) 45%, rgba(245, 241, 235, 0.52) 100%),
                url('{{ asset('images/marketing/homepage/hero-care.jpg') }}');
            background-position: center;
            background-size: cover;
            opacity: 0.96;
        }

        .lolo-getcare-hero > * {
            position: relative;
            z-index: 1;
        }

        .lolo-getcare-grid {
            display: grid;
            gap: 2rem;
            align-items: center;
            padding-block: 2.8rem 1.75rem;
        }

        .lolo-getcare-copy {
            max-width: 34rem;
        }

        .lolo-getcare-pill,
        .lolo-wizard-eyebrow {
            color: var(--care-cta);
            font-size: 0.74rem;
            font-weight: 850;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .lolo-getcare-copy h1 {
            margin-top: 0.9rem;
            max-width: 12ch;
            font-size: 4.8rem;
            line-height: 0.95;
        }

        .lolo-getcare-copy h1 span {
            color: var(--care-cta);
        }

        .lolo-getcare-copy p.lolo-getcare-body {
            margin-top: 1.1rem;
            max-width: 31rem;
            color: rgba(36, 48, 45, 0.76);
            font-size: 1.08rem;
            font-weight: 550;
            line-height: 1.65;
        }

        .lolo-getcare-proof {
            display: grid;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }

        .lolo-getcare-proof span {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            color: rgba(36, 48, 45, 0.72);
            font-size: 0.92rem;
            font-weight: 740;
        }

        .lolo-getcare-proof span::before {
            content: "";
            width: 0.52rem;
            height: 0.52rem;
            border-radius: 999px;
            background: var(--care-blue);
            box-shadow: 0 0 0 0.32rem rgba(79, 111, 175, 0.12);
            flex: 0 0 auto;
        }

        .lolo-getcare-proof span:nth-child(2)::before {
            background: var(--care-lavender);
            box-shadow: 0 0 0 0.32rem rgba(124, 93, 220, 0.12);
        }

        .lolo-getcare-proof span:nth-child(3)::before {
            background: var(--care-coral);
            box-shadow: 0 0 0 0.32rem rgba(201, 107, 85, 0.12);
        }

        .lolo-getcare-wizard {
            width: min(100%, 33.5rem);
            justify-self: end;
        }

        .lolo-wizard-card {
            width: 100%;
            border: 1px solid rgba(15, 61, 62, 0.14);
            border-radius: 8px;
            background: rgba(250, 249, 247, 0.97);
            box-shadow: 0 32px 88px -42px rgba(15, 61, 62, 0.42);
            padding: 1.15rem;
        }

        .lolo-wizard-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.2rem;
        }

        .lolo-wizard-step-label,
        .lolo-wizard-time {
            color: rgba(36, 48, 45, 0.58);
            font-size: 0.76rem;
            font-weight: 800;
        }

        .lolo-wizard-progress {
            width: 8.5rem;
            height: 0.42rem;
            margin-top: 0.42rem;
            overflow: hidden;
            border-radius: 999px;
            background: rgba(15, 61, 62, 0.1);
        }

        .lolo-wizard-progress span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, var(--care-blue), var(--care-lavender), var(--care-coral));
            transition: width .22s ease;
        }

        .lolo-wizard-step,
        .lolo-wizard-contact,
        .lolo-wizard-success {
            display: grid;
            gap: 1rem;
        }

        .lolo-wizard-step h2,
        .lolo-wizard-contact h2,
        .lolo-wizard-success h2 {
            margin-top: 0.32rem;
            font-size: 2rem;
            line-height: 1.05;
        }

        .lolo-wizard-intro,
        .lolo-wizard-success p {
            color: rgba(36, 48, 45, 0.68);
            font-size: 0.95rem;
            line-height: 1.55;
        }

        .lolo-wizard-options {
            display: grid;
            gap: 0.72rem;
        }

        .lolo-wizard-option {
            width: 100%;
            min-height: 4.7rem;
            border: 1px solid rgba(15, 61, 62, 0.12);
            border-radius: 8px;
            background: #fff;
            color: var(--care-deep);
            padding: 0.9rem 1rem;
            text-align: left;
            cursor: pointer;
            transition: border-color .16s ease, box-shadow .16s ease, transform .16s ease, background-color .16s ease;
        }

        .lolo-wizard-option:hover,
        .lolo-wizard-option.is-selected {
            border-color: rgba(79, 111, 175, 0.58);
            background: rgba(79, 111, 175, 0.07);
            box-shadow: 0 14px 28px -24px rgba(15, 61, 62, 0.4);
            transform: translateY(-1px);
        }

        .lolo-wizard-option-title {
            display: block;
            color: var(--care-deep);
            font-size: 1rem;
            font-weight: 850;
            line-height: 1.2;
        }

        .lolo-wizard-option-body {
            display: block;
            margin-top: 0.28rem;
            color: rgba(36, 48, 45, 0.62);
            font-size: 0.86rem;
            font-weight: 650;
            line-height: 1.35;
        }

        .lolo-wizard-field-grid {
            display: grid;
            gap: 0.85rem;
        }

        .lolo-wizard-contact label {
            display: grid;
            gap: 0.4rem;
            color: rgba(36, 48, 45, 0.78);
            font-size: 0.82rem;
            font-weight: 820;
        }

        .lolo-wizard-contact input:not([type="checkbox"]),
        .lolo-wizard-contact textarea {
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

        .lolo-wizard-contact textarea {
            min-height: 6.3rem;
            resize: vertical;
        }

        .lolo-wizard-contact input:not([type="checkbox"]):focus,
        .lolo-wizard-contact textarea:focus {
            border-color: rgba(79, 111, 175, 0.72);
            box-shadow: 0 0 0 4px rgba(79, 111, 175, 0.13);
        }

        .lolo-wizard-contact small,
        .lolo-wizard-error {
            color: var(--care-cta);
            font-size: 0.78rem;
            font-weight: 780;
        }

        .lolo-wizard-consent {
            display: grid;
            grid-template-columns: 1.2rem minmax(0, 1fr);
            align-items: flex-start;
            gap: 0.7rem;
            border: 1px solid rgba(15, 61, 62, 0.12);
            border-radius: 8px;
            background: #fff;
            padding: 0.82rem 0.88rem;
            color: rgba(36, 48, 45, 0.74);
            font-size: 0.78rem;
            font-weight: 750;
            line-height: 1.45;
        }

        .lolo-wizard-consent-input {
            appearance: auto;
            -webkit-appearance: auto;
            width: 1.12rem;
            height: 1.12rem;
            min-width: 1.12rem;
            min-height: 1.12rem;
            margin: 0.08rem 0 0;
            padding: 0;
            accent-color: var(--care-deep);
            cursor: pointer;
        }

        .lolo-wizard-contact .lolo-wizard-consent-label {
            display: block;
            min-width: 0;
            color: inherit;
            font-size: inherit;
            font-weight: inherit;
            line-height: inherit;
            overflow-wrap: anywhere;
            cursor: pointer;
        }

        .lolo-wizard-consent a {
            color: var(--care-lavender);
            text-decoration: underline;
            text-underline-offset: 0.16rem;
        }

        .lolo-wizard-consent-wrap {
            display: grid;
            gap: 0.35rem;
        }

        .lolo-wizard-actions {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            gap: 0.75rem;
            align-items: center;
        }

        .lolo-wizard-back,
        .lolo-wizard-submit,
        .lolo-wizard-secondary {
            display: inline-flex;
            min-height: 3.05rem;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            padding: 0.82rem 1rem;
            font-size: 0.92rem;
            font-weight: 850;
            text-decoration: none;
            transition: transform .16s ease, background-color .16s ease, border-color .16s ease;
        }

        .lolo-wizard-back {
            border: 1px solid rgba(15, 61, 62, 0.14);
            background: rgba(250, 249, 247, 0.82);
            color: var(--care-deep);
        }

        .lolo-wizard-back-inline {
            margin-top: 1rem;
            width: 100%;
        }

        .lolo-wizard-submit,
        .lolo-wizard-secondary {
            border: 0;
            background: var(--care-deep);
            color: #fff;
            box-shadow: 0 18px 34px -24px rgba(15, 61, 62, 0.54);
        }

        .lolo-wizard-submit:hover,
        .lolo-wizard-secondary:hover,
        .lolo-wizard-back:hover {
            transform: translateY(-1px);
        }

        .lolo-wizard-submit:hover,
        .lolo-wizard-secondary:hover {
            background: #0b3334;
        }

        .lolo-wizard-submit:disabled {
            cursor: wait;
            opacity: 0.72;
        }

        .lolo-wizard-note {
            color: rgba(36, 48, 45, 0.6);
            font-size: 0.78rem;
            font-weight: 720;
            line-height: 1.45;
            text-align: center;
        }

        .lolo-wizard-summary {
            margin-top: 1rem;
            border-top: 1px solid rgba(15, 61, 62, 0.1);
            padding-top: 0.9rem;
        }

        .lolo-wizard-summary p {
            color: rgba(36, 48, 45, 0.6);
            font-size: 0.74rem;
            font-weight: 850;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .lolo-wizard-summary div {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            margin-top: 0.6rem;
        }

        .lolo-wizard-summary span {
            border: 1px solid rgba(15, 61, 62, 0.11);
            border-radius: 8px;
            background: rgba(245, 241, 235, 0.72);
            color: rgba(36, 48, 45, 0.72);
            padding: 0.42rem 0.55rem;
            font-size: 0.76rem;
            font-weight: 680;
            line-height: 1.25;
        }

        .lolo-wizard-summary strong {
            color: var(--care-deep);
        }

        .lolo-wizard-success {
            min-height: 27rem;
            align-content: center;
            text-align: center;
        }

        @media (min-width: 760px) {
            .lolo-wizard-field-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (min-width: 980px) {
            .lolo-getcare-grid {
                grid-template-columns: minmax(0, 0.9fr) minmax(28rem, 0.78fr);
                gap: 4rem;
                min-height: 46rem;
            }

            .lolo-wizard-card {
                padding: 1.35rem;
            }
        }

        @media (max-width: 760px) {
            .lolo-getcare-container {
                width: min(100%, calc(100% - 1.1rem));
            }

            .lolo-getcare-hero {
                padding-bottom: 1.4rem;
            }

            .lolo-getcare-hero::before {
                background-image:
                    linear-gradient(180deg, rgba(245, 241, 235, 0.98) 0%, rgba(245, 241, 235, 0.94) 58%, rgba(245, 241, 235, 0.82) 100%),
                    url('{{ asset('images/marketing/homepage/hero-care.jpg') }}');
                background-position: center top;
            }

            .lolo-getcare-logo {
                height: 1.75rem;
            }

            .lolo-getcare-phone {
                min-height: 2.35rem;
                padding: 0.58rem 0.68rem;
                font-size: 0.76rem;
            }

            .lolo-getcare-grid {
                gap: 1rem;
                padding-block: 1.2rem 0;
            }

            .lolo-getcare-copy h1 {
                max-width: 12ch;
                font-size: 2.65rem;
                line-height: 1;
            }

            .lolo-getcare-copy p.lolo-getcare-body {
                margin-top: 0.78rem;
                font-size: 0.95rem;
                line-height: 1.5;
            }

            .lolo-getcare-proof {
                display: none;
            }

            .lolo-getcare-wizard {
                width: 100%;
            }

            .lolo-wizard-card {
                padding: 0.9rem;
            }

            .lolo-wizard-top {
                margin-bottom: 0.95rem;
            }

            .lolo-wizard-time {
                display: none;
            }

            .lolo-wizard-step,
            .lolo-wizard-contact {
                gap: 0.82rem;
            }

            .lolo-wizard-step h2,
            .lolo-wizard-contact h2,
            .lolo-wizard-success h2 {
                font-size: 1.58rem;
                line-height: 1.06;
            }

            .lolo-wizard-intro {
                font-size: 0.88rem;
            }

            .lolo-wizard-option {
                min-height: 4.2rem;
                padding: 0.78rem 0.85rem;
            }

            .lolo-wizard-option-title {
                font-size: 0.95rem;
            }

            .lolo-wizard-option-body {
                font-size: 0.8rem;
            }

            .lolo-wizard-summary div {
                max-height: 5.6rem;
                overflow: auto;
            }
        }
    </style>

    <main class="lolo-getcare-page">
        <section class="lolo-getcare-hero">
            <nav class="lolo-getcare-container lolo-getcare-nav" aria-label="LoLo callback page">
                <a href="{{ route('landing') }}" aria-label="LoLo homepage">
                    <img src="{{ asset('images/marketing/lolo/lolo-wordmark-evergreen.svg') }}" alt="LoLo" class="lolo-getcare-logo">
                </a>

                <a href="tel:9844004008" class="lolo-getcare-phone">Call or text (984) 400-4008</a>
            </nav>

            <div class="lolo-getcare-container lolo-getcare-grid">
                <div class="lolo-getcare-copy">
                    <p class="lolo-getcare-pill">Private-pay non-medical support</p>
                    <h1>Find trusted home care <span>support.</span></h1>
                    <p class="lolo-getcare-body">Answer a few quick questions, then LoLo calls back with clear next steps for non-medical support at home.</p>

                    <div class="lolo-getcare-proof" aria-label="LoLo care highlights">
                        <span>Companionship and everyday help from $30/hr</span>
                        <span>One visit, recurring care, or planning ahead</span>
                        <span>Vetted caregiver profiles and clear next steps</span>
                    </div>
                </div>

                <div class="lolo-getcare-wizard">
                    <livewire:family.callback-request />
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
