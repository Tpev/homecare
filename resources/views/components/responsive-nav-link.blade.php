@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full rounded-2xl border border-[#0F3D3E]/10 bg-[#0F3D3E] px-4 py-3 text-start text-base font-semibold text-[#FAF9F7] shadow-sm transition'
            : 'block w-full rounded-2xl border border-transparent px-4 py-3 text-start text-base font-medium text-[#0F3D3E]/76 transition hover:border-[#DED6CA] hover:bg-[#F5F1EB] hover:text-[#0F3D3E]';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
