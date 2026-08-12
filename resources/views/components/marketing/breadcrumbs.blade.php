@props(['items'])

@php
    $breadcrumbs = collect($items)
        ->filter(fn (array $item): bool => filled($item['name'] ?? null) && filled($item['url'] ?? null))
        ->values();
    $breadcrumbSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $breadcrumbs->map(fn (array $item, int $index) => [
            '@type' => 'ListItem',
            'position' => $index + 1,
            'name' => (string) $item['name'],
            'item' => (string) $item['url'],
        ])->all(),
    ];
@endphp

<nav {{ $attributes->class('text-sm text-slate-500') }} aria-label="Breadcrumb">
    <ol class="flex flex-wrap items-center gap-x-2 gap-y-1">
        @foreach ($breadcrumbs as $item)
            <li class="flex items-center gap-x-2">
                @if (! $loop->first)
                    <span aria-hidden="true">/</span>
                @endif

                @if ($loop->last)
                    <span aria-current="page">{{ $item['name'] }}</span>
                @else
                    <a href="{{ $item['url'] }}" class="transition hover:text-blue-700">{{ $item['name'] }}</a>
                @endif
            </li>
        @endforeach
    </ol>
</nav>

<script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
