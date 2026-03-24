@extends('layouts.marketing')

@section('title', ($page['title'] ?? 'Legal Document').' | HomeCare')
@section('meta_description', $page['description'] ?? 'Legal document for HomeCare platform users.')
@section('canonical', route('legal.show', ['slug' => $slug]))
@section('robots', 'noindex,follow')

@section('content')
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <header class="sticky top-0 z-50 border-b border-slate-200/80 bg-white/90 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
                <a href="{{ route('landing.caregiver') }}" class="flex items-center gap-3">
                    <x-application-logo class="h-9 w-9" />
                    <div>
                        <div class="text-base font-bold tracking-tight">HomeCare</div>
                        <div class="text-xs text-slate-500">Legal</div>
                    </div>
                </a>
                <a href="{{ route('legal.index') }}" class="text-sm font-semibold text-cyan-700 hover:underline">All legal documents</a>
            </div>
        </header>

        <main class="mx-auto grid max-w-6xl gap-8 px-4 py-6 sm:px-6 lg:grid-cols-[minmax(0,1fr)_18rem] lg:px-8 lg:py-8">
            <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                @if ($documentHeader)
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ $documentHeader }}</p>
                @endif

                <h1 class="mt-2 text-2xl font-black tracking-tight sm:text-3xl">{{ $documentTitle }}</h1>

                @if ($effectiveLine)
                    <p class="mt-2 text-sm text-slate-600">{{ $effectiveLine }}</p>
                @endif

                <div class="mt-6 space-y-4 text-slate-700">
                    @foreach ($lines as $line)
                        @php
                            $isNumberedHeading = preg_match('/^\d+(?:\.\d+)*\.?\s+/', $line) === 1;
                            $headingDepth = 0;

                            if ($isNumberedHeading) {
                                preg_match('/^(\d+(?:\.\d+)*)\.?\s+/', $line, $matches);
                                $headingDepth = isset($matches[1]) ? substr_count($matches[1], '.') : 0;
                            }

                            $isShortHeading = ! $isNumberedHeading && str_ends_with($line, ':') && mb_strlen($line) <= 120;
                        @endphp

                        @if ($isNumberedHeading)
                            @if ($headingDepth <= 0)
                                <h2 class="pt-2 text-xl font-bold text-slate-900">{{ $line }}</h2>
                            @elseif ($headingDepth === 1)
                                <h3 class="pt-1 text-lg font-bold text-slate-900">{{ $line }}</h3>
                            @else
                                <h4 class="pt-1 text-base font-semibold text-slate-900">{{ $line }}</h4>
                            @endif
                        @elseif ($isShortHeading)
                            <h3 class="pt-1 text-base font-semibold text-slate-900">{{ $line }}</h3>
                        @else
                            <p class="text-sm leading-6">{{ $line }}</p>
                        @endif
                    @endforeach
                </div>
            </article>

            <aside class="order-first h-fit rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:order-none lg:sticky lg:top-20">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">More legal docs</p>
                <div class="mt-3 space-y-1">
                    @foreach ($allPages as $item)
                        <a
                            href="{{ $item['url'] }}"
                            class="block rounded-lg px-3 py-2 text-sm transition {{ $item['slug'] === $slug ? 'bg-cyan-50 font-semibold text-cyan-800' : 'text-slate-700 hover:bg-slate-50' }}"
                        >
                            {{ $item['title'] }}
                        </a>
                    @endforeach
                </div>
            </aside>
        </main>
    </div>
@endsection
