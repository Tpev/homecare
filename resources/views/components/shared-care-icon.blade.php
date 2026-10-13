@props(['name'])
<svg {{ $attributes->class(['sc-icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('calendar')<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v4m10-4v4M3 11h18m-14 4h2m6 0h2m-10 3h2"/>@break
        @case('heart')<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/>@break
        @case('people')<circle cx="9" cy="8" r="3"/><path d="M3 21v-2a6 6 0 0 1 12 0v2m1-16a3 3 0 0 1 0 6m2 4a5 5 0 0 1 3 4v2"/>@break
        @case('receipt')<path d="M5 3l3 2 4-2 4 2 3-2v18l-3-2-4 2-4-2-3 2V3Zm4 6h6m-6 4h6"/>@break
        @case('plus')<path d="M12 5v14M5 12h14"/>@break
        @case('minus')<path d="M5 12h14"/>@break
        @case('arrow')<path d="M5 12h14m-6-6 6 6-6 6"/>@break
        @case('left')<path d="m14 6-6 6 6 6"/>@break
        @case('right')<path d="m10 6 6 6-6 6"/>@break
        @case('check')<path d="m5 12 4 4L19 6"/>@break
        @case('close')<path d="m6 6 12 12M6 18 18 6"/>@break
        @case('clock')<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>@break
        @case('globe')<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c5 5 5 13 0 18-5-5-5-13 0-18Z"/>@break
        @case('list')<path d="M9 6h12M9 12h12M9 18h12M3 6h.01M3 12h.01M3 18h.01"/>@break
        @case('alert')<circle cx="12" cy="12" r="9"/><path d="M12 7v6m0 4h.01"/>@break
        @case('sun')<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1 1m12 12 1 1M5 19l1-1M18 6l1-1"/>@break
        @case('message')<path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H3l2-5a8.5 8.5 0 1 1 16-3.5Z"/><path d="M8 9h9m-9 4h6"/>@break
        @case('pin')<path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0Z"/><circle cx="12" cy="10" r="2"/>@break
    @endswitch
</svg>
