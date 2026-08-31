@php
    $isOpenRequest = $event['kind'] === 'open_request';
    $tone = $isOpenRequest
        ? 'border-amber-300 bg-amber-50 text-amber-950 hover:border-amber-400'
        : match ($event['status']) {
            \App\Models\CareBooking::STATUS_IN_PROGRESS => 'border-emerald-300 bg-emerald-50 text-emerald-950 hover:border-emerald-400',
            \App\Models\CareBooking::STATUS_PAUSED => 'border-orange-300 bg-orange-50 text-orange-950 hover:border-orange-400',
            \App\Models\CareBooking::STATUS_COMPLETED,
            \App\Models\CareBooking::STATUS_REVIEWED => 'border-indigo-200 bg-indigo-50 text-indigo-950 hover:border-indigo-300',
            \App\Models\CareBooking::STATUS_DISPUTED => 'border-rose-300 bg-rose-50 text-rose-950 hover:border-rose-400',
            \App\Models\CareBooking::STATUS_CANCELLED => 'border-slate-300 bg-slate-100 text-slate-700 hover:border-slate-400',
            default => 'border-sky-300 bg-sky-50 text-sky-950 hover:border-sky-400',
        };
@endphp

<a
    href="{{ $event['url'] }}"
    wire:navigate
    class="block rounded-xl border p-2.5 shadow-sm transition {{ $tone }}"
    aria-label="Open {{ strtolower($event['type_label']) }} for {{ $event['customer'] }}"
>
    <div class="flex items-start justify-between gap-2">
        <p class="text-[11px] font-black leading-4">
            {{ $event['start_at']->format('g:i A') }}@if($event['end_at'])–{{ $event['end_at']->format('g:i A') }}@endif
        </p>
        <span class="shrink-0 rounded-full bg-white/80 px-1.5 py-0.5 text-[9px] font-black uppercase tracking-wide">
            {{ $isOpenRequest ? 'Request' : 'Shift' }}
        </span>
    </div>

    <p class="mt-1 truncate text-xs font-extrabold" title="{{ $event['customer'] }}">{{ $event['customer'] }}</p>
    <p class="truncate text-[10px] font-semibold opacity-80" title="{{ $event['title'] }}">{{ $event['title'] }}</p>

    <dl class="mt-2 space-y-1 text-[10px] leading-4">
        <div>
            <dt class="inline font-bold uppercase tracking-wide opacity-60">Customer</dt>
            <dd class="inline font-semibold"> · {{ $event['customer'] }}</dd>
        </div>
        <div>
            <dt class="inline font-bold uppercase tracking-wide opacity-60">Family</dt>
            <dd class="inline font-semibold"> · {{ $event['family'] }}@if($event['family_account_id']) (#{{ $event['family_account_id'] }})@endif</dd>
        </div>
        <div>
            <dt class="inline font-bold uppercase tracking-wide opacity-60">Caregiver</dt>
            <dd class="inline font-semibold {{ $isOpenRequest ? 'text-amber-800' : '' }}"> · {{ $event['caregiver'] ?: 'Unassigned' }}</dd>
        </div>
    </dl>

    <div class="mt-2 flex flex-wrap items-center gap-1">
        <span class="rounded-full bg-white/80 px-1.5 py-0.5 text-[9px] font-bold">{{ $event['status_label'] }}</span>
        <span class="rounded-full bg-white/80 px-1.5 py-0.5 text-[9px] font-bold">{{ $event['type_label'] }}</span>
        @if($event['is_private'])
            <span class="rounded-full bg-indigo-700 px-1.5 py-0.5 text-[9px] font-bold text-white">Private</span>
        @endif
    </div>

    @if($isOpenRequest)
        <p class="mt-1.5 text-[9px] font-semibold opacity-75">{{ $event['applications'] }} applicants · {{ $event['invitations'] }} invites</p>
    @endif
</a>
