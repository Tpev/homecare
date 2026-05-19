@extends('layouts.marketing')

@section('title', 'Request a callback | LoLo')
@section('meta_description', 'Tell LoLo what kind of home care and companionship support you need. Share a few details and request a callback.')
@section('canonical', route('landing.get-care'))

@section('content')
    <style>
        .lolo-callback-page {
            min-height: 100svh;
            background:
                linear-gradient(90deg, rgba(255, 247, 234, 0.96) 0%, rgba(241, 229, 210, 0.8) 54%, rgba(201, 107, 85, 0.1) 100%),
                #fff7ea;
            color: #23483f;
            font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
        }

        .lolo-callback-page h1,
        .lolo-callback-page h2 {
            font-family: 'Source Serif 4', ui-serif, Georgia, serif;
            color: #23483f;
            letter-spacing: -0.045em;
        }

        .lolo-callback-container {
            width: min(1120px, calc(100vw - 2rem));
            margin-inline: auto;
        }

        .lolo-callback-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding-block: 1.4rem;
        }

        .lolo-callback-logo {
            height: 2.15rem;
            width: auto;
        }

        .lolo-callback-phone {
            border-radius: 999px;
            border: 1px solid rgba(35, 72, 63, 0.13);
            background: rgba(255, 250, 240, 0.72);
            padding: 0.75rem 1.1rem;
            color: #23483f;
            white-space: nowrap;
            font-weight: 750;
            text-decoration: none;
        }

        .lolo-callback-main {
            display: grid;
            gap: 2rem;
            align-items: center;
            padding-block: 4rem 6rem;
        }

        .lolo-callback-copy {
            max-width: 34rem;
        }

        .lolo-callback-pill,
        .lolo-callback-kicker {
            color: #c96b55;
            font-size: 0.78rem;
            font-weight: 850;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        .lolo-callback-copy h1 {
            margin-top: 1.2rem;
            font-size: clamp(3.2rem, 8vw, 6.35rem);
            line-height: 0.95;
        }

        .lolo-callback-copy h1 em {
            color: #c96b55;
            font-style: italic;
            font-weight: 400;
        }

        .lolo-callback-copy p {
            margin-top: 1.35rem;
            color: rgba(35, 72, 63, 0.76);
            font-size: 1.12rem;
            line-height: 1.7;
        }

        .lolo-callback-proof {
            display: grid;
            gap: 0.85rem;
            margin-top: 2rem;
        }

        .lolo-callback-proof div {
            border-radius: 1.2rem;
            border: 1px solid rgba(35, 72, 63, 0.1);
            background: rgba(255, 250, 240, 0.76);
            padding: 1rem;
            box-shadow: 0 18px 42px -32px rgba(35, 72, 63, 0.25);
        }

        .lolo-callback-proof strong {
            display: block;
            color: #23483f;
            font-size: 1rem;
        }

        .lolo-callback-proof span {
            display: block;
            margin-top: 0.35rem;
            color: rgba(35, 72, 63, 0.66);
            font-size: 0.92rem;
            line-height: 1.5;
        }

        .lolo-callback-card {
            border-radius: 2rem;
            border: 1px solid rgba(35, 72, 63, 0.12);
            background: rgba(255, 250, 240, 0.95);
            box-shadow: 0 34px 90px -46px rgba(35, 72, 63, 0.34);
            padding: 1.35rem;
        }

        .lolo-callback-form,
        .lolo-callback-success {
            display: grid;
            gap: 1rem;
        }

        .lolo-callback-form h2,
        .lolo-callback-success h2 {
            margin-top: 0.35rem;
            font-size: clamp(2.05rem, 4vw, 3.2rem);
            line-height: 1;
        }

        .lolo-callback-intro,
        .lolo-callback-success p {
            margin-top: 0.75rem;
            color: rgba(35, 72, 63, 0.72);
            line-height: 1.62;
        }

        .lolo-callback-grid {
            display: grid;
            gap: 1rem;
        }

        .lolo-callback-form label {
            display: grid;
            gap: 0.45rem;
            color: rgba(35, 72, 63, 0.75);
            font-size: 0.85rem;
            font-weight: 800;
        }

        .lolo-callback-form input,
        .lolo-callback-form select,
        .lolo-callback-form textarea {
            width: 100%;
            border: 0;
            border-radius: 1rem;
            background: rgba(241, 229, 210, 0.78);
            color: #23483f;
            font-size: 1rem;
            font-weight: 650;
            padding: 0.95rem 1rem;
            outline: 0;
        }

        .lolo-callback-form input:focus,
        .lolo-callback-form select:focus,
        .lolo-callback-form textarea:focus {
            box-shadow: 0 0 0 4px rgba(201, 107, 85, 0.16);
            background: #fffaf0;
        }

        .lolo-callback-form small {
            color: #b95745;
            font-weight: 750;
        }

        .lolo-callback-submit,
        .lolo-callback-secondary {
            min-height: 3.45rem;
            border-radius: 999px;
            background: #b95745;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.9rem 1.35rem;
            font-weight: 800;
            text-decoration: none;
            box-shadow: 0 18px 40px -24px rgba(185, 87, 69, 0.45);
            transition: transform .18s ease, background-color .18s ease;
        }

        .lolo-callback-submit:hover,
        .lolo-callback-secondary:hover {
            background: #a84f3f;
            transform: translateY(-1px);
        }

        .lolo-callback-submit:disabled {
            cursor: wait;
            opacity: 0.72;
        }

        .lolo-callback-note {
            text-align: center;
            color: rgba(35, 72, 63, 0.68);
            font-size: 0.92rem;
            font-weight: 700;
        }

        .lolo-callback-success {
            min-height: 22rem;
            align-content: center;
            text-align: center;
            padding: 2rem 1rem;
        }

        @media (min-width: 860px) {
            .lolo-callback-main {
                grid-template-columns: minmax(0, 0.92fr) minmax(24rem, 1fr);
                gap: 4rem;
            }

            .lolo-callback-grid,
            .lolo-callback-proof {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .lolo-callback-card {
                padding: 1.7rem;
            }
        }

        @media (max-width: 480px) {
            .lolo-callback-container {
                width: min(100%, calc(100vw - 1.5rem));
            }

            .lolo-callback-logo {
                height: 1.85rem;
            }

            .lolo-callback-phone {
                padding: 0.62rem 0.78rem;
                font-size: 0.82rem;
            }
        }
    </style>

    <main class="lolo-callback-page">
        <nav class="lolo-callback-container lolo-callback-nav">
            <a href="{{ route('landing') }}">
                <img src="{{ asset('images/marketing/lolo/lolo-wordmark-evergreen.svg') }}" alt="LoLo" class="lolo-callback-logo">
            </a>

            <a href="tel:9844004008" class="lolo-callback-phone">Call or text (984) 400-4008</a>
        </nav>

        <section class="lolo-callback-container lolo-callback-main">
            <div class="lolo-callback-copy">
                <p class="lolo-callback-pill">Care from $30/hr</p>
                <h1>Request a <em>callback.</em></h1>
                <p>Tell us what your family needs. We will use these details to understand the situation before calling you back.</p>

                <div class="lolo-callback-proof">
                    <div>
                        <strong>Clear hourly care</strong>
                        <span>Companionship starts at $30/hr with no long-term commitment.</span>
                    </div>
                    <div>
                        <strong>Vetted caregivers</strong>
                        <span>Profiles, checks, and reviews before families choose care.</span>
                    </div>
                    <div>
                        <strong>Flexible support</strong>
                        <span>One visit, a few hours, or recurring help at home.</span>
                    </div>
                    <div>
                        <strong>Simple next step</strong>
                        <span>Share the basics and we will follow up by phone.</span>
                    </div>
                </div>
            </div>

            <livewire:family.callback-request />
        </section>
    </main>
@endsection
