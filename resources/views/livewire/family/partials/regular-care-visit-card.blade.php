@php
    $visitStart = $visit->scheduled_start_at?->copy()->setTimezone($timezone);
    $visitEnd = $visit->scheduled_end_at?->copy()->setTimezone($timezone);
    $visitActions = $this->visitAttention($visit);
    $visitTypeLabel = match ($visit->plan_visit_kind) {
        'coverage' => 'Continuous care',
        'extra', 'completed_extra' => 'Extra visit',
        default => 'Recurring visit',
    };
@endphp
<article id="care-visit-{{ $visit->id }}" class="scroll-mt-24 space-y-4 p-5 sm:p-6" wire:key="plan-{{ $activeTab }}-visit-{{ $visit->id }}">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div><p class="text-sm text-[#485E53]">{{ $visitTypeLabel }} · Visit #{{ $visit->id }}</p><h3 class="mt-1 {{ $prominent ? 'font-display text-2xl' : 'text-lg' }} font-semibold text-[#23483F]">{{ $visitStart?->format('l, F j, Y') ?: 'Date pending' }}</h3><p class="mt-1">{{ $visitStart?->format('g:i A') }}–{{ $visitEnd?->format('g:i A T') }} · {{ $visit->caregiver?->name ?: 'Caregiver pending' }}</p></div>
        <span class="inline-flex self-start rounded-full bg-[#E8F1E9] px-3 py-1.5 text-sm font-semibold text-[#23483F]">{{ $this->visitStatusLabel($visit) }}</span>
    </div>
    @if ($prominent)<div class="grid gap-4 sm:grid-cols-2"><div><p class="text-sm font-semibold text-[#485E53]">Care location</p><p class="mt-1">{{ $visit->careRequest?->address_line1 }}@if($visit->careRequest?->address_line2), {{ $visit->careRequest->address_line2 }}@endif<br>{{ $visit->careRequest?->city }}, {{ $visit->careRequest?->state }} {{ $visit->careRequest?->zip }}</p></div><div><p class="text-sm font-semibold text-[#485E53]">Payment</p><p class="mt-1">{{ $this->paymentLabel($visit) }}</p>@if($visit->started_at)<p class="mt-2 text-sm">Checked in {{ $visit->started_at->copy()->setTimezone($timezone)->format('M j · g:i A') }}</p>@endif</div></div>@else<p class="text-sm {{ $visit->payment?->requiresFamilyAction() ? 'font-semibold text-amber-950' : 'text-[#485E53]' }}">{{ $this->paymentLabel($visit) }}</p>@endif
    @if ($visit->cancellation_reason)<p class="text-sm text-[#485E53]">{{ $visit->cancellation_reason }}</p>@endif
    <div class="flex flex-wrap gap-3"><a href="{{ $this->visitUrl($visit, $visitActions[0]['tab'] ?? 'shift') }}" wire:navigate class="{{ $prominent ? 'hc-primary-button' : 'hc-secondary-button' }}">{{ $visitActions[0]['action'] ?? 'View visit details' }}</a><a href="{{ $this->visitUrl($visit, 'support') }}" wire:navigate class="hc-secondary-button">Get help with this visit</a>
        @if ($canManage && $visit->status === \App\Models\CareBooking::STATUS_SCHEDULED && ! $visit->checkInWindowHasClosed())<button type="button" wire:click="skipVisit({{ $visit->id }})" wire:confirm="{{ $visit->scheduled_start_at?->lte(now()->addHours(24)) ? 'This visit is inside the 24-hour cancellation window. Skip it anyway? Your other recurring visits continue.' : 'Skip this visit? Your other recurring visits will continue.' }}" wire:loading.attr="disabled" class="hc-secondary-button">Skip this visit</button>@endif
    </div>
</article>
