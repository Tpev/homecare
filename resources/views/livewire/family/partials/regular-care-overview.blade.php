<div class="space-y-6">
    @if ($plan->pause_starts_on && ! $isPaused && $canManage)
        <section class="rounded-2xl border border-amber-400 bg-amber-50 p-5 sm:p-6"><h2 class="hc-care-title">A pause is scheduled</h2><p class="mt-2">Your pause starts {{ $plan->pause_starts_on->format('M j, Y') }}. {{ $plan->resumes_on ? 'Care resumes '.$plan->resumes_on->format('M j, Y').'.' : 'You can resume when you are ready.' }} Visits outside this pause keep their own booking status.</p><button type="button" wire:click="setActiveTab('visits')" class="hc-secondary-button mt-4">View pause and visits</button></section>
    @endif
    @if ($plan->status === \App\Models\CarePlan::STATUS_COUNTERED)
        <section class="hc-surface space-y-4 p-5 sm:p-6">
            <p class="hc-care-eyebrow">Your response is needed</p>
            <h2 class="hc-care-title">{{ $plan->caregiver?->name }} proposed a different schedule</h2>
            <p class="hc-care-subtitle">{{ $counterScheduleLabel }}</p>
            @if ($plan->counter_note)<p class="whitespace-pre-line">{{ $plan->counter_note }}</p>@endif
            <div class="rounded-xl border border-[#A4B3A7] bg-[#FFF7EA] p-4"><p class="font-semibold">Proposed dates · not booked yet</p><ul class="mt-2 space-y-1">@foreach($counterVisits as $proposal)<li>{{ $proposal['start']->format('l, F j · g:i A') }}–{{ $proposal['end']->format('g:i A') }}</li>@endforeach</ul></div>
            <button type="button" wire:click="acceptCounter" wire:loading.attr="disabled" class="hc-primary-button">Accept proposed schedule</button>
        </section>
    @elseif ($isPending)
        <section class="hc-surface space-y-3 p-5 sm:p-6"><h2 class="hc-care-title">{{ $plan->status === \App\Models\CarePlan::STATUS_DRAFT ? 'Your recurring care is a draft' : 'Waiting for your caregiver to accept' }}</h2><p class="hc-care-subtitle">The schedule shown above is an offer. Visits will appear here after the offer is accepted and bookings are created.</p>@if($plan->expires_at)<p class="text-sm">Offer expires {{ $plan->expires_at->copy()->setTimezone($timezone)->format('M j, Y · g:i A') }}.</p>@endif</section>
    @elseif ($isPaused)
        <section class="rounded-2xl border border-[#A4B3A7] bg-[#FFF7EA] p-5 sm:p-6"><h2 class="hc-care-title">Recurring care is paused</h2><p class="mt-2">@if($plan->pause_starts_on)Pause starts {{ $plan->pause_starts_on->format('M j, Y') }}. @endif{{ $plan->resumes_on ? 'Scheduled to resume '.$plan->resumes_on->format('M j, Y').'.' : 'You can resume when you are ready.' }} Any visits still booked are listed below.</p><button type="button" wire:click="setActiveTab('visits')" class="hc-secondary-button mt-4">Manage your schedule</button></section>
    @elseif ($isEnded)
        <section class="hc-surface p-5 sm:p-6"><h2 class="hc-care-title">{{ $planStateLabel }}</h2><p class="mt-2 hc-care-subtitle">No new regular visits will be created. {{ $nextVisit ? 'A visit is still booked below and keeps its own hours, payment and review.' : 'Your previous visits, payments and care records remain available.' }}</p></section>
    @elseif (in_array($plan->status, [\App\Models\CarePlan::STATUS_DECLINED, \App\Models\CarePlan::STATUS_EXPIRED], true))
        <section class="hc-surface p-5 sm:p-6"><h2 class="hc-care-title">{{ $planStateLabel }}</h2><p class="mt-2 hc-care-subtitle">This offer did not become active recurring care. Its offer and care details remain available.</p></section>
    @endif

    @php($hasCurrentVisit = $nextVisit && in_array($nextVisit->status, [\App\Models\CareBooking::STATUS_IN_PROGRESS, \App\Models\CareBooking::STATUS_PAUSED], true))
    @if ($hasCurrentVisit)
        @include('livewire.family.partials.regular-care-next-visit')
    @endif

    @if ($reportAttention->isNotEmpty())
        <section class="flex flex-col gap-4 rounded-2xl border border-amber-400 bg-amber-50 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6"><div><h2 class="hc-care-title">{{ $reportAttention->count() }} reported extra {{ Str::plural('visit', $reportAttention->count()) }} to follow up</h2><p class="mt-2">Review the report, hours and payment status below.</p></div><a href="#completed-extra-visit-{{ $reportAttention->first()->id }}" class="hc-secondary-button shrink-0">View extra-visit report</a></section>
    @endif

    @if ($attentionVisits->isNotEmpty())
        <section class="overflow-hidden rounded-2xl border border-amber-400 bg-amber-50" aria-labelledby="care-follow-up-heading">
            <div class="p-5 sm:p-6"><p class="hc-care-eyebrow">Across your visits</p><h2 id="care-follow-up-heading" class="hc-care-title mt-1">{{ $attentionVisits->count() }} {{ Str::plural('visit', $attentionVisits->count()) }} to follow up</h2><p class="mt-2 text-amber-950">Each date keeps its own changes, hours, payment and review.</p></div>
            <div class="divide-y divide-amber-300">
                @foreach ($attentionVisits as $attentionVisit)
                    <article class="flex flex-col gap-3 bg-[#FFFDFA] p-5 sm:flex-row sm:items-start sm:justify-between sm:px-6" wire:key="plan-attention-{{ $attentionVisit->id }}">
                        <div><h3 class="font-semibold">{{ $attentionVisit->scheduled_start_at?->copy()->setTimezone($timezone)->format('l, M j · g:i A') }} · Visit #{{ $attentionVisit->id }}</h3><p class="mt-1 text-sm">{{ $attentionVisit->caregiver?->name }}</p><ul class="mt-2 space-y-1 text-sm">@foreach($this->visitAttention($attentionVisit) as $item)<li>{{ $item['label'] }}</li>@endforeach</ul></div>
                        @php($firstAction = $this->visitAttention($attentionVisit)[0] ?? ['action' => 'View visit', 'tab' => 'shift'])
                        <a href="{{ $this->visitUrl($attentionVisit, $firstAction['tab']) }}" wire:navigate class="hc-secondary-button shrink-0">{{ $firstAction['action'] }} <span class="sr-only">for visit #{{ $attentionVisit->id }}</span></a>
                    </article>
                @endforeach
            </div>
        </section>
    @elseif ($plan->status === \App\Models\CarePlan::STATUS_PAYMENT_ATTENTION)
        <section class="rounded-2xl border border-amber-400 bg-amber-50 p-5"><h2 class="hc-care-title">Payment needs attention</h2><p class="mt-2">{{ $plan->last_error ?: 'Open your booked visits to check the payment that needs attention.' }}</p><button type="button" wire:click="setVisitFilter('all')" class="hc-secondary-button mt-4">View visit payments</button></section>
    @endif

    @if ($nextVisit)
        @unless ($hasCurrentVisit)
            @include('livewire.family.partials.regular-care-next-visit')
        @endunless
    @elseif ($plan->status === \App\Models\CarePlan::STATUS_ACTIVE)
        <section class="hc-surface p-5 sm:p-6"><h2 class="hc-care-title">No upcoming visit is booked yet</h2><p class="mt-2 hc-care-subtitle">Your recurring schedule remains active. New dates will appear when their bookings are prepared.</p><button type="button" wire:click="setActiveTab('visits')" class="hc-secondary-button mt-4">View schedule and visits</button></section>
    @endif

    @if ($plan->pendingScheduleChanges->isNotEmpty())
        <section class="rounded-2xl border border-amber-400 bg-amber-50 p-5 sm:p-6"><h2 class="hc-care-title">Waiting for {{ $plan->caregiver?->name }}</h2><p class="mt-2">{{ $plan->pendingScheduleChanges->count() }} schedule or extra-visit {{ Str::plural('request', $plan->pendingScheduleChanges->count()) }} waiting for a response. Existing bookings remain unchanged until acceptance.</p><button type="button" wire:click="setActiveTab('visits')" class="hc-secondary-button mt-4">View change requests</button></section>
    @endif

    @include('livewire.family.partials.completed-extra-visits')

    @if (filled(data_get($careProfileSnapshot, 'sections.important_for_safety')))
        <section class="rounded-2xl border border-amber-400 bg-amber-50 p-5 sm:p-6"><h2 class="hc-care-title">Important for {{ $profileName }}’s safety</h2><div class="mt-3 space-y-2">@foreach((array) data_get($careProfileSnapshot, 'sections.important_for_safety') as $value)@if(is_array($value))<ul class="list-inside list-disc">@foreach($value as $item)<li>{{ $item }}</li>@endforeach</ul>@elseif(filled($value))<p class="whitespace-pre-line">{{ $value }}</p>@endif@endforeach</div><button type="button" wire:click="setActiveTab('details')" class="hc-care-text-link mt-3 min-h-11">Read full care details →</button></section>
    @endif
</div>
