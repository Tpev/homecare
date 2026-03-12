@extends('layouts.marketing')

@section('title', 'Raleigh Home Care Blog | Family + Caregiver Guides | HomeCare')
@section('meta_description', 'Read local Raleigh NC home care guides for families and caregivers: costs, post-surgery support, jobs, and how to hire faster with confidence.')
@section('canonical', route('blog.index'))
@section('og_image', 'https://images.unsplash.com/photo-1469474968028-56623f02e42e?auto=format&fit=crop&w=1600&q=80')

@section('structured_data')
    @php
        $items = collect($posts)->take(20)->values()->map(function ($post, $index) {
            return [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'url' => url($post['path']),
                'name' => $post['title'],
            ];
        })->all();

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => 'HomeCare Raleigh Blog',
            'description' => 'Raleigh home care guides for families and caregivers.',
            'url' => route('blog.index'),
            'hasPart' => $items,
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endsection

@section('content')
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/90 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
                <a href="{{ route('landing') }}" class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-2xl bg-gradient-to-br from-cyan-700 via-blue-700 to-emerald-600"></div>
                    <div class="leading-tight">
                        <div class="text-lg font-extrabold tracking-tight">HomeCare</div>
                        <div class="text-xs text-slate-500">Raleigh, NC</div>
                    </div>
                </a>
                <nav class="hidden lg:flex items-center gap-5 text-sm font-medium text-slate-600">
                    <a href="{{ route('landing.family') }}" class="hover:text-slate-900">Families</a>
                    <a href="{{ route('landing.caregiver') }}" class="hover:text-slate-900">Caregivers</a>
                    <a href="{{ route('blog.index') }}" class="text-slate-900">Blog</a>
                </nav>
                <div class="flex items-center gap-2">
                    <a href="{{ route('login') }}"><x-button color="slate" sm light>Sign in</x-button></a>
                    <a href="{{ route('register') }}"><x-button color="blue" sm>Get care fast</x-button></a>
                </div>
            </div>
        </header>

        <section class="border-b border-slate-200 bg-gradient-to-b from-white to-slate-100">
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8 lg:py-16">
                <x-badge color="blue" text="Raleigh Home Care Blog" round light />
                <h1 class="mt-4 text-4xl font-black tracking-tight sm:text-5xl">Local guides to help families hire better and faster.</h1>
                <p class="mt-4 max-w-3xl text-lg text-slate-600">
                    Practical Raleigh-focused content on non-medical home care, caregiver hiring, costs, and day-to-day coordination.
                </p>
                <div class="mt-6 flex flex-wrap gap-2 text-sm text-slate-700">
                    <a href="/raleigh-home-care" class="rounded-full border border-slate-300 bg-white px-3 py-1 hover:border-blue-300 hover:text-blue-700">Raleigh home care guide</a>
                    <a href="/home-care-cost-raleigh-nc" class="rounded-full border border-slate-300 bg-white px-3 py-1 hover:border-blue-300 hover:text-blue-700">Cost guide</a>
                    <a href="/caregiver-jobs-raleigh-nc" class="rounded-full border border-slate-300 bg-white px-3 py-1 hover:border-blue-300 hover:text-blue-700">Caregiver jobs</a>
                    <a href="/how-homecare-works-raleigh" class="rounded-full border border-slate-300 bg-white px-3 py-1 hover:border-blue-300 hover:text-blue-700">How it works</a>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            @if($posts !== [])
                <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($posts as $post)
                        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <a href="{{ route('blog.show', ['blogSlug' => $post['slug']]) }}">
                                <img
                                    src="{{ $post['cover_image'] }}"
                                    alt="{{ $post['title'] }}"
                                    class="h-44 w-full object-cover"
                                    loading="lazy"
                                />
                            </a>
                            <div class="p-5">
                                <div class="mb-2 flex flex-wrap gap-1">
                                    @foreach(array_slice($post['topics'], 0, 2) as $topic)
                                        <span class="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-700">{{ strtoupper($topic) }}</span>
                                    @endforeach
                                </div>
                                <h2 class="text-xl font-bold tracking-tight">
                                    <a href="{{ route('blog.show', ['blogSlug' => $post['slug']]) }}" class="hover:text-blue-700">{{ $post['title'] }}</a>
                                </h2>
                                <p class="mt-2 text-sm text-slate-600">{{ $post['excerpt'] }}</p>
                                <p class="mt-3 text-xs text-slate-500">{{ $post['read_minutes'] }} min read</p>
                                <div class="mt-4">
                                    <a href="{{ route('blog.show', ['blogSlug' => $post['slug']]) }}" class="text-sm font-semibold text-blue-700 hover:underline">
                                        Read guide
                                    </a>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">
                    Blog content is being prepared. Check back shortly.
                </div>
            @endif
        </section>
    </div>
@endsection
