@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full rounded-xl px-3 py-2 text-start text-base font-semibold text-cyan-800 bg-cyan-50 ring-1 ring-cyan-100 transition'
            : 'block w-full rounded-xl px-3 py-2 text-start text-base font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
