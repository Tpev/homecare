@extends('layouts.marketing')

@section('title', $page['meta_title'] ?? ($page['h1'] ?? 'HomeCare Raleigh'))
@section('meta_description', $page['meta_description'] ?? 'HomeCare Raleigh non-medical care marketplace.')
@section('canonical', url($page['path'] ?? request()->path()))
@section('og_image', $page['hero_image'] ?? 'https://images.unsplash.com/photo-1505751172876-fa1923c5c528?auto=format&fit=crop&w=1600&q=80')

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
            'name' => (string) ($page['meta_title'] ?? $page['h1'] ?? 'HomeCare'),
            'description' => (string) ($page['meta_description'] ?? ''),
            'url' => url($page['path'] ?? request()->path()),
            'about' => 'Non-medical home care in Raleigh, North Carolina',
        ];

        $faqSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $faqEntities,
        ];

        $breadcrumbSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'HomeCare',
                    'item' => route('landing'),
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => 'Raleigh Care Guides',
                    'item' => route('seo.page', ['seoSlug' => 'raleigh-home-care']),
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 3,
                    'name' => (string) ($page['h1'] ?? ''),
                    'item' => url($page['path'] ?? request()->path()),
                ],
            ],
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode($webPageSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @if($faqEntities !== [])
        <script type="application/ld+json">{!! json_encode($faqSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif
@endsection

@section('content')
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <header class="sticky top-0 z-50 border-b border-slate-200 bg-white/90 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
                <a href="{{ route('landing') }}" class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-2xl bg-gradient-to-br from-cyan-700 via-blue-700 to-emerald-600 shadow-sm ring-1 ring-black/5"></div>
                    <div class="leading-tight">
                        <div class="text-lg font-extrabold tracking-tight">HomeCare</div>
                        <div class="text-xs text-slate-500">Raleigh, NC</div>
                    </div>
                </a>

                <nav class="hidden items-center gap-5 text-sm font-medium text-slate-600 lg:flex">
                    <a href="{{ route('landing.family') }}" class="transition hover:text-slate-900">Families</a>
                    <a href="{{ route('landing.caregiver') }}" class="transition hover:text-slate-900">Caregivers</a>
                    <a href="{{ route('landing.agency') }}" class="transition hover:text-slate-900">Agencies</a>
                    <a href="{{ route('seo.page', ['seoSlug' => 'raleigh-home-care']) }}" class="transition hover:text-slate-900">Raleigh Guides</a>
                    <a href="{{ route('blog.index') }}" class="transition hover:text-slate-900">Blog</a>
                </nav>

                <div class="flex items-center gap-2">
                    <a href="{{ route('login') }}">
                        <x-button color="slate" sm light>Sign in</x-button>
                    </a>
                    <a href="{{ route('register') }}">
                        <x-button color="blue" sm>Get help now</x-button>
                    </a>
                </div>
            </div>
        </header>

        <section class="relative overflow-hidden border-b border-slate-200 bg-gradient-to-b from-white to-slate-100">
            <div class="absolute -top-24 -right-16 h-72 w-72 rounded-full bg-cyan-200/40 blur-3xl"></div>
            <div class="absolute -bottom-24 -left-16 h-72 w-72 rounded-full bg-emerald-200/45 blur-3xl"></div>

            <div class="relative mx-auto grid max-w-7xl gap-10 px-4 pb-14 pt-12 sm:px-6 lg:grid-cols-12 lg:px-8 lg:pb-16 lg:pt-16">
                <div class="lg:col-span-7">
                    <x-badge color="blue" :text="$page['eyebrow'] ?? 'Raleigh NC'" round light />
                    <h1 class="mt-4 text-4xl font-black leading-tight tracking-tight sm:text-5xl">
                        {{ $page['h1'] ?? '' }}
                    </h1>
                    <p class="mt-5 max-w-2xl text-lg text-slate-600">
                        {{ $page['intro'] ?? '' }}
                    </p>

                    <div class="mt-7 flex flex-wrap gap-3">
                        <a href="{{ route($page['primary_cta']['route'], $page['primary_cta']['params'] ?? []) }}">
                            <x-button color="blue" lg>{{ $page['primary_cta']['label'] ?? 'Get started' }}</x-button>
                        </a>
                        @if(!empty($page['secondary_cta']['route']))
                            <a href="{{ route($page['secondary_cta']['route'], $page['secondary_cta']['params'] ?? []) }}">
                                <x-button color="emerald" lg outline>{{ $page['secondary_cta']['label'] ?? 'Learn more' }}</x-button>
                            </a>
                        @endif
                    </div>

                    @if(!empty($page['highlights']))
                        <div class="mt-7 grid gap-3 text-sm sm:grid-cols-2">
                            @foreach($page['highlights'] as $highlight)
                                <div class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-700">{{ $highlight }}</div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="lg:col-span-5">
                    <div class="overflow-hidden rounded-3xl ring-1 ring-black/10 shadow-xl">
                        <img
                            src="{{ $page['hero_image'] ?? 'https://images.unsplash.com/photo-1516302752625-fcc3c50ae61f?auto=format&fit=crop&w=1600&q=80' }}"
                            alt="{{ $page['h1'] ?? 'Raleigh home care' }}"
                            class="h-80 w-full object-cover"
                            loading="lazy"
                        />
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-white py-14">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-6 md:grid-cols-3">
                    @foreach(($page['sections'] ?? []) as $section)
                        <x-card class="ring-1 ring-black/5 shadow-sm">
                            <x-slot:header>
                                <div class="font-bold">{{ $section['title'] ?? '' }}</div>
                            </x-slot:header>
                            <p class="text-sm text-slate-600">{{ $section['body'] ?? '' }}</p>
                        </x-card>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="border-y border-slate-200 bg-slate-100 py-14">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl font-extrabold tracking-tight">Raleigh home care FAQ</h2>
                <div class="mt-6 space-y-3">
                    @foreach(($page['faqs'] ?? []) as $faq)
                        <details class="rounded-2xl border border-slate-200 bg-white p-4">
                            <summary class="cursor-pointer font-semibold text-slate-900">{{ $faq['q'] ?? '' }}</summary>
                            <p class="mt-2 text-sm text-slate-600">{{ $faq['a'] ?? '' }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="bg-white py-14">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <h2 class="text-2xl font-extrabold tracking-tight">Related Raleigh care pages</h2>
                        <p class="mt-1 text-sm text-slate-600">Built to help families and caregivers find the right path quickly.</p>
                    </div>
                    <a href="{{ route('seo.page', ['seoSlug' => 'raleigh-home-care']) }}" class="text-sm font-semibold text-blue-700 hover:underline">
                        Raleigh guides hub
                    </a>
                </div>

                <div class="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    @foreach($relatedPages as $related)
                        <a href="{{ $related['path'] }}" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 transition hover:bg-white hover:shadow-sm">
                            <p class="text-sm font-semibold text-slate-900">{{ $related['title'] }}</p>
                        </a>
                    @endforeach
                    <a href="{{ route('blog.index') }}" class="rounded-2xl border border-slate-200 bg-blue-50 p-4 transition hover:bg-white hover:shadow-sm">
                        <p class="text-sm font-semibold text-blue-900">Raleigh HomeCare Blog</p>
                    </a>
                </div>
            </div>
        </section>

        <section class="border-t border-slate-200 bg-slate-100 py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <h2 class="text-xl font-extrabold tracking-tight">All Raleigh SEO pages</h2>
                <div class="mt-4 flex flex-wrap gap-2 text-sm">
                    @foreach($allPages as $entry)
                        <a href="{{ $entry['path'] }}" class="rounded-full border border-slate-300 bg-white px-3 py-1 text-slate-700 hover:border-blue-300 hover:text-blue-700">
                            {{ $entry['title'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
            <div class="rounded-3xl bg-gradient-to-r from-cyan-700 via-blue-700 to-emerald-600 p-7 text-white shadow-2xl sm:p-10">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h3 class="text-3xl font-extrabold tracking-tight">Need help in Raleigh today?</h3>
                        <p class="mt-2 text-white/90">Post your request now and start coordinating care with better clarity and speed.</p>
                    </div>
                    <div class="flex gap-3">
                        <a href="{{ route('register') }}">
                            <x-button color="white" lg>Family sign up</x-button>
                        </a>
                        <a href="{{ route('caregiver.register') }}">
                            <x-button color="white" lg light>Caregiver sign up</x-button>
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
