@props([
    'summary' => ['tags' => [], 'hidden_count' => 0, 'total' => 0],
    'showLabel' => true,
    'compact' => false,
])

@php
    $tags = collect($summary['tags'] ?? []);
    $hiddenCount = (int) ($summary['hidden_count'] ?? 0);
    $padding = $compact ? 'px-2 py-1 text-[11px]' : 'px-2.5 py-1 text-xs';
@endphp

@if ($tags->isNotEmpty())
    <div {{ $attributes->class(['space-y-1.5']) }}>
        @if ($showLabel)
            <p class="text-xs font-semibold text-[#526474]">Certifications &amp; training</p>
        @endif
        <div class="flex flex-wrap gap-1.5" aria-label="Caregiver certifications and training">
            @foreach ($tags as $tag)
                <span
                    class="inline-flex rounded-full border font-medium {{ $padding }} {{ $tag['verified'] ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-[#D8D0C5] bg-[#F7F2EA] text-[#4B5B6B]' }}"
                    aria-label="{{ $tag['label'] }}, {{ $tag['status_label'] }}"
                >
                    {{ $tag['label'] }} <span aria-hidden="true">&nbsp;·&nbsp;</span>{{ $tag['status_label'] }}
                </span>
            @endforeach
            @if ($hiddenCount > 0)
                <span class="inline-flex rounded-full border border-[#D8D0C5] bg-white font-medium text-[#607080] {{ $padding }}" aria-label="{{ $hiddenCount }} more certifications or training">
                    +{{ $hiddenCount }} more
                </span>
            @endif
        </div>
    </div>
@endif
