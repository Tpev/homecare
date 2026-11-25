@props(['active' => false])
@if($active)
    <span {{ $attributes->class(['inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-900']) }}>Care Assistance</span>
@endif
