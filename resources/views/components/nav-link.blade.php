@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center rounded-full border border-[#0F3D3E]/10 bg-[#0F3D3E] px-3 py-2 text-sm font-semibold text-[#FAF9F7] shadow-sm transition'
            : 'inline-flex items-center rounded-full border border-transparent px-3 py-2 text-sm font-medium text-[#0F3D3E]/70 transition hover:border-[#DED6CA] hover:bg-[#F5F1EB] hover:text-[#0F3D3E]';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
