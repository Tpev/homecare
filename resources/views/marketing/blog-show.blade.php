@extends('layouts.marketing')

@section('title', $post['seo_title'])
@section('meta_description', $post['meta_description'])
@section('canonical', $post['canonical_url'])
@section('robots', $post['robots_directives'])
@section('og_type', 'article')
@section('og_title', $post['social_title'])
@section('og_description', $post['social_description'])
@section('og_image', $post['featured_media']?->variantUrl('large') ?? asset('images/marketing/lolo/lolo-app-icon-1024.png'))
@section('og_image_alt', $post['featured_media']?->alt_text ?? 'LoLo Care')
@section('article_published_time', $post['published_at']?->toIso8601String())
@section('article_modified_time', $post['modified_at']?->toIso8601String())
@section('feed_url', route('blog.feed'))

@section('structured_data')
    @php
        $article = [
            '@context' => 'https://schema.org',
            '@type' => $post['schema_type'],
            'headline' => $post['title'],
            'description' => $post['meta_description'],
            'image' => $post['featured_media'] ? [
                $post['featured_media']->variantUrl('large'),
                $post['featured_media']->variantUrl('medium'),
                $post['featured_media']->variantUrl('small'),
            ] : [],
            'author' => $post['author'] ? [
                '@type' => $post['author']->schema_type,
                'name' => $post['author']->name,
                'url' => route('blog.author', $post['author']),
                'sameAs' => $post['author']->same_as ?: [],
            ] : ['@type' => 'Organization', 'name' => 'LoLo Care', 'url' => route('landing')],
            'reviewedBy' => $post['reviewer'] ? [
                '@type' => $post['reviewer']->schema_type,
                'name' => $post['reviewer']->name,
                'url' => route('blog.author', $post['reviewer']),
            ] : null,
            'publisher' => [
                '@type' => 'Organization', 'name' => 'LoLo Care', 'url' => route('landing'),
                'logo' => ['@type' => 'ImageObject', 'url' => asset('images/marketing/lolo/lolo-app-icon-1024.png')],
            ],
            'datePublished' => $post['published_at']?->toIso8601String(),
            'dateModified' => $post['modified_at']?->toIso8601String(),
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $post['canonical_url']],
            'articleSection' => $post['categories']->pluck('name')->all(),
            'keywords' => $post['tags']->pluck('name')->all(),
            'wordCount' => $post['word_count'],
            'inLanguage' => 'en-US',
        ];
        $schemas = [array_filter($article, fn($value) => $value !== null && $value !== [])];
        if ($post['faqs'] !== []) {
            $schemas[] = [
                '@context' => 'https://schema.org', '@type' => 'FAQPage',
                'mainEntity' => collect($post['faqs'])->map(fn($faq) => [
                    '@type' => 'Question', 'name' => $faq['question'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['answer']],
                ])->all(),
            ];
        }
    @endphp
    @foreach($schemas as $schema)<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>@endforeach
@endsection

