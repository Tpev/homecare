@extends('layouts.marketing')
@section('title', '[PREVIEW] '.($post->seo_title ?: $post->title))
@section('meta_description', $post->meta_description ?: $post->excerpt)
@section('robots', 'noindex,nofollow,noarchive')
@section('content')
    <div class="min-h-screen bg-[#F7F3ED] px-4 py-8">
        <div class="mx-auto max-w-4xl">
            <div class="mb-6 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-950">Signed editorial preview · This working draft is not public or indexable.</div>
            <article class="overflow-hidden rounded-3xl border border-[#DED6CA] bg-white shadow-sm">
                @if($post->featuredMedia)<img src="{{ $post->featuredMedia->variantUrl('large') }}" alt="{{ $post->featuredMedia->alt_text }}" class="aspect-[16/9] w-full object-cover">@endif
                <div class="p-6 sm:p-10"><div class="flex flex-wrap gap-2">@foreach($post->categories as $category)<span class="hc-muted-chip">{{ $category->name }}</span>@endforeach</div><h1 class="mt-4 font-display text-4xl font-semibold text-[#17313F]">{{ $post->title }}</h1><p class="mt-3 text-sm text-[#526474]">By {{ $post->author?->name ?? 'Unassigned' }} · reviewed by {{ $post->reviewer?->name ?? 'not reviewed' }} · {{ $post->read_minutes }} min read</p><p class="mt-5 text-lg text-[#526474]">{{ $post->excerpt }}</p><div class="public-article-content mt-8">{!! $post->body_html !!}</div>@if($post->sources->isNotEmpty())<section class="mt-10 border-t border-[#E5DED5] pt-6"><h2 class="font-display text-2xl font-semibold">Sources</h2><ol class="mt-3 space-y-3">@foreach($post->sources as $source)<li id="source-{{ $source->uuid ?? $loop->iteration }}"><a href="{{ $source->url }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-[#0F5B52] underline">{{ $source->title }}</a> @if($source->publisher)· {{ $source->publisher }}@endif</li>@endforeach</ol></section>@endif</div>
            </article>
        </div>
    </div>
@endsection
