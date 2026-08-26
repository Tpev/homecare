@props([
    'item',
    'compact' => false,
])

@php
    $tone = match ($item['tone'] ?? 'blue') {
        'amber' => [
            'card' => 'border-amber-300 bg-amber-50 text-amber-950',
            'eyebrow' => 'text-amber-800',
            'muted' => 'text-amber-900',
            'button' => 'bg-white text-amber-950',
        ],
        'green' => [
            'card' => 'border-emerald-200 bg-emerald-50 text-emerald-950',
            'eyebrow' => 'text-emerald-700',
            'muted' => 'text-emerald-900',
            'button' => 'bg-white text-emerald-950',
        ],
        'rose' => [
            'card' => 'border-rose-300 bg-rose-50 text-rose-950',
            'eyebrow' => 'text-rose-700',
            'muted' => 'text-rose-900',
            'button' => 'bg-white text-rose-950',
        ],
        default => [
            'card' => 'border-sky-200 bg-sky-50 text-sky-950',
            'eyebrow' => 'text-sky-700',
            'muted' => 'text-sky-900',
            'button' => 'bg-white text-sky-950',
        ],
    };
    $consequence = match ($item['type'] ?? null) {
        'payment' => 'Complete this to keep the visit financially protected and care moving forward.',
        'time_correction', 'timesheet' => 'The final family charge and caregiver earnings wait for this review.',
        'completed_extra_visit' => 'No charge or caregiver earnings are finalized until you decide.',
        'applicants' => 'A caregiver must be selected before this becomes confirmed care.',
        'request_date_passed' => 'Resolving this keeps old requests from being confused with future care.',
        'visit_change' => 'The current visit remains unchanged until you decide.',
        'live_visit' => null,
        default => null,
    };
@endphp

<a
    href="{{ $item['href'] }}"
    wire:navigate
    {{ $attributes->class([
        'group block rounded-2xl border transition hover:-translate-y-px hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-[#0F6B62] focus:ring-offset-2',
        $compact ? 'p-4' : 'p-4 sm:p-5',
        $tone['card'],
    ]) }}
>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            @if (filled($item['eyebrow'] ?? null))
                <p class="text-[11px] font-bold uppercase tracking-[0.14em] {{ $tone['eyebrow'] }}">{{ $item['eyebrow'] }}</p>
            @endif
            <p class="mt-1 font-display font-semibold {{ $compact ? 'text-lg' : 'text-xl' }}">{{ $item['title'] }}</p>
            @if (filled($item['subject'] ?? null))
                <p class="mt-1 font-semibold">{{ $item['subject'] }}</p>
            @endif
            @if (filled($item['body'] ?? null) && ! $compact)
                <p class="mt-1 text-sm leading-6 {{ $tone['muted'] }}">{{ $item['body'] }}</p>
            @endif
            @if (filled($item['meta'] ?? null))
                <p class="mt-1 text-sm font-medium {{ $tone['muted'] }}">{{ $item['meta'] }}</p>
            @endif
            @if (filled($consequence) && ! $compact)
                <p class="mt-3 hidden border-t border-current/10 pt-3 text-xs font-semibold leading-5 sm:block {{ $tone['muted'] }}">Why this matters: {{ $consequence }}</p>
            @endif
            @if (($item['caregiver'] ?? null) && ! $compact)
                <x-caregiver-identity :caregiver="$item['caregiver']" class="mt-3 hidden sm:flex" />
            @endif
        </div>
        <span class="inline-flex min-h-11 shrink-0 items-center justify-center self-stretch rounded-xl px-4 text-sm font-semibold shadow-sm transition group-hover:shadow sm:self-center {{ $tone['button'] }}">
            {{ $item['label'] }}
        </span>
    </div>
</a>
