@props([
    'options',
    'selected' => [],
    'verification' => 'any_current',
    'removeMethod' => 'removeCertificationFilter',
    'includeReportedMethod' => 'includeReportedCertifications',
])

@php
    $selectedSlugs = collect($selected)->map(fn ($value) => (string) $value)->all();
    $selectedOptions = collect($options)->filter(fn ($option) => in_array((string) $option->slug, $selectedSlugs, true));
@endphp

@if ($selectedOptions->isNotEmpty())
    <div {{ $attributes->class(['flex flex-wrap gap-2']) }} aria-label="Active certification filters">
        @foreach ($selectedOptions as $option)
            <button
                type="button"
                wire:click="{{ $removeMethod }}('{{ $option->slug }}')"
                class="inline-flex min-h-11 items-center gap-2 rounded-full border border-[#9FB9B0] bg-[#F2F8F4] px-3 py-2 text-sm font-semibold text-[#0F3D3E] focus:outline-none focus:ring-2 focus:ring-[#4F6FAF]"
                aria-label="Remove {{ $option->label }} filter"
            >
                <span>{{ $option->label }}</span><span aria-hidden="true">×</span>
            </button>
        @endforeach

        @if ($verification === 'verified_only')
            <button
                type="button"
                wire:click="{{ $includeReportedMethod }}"
                class="inline-flex min-h-11 items-center gap-2 rounded-full border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-900 focus:outline-none focus:ring-2 focus:ring-[#4F6FAF]"
                aria-label="Remove LoLo verified only filter"
            >
                <span>LoLo verified only</span><span aria-hidden="true">×</span>
            </button>
        @endif
    </div>
@endif
