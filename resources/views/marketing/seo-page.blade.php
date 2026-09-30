@extends('layouts.marketing')

@section('title', $page['meta_title'] ?? ($page['h1'] ?? 'LoLo Care in Raleigh'))
@section('meta_description', $page['meta_description'] ?? 'Find flexible non-medical support at home with LoLo Care in Raleigh.')
@section('canonical', url($page['path'] ?? request()->path()))
@section('og_image', asset('images/marketing/homepage/human-moment.jpg'))
@section('og_image_alt', 'A caregiver and an older adult sharing a joyful moment at home.')

@section('structured_data')
    @php
        $faqEntities = collect($page['faqs'] ?? [])->map(fn ($faq) => [
            '@type' => 'Question',
            'name' => (string) ($faq['q'] ?? ''),
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => (string) ($faq['a'] ?? ''),
            ],
        ])->values()->all();

        $webPageSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => (string) ($page['meta_title'] ?? $page['h1'] ?? 'LoLo Care'),
            'description' => (string) ($page['meta_description'] ?? ''),
            'url' => url($page['path'] ?? request()->path()),
            'about' => 'Non-medical home care in Raleigh, North Carolina',
        ];

        $faqSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $faqEntities,
        ];

    @endphp
    <script type="application/ld+json">{!! json_encode($webPageSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @if($faqEntities !== [])
        <script type="application/ld+json">{!! json_encode($faqSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @endif
@endsection

@section('content')
    <style>
        .care-guide { --guide-green: #23483f; --guide-cream: #fff7ea; --guide-coral: #b95745; }
        .care-guide h1, .care-guide h2, .care-guide h3 { font-family: 'Source Serif 4', Georgia, serif; font-weight: 600; }
        .care-guide h1 { max-width: 720px; line-height: 1.08; letter-spacing: -.035em; }
        .care-guide p { line-height: 1.8; }
        .care-guide .guide-button { display: inline-flex; align-items: center; justify-content: center; min-height: 48px; padding: 12px 24px; border: 1px solid var(--guide-green); border-radius: 999px; background: var(--guide-green); color: white; font-size: 14px; font-weight: 600; text-align: center; }
        .care-guide .guide-button:hover { background: #16392f; }
        .care-guide .guide-button.secondary { background: transparent; color: var(--guide-green); }
        .care-guide .guide-button.secondary:hover { background: #f3eadc; }
        .care-guide .guide-button.light { background: var(--guide-cream); color: var(--guide-green); border-color: var(--guide-cream); }
        .care-guide .guide-button.small { min-height: 40px; padding: 8px 18px; }
        .care-guide .guide-eyebrow { color: var(--guide-coral); font-size: 12px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
        .care-guide .guide-card { padding: 28px; border: 1px solid #e4dacb; border-radius: 20px; background: #fffaf2; }
        .care-guide .guide-card h2 { margin: 0 0 16px; color: var(--guide-green); font-size: 25px; line-height: 1.2; }
        .care-guide .guide-closing h2 { color: var(--guide-cream); }
        .care-guide :focus-visible { outline: 3px solid var(--guide-coral); outline-offset: 4px; }
        .care-guide .guide-hero-photo { border-radius: 120px 120px 24px 24px; }
        .care-guide .guide-hero-photo img { height: 440px; }
        .care-guide .guide-mobile-menu nav { position: absolute; right: 16px; top: 72px; z-index: 30; min-width: 200px; padding: 12px; border: 1px solid #e4dacb; border-radius: 16px; background: #fffaf2; box-shadow: 0 12px 30px #23483f20; }
        .care-guide .guide-mobile-menu a { display: block; padding: 12px; }
        @media (max-width: 640px) { .care-guide .guide-hero-photo img { height: 280px; } .care-guide .guide-hero-photo { border-radius: 80px 80px 20px 20px; } }
    </style>
    <div class="care-guide min-h-screen bg-[#FFF7EA] text-[#23483F]">
        <header class="sticky top-0 z-50 border-b border-[#E4DACB] bg-[#FFF7EA]/95 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
                <a href="{{ route('landing') }}" class="flex items-center gap-3" aria-label="LoLo Care home">
                    <img src="{{ asset('images/marketing/lolo/lolo-wordmark-evergreen.svg') }}" alt="LoLo Care" width="112" height="46" class="h-11 w-28 object-contain">
                </a>

                <nav class="hidden items-center gap-5 text-sm font-medium text-[#53645D] lg:flex">
                    <a href="{{ route('landing') }}" class="transition hover:text-[#23483F]">Families</a>
                    <a href="{{ route('landing.caregiver') }}" class="transition hover:text-[#23483F]">Caregivers</a>
                    <a href="{{ route('seo.page', ['seoSlug' => 'raleigh-home-care']) }}" class="transition hover:text-[#23483F]">Raleigh Guides</a>
                    <a href="{{ route('blog.index') }}" class="transition hover:text-[#23483F]">Blog</a>
                </nav>

                <details class="guide-mobile-menu lg:hidden"><summary class="cursor-pointer p-3 text-sm font-semibold">Menu</summary><nav aria-label="Mobile navigation"><a href="{{ route('landing') }}">For families</a><a href="{{ route('landing.caregiver') }}">For caregivers</a><a href="{{ route('blog.index') }}">Care guides</a><a href="{{ route('login') }}">Sign in</a></nav></details>

                <div class="flex flex-wrap items-center justify-end gap-2">
                    <a href="{{ route('login') }}" class="hidden p-3 text-sm sm:block">Sign in</a>
                    <a href="{{ route('register') }}" class="guide-button small">Find care</a>
                </div>
            </div>
        </header>

        <section class="relative overflow-hidden border-b border-[#E4DACB] bg-[#FFF7EA]">

            <div class="relative mx-auto grid max-w-7xl gap-10 px-4 pb-14 pt-12 sm:px-6 lg:grid-cols-12 lg:px-8 lg:pb-16 lg:pt-16">
                <div class="lg:col-span-7">
                    @php
                        $currentGuideUrl = url($page['path'] ?? request()->path());
                        $guideHubUrl = route('seo.page', ['seoSlug' => 'raleigh-home-care']);
                        $guideBreadcrumbs = [
                            ['name' => 'Home', 'url' => route('landing')],
                            ['name' => 'Raleigh Care Guides', 'url' => $guideHubUrl],
                        ];

                        if ($currentGuideUrl !== $guideHubUrl) {
                            $guideBreadcrumbs[] = ['name' => (string) ($page['h1'] ?? ''), 'url' => $currentGuideUrl];
                        }
                    @endphp
                    <x-marketing.breadcrumbs :items="$guideBreadcrumbs" class="mb-5" />
                    <p class="guide-eyebrow">{{ $page['eyebrow'] ?? 'Raleigh NC' }}</p>
                    <h1 class="mt-4 text-4xl font-black leading-tight tracking-tight sm:text-5xl">
                        {{ $page['h1'] ?? '' }}
                    </h1>
                    <p class="mt-5 max-w-2xl text-lg text-[#53645D]">
                        {{ $page['intro'] ?? '' }}
                    </p>

                    <div class="mt-7 grid grid-cols-1 gap-3 sm:flex sm:flex-wrap">
                        <a class="guide-button" href="{{ route($page['primary_cta']['route'], $page['primary_cta']['params'] ?? []) }}">{{ $page['primary_cta']['label'] ?? 'Get started' }}</a>
                        @if(!empty($page['secondary_cta']['route']))
                            <a class="guide-button secondary" href="{{ route($page['secondary_cta']['route'], $page['secondary_cta']['params'] ?? []) }}">{{ $page['secondary_cta']['label'] ?? 'Learn more' }}</a>
                        @endif
                    </div>

                    @if(!empty($page['highlights']))
                        <div class="mt-7 grid gap-3 text-sm sm:grid-cols-2">
                            @foreach($page['highlights'] as $highlight)
                                <div class="rounded-xl border border-[#E4DACB] bg-white px-3 py-2 text-[#53645D]">{{ $highlight }}</div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="lg:col-span-5">
                    <div class="guide-hero-photo overflow-hidden ring-1 ring-black/10">
                        <img
                            src="{{ asset('images/marketing/homepage/human-moment.jpg') }}"
                            alt="A caregiver and an older adult sharing a joyful moment at home."
                            class="h-80 w-full object-cover"
                            width="1000" height="1250" fetchpriority="high"
                        />
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-white py-14">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-6 md:grid-cols-3">
                    @foreach(($page['sections'] ?? []) as $section)
                        <article class="guide-card">
                            <h2>{{ $section['title'] ?? '' }}</h2>
                            <p class="text-sm text-[#53645D]">{{ $section['body'] ?? '' }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="border-y border-[#E4DACB] bg-[#F3EADC] py-14">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl font-extrabold tracking-tight">Raleigh home care FAQ</h2>
                <div class="mt-6 space-y-3">
                    @foreach(($page['faqs'] ?? []) as $faq)
                        <details class="rounded-2xl border border-[#E4DACB] bg-white p-4">
                            <summary class="cursor-pointer font-semibold text-[#23483F]">{{ $faq['q'] ?? '' }}</summary>
                            <p class="mt-2 text-sm text-[#53645D]">{{ $faq['a'] ?? '' }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="bg-white py-14">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-2xl font-extrabold tracking-tight">Explore care in Raleigh</h2>
                        <p class="mt-1 text-sm text-[#53645D]">Find support that fits your family and everyday life.</p>
                    </div>
                    <a href="{{ route('seo.page', ['seoSlug' => 'raleigh-home-care']) }}" class="text-sm font-semibold text-[#23483F] hover:underline">
                        Raleigh guides hub
                    </a>
                </div>

                <div class="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    @foreach($relatedPages as $related)
                        <a href="{{ $related['path'] }}" class="rounded-2xl border border-[#E4DACB] bg-[#FFFAF2] p-4 transition hover:bg-white hover:shadow-sm">
                            <p class="text-sm font-semibold text-[#23483F]">{{ $related['title'] }}</p>
                        </a>
                    @endforeach
                    <a href="{{ route('blog.index') }}" class="rounded-2xl border border-[#E4DACB] bg-[#FFF7EA] p-4 transition hover:bg-white hover:shadow-sm">
                        <p class="text-sm font-semibold text-[#23483F]">LoLo Care guides</p>
                    </a>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
            <div class="guide-closing rounded-3xl bg-[#23483F] p-7 text-white shadow-2xl sm:p-10">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-3xl font-extrabold tracking-tight">A little help can change the whole week.</h2>
                        <p class="mt-2 text-white/90">Tell us what your family needs. We will help you take the next step.</p>
                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:flex sm:gap-3">
                        <a href="{{ route('register') }}" class="guide-button light">Find care</a>
                        <a href="{{ route('caregiver.register') }}" class="guide-button light">Become a caregiver</a>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
