@props(['active' => 'overview'])

@php
    $items = [
        ['key' => 'overview', 'label' => 'Overview', 'href' => route('family.requests.index')],
        ['key' => 'arrangements', 'label' => 'Arrangements', 'href' => route('family.care.index')],
        ['key' => 'schedule', 'label' => 'Schedule', 'href' => route('family.care.schedule')],
        ['key' => 'history', 'label' => 'History', 'href' => route('family.care.history')],
    ];
@endphp

<nav aria-label="Care sections">
    <div class="hc-tab-rail grid w-full grid-cols-4 gap-1 sm:inline-flex sm:w-auto" role="list">
        @foreach ($items as $item)
            <a
                href="{{ $item['href'] }}"
                wire:navigate
                @class([$item['key'] === $active ? 'hc-tab-active' : 'hc-tab', 'min-h-11 min-w-0 whitespace-nowrap !px-1.5 !text-xs sm:!px-3 sm:!text-sm inline-flex items-center justify-center'])
                @if ($item['key'] === $active) aria-current="page" @endif
            >
                {{ $item['label'] }}
            </a>
        @endforeach
    </div>
</nav>
