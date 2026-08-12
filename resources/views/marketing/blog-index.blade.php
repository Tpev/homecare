@extends('layouts.marketing')

@section('title', 'Raleigh Home Care Resources | LoLo Care Guides')
@section('meta_description', 'Evidence-backed Raleigh and Triangle care guides for families and independent caregivers, reviewed and maintained by the LoLo Care editorial team.')
@section('canonical', route('blog.index'))
@section('feed_url', route('blog.feed'))

@section('structured_data')
    @php
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => 'LoLo Care Resource Center',
            'description' => 'Reviewed local care guidance for Raleigh and the Triangle.',
            'url' => route('blog.index'),
            'isPartOf' => ['@type' => 'WebSite', 'name' => 'LoLo Care', 'url' => route('landing')],
            'mainEntity' => [
                '@type' => 'ItemList',
                'itemListElement' => $presentedPosts->values()->map(fn ($item, $index) => [
                    '@type' => 'ListItem', 'position' => $index + 1, 'url' => $item['url'], 'name' => $item['title'],
                ])->all(),
            ],
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endsection

@section('content')
    <div class="min-h-screen bg-[#F7F3ED] text-[#17313F]">
        <header class="border-b border-[#DED6CA] bg-white/95">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                <a href="{{ route('landing') }}" class="flex items-center gap-3"><img src="{{ asset('images/marketing/lolo/lolo-app-icon.svg') }}" alt="" class="h-10 w-10 rounded-xl"><div><div class="text-lg font-extrabold">LoLo Care</div><div class="text-xs text-[#6A7784]">Raleigh, North Carolina</div></div></a>
                <nav class="flex items-center gap-3 text-sm font-semibold"><a href="{{ route('landing.family') }}" class="hidden text-[#526474] sm:inline">For families</a><a href="{{ route('landing.caregiver') }}" class="hidden text-[#526474] sm:inline">For caregivers</a><a href="{{ route('register') }}" class="rounded-xl bg-[#0F5B52] px-4 py-2.5 text-white">Find care</a></nav>
            </div>
        </header>

        <section class="border-b border-[#DED6CA] bg-gradient-to-br from-white via-[#FFF9F3] to-[#EDF7F4]">
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8 lg:py-20">
                <x-marketing.breadcrumbs :items="[['name' => 'Home', 'url' => route('landing')], ['name' => 'Resources', 'url' => route('blog.index')]]" />
                <div class="mt-8 max-w-4xl"><p class="hc-brand-kicker">Reviewed local guidance</p><h1 class="mt-3 font-display text-4xl font-semibold leading-tight sm:text-6xl">Clear answers for arranging care at home.</h1><p class="mt-5 max-w-3xl text-lg leading-8 text-[#526474]">Practical guides grounded in visible sources, real authorship, local context, and ongoing editorial review.</p></div>
                @if($categories->isNotEmpty())<nav class="mt-8 flex flex-wrap gap-2" aria-label="Resource categories">@foreach($categories as $category)<a href="{{ route('blog.category', $category) }}" class="rounded-full border border-[#CFC6BA] bg-white px-4 py-2 text-sm font-semibold text-[#0F5B52]">{{ $category->name }} <span class="text-[#7A857E]">{{ $category->posts_count }}</span></a>@endforeach</nav>@endif
            </div>
        </section>

        <main class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            @if($featured)
                <section class="mb-12"><a href="{{ $featured['url'] }}" class="group grid overflow-hidden rounded-3xl border border-[#DED6CA] bg-white shadow-sm lg:grid-cols-2">@if($featured['featured_media'])<img src="{{ $featured['featured_media']->variantUrl('large') }}" srcset="{{ $featured['featured_media']->variantUrl('small') }} 480w, {{ $featured['featured_media']->variantUrl('medium') }} 960w, {{ $featured['featured_media']->variantUrl('large') }} 1600w" sizes="(min-width:1024px) 50vw, 100vw" alt="{{ $featured['featured_media']->alt_text }}" width="{{ $featured['featured_media']->width }}" height="{{ $featured['featured_media']->height }}" fetchpriority="high" class="h-full min-h-72 w-full object-cover">@endif<div class="flex flex-col justify-center p-7 sm:p-10"><p class="hc-brand-kicker">Latest reviewed guide</p><h2 class="mt-3 font-display text-3xl font-semibold leading-tight group-hover:text-[#0F5B52]">{{ $featured['title'] }}</h2><p class="mt-4 leading-7 text-[#526474]">{{ $featured['excerpt'] }}</p><p class="mt-6 text-sm text-[#6A7784]">{{ $featured['author']?->name }} · {{ $featured['published_at']?->format('M j, Y') }} · {{ $featured['read_minutes'] }} min read</p></div></a></section>
            @endif

            <section aria-labelledby="all-guides-heading"><div class="mb-6 flex items-end justify-between"><div><p class="hc-brand-kicker">Resource library</p><h2 id="all-guides-heading" class="mt-2 font-display text-3xl font-semibold">All reviewed guides</h2></div><a href="{{ route('blog.feed') }}" class="text-sm font-semibold text-[#0F5B52] underline">Atom feed</a></div>
                @if($presentedPosts->isNotEmpty())
                    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                        @foreach($presentedPosts as $item)
                            <article class="flex flex-col overflow-hidden rounded-2xl border border-[#DED6CA] bg-white shadow-sm">
                                <a href="{{ $item['url'] }}">@if($item['featured_media'])<img src="{{ $item['featured_media']->variantUrl('medium') }}" alt="{{ $item['featured_media']->alt_text }}" width="{{ $item['featured_media']->width }}" height="{{ $item['featured_media']->height }}" loading="lazy" decoding="async" class="aspect-[16/9] w-full object-cover">@endif</a>
                                <div class="flex flex-1 flex-col p-5"><div class="flex flex-wrap gap-1.5">@foreach($item['categories'] as $category)<a href="{{ route('blog.category', $category) }}" class="hc-muted-chip">{{ $category->name }}</a>@endforeach</div><h3 class="mt-3 font-display text-2xl font-semibold leading-tight"><a href="{{ $item['url'] }}" class="hover:text-[#0F5B52]">{{ $item['title'] }}</a></h3><p class="mt-3 flex-1 text-sm leading-6 text-[#526474]">{{ $item['excerpt'] }}</p><p class="mt-5 text-xs text-[#6A7784]">{{ $item['author']?->name }} · {{ $item['published_at']?->format('M j, Y') }} · {{ $item['read_minutes'] }} min</p></div>
                            </article>
                        @endforeach
                    </div>
                    <div class="mt-10">{{ $posts->links() }}</div>
                @else
                    <div class="rounded-2xl border border-dashed border-[#CFC6BA] bg-white p-10 text-center"><h3 class="font-display text-2xl font-semibold">Editorial review is underway</h3><p class="mt-2 text-[#526474]">Legacy articles have been removed from public discovery until their claims, sources, authorship, and media are reviewed.</p></div>
                @endif
            </section>
        </main>
    </div>
@endsection
