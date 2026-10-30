@once
    @push('head')
        <style>
            .public-footer { display: grid; grid-template-columns: 2fr repeat(3, minmax(0, 1fr)); gap: 50px; border-top: 1px solid #dfd4c2; padding: 70px max(5vw, 36px) 30px; background: #fff7ea; color: #24302d; font-family: inherit; line-height: 1.6; }
            .public-footer, .public-footer * { box-sizing: border-box; }
            .public-footer p { margin: 0; }
            .public-footer a { color: inherit; text-decoration: none; text-underline-offset: 4px; }
            .public-footer a:hover { text-decoration: underline; }
            .public-footer a:focus-visible { outline: 3px solid #b95745; outline-offset: 5px; }
            .public-footer-brand, .public-footer-nav { display: flex; flex-direction: column; align-items: flex-start; gap: 12px; min-width: 0; font-size: .92rem; }
            .public-footer-brand img { display: block; width: 84px; max-width: 100%; height: auto; }
            .public-footer-brand p { max-width: 300px; color: #6f766f; line-height: 1.6; }
            .public-footer-nav strong { font-weight: 600; }
            .public-footer .public-footer-copyright { grid-column: 1 / -1; margin-top: 20px; border-top: 1px solid #dfd4c2; color: #6f766f; padding-top: 25px; font-size: .76rem; }
            @media (max-width: 900px) { .public-footer { grid-template-columns: repeat(2, minmax(0, 1fr)); } .public-footer-brand { grid-column: 1 / -1; } }
            @media (max-width: 560px) { .public-footer { padding: 60px 22px; } }
        </style>
    @endpush
@endonce

<footer {{ $attributes->class('public-footer') }}>
    <div class="public-footer-brand">
        <a href="{{ route('landing') }}" aria-label="LoLo Care home"><img src="{{ asset('images/marketing/lolo/lolo-wordmark-evergreen.svg') }}" alt="LoLo Care" width="652" height="222"></a>
        <p>LoLo Care helps families in Raleigh, Durham, and Chapel Hill find trusted, non-medical help at home. Book companionship, errands, and everyday support by the hour, with no long-term contract.</p>
    </div>
    <nav class="public-footer-nav" aria-label="Family resources">
        <strong>Families</strong>
        <a href="{{ route('register') }}">Find care</a>
        <a href="{{ route('care-assistance.index') }}">Care assistance</a>
        <a href="{{ route('prepaid-care-hours') }}">Prepaid care hours</a>
        <a href="{{ route('care-gift-cards') }}">Gift cards</a>
        <a href="{{ route('landing') }}#how">How it works</a>
        <a href="{{ route('landing') }}#safety">Safety</a>
    </nav>
    <nav class="public-footer-nav" aria-label="Caregiver resources">
        <strong>Caregivers</strong>
        <a href="{{ route('caregiver.register') }}">Become a caregiver</a>
        <a href="{{ route('login') }}">Caregiver login</a>
    </nav>
    <nav class="public-footer-nav" aria-label="Company resources">
        <strong>Company</strong>
        <a href="{{ route('about') }}">About LoLo</a>
        <a href="{{ route('faq') }}">FAQs</a>
        <a href="{{ route('blog.index') }}">Resources</a>
        <a href="mailto:hello@carelolo.com">Contact</a>
        <a href="{{ route('legal.index') }}">Legal & privacy</a>
    </nav>
    <p class="public-footer-copyright">© {{ now()->year }} LoLo Care Inc. Non-medical home support.</p>
</footer>