@section('content')
    <div class="min-h-screen bg-[#F7F3ED] text-[#17313F]" data-blog-article data-blog-post-id="{{ $post['id'] }}" data-event-url="{{ route('blog.events', $post['id']) }}">
        <header class="border-b border-[#DED6CA] bg-white/95"><div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8"><a href="{{ route('landing') }}" class="flex items-center gap-3"><img src="{{ asset('images/marketing/lolo/lolo-app-icon.svg') }}" alt="" class="h-10 w-10 rounded-xl"><div><div class="text-lg font-extrabold">LoLo Care</div><div class="text-xs text-[#6A7784]">Raleigh, North Carolina</div></div></a><nav class="flex items-center gap-3 text-sm font-semibold"><a href="{{ route('blog.index') }}" class="text-[#526474]">Resources</a><a href="{{ route('register') }}" data-blog-event="cta_click" data-placement="header" class="rounded-xl bg-[#0F5B52] px-4 py-2.5 text-white">Find care</a></nav></div></header>

        <main>
            <article>
                <div class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8"><x-marketing.breadcrumbs :items="[['name'=>'Home','url'=>route('landing')],['name'=>'Resources','url'=>route('blog.index')],['name'=>$post['title'],'url'=>$post['url']]]" /></div>
                <header class="mx-auto max-w-5xl px-4 py-10 text-center sm:px-6 lg:py-14">
                    <div class="flex flex-wrap justify-center gap-2">@foreach($post['categories'] as $category)<a href="{{ route('blog.category',$category) }}" class="hc-muted-chip">{{ $category->name }}</a>@endforeach</div>
                    <h1 class="mt-5 font-display text-4xl font-semibold leading-[1.08] sm:text-6xl">{{ $post['title'] }}</h1>
                    <p class="mx-auto mt-5 max-w-3xl text-lg leading-8 text-[#526474]">{{ $post['excerpt'] }}</p>
                    <div class="mt-7 flex flex-wrap items-center justify-center gap-x-3 gap-y-2 text-sm text-[#526474]">
                        @if($post['author'])<span>By <a href="{{ route('blog.author',$post['author']) }}" rel="author" class="font-semibold text-[#0F5B52] underline">{{ $post['author']->name }}</a></span>@endif
                        <span>Published <time datetime="{{ $post['published_at']?->toIso8601String() }}">{{ $post['published_at']?->format('F j, Y') }}</time></span>
                        @if($post['modified_at'] && !$post['modified_at']->isSameDay($post['published_at']))<span>Updated <time datetime="{{ $post['modified_at']->toIso8601String() }}">{{ $post['modified_at']->format('F j, Y') }}</time></span>@endif
                        <span>{{ $post['read_minutes'] }} min read</span>
                    </div>
                    @if($post['reviewer'])<p class="mt-3 text-xs text-[#6A7784]">Editorially reviewed by <a href="{{ route('blog.author',$post['reviewer']) }}" class="font-semibold underline">{{ $post['reviewer']->name }}</a>@if($post['reviewer']->credentials) · {{ $post['reviewer']->credentials }}@endif</p>@endif
                </header>

                @if($post['featured_media'])<figure class="mx-auto max-w-6xl px-4 sm:px-6"><img src="{{ $post['featured_media']->variantUrl('large') }}" srcset="{{ $post['featured_media']->variantUrl('small') }} 480w, {{ $post['featured_media']->variantUrl('medium') }} 960w, {{ $post['featured_media']->variantUrl('large') }} 1600w" sizes="(min-width:1200px) 1152px, 100vw" alt="{{ $post['featured_media']->alt_text }}" width="{{ $post['featured_media']->width }}" height="{{ $post['featured_media']->height }}" fetchpriority="high" decoding="async" class="aspect-[16/9] w-full rounded-3xl object-cover shadow-sm">@if($post['featured_media']->caption)<figcaption class="mt-2 text-center text-xs text-[#6A7784]">{{ $post['featured_media']->caption }} @if($post['featured_media']->credit)· {{ $post['featured_media']->credit }}@endif</figcaption>@endif</figure>@endif

                <div class="mx-auto grid max-w-6xl gap-10 px-4 py-12 sm:px-6 lg:grid-cols-[220px_minmax(0,760px)] lg:py-16">
                    <aside class="lg:sticky lg:top-24 lg:self-start">
                        @if(count($post['table_of_contents']) >= 2)<nav aria-label="On this page" class="rounded-2xl border border-[#DED6CA] bg-white p-4"><h2 class="text-sm font-bold uppercase tracking-[0.12em]">On this page</h2><ol class="mt-3 space-y-2 text-sm">@foreach($post['table_of_contents'] as $heading)<li class="{{ $heading['level'] > 2 ? 'pl-3' : '' }}"><a href="#{{ $heading['id'] }}" class="text-[#526474] hover:text-[#0F5B52]">{{ $heading['text'] }}</a></li>@endforeach</ol></nav>@endif
                    </aside>
                    <div>
                        <div class="public-article-content">{!! $post['body_html'] !!}</div>

                        @if($relatedPosts->isNotEmpty())<nav class="mt-12 rounded-2xl border border-[#DED6CA] bg-white p-6" aria-labelledby="related-guidance-heading"><p class="hc-brand-kicker">Continue planning</p><h2 id="related-guidance-heading" class="mt-2 font-display text-2xl font-semibold">Related local guidance</h2><ul class="mt-4 space-y-3">@foreach($relatedPosts as $related)<li><a href="{{ $related['url'] }}" data-blog-event="related_click" data-placement="article_guidance" class="font-semibold text-[#0F5B52] underline decoration-[#9AC2B8] underline-offset-4 hover:decoration-[#0F5B52]">{{ $related['title'] }}</a><p class="mt-1 text-sm leading-6 text-[#526474]">{{ \Illuminate\Support\Str::limit($related['excerpt'],140) }}</p></li>@endforeach</ul></nav>@endif

                        @if($post['research_methodology'])<section class="mt-10 rounded-2xl border border-[#BFD7D0] bg-[#EFF8F5] p-5"><h2 class="font-display text-2xl font-semibold">Methodology</h2><p class="mt-2 leading-7 text-[#394B57]">{{ $post['research_methodology'] }}</p></section>@endif

                        @if($post['sources']->isNotEmpty())<section class="mt-12 border-t border-[#DED6CA] pt-8" aria-labelledby="sources-heading"><h2 id="sources-heading" class="font-display text-3xl font-semibold">Sources</h2><p class="mt-2 text-sm text-[#526474]">Sources were accessed and reviewed during the editorial process. External links open in a new tab.</p><ol class="mt-5 space-y-4">@foreach($post['sources'] as $source)<li id="source-{{ $source['uuid'] ?? $loop->iteration }}" class="rounded-xl border border-[#E5DED5] bg-white p-4"><a href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer" data-blog-event="source_click" data-placement="sources" class="font-semibold text-[#0F5B52] underline">{{ $source['title'] }}</a><p class="mt-1 text-xs text-[#6A7784]">{{ $source['publisher'] ?? 'Source' }} @if($source['published_on'] ?? null)· published {{ \Illuminate\Support\Carbon::parse($source['published_on'])->format('M j, Y') }}@endif @if($source['accessed_on'] ?? null)· accessed {{ \Illuminate\Support\Carbon::parse($source['accessed_on'])->format('M j, Y') }}@endif</p>@if($source['notes'] ?? null)<p class="mt-2 text-sm text-[#526474]">{{ $source['notes'] }}</p>@endif</li>@endforeach</ol></section>@endif

                        @if($post['author'])<section class="mt-12 rounded-2xl border border-[#DED6CA] bg-white p-6"><p class="hc-brand-kicker">About the author</p><div class="mt-3 flex gap-4">@if($post['author']->avatar)<img src="{{ $post['author']->avatar->variantUrl('small') }}" alt="" class="h-16 w-16 rounded-full object-cover">@endif<div><h2 class="font-display text-2xl font-semibold"><a href="{{ route('blog.author',$post['author']) }}">{{ $post['author']->name }}</a></h2><p class="text-sm font-semibold text-[#0F5B52]">{{ $post['author']->headline }}</p><p class="mt-2 text-sm leading-6 text-[#526474]">{{ $post['author']->bio }}</p></div></div></section>@endif

                        <section class="mt-10 rounded-3xl bg-[#173F39] p-7 text-white sm:p-9"><p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#F2B8A8]">Put the guidance into action</p><h2 class="mt-2 font-display text-3xl font-semibold text-white">Find flexible support for someone you love.</h2><p class="mt-3 max-w-2xl text-white/80">Tell LoLo what your family needs, review independent caregivers, and coordinate care directly.</p><div class="mt-6 flex flex-wrap gap-3"><a href="{{ route('register') }}" data-blog-event="cta_click" data-placement="article_end" class="rounded-xl bg-white px-5 py-3 font-semibold text-[#173F39]">Post a care request</a><a href="{{ route('landing.caregiver') }}" data-blog-event="cta_click" data-placement="article_end_caregiver" class="rounded-xl border border-white/40 px-5 py-3 font-semibold text-white">Caregiver opportunities</a></div></section>
                    </div>
                </div>
            </article>

        </main>
    </div>
@endsection
