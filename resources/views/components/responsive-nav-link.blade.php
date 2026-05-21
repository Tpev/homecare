@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full rounded-2xl border border-[#23483F]/10 bg-[#23483F] px-4 py-3 text-start text-base font-semibold text-[#FFFBF4] shadow-sm transition'
            : 'block w-full rounded-2xl border border-transparent px-4 py-3 text-start text-base font-medium text-[#23483F]/76 transition hover:border-[#E3D6C5] hover:bg-[#F8F0E2] hover:text-[#23483F]';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
