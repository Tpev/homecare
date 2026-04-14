@extends('layouts.marketing')

@section('title', 'Home Care HUB | Trusted Home Support for Older Adults')
@section('meta_description', 'Home Care HUB helps families arrange trusted, non-medical support at home for an older parent, from 30-minute quick help to a few hours of support to traditional full-day coverage.')
@section('canonical', route('landing'))

@section('content')
    <style>
    .hub-family-landing {
        --hub-deep-teal: #0F3D3E;
        --hub-soft-blue: #4F6FAF;
        --hub-lavender: #7C5DDC;
        --hub-cream: #F5F1EB;
        --hub-soft-white: #FAF9F7;
        --hub-border: #DED6CA;
        --hub-copy: #5C6871;
        background: var(--hub-soft-white);
    }

    .hub-family-landing h1,
    .hub-family-landing h2,
    .hub-family-landing h3,
    .hub-family-landing .hub-display {
        font-family: 'Source Serif 4', ui-serif, Georgia, serif;
        font-style: normal !important;
        font-weight: 600 !important;
        letter-spacing: -0.035em !important;
        text-transform: none !important;
        color: var(--hub-deep-teal);
    }

    .hub-family-landing p,
    .hub-family-landing li,
    .hub-family-landing dd,
    .hub-family-landing dt,
    .hub-family-landing span,
    .hub-family-landing a,
    .hub-family-landing button,
    .hub-family-landing input,
    .hub-family-landing textarea,
    .hub-family-landing select {
        font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
    }

    .hub-family-landing .hero-gradient {
        background:
            radial-gradient(circle at 12% 16%, rgba(124, 93, 220, 0.12), transparent 24%),
            radial-gradient(circle at 82% 10%, rgba(79, 111, 175, 0.12), transparent 32%),
            linear-gradient(180deg, #F5F1EB 0%, #FAF9F7 60%, #ffffff 100%);
    }

    .hub-family-landing .bg-grid {
        background-image:
            radial-gradient(rgba(79, 111, 175, 0.08) 1px, transparent 1px),
            linear-gradient(180deg, rgba(245, 241, 235, 0.92) 0%, rgba(250, 249, 247, 0.94) 60%, #ffffff 100%);
        background-size: 34px 34px, auto;
    }

    .hub-family-landing .glass-nav {
        backdrop-filter: blur(20px);
        background: rgba(250, 249, 247, 0.92);
        border-bottom: 1px solid rgba(15, 61, 62, 0.10);
    }

    .hub-family-landing .hub-hero-card {
        background: rgba(255, 252, 248, 0.92);
        border: 1px solid rgba(222, 214, 202, 0.88);
        box-shadow: 0 26px 80px -44px rgba(15, 61, 62, 0.22);
    }

    .hub-family-landing .hub-proof {
        border: 1px solid rgba(222, 214, 202, 0.88);
        background: rgba(255, 255, 255, 0.82);
        color: var(--hub-copy);
    }

    .hub-family-landing .hub-hero-eyebrow {
        color: var(--hub-lavender);
        letter-spacing: 0.16em;
        text-transform: uppercase;
    }

    .hub-family-landing .hub-range-card {
        border: 1px solid rgba(222, 214, 202, 0.92);
        background: rgba(255, 252, 248, 0.9);
        box-shadow: 0 14px 34px -28px rgba(15, 61, 62, 0.18);
    }

    .hub-family-landing .hub-range-card strong {
        display: block;
        color: var(--hub-deep-teal);
        font-weight: 700;
    }

    .hub-family-landing .hub-contact-chip {
        border: 1px solid rgba(124, 93, 220, 0.2);
        background: rgba(124, 93, 220, 0.08);
        color: var(--hub-lavender);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.65);
    }

    .hub-family-landing .hub-trust-row {
        display: grid;
        gap: 0.75rem;
        grid-template-columns: repeat(1, minmax(0, 1fr));
    }

    .hub-family-landing .hub-preview-shell {
        border: 1px solid rgba(222, 214, 202, 0.9);
        background: rgba(255, 252, 248, 0.9);
        box-shadow: 0 28px 70px -38px rgba(15, 61, 62, 0.28);
    }

    .hub-family-landing .hub-preview-glow {
        background: radial-gradient(circle, rgba(124, 93, 220, 0.12) 0%, rgba(124, 93, 220, 0) 70%);
    }

    .hub-family-landing .text-slate-950,
    .hub-family-landing .text-slate-900,
    .hub-family-landing .text-slate-800 {
        color: var(--hub-deep-teal) !important;
    }

    .hub-family-landing .text-slate-600,
    .hub-family-landing .text-slate-500,
    .hub-family-landing .text-slate-400 {
        color: var(--hub-copy) !important;
    }

    .hub-family-landing .text-blue-700,
    .hub-family-landing .text-blue-600,
    .hub-family-landing .text-blue-300 {
        color: var(--hub-lavender) !important;
    }

    .hub-family-landing .bg-blue-600 {
        background-color: var(--hub-deep-teal) !important;
    }

    .hub-family-landing .bg-blue-50,
    .hub-family-landing .hover\:bg-blue-50:hover {
        background-color: rgba(124, 93, 220, 0.10) !important;
    }

    .hub-family-landing .bg-blue-100 {
        background-color: rgba(79, 111, 175, 0.12) !important;
    }

    .hub-family-landing .border-blue-200,
    .hub-family-landing .border-blue-100 {
        border-color: rgba(124, 93, 220, 0.22) !important;
    }

    .hub-family-landing .bg-slate-950,
    .hub-family-landing .bg-slate-900 {
        background: linear-gradient(160deg, #0F3D3E 0%, #164a4c 100%) !important;
    }

    .hub-family-landing .bg-slate-100,
    .hub-family-landing .bg-slate-50 {
        background-color: #FFFCF8 !important;
    }

    .hub-family-landing .border-slate-200,
    .hub-family-landing .border-slate-100,
    .hub-family-landing .border-white\/10,
    .hub-family-landing .border-white\/20 {
        border-color: rgba(222, 214, 202, 0.92) !important;
    }

    .hub-family-landing .bg-white,
    .hub-family-landing .bg-white\/10,
    .hub-family-landing .bg-white\/5,
    .hub-family-landing .bg-white\/90 {
        background-color: rgba(255, 252, 248, 0.96) !important;
    }

    .hub-family-landing .shadow-2xl,
    .hub-family-landing .shadow-xl,
    .hub-family-landing .shadow-lg {
        box-shadow: 0 24px 60px -36px rgba(15, 61, 62, 0.24) !important;
    }

    .hub-family-landing .underline {
        text-decoration-color: rgba(124, 93, 220, 0.55) !important;
    }

    .hub-family-landing input,
    .hub-family-landing textarea,
    .hub-family-landing select {
        background: rgba(255, 252, 248, 0.96);
    }
    .hub-quick-request {
        border-color: rgba(222, 214, 202, 0.92);
        background: rgba(255, 252, 248, 0.98);
        box-shadow: 0 26px 70px -38px rgba(15, 61, 62, 0.20);
    }

    .hub-quick-request h2,
    .hub-quick-request h3 {
        font-family: 'Source Serif 4', ui-serif, Georgia, serif;
        font-style: normal;
        font-weight: 600;
        letter-spacing: -0.03em;
        color: #0F3D3E;
    }

    .hub-quick-request .hub-step-active {
        border-color: #0F3D3E;
        background: #0F3D3E;
        color: #FAF9F7;
    }

    .hub-quick-request .hub-step-idle {
        border-color: rgba(222, 214, 202, 0.92);
        background: rgba(255, 252, 248, 0.88);
        color: #5C6871;
    }

    .hub-quick-request .hub-pill-active {
        border-color: #0F3D3E;
        background: #0F3D3E;
        color: #FAF9F7;
        box-shadow: 0 14px 30px -18px rgba(15, 61, 62, 0.45);
    }

    .hub-quick-request .hub-pill-idle {
        border-color: rgba(222, 214, 202, 0.92);
        background: rgba(255, 252, 248, 0.96);
        color: #0F3D3E;
    }

    @media (min-width: 640px) {
        .hub-family-landing .hub-trust-row {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }
</style>
    <livewire:family-landing />
    <script>

    document.addEventListener('DOMContentLoaded', () => {

        const observer = new IntersectionObserver((entries) => {

            entries.forEach((entry) => {

                if (entry.isIntersecting) {

                    entry.target.classList.add('active');

                }

            });

        }, {

            threshold: 0.15,

        });



        document.querySelectorAll('.reveal').forEach((element) => observer.observe(element));

    });

</script>
@endsection

