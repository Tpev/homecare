@extends('layouts.marketing-lean')

@section('title', 'Care Assistance Program | $23/hour Home Care | LoLo Care')
@section('meta_description', 'Care assistance in Raleigh, Durham and Chapel Hill: $23/hour home care for approved applicants facing hardship. Review in about five business days.')
@section('canonical', route('care-assistance.index'))
@section('robots', 'index,follow,max-image-preview:large')
@section('og_image', asset('images/marketing/care-assistance/community-support-social.jpg'))
@section('og_image_alt', 'A volunteer and an older man in a wheelchair smiling together outdoors.')
@section('og_image_width', '1200')
@section('og_image_height', '800')
@section('og_image_type', 'image/jpeg')
@section('disable_marketing_tracking', '1')

@php
    $assistanceFaqs = config('care_assistance.faqs', []);
    $selectedCareNeeds = array_values(array_filter((array) old('care_needs', []), 'is_string'));
    $oldString = static function (string $key): string {
        $value = old($key, '');

        return is_scalar($value) ? (string) $value : '';
    };
@endphp

@section('structured_data')
    @php
        $assistanceUrl = route('care-assistance.index');
        $assistanceSchema = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'WebPage',
                    '@id' => $assistanceUrl.'#webpage',
                    'url' => $assistanceUrl,
                    'name' => 'LoLo Care Assistance Program',
                    'description' => trim($__env->yieldContent('meta_description')),
                    'inLanguage' => str_replace('_', '-', app()->getLocale()),
                    'primaryImageOfPage' => ['@id' => $assistanceUrl.'#primaryimage'],
                    'about' => ['@id' => $assistanceUrl.'#service'],
                    'breadcrumb' => ['@id' => $assistanceUrl.'#breadcrumb'],
                ],
                [
                    '@type' => 'Service',
                    '@id' => $assistanceUrl.'#service',
                    'name' => 'LoLo Care Assistance Program',
                    'serviceType' => 'Non-medical home care assistance',
                    'url' => $assistanceUrl,
                    'mainEntityOfPage' => ['@id' => $assistanceUrl.'#webpage'],
                    'image' => ['@id' => $assistanceUrl.'#primaryimage'],
                    'provider' => ['@type' => 'Organization', 'name' => 'LoLo Care', 'url' => route('landing')],
                    'areaServed' => array_map(fn ($city) => [
                        '@type' => 'City',
                        'name' => $city,
                        'containedInPlace' => ['@type' => 'State', 'name' => 'North Carolina'],
                    ], ['Raleigh', 'Durham', 'Chapel Hill']),
                    'description' => 'Reduced-rate non-medical home care for approved applicants facing financial hardship, supported by LoLo and participating local caregivers.',
                    'offers' => [
                        '@type' => 'Offer',
                        'url' => $assistanceUrl.'#apply',
                        'description' => 'Care at $23 per hour for approved Care Assistance Program applicants.',
                        'priceSpecification' => [
                            '@type' => 'UnitPriceSpecification',
                            'price' => '23.00',
                            'priceCurrency' => 'USD',
                            'unitText' => 'hour',
                        ],
                    ],
                ],
                [
                    '@type' => 'ImageObject',
                    '@id' => $assistanceUrl.'#primaryimage',
                    'url' => asset('images/marketing/care-assistance/community-support-social.jpg'),
                    'contentUrl' => asset('images/marketing/care-assistance/community-support-social.jpg'),
                    'width' => 1200,
                    'height' => 800,
                    'caption' => trim($__env->yieldContent('og_image_alt')),
                ],
                [
                    '@type' => 'FAQPage',
                    '@id' => $assistanceUrl.'#faq',
                    'mainEntity' => array_map(fn ($faq) => [
                        '@type' => 'Question',
                        'name' => $faq['question'],
                        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['answer']],
                    ], $assistanceFaqs),
                ],
                [
                    '@type' => 'BreadcrumbList',
                    '@id' => $assistanceUrl.'#breadcrumb',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('landing')],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Care Assistance', 'item' => $assistanceUrl],
                    ],
                ],
            ],
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode($assistanceSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endsection

@push('head')
    <link rel="preload" as="image" href="{{ asset('images/marketing/care-assistance/community-support-960.webp') }}" imagesrcset="{{ asset('images/marketing/care-assistance/community-support-640.webp') }} 640w, {{ asset('images/marketing/care-assistance/community-support-960.webp') }} 960w, {{ asset('images/marketing/care-assistance/community-support-1440.webp') }} 1440w" imagesizes="(max-width: 760px) calc(100vw - 48px), (max-width: 1050px) calc((100vw - 96px) * .55), (max-width: 1344px) calc((100vw - 152px) * .55), 656px" fetchpriority="high">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&display=swap" rel="stylesheet">
@endpush

@section('content')
    <style>
        html, body { margin: 0; }
        .care-assistance { --cream: #fff7ea; --paper: #fffcf6; --green: #173f35; --deep: #12372e; --coral: #ae4e3d; --peach: #f1c7b2; --oat: #eee4d4; --ink: #293d35; --muted: #59665c; --line: #d7d4c4; background: var(--cream); color: var(--ink); font-family: 'DM Sans', sans-serif; font-size: 16px; line-height: 1.65; overflow-x: clip; }
        .care-assistance *, .care-assistance *::before, .care-assistance *::after { box-sizing: border-box; }
        .care-assistance a { color: inherit; }
        .care-assistance img { display: block; max-width: 100%; height: auto; }
        .care-assistance h1, .care-assistance h2, .care-assistance h3, .care-assistance p { margin-top: 0; }
        .care-assistance h1, .care-assistance h2, .care-assistance h3 { font-family: 'Fraunces', Georgia, serif; font-weight: 500; letter-spacing: -.045em; color: var(--green); }
        .care-assistance h2 { font-size: clamp(2.4rem, 4.1vw, 4.3rem); line-height: 1.07; margin-bottom: 25px; }
        .care-assistance h3 { font-size: 1.65rem; line-height: 1.2; }
        .care-assistance p:last-child { margin-bottom: 0; }
        .care-assistance :focus-visible { outline: 3px solid var(--coral); outline-offset: 5px; }
        .care-assistance [id] { scroll-margin-top: 30px; }
        .care-assistance .shell { max-width: 1344px; margin: 0 auto; padding-inline: 48px; }
        .care-assistance .button { display: inline-flex; justify-content: center; align-items: center; gap: 20px; min-height: 54px; border: 1px solid transparent; border-radius: 999px; padding: 15px 26px; background: var(--green); color: var(--paper); text-decoration: none; font-family: inherit; font-size: .91rem; font-weight: 700; cursor: pointer; transition: background .2s, transform .2s; }
        .care-assistance .button:hover { background: #245747; transform: translateY(-2px); }
        .care-assistance .text-link { text-decoration-thickness: 1px; text-underline-offset: 5px; font-size: .9rem; font-weight: 600; }
        .care-assistance .skip-link { position: absolute; z-index: 50; left: 15px; top: -100px; background: var(--paper); padding: 12px 22px; }
        .care-assistance .skip-link:focus { top: 10px; }
        .care-assistance .nav-shell { position: relative; display: flex; gap: 32px; align-items: center; min-height: 94px; border-bottom: 1px solid var(--line); }
        .care-assistance .brand { display: inline-flex; flex-shrink: 0; }
        .care-assistance .brand img { width: 92px; }
        .care-assistance .nav-links { display: flex; gap: 29px; margin-left: auto; font-size: .85rem; font-weight: 500; }
        .care-assistance .nav-links a { text-decoration: none; }
        .care-assistance .nav-links a:hover { color: var(--coral); }
        .care-assistance .nav-shell > .button { min-height: 43px; padding: 10px 21px; font-size: .8rem; }
        .care-assistance .mobile-menu { display: none; position: relative; margin-left: auto; }
        .care-assistance .mobile-menu summary { cursor: pointer; padding: 9px; font-size: .85rem; }
        .care-assistance .mobile-menu nav { position: absolute; z-index: 30; right: 0; top: 50px; width: 240px; display: grid; gap: 12px; padding: 24px; background: var(--paper); border: 1px solid var(--line); border-radius: 16px; box-shadow: 0 16px 40px #173f3514; }
        .care-assistance .mobile-menu nav a { padding-block: 5px; text-decoration: none; }
        .care-assistance .breadcrumb { margin: 26px 0 0; color: var(--muted); font-size: .72rem; }
        .care-assistance .breadcrumb a { text-decoration: none; }
        .care-assistance .breadcrumb span { margin-inline: 10px; }
        .care-assistance .hero { display: grid; grid-template-columns: minmax(0, .9fr) minmax(0, 1.1fr); gap: 56px; align-items: center; padding-block: 50px 72px; }
        .care-assistance .hero h1 { font-size: clamp(3.4rem, 4.9vw, 4.8rem); line-height: 1.04; margin-bottom: 27px; }
        .care-assistance .hero h1 em { color: var(--coral); font-style: normal; }
        .care-assistance .hero-copy > p { max-width: 510px; font-size: 1rem; color: var(--muted); }
        .care-assistance .hero-copy .hero-lead { font-size: 1.09rem !important; }
        .care-assistance .hero-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 23px; margin: 29px 0 14px; }
        .care-assistance .microcopy { font-size: .75rem !important; }
        .care-assistance .hero-visual { margin: 0; overflow: hidden; border-radius: 24px; background: var(--green); box-shadow: 0 20px 55px #173f3510; }
        .care-assistance .hero-photo { width: 100%; height: auto; aspect-ratio: 4 / 3; object-fit: cover; object-position: 45% 45%; }
        .care-assistance .hero-caption { display: flex; align-items: center; justify-content: space-between; gap: 24px; padding: 25px 28px; color: var(--cream); }
        .care-assistance .photo-caption { font-family: 'Fraunces', Georgia, serif; font-size: clamp(1.2rem, 1.8vw, 1.55rem); line-height: 1.3; letter-spacing: -.025em; margin: 0; }
        .care-assistance .price-card { flex-shrink: 0; max-width: 170px; padding-left: 24px; border-left: 1px solid #769084; }
        .care-assistance .price { display: block; font-family: 'Fraunces', Georgia, serif; font-size: 3.6rem; line-height: 1; letter-spacing: -.06em; color: var(--cream); white-space: nowrap; }
        .care-assistance .price small { font-family: 'DM Sans', sans-serif; font-size: .75rem; letter-spacing: 0; }
        .care-assistance .price-card p { font-size: .69rem; line-height: 1.5; margin: 9px 0 0; color: #d9e4d9; }
        .care-assistance .reassurance { display: grid; grid-template-columns: repeat(3, 1fr); border-block: 1px solid var(--line); padding-block: 27px; margin-bottom: 90px; gap: 35px; }
        .care-assistance .reassurance > div { display: flex; align-items: center; justify-content: center; gap: 12px; font-size: .85rem; }
        .care-assistance .reassurance svg { color: var(--coral); width: 23px; height: 23px; flex-shrink: 0; }
        .care-assistance .community { display: grid; grid-template-columns: minmax(0, .95fr) minmax(0, 1.05fr); gap: 95px; padding-bottom: 95px; align-items: start; }
        .care-assistance .community h2 { max-width: 470px; }
        .care-assistance .community-copy { padding-top: 40px; color: var(--muted); font-size: 1.02rem; }
        .care-assistance .community-copy strong { color: var(--green); font-weight: 600; }
        .care-assistance .community-note { display: flex; align-items: center; gap: 13px; margin-top: 30px; font-size: .8rem; color: var(--green); }
        .care-assistance .community-note::before { content: ''; width: 36px; height: 1px; background: var(--coral); }
        .care-assistance .belong { background: var(--green); color: var(--cream); padding-block: 76px; }
        .care-assistance .belong-inner { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(0, .9fr); align-items: center; gap: 72px; }
        .care-assistance .belong-photo { position: relative; margin: 0; }
        .care-assistance .belong-photo img { height: auto; width: 100%; aspect-ratio: 3 / 2; object-fit: cover; border-radius: 18px; }
        .care-assistance .belong-photo figcaption { font-family: 'Fraunces', Georgia, serif; font-size: 1.4rem; line-height: 1.4; letter-spacing: -.03em; margin: 22px 0 0; padding-left: 20px; border-left: 2px solid var(--peach); color: var(--peach); }
        .care-assistance .belong h2 { font-size: clamp(2.4rem, 3.5vw, 3.5rem); }
        .care-assistance .belong h2 { color: var(--cream); }
        .care-assistance .belong-copy > p { color: #e0e7dc; max-width: 530px; }
        .care-assistance .care-tags { display: flex; flex-wrap: wrap; gap: 10px; padding: 0; list-style: none; margin: 28px 0 0; }
        .care-assistance .care-tags li { border: 1px solid #769084; padding: 8px 14px; border-radius: 999px; font-size: .75rem; color: var(--cream); }
        .care-assistance .steps { padding-block: 92px 95px; }
        .care-assistance .section-head { display: flex; align-items: end; justify-content: space-between; gap: 50px; margin-bottom: 40px; }
        .care-assistance .section-head h2 { margin-bottom: 0; }
        .care-assistance .section-head > p { max-width: 310px; color: var(--muted); font-size: .92rem; }
        .care-assistance .step-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 38px; list-style: none; padding: 0; margin: 0; }
        .care-assistance .step-grid li { border-top: 1px solid var(--line); padding-top: 25px; }
        .care-assistance .step-number { display: inline-block; font-family: 'Fraunces', Georgia, serif; font-size: 1.2rem; color: var(--coral); margin-bottom: 22px; }
        .care-assistance .step-grid h3 { margin-bottom: 13px; }
        .care-assistance .step-grid p { color: var(--muted); font-size: .9rem; }
        .care-assistance .apply-section { background: var(--oat); padding-block: 84px; }
        .care-assistance .apply-layout { display: grid; grid-template-columns: minmax(0, .72fr) minmax(0, 1.28fr); align-items: start; gap: 74px; }
        .care-assistance .apply-intro { position: sticky; top: 35px; }
        .care-assistance .apply-intro h2 { font-size: clamp(2.5rem, 3.8vw, 4rem); }
        .care-assistance .apply-intro > p { font-size: .96rem; color: #526052; }
        .care-assistance .application-notes { margin: 33px 0; padding: 0; list-style: none; }
        .care-assistance .application-notes li { display: flex; align-items: baseline; gap: 11px; margin-bottom: 14px; font-size: .83rem; }
        .care-assistance .application-notes li span { color: var(--coral); }
        .care-assistance .help-note { border-top: 1px solid #c9c7b6; padding-top: 24px; font-size: .82rem !important; }
        .care-assistance .help-note a { text-underline-offset: 4px; }
        .care-assistance .application-card { background: var(--paper); border: 1px solid #dedace; border-radius: 16px; padding: 38px; box-shadow: 0 15px 40px #35493605; }
        .care-assistance .form-intro { display: flex; justify-content: space-between; gap: 16px; align-items: baseline; padding-bottom: 24px; border-bottom: 1px solid var(--line); margin-bottom: 28px; }
        .care-assistance .form-intro h3 { margin: 0; font-size: 1.5rem; }
        .care-assistance .form-intro p { font-size: .72rem; color: var(--muted); }
        .care-assistance .form-section { padding: 0; border: 0; margin: 0 0 30px; min-width: 0; }
        .care-assistance .form-section > legend { display: flex; align-items: center; gap: 10px; width: 100%; padding: 0; margin-bottom: 19px; font-weight: 700; font-size: .85rem; }
        .care-assistance .form-number { display: inline-grid; place-items: center; width: 25px; height: 25px; background: #edeedc; border-radius: 50%; font-size: .66rem; color: var(--green); }
        .care-assistance .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; }
        .care-assistance .field.full { grid-column: 1 / -1; }
        .care-assistance .field > label, .care-assistance .care-needs > legend { display: block; font-size: .8rem; font-weight: 600; margin-bottom: 7px; }
        .care-assistance .field input, .care-assistance .field select, .care-assistance .field textarea { width: 100%; min-height: 47px; border: 1px solid #b7c0b4; border-radius: 7px; padding: 11px 12px; background: #fffefa; color: var(--ink); font-family: inherit; font-size: .9rem; line-height: 1.4; }
        .care-assistance .field input::placeholder, .care-assistance .field textarea::placeholder { color: #757b70; }
        .care-assistance .field textarea { resize: vertical; min-height: 145px; }
        .care-assistance .field [aria-invalid=true] { border-color: #a23529; }
        .care-assistance .field-hint { color: var(--muted); font-size: .72rem; line-height: 1.6; margin: 7px 0 0; }
        .care-assistance .care-needs { border: 0; margin: 22px 0; padding: 0; min-width: 0; }
        .care-assistance .care-needs > legend { padding: 0; }
        .care-assistance .care-needs > p { margin: 0 0 11px; }
        .care-assistance .checkbox-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .care-assistance .checkbox-option { display: flex; align-items: center; gap: 9px; border: 1px solid var(--line); border-radius: 7px; padding: 12px; cursor: pointer; font-size: .75rem; line-height: 1.4; }
        .care-assistance .checkbox-option:has(input:checked) { border-color: var(--green); background: #eff1e6; }
        .care-assistance input[type=checkbox] { accent-color: var(--green); width: 17px; height: 17px; flex-shrink: 0; margin: 1px 0; }
        .care-assistance .consent { display: flex; gap: 11px; align-items: flex-start; font-size: .75rem; line-height: 1.65; }
        .care-assistance .consent input { margin-top: 4px; }
        .care-assistance .consent a { text-underline-offset: 3px; }
        .care-assistance .submit-button { width: 100%; margin-top: 24px; }
        .care-assistance .submit-note { text-align: center; color: var(--muted); font-size: .7rem; margin: 11px 0 0; }
        .care-assistance .honeypot { position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden; }
        .care-assistance .field-error { color: #992f24; font-size: .76rem; margin: 7px 0 0; }
        .care-assistance .error-summary { background: #fff0e8; border: 1px solid #bf7564; border-radius: 9px; padding: 20px; font-size: .85rem; margin-bottom: 25px; }
        .care-assistance .error-summary ul { padding-left: 19px; margin: 9px 0 0; }
        .care-assistance .error-summary li { margin: 6px 0; }
        .care-assistance .receipt { padding-block: 30px; }
        .care-assistance .receipt-icon { display: grid; place-items: center; width: 64px; height: 64px; margin-bottom: 27px; border-radius: 50%; background: #e8edde; color: var(--green); font-size: 1.7rem; }
        .care-assistance .receipt h3 { font-size: 2.6rem; }
        .care-assistance .receipt p { color: var(--muted); }
        .care-assistance .receipt .receipt-next { background: #f1f0e5; padding: 22px; border-radius: 9px; margin: 25px 0; font-size: .9rem; }
        .care-assistance .receipt-next strong { display: block; color: var(--green); margin-bottom: 8px; }
        .care-assistance .faq-section { padding-block: 93px; }
        .care-assistance .faq-layout { display: grid; grid-template-columns: minmax(0, .7fr) minmax(0, 1.3fr); gap: 80px; }
        .care-assistance .faq-intro p { color: var(--muted); font-size: .95rem; }
        .care-assistance .faq-list { border-top: 1px solid var(--line); }
        .care-assistance .faq-item { border-bottom: 1px solid var(--line); }
        .care-assistance .faq-item summary { display: grid; grid-template-columns: 1fr 24px; gap: 9px 22px; padding: 23px 0; cursor: pointer; list-style: none; }
        .care-assistance .faq-item summary::-webkit-details-marker { display: none; }
        .care-assistance .faq-tag { grid-column: 1; justify-self: start; padding: 3px 9px; background: #ecebdc; color: #43583f; border-radius: 4px; font-size: .59rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        .care-assistance .faq-item:nth-child(3n + 2) .faq-tag { background: #f4dfd2; color: #844832; }
        .care-assistance .faq-item:nth-child(3n) .faq-tag { background: #e3e9e2; color: #3f5e51; }
        .care-assistance .faq-question { grid-column: 1; color: var(--green); font-weight: 600; font-size: .97rem; }
        .care-assistance .faq-plus { grid-column: 2; grid-row: 1 / 3; align-self: center; font-size: 1.4rem; font-weight: 400; color: var(--coral); }
        .care-assistance .faq-item[open] .faq-plus { transform: rotate(45deg); }
        .care-assistance .faq-answer { padding: 0 38px 24px 0; color: var(--muted); font-size: .9rem; }
        .care-assistance .closing { border-top: 1px solid var(--line); text-align: center; padding-block: 60px 80px; }
        .care-assistance .closing h2 { max-width: 720px; margin: 0 auto 29px; }
        .care-assistance .footer { background: var(--green); color: #e4e9df; padding-block: 40px 24px; }
        .care-assistance .footer-top { display: flex; align-items: start; justify-content: space-between; gap: 50px; }
        .care-assistance .footer-brand { max-width: 460px; }
        .care-assistance .footer-brand img { width: 85px; margin-bottom: 18px; }
        .care-assistance .footer-brand p { font-size: .78rem; color: #c8d5c8; }
        .care-assistance .footer nav { display: flex; flex-wrap: wrap; gap: 15px 25px; padding-top: 12px; font-size: .8rem; }
        .care-assistance .footer nav a { text-decoration: none; }
        .care-assistance .footer nav a:hover { text-decoration: underline; text-underline-offset: 4px; }
        .care-assistance .footer-bottom { border-top: 1px solid #486459; margin-top: 32px; padding-top: 20px; font-size: .66rem; color: #b9ccbc; }
        @media (min-width: 1500px) { .care-assistance .hero { padding-block: 70px 100px; } }
        @media (max-width: 1050px) {
            .care-assistance .shell { padding-inline: 30px; }
            .care-assistance .nav-links { gap: 19px; }
            .care-assistance .hero { gap: 36px; }
            .care-assistance .hero h1 { font-size: clamp(3rem, 5.1vw, 3.6rem); }
            .care-assistance .hero-caption { gap: 18px; padding: 22px; }
            .care-assistance .price-card { max-width: 140px; padding-left: 18px; }
            .care-assistance .price { font-size: 3rem; }
            .care-assistance .community, .care-assistance .belong-inner, .care-assistance .apply-layout, .care-assistance .faq-layout { gap: 45px; }
            .care-assistance .application-card { padding: 26px; }
            .care-assistance .apply-layout { grid-template-columns: minmax(0, .65fr) minmax(0, 1.35fr); }
        }
        @media (max-width: 760px) {
            .care-assistance .shell { padding-inline: 24px; }
            .care-assistance .nav-shell { min-height: 79px; gap: 14px; }
            .care-assistance .brand img { width: 77px; }
            .care-assistance .nav-links { display: none; }
            .care-assistance .mobile-menu { display: block; position: static; }
            .care-assistance .mobile-menu nav { left: 0; right: 0; top: 78px; width: auto; }
            .care-assistance .nav-shell > .button { font-size: .72rem; padding-inline: 16px; }
            .care-assistance .breadcrumb { margin-top: 21px; font-size: .65rem; }
            .care-assistance .hero { grid-template-columns: 1fr; gap: 37px; padding-block: 40px 42px; }
            .care-assistance .hero h1 { font-size: clamp(3.25rem, 9vw, 5rem); max-width: 610px; }
            .care-assistance .hero-copy > p { max-width: 580px; }
            .care-assistance .hero-visual { width: 100%; border-radius: 18px; }
            .care-assistance .hero-photo { aspect-ratio: 3 / 2; }
            .care-assistance .photo-caption { font-size: 1.4rem; }
            .care-assistance .reassurance { grid-template-columns: 1fr; padding-block: 24px; gap: 18px; margin-bottom: 58px; }
            .care-assistance .reassurance > div { justify-content: flex-start; font-size: .82rem; }
            .care-assistance .community { grid-template-columns: 1fr; gap: 0; padding-bottom: 60px; }
            .care-assistance .community-copy { padding-top: 0; }
            .care-assistance .community h2 { max-width: 550px; }
            .care-assistance .belong { padding-block: 55px; }
            .care-assistance .belong-inner { grid-template-columns: 1fr; gap: 40px; }
            .care-assistance .belong-photo { order: 2; }
            .care-assistance .belong-photo figcaption { font-size: 1.4rem; }
            .care-assistance .steps { padding-block: 61px; }
            .care-assistance .section-head { display: block; margin-bottom: 30px; }
            .care-assistance .section-head > p { margin-top: 23px; max-width: 500px; }
            .care-assistance .step-grid { grid-template-columns: 1fr; gap: 25px; }
            .care-assistance .step-grid li { display: grid; grid-template-columns: 35px 1fr; gap: 0 14px; }
            .care-assistance .step-number { grid-row: 1 / 3; margin-bottom: 0; }
            .care-assistance .step-grid h3 { margin-top: 2px; }
            .care-assistance .step-grid p { grid-column: 2; }
            .care-assistance .apply-section { padding-block: 55px; }
            .care-assistance .apply-layout { grid-template-columns: 1fr; gap: 28px; }
            .care-assistance .apply-intro { position: static; }
            .care-assistance .field input, .care-assistance .field select, .care-assistance .field textarea { font-size: 16px; }
            .care-assistance .apply-intro h2 { max-width: 500px; }
            .care-assistance .application-notes { margin: 25px 0; }
            .care-assistance .help-note { padding-top: 19px; }
            .care-assistance .faq-section { padding-block: 60px; }
            .care-assistance .faq-layout { grid-template-columns: 1fr; gap: 30px; }
            .care-assistance .faq-intro h2 { max-width: 480px; }
            .care-assistance .closing { padding-block: 50px 60px; }
            .care-assistance .footer-top { display: block; }
            .care-assistance .footer nav { padding-top: 27px; }
        }
        @media (max-width: 430px) {
            .care-assistance .shell { padding-inline: 21px; }
            .care-assistance .nav-shell { gap: 9px; }
            .care-assistance .mobile-menu summary { padding-inline: 3px; }
            .care-assistance .nav-shell > .button { padding-inline: 13px; }
            .care-assistance .hero h1 { font-size: 3.2rem; }
            .care-assistance .hero-actions { gap: 19px; }
            .care-assistance .hero-caption { gap: 16px; padding: 21px 18px; }
            .care-assistance .price-card { max-width: 132px; padding-left: 16px; }
            .care-assistance .price { font-size: 2.8rem; }
            .care-assistance .price small { font-size: .67rem; }
            .care-assistance .price-card p { font-size: .64rem; }
            .care-assistance .photo-caption { font-size: 1.18rem; }
            .care-assistance .application-card { padding: 24px 18px; border-radius: 12px; }
            .care-assistance .form-intro { display: block; }
            .care-assistance .form-intro p { margin-top: 7px; }
            .care-assistance .form-grid { grid-template-columns: 1fr; gap: 17px; }
            .care-assistance .checkbox-grid { grid-template-columns: 1fr; }
            .care-assistance .field input, .care-assistance .field select, .care-assistance .field textarea { font-size: 16px; }
        }
        @media (max-width: 375px) {
            .care-assistance .hero-caption { flex-direction: column; align-items: stretch; }
            .care-assistance .price-card { display: flex; align-items: center; gap: 18px; max-width: none; padding: 16px 0 0; border-left: 0; border-top: 1px solid #769084; }
            .care-assistance .price-card p { margin: 0; }
        }
        @media (prefers-reduced-motion: reduce) { .care-assistance *, .care-assistance *::before, .care-assistance *::after { transition: none !important; scroll-behavior: auto !important; } }
    </style>

    <div class="care-assistance">
        <a class="skip-link" href="#main-content">Skip to main content</a>
        <header class="shell">
            <div class="nav-shell">
                <a class="brand" href="{{ route('landing') }}" aria-label="LoLo Care home"><img src="{{ asset('images/marketing/lolo/lolo-wordmark-evergreen.svg') }}" alt="LoLo Care" width="652" height="222"></a>
                <nav class="nav-links" aria-label="Main navigation">
                    <a href="#our-community">Our community</a><a href="#how-it-works">How it works</a><a href="#faq">Common questions</a><a href="{{ route('about') }}">About LoLo</a>
                </nav>
                <details class="mobile-menu">
                    <summary>Menu</summary>
                    <nav aria-label="Mobile navigation"><a href="#our-community">Our community</a><a href="#how-it-works">How it works</a><a href="#faq">Common questions</a><a href="{{ route('about') }}">About LoLo</a><a href="{{ route('landing') }}">LoLo Care home</a></nav>
                </details>
                <a class="button" href="#apply">Apply for assistance <span aria-hidden="true">↗</span></a>
            </div>
        </header>

        <main id="main-content">
            <div class="shell">
                <nav class="breadcrumb" aria-label="Breadcrumb"><a href="{{ route('landing') }}">Home</a><span aria-hidden="true">/</span><strong aria-current="page">Care Assistance</strong></nav>
                <section class="hero" aria-labelledby="assistance-title">
                    <div class="hero-copy">
                        <h1 id="assistance-title">Everyone deserves to feel <em>supported</em> at home.</h1>
                        <p class="hero-lead">Care can make a meaningful difference in everyday life. When money is tight, finding that support shouldn’t feel out of reach.</p>
                        <p>Our Care Assistance Program offers non-medical home care at <strong>$23 per hour for approved applicants</strong> in Raleigh, Durham, and Chapel Hill. It’s here for individuals and families facing limited incomes or financial hardship.</p>
                        <div class="hero-actions"><a class="button" href="#apply">Apply for care assistance <span aria-hidden="true">↗</span></a><a class="text-link" href="#how-it-works">How it works</a></div>
                        <p class="microcopy">No account needed. No commitment to book care.</p>
                    </div>
                    <figure class="hero-visual">
                        <img class="hero-photo" src="{{ asset('images/marketing/care-assistance/community-support-960.webp') }}" srcset="{{ asset('images/marketing/care-assistance/community-support-640.webp') }} 640w, {{ asset('images/marketing/care-assistance/community-support-960.webp') }} 960w, {{ asset('images/marketing/care-assistance/community-support-1440.webp') }} 1440w" sizes="(max-width: 760px) calc(100vw - 48px), (max-width: 1050px) calc((100vw - 96px) * .55), (max-width: 1344px) calc((100vw - 152px) * .55), 656px" alt="A volunteer and an older man in a wheelchair smiling together outdoors" width="1440" height="960" fetchpriority="high" decoding="async">
                        <figcaption class="hero-caption">
                            <p class="photo-caption">More everyday moments.<br>More connection.</p>
                            <div class="price-card"><span class="price">$23<small> / hour</small></span><p>In-home care for approved program applicants.</p></div>
                        </figcaption>
                    </figure>
                </section>

                <div class="reassurance" aria-label="Program at a glance">
                    <div><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m4 12 5 5L20 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg><span>A simple, personal application</span></div>
                    <div><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5"/><path d="M12 7v5l3 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg><span>Reviews in about five business days</span></div>
                    <div><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 21s-9-5.5-9-12a5 5 0 0 1 9-3 5 5 0 0 1 9 3c0 6.5-9 12-9 12Z" stroke="currentColor" stroke-width="1.5"/></svg><span>People helping people, locally</span></div>
                </div>

                <section class="community" id="our-community" aria-labelledby="community-title">
                    <div><h2 id="community-title">Caring for the community we call home.</h2></div>
                    <div class="community-copy">
                        <p><strong>Strong communities are built by people who show up for one another.</strong></p>
                        <p>Through this program, <strong>LoLo reduces its own fee</strong> to help make care more affordable. Together with participating local caregivers who share our commitment to supporting their neighbors, we’re working to bring dependable, compassionate help within reach of more families.</p>
                        <p>Whether it’s companionship, help with everyday routines, or support for a loved one, our purpose is simple: to help people feel cared for, respected, and connected at home.</p>
                        <p class="community-note">Neighbors supporting neighbors. That’s the heart of LoLo.</p>
                    </div>
                </section>
            </div>

            <section class="belong" aria-labelledby="belong-title">
                <div class="shell belong-inner">
                    <figure class="belong-photo">
                        <img src="{{ asset('images/marketing/care-assistance/helping-hand-at-home-960.webp') }}" srcset="{{ asset('images/marketing/care-assistance/helping-hand-at-home-640.webp') }} 640w, {{ asset('images/marketing/care-assistance/helping-hand-at-home-960.webp') }} 960w, {{ asset('images/marketing/care-assistance/helping-hand-at-home-1440.webp') }} 1440w" sizes="(max-width: 760px) calc(100vw - 48px), (max-width: 1050px) calc((100vw - 105px) * .55), (max-width: 1344px) calc((100vw - 168px) * .55), 647px" alt="A person supporting an older adult’s arm as they use a cane at home" width="1440" height="960" loading="lazy" decoding="async">
                        <figcaption>A little support. More room for life.</figcaption>
                    </figure>
                    <div class="belong-copy">
                        <h2 id="belong-title">When finances make care difficult, you’re welcome here.</h2>
                        <p>You may be living on a limited income, facing an unexpected change, or balancing the cost of care with other essential expenses. Every situation is different, and we want to understand yours.</p>
                        <p>The application gives you space to explain what you need and what makes care difficult to afford. You don’t need to find the perfect words—just tell us a little about your circumstances.</p>
                        <ul class="care-tags" aria-label="Everyday non-medical support"><li>Companionship</li><li>Meals & everyday routines</li><li>Errands & rides</li><li>Light housekeeping</li><li>Support for family caregivers</li></ul>
                    </div>
                </div>
            </section>

            <section class="shell steps" id="how-it-works" aria-labelledby="steps-title">
                <div class="section-head"><div><h2 id="steps-title">A little about you.<br>A real person listening.</h2></div><p>We review every application individually, with care for the person and circumstances behind it.</p></div>
                <ol class="step-grid">
                    <li><span class="step-number" aria-hidden="true">01</span><h3>Tell us your story.</h3><p>Complete a short application with your care needs and contact details. You can apply for yourself or someone you care about.</p></li>
                    <li><span class="step-number" aria-hidden="true">02</span><h3>Give us time to listen.</h3><p>Our team reviews applications individually. Reviews usually take about <strong>five business days</strong>.</p></li>
                    <li><span class="step-number" aria-hidden="true">03</span><h3>Hear from our team.</h3><p>We’ll contact you with an update, ask for any additional information needed, and explain the next steps if you’re approved.</p></li>
                </ol>
            </section>

            <section class="apply-section" id="apply" aria-labelledby="apply-title">
                <div class="shell apply-layout">
                    <div class="apply-intro">
                        <h2 id="apply-title">A helping hand starts with a conversation.</h2>
                        <p>If the cost of care has been holding you back, we invite you to apply. Tell us what a little extra support would mean for you or someone you love.</p>
                        <ul class="application-notes"><li><span aria-hidden="true">✓</span> $23/hour for approved applicants</li><li><span aria-hidden="true">✓</span> Review in about five business days</li><li><span aria-hidden="true">✓</span> Applying does not commit you to booking care</li></ul>
                        <p class="help-note">Need help with your application?<br><a href="mailto:hello@carelolo.com">hello@carelolo.com</a></p>
                    </div>

                    <div class="application-card">
                        @if (session('care_assistance_submitted'))
                            <div class="receipt" role="status" tabindex="-1" id="application-received">
                                <div class="receipt-icon" aria-hidden="true">✓</div>
                                <h3>Thank you for sharing your story.</h3>
                                <p>Asking for support is a meaningful first step. Our team has received your application and will review it with care.</p>
                                <div class="receipt-next"><strong>What happens next?</strong>Reviews usually take about five business days. We’ll contact you using the details you provided with an update or any questions, and explain the next steps if you’re approved.</div>
                                <p class="microcopy">You don’t need to submit another application. Applying does not commit you to booking care.</p>
                                <a class="text-link" href="#faq">Explore common questions</a>
                            </div>
                        @else
                            <div class="form-intro"><h3>Your care assistance application</h3><p>All fields are required.</p></div>
                            @if ($errors->any())
                                <div class="error-summary" role="alert" tabindex="-1" id="application-errors">
                                    <strong>A few details need another look.</strong>
                                    <ul>
                                        @foreach ($errors->messages() as $field => $messages)
                                            @foreach ($messages as $message)
                                                <li><a href="#{{ str_starts_with($field, 'care_needs') ? 'care-needs' : str_replace('_', '-', $field) }}">{{ $message }}</a></li>
                                            @endforeach
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            <form method="POST" action="{{ route('care-assistance.store') }}#apply">
                                @csrf
                                <div class="honeypot" aria-hidden="true"><label for="website">Leave this field empty</label><input type="text" name="website" id="website" tabindex="-1" autocomplete="off"></div>
                                <fieldset class="form-section">
                                    <legend><span class="form-number" aria-hidden="true">1</span> A little about you</legend>
                                    <div class="form-grid">
                                        <div class="field full"><label for="name">Your full name</label><input type="text" name="name" id="name" value="{{ $oldString('name') }}" required maxlength="120" autocomplete="name" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>@error('name')<p class="field-error" id="name-error">{{ $message }}</p>@enderror</div>
                                        <div class="field"><label for="email">Email address</label><input type="email" name="email" id="email" value="{{ $oldString('email') }}" required maxlength="255" autocomplete="email" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>@error('email')<p class="field-error" id="email-error">{{ $message }}</p>@enderror</div>
                                        <div class="field"><label for="phone">Phone number</label><input type="tel" name="phone" id="phone" value="{{ $oldString('phone') }}" required maxlength="30" autocomplete="tel" @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror>@error('phone')<p class="field-error" id="phone-error">{{ $message }}</p>@enderror</div>
                                        <div class="field full"><label for="relationship">Who are you applying for?</label><select name="relationship" id="relationship" required @error('relationship') aria-invalid="true" aria-describedby="relationship-error" @enderror><option value="">Select an option</option>@foreach (config('care_assistance.relationship_options', []) as $value => $label)<option value="{{ $value }}" @selected($oldString('relationship') === $value)>{{ $label }}</option>@endforeach</select>@error('relationship')<p class="field-error" id="relationship-error">{{ $message }}</p>@enderror</div>
                                    </div>
                                </fieldset>
                                <fieldset class="form-section">
                                    <legend><span class="form-number" aria-hidden="true">2</span> The support you’re looking for</legend>
                                    <div class="form-grid">
                                        <div class="field"><label for="city">City where care is needed</label><input type="text" name="city" id="city" value="{{ $oldString('city') }}" required maxlength="120" autocomplete="address-level2" @error('city') aria-invalid="true" aria-describedby="city-error" @enderror>@error('city')<p class="field-error" id="city-error">{{ $message }}</p>@enderror</div>
                                        <div class="field"><label for="zip">ZIP code where care is needed</label><input type="text" name="zip" id="zip" value="{{ $oldString('zip') }}" required maxlength="10" autocomplete="postal-code" inputmode="numeric" @error('zip') aria-invalid="true" aria-describedby="zip-error" @enderror>@error('zip')<p class="field-error" id="zip-error">{{ $message }}</p>@enderror</div>
                                    </div>
                                    <fieldset class="care-needs" id="care-needs" tabindex="-1" aria-describedby="care-needs-hint{{ $errors->has('care_needs') || $errors->has('care_needs.*') ? ' care-needs-error' : '' }}">
                                        <legend>What would you like help with?</legend><p class="field-hint" id="care-needs-hint">Select at least one. All care is non-medical.</p>
                                        <div class="checkbox-grid">
                                            @foreach (config('care_assistance.care_need_options', []) as $value => $label)
                                                <label class="checkbox-option"><input type="checkbox" name="care_needs[]" value="{{ $value }}" @checked(in_array($value, $selectedCareNeeds, true))><span>{{ $label }}</span></label>
                                            @endforeach
                                        </div>
                                        @if ($errors->has('care_needs') || $errors->has('care_needs.*'))<p class="field-error" id="care-needs-error">{{ $errors->first('care_needs') ?: $errors->first('care_needs.*') }}</p>@endif
                                    </fieldset>
                                    <div class="form-grid">
                                        <div class="field"><label for="weekly-hours">Approximate hours per week</label><select name="weekly_hours" id="weekly-hours" required @error('weekly_hours') aria-invalid="true" aria-describedby="weekly-hours-error" @enderror><option value="">Select an option</option>@foreach (config('care_assistance.weekly_hour_options', []) as $value => $label)<option value="{{ $value }}" @selected($oldString('weekly_hours') === $value)>{{ $label }}</option>@endforeach</select>@error('weekly_hours')<p class="field-error" id="weekly-hours-error">{{ $message }}</p>@enderror</div>
                                        <div class="field"><label for="start-timeframe">When would you like care to start?</label><select name="start_timeframe" id="start-timeframe" required @error('start_timeframe') aria-invalid="true" aria-describedby="start-timeframe-error" @enderror><option value="">Select an option</option>@foreach (config('care_assistance.start_timeframe_options', []) as $value => $label)<option value="{{ $value }}" @selected($oldString('start_timeframe') === $value)>{{ $label }}</option>@endforeach</select>@error('start_timeframe')<p class="field-error" id="start-timeframe-error">{{ $message }}</p>@enderror</div>
                                    </div>
                                </fieldset>
                                <fieldset class="form-section">
                                    <legend><span class="form-number" aria-hidden="true">3</span> Your circumstances, in your own words</legend>
                                    <div class="field"><label for="hardship-details">What makes paying for care difficult right now?</label><textarea name="hardship_details" id="hardship-details" rows="5" required maxlength="3000" aria-describedby="hardship-details-hint{{ $errors->has('hardship_details') ? ' hardship-details-error' : '' }}" @error('hardship_details') aria-invalid="true" @enderror>{{ $oldString('hardship_details') }}</textarea><p class="field-hint" id="hardship-details-hint">A few sentences are enough. Share what you’re comfortable sharing, without including Social Security numbers, bank details, or medical records. Up to 3,000 characters.</p>@error('hardship_details')<p class="field-error" id="hardship-details-error">{{ $message }}</p>@enderror</div>
                                </fieldset>
                                <label class="consent" for="consent"><input type="checkbox" name="consent" id="consent" value="1" required @checked(in_array($oldString('consent'), ['1', 'true', 'on', 'yes'], true)) @error('consent') aria-invalid="true" aria-describedby="consent-error" @enderror><span>I confirm the information I’ve provided is accurate and agree that LoLo may contact me about this application. I have read the <a href="{{ route('legal.show', ['slug' => 'privacy-policy']) }}">privacy information</a> and understand that submitting an application does not guarantee approval.</span></label>
                                @error('consent')<p class="field-error" id="consent-error">{{ $message }}</p>@enderror
                                <button class="button submit-button" type="submit">Submit my application <span aria-hidden="true">↗</span></button>
                                <p class="submit-note">A real person will review your application. No payment required to apply.</p>
                            </form>
                        @endif
                    </div>
                </div>
            </section>

            <section class="shell faq-section" id="faq" aria-labelledby="faq-title">
                <div class="faq-layout">
                    <div class="faq-intro"><h2 id="faq-title">Good questions.<br>Thoughtful answers.</h2><p>Learn more about reduced-rate care, applying for support, and what happens next.</p><a class="text-link" href="mailto:hello@carelolo.com">Talk to the LoLo team <span aria-hidden="true">↗</span></a></div>
                    <div class="faq-list">
                        @foreach ($assistanceFaqs as $faq)
                            <details class="faq-item"><summary><span class="faq-tag">{{ $faq['category'] }}</span><span class="faq-question">{{ $faq['question'] }}</span><span class="faq-plus" aria-hidden="true">+</span></summary><div class="faq-answer"><p>{{ $faq['answer'] }}</p></div></details>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="shell closing" aria-labelledby="closing-title"><h2 id="closing-title">Let’s make room for more good days at home.</h2><a class="button" href="#apply">Apply for care assistance <span aria-hidden="true">↗</span></a></section>
        </main>

        <footer class="footer">
            <div class="shell"><div class="footer-top"><div class="footer-brand"><a href="{{ route('landing') }}" aria-label="LoLo Care home"><img src="{{ asset('images/marketing/lolo/lolo-wordmark-ivory.svg') }}" alt="LoLo Care" width="652" height="222"></a><p>Local people. Meaningful support. LoLo connects families in Raleigh, Durham, and Chapel Hill with non-medical help at home.</p></div><nav aria-label="Footer navigation"><a href="{{ route('about') }}">About LoLo</a><a href="{{ route('faq') }}">FAQs</a><a href="{{ route('caregiver.register') }}">Become a caregiver</a><a href="{{ route('legal.index') }}">Legal & privacy</a></nav></div><p class="footer-bottom">© {{ now()->year }} LoLo Care Inc. · Non-medical home support. Program rate available to approved applicants.</p></div>
        </footer>
    </div>
    @if ($errors->any() || session('care_assistance_submitted'))
        <script>document.getElementById(@json($errors->any() ? 'application-errors' : 'application-received'))?.focus();</script>
    @endif
@endsection
