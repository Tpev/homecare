@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center rounded-full border border-[#23483F]/10 bg-[#23483F] px-3 py-2 text-sm font-semibold text-[#FFFBF4] shadow-sm transition'
            : 'inline-flex items-center rounded-full border border-transparent px-3 py-2 text-sm font-medium text-[#23483F]/74 transition hover:border-[#E3D6C5] hover:bg-[#F8F0E2] hover:text-[#23483F]';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
