@extends('layouts.marketing')

@section('title', $post['meta_title'])
@section('meta_description', $post['meta_description'])
@section('canonical', url($post['path']))
@section('og_image', $post['cover_image'])

@section('structured_data')
    @php
        $blogSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $post['title'],
            'description' => $post['meta_description'],
            'image' => [$post['cover_image']],
            'author' => [
                '@type' => 'Organization',
                'name' => 'HomeCare',
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'HomeCare',
            ],
            'datePublished' => $post['published_at'],
            'dateModified' => $post['published_at'],
            'mainEntityOfPage' => url($post['path']),
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode($blogSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endsection

@section('content')
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <header class="sticky top-0 z-50 border-b border-slate-200 bg-white/90 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
                <a href="{{ route('landing') }}" class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-2xl bg-gradient-to-br from-teal-600 via-cyan-700 to-blue-700 shadow-sm ring-1 ring-black/5"></div>
                    <div class="leading-tight">
                        <div class="text-lg font-extrabold tracking-tight">HomeCare</div>
                        <div class="text-xs text-slate-500">Raleigh, NC</div>
                    </div>
                </a>

                <nav class="hidden items-center gap-6 text-sm font-medium text-slate-600 lg:flex">
                    <a href="{{ route('landing.family') }}" class="transition hover:text-slate-900">Families</a>
                    <a href="{{ route('landing.caregiver') }}" class="transition hover:text-slate-900">Caregivers</a>
                    <a href="{{ route('landing.agency') }}" class="transition hover:text-slate-900">Agencies</a>
                </nav>

                <div class="flex items-center gap-2">
                    <a href="{{ route('login') }}">
                        <x-button color="slate" sm light>Sign in</x-button>
                    </a>
                    <a href="{{ route('register') }}">
                        <x-button color="blue" sm>Family sign up</x-button>
                    </a>
                    <a href="{{ route('caregiver.register') }}" class="hidden sm:block">
                        <x-button color="emerald" sm outline>Caregiver sign up</x-button>
                    </a>
                </div>
            </div>
        </header>

        <article class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
            <div class="mb-6 text-sm text-slate-500">
                <a href="{{ route('blog.index') }}" class="hover:text-blue-700">Blog</a>
                <span class="mx-1">/</span>
                <span>{{ $post['title'] }}</span>
            </div>

            <div class="grid gap-8 lg:grid-cols-12">
                <div class="lg:col-span-8">
                    <div class="rounded-3xl border border-slate-200 bg-white overflow-hidden shadow-sm">
                        <img
                            src="{{ $post['cover_image'] }}"
                            alt="{{ $post['title'] }}"
                            class="h-72 w-full object-cover sm:h-80"
                            loading="lazy"
                        />
                        <div class="p-6 sm:p-8">
                            <div class="mb-3 flex flex-wrap gap-2">
                                @foreach($post['topics'] as $topic)
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700">{{ strtoupper($topic) }}</span>
                                @endforeach
                            </div>
                            <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ $post['title'] }}</h1>
                            <p class="mt-3 text-sm text-slate-500">{{ $post['read_minutes'] }} min read</p>

                            <div class="prose prose-slate max-w-none mt-6 prose-p:leading-7">
                                @foreach($post['paragraphs'] as $paragraph)
                                    <p>{{ $paragraph }}</p>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <aside class="space-y-4 lg:col-span-4">
                    <x-card>
                        <x-slot:header>
                            <h2 class="font-semibold">Next best actions</h2>
                        </x-slot:header>
                        <div class="space-y-2 text-sm">
                            <a href="{{ route('register') }}" class="block rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 hover:border-blue-300 hover:text-blue-700">Post a care request</a>
                            <a href="{{ route('caregiver.register') }}" class="block rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 hover:border-blue-300 hover:text-blue-700">Become a caregiver</a>
                            <a href="/how-homecare-works-raleigh" class="block rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 hover:border-blue-300 hover:text-blue-700">How HomeCare works</a>
                            <a href="/trusted-caregiver-screening" class="block rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 hover:border-blue-300 hover:text-blue-700">Trust and screening</a>
                        </div>
                    </x-card>

                    <x-card>
                        <x-slot:header>
                            <h2 class="font-semibold">Related guides</h2>
                        </x-slot:header>
                        <div class="space-y-2 text-sm">
                            @foreach($post['related_posts'] as $related)
                                <a href="{{ $related['path'] }}" class="block rounded-lg border border-slate-200 bg-white px-3 py-2 hover:border-blue-300 hover:text-blue-700">
                                    {{ $related['title'] }}
                                </a>
                            @endforeach
                        </div>
                    </x-card>

                    <x-card>
                        <x-slot:header>
                            <h2 class="font-semibold">Core Raleigh pages</h2>
                        </x-slot:header>
                        <div class="space-y-2 text-sm">
                            @foreach($post['internal_links'] as $link)
                                <a href="{{ $link['path'] }}" class="block rounded-lg border border-slate-200 bg-white px-3 py-2 hover:border-blue-300 hover:text-blue-700">{{ $link['title'] }}</a>
                            @endforeach
                        </div>
                    </x-card>
                </aside>
            </div>
        </article>
    </div>
@endsection
