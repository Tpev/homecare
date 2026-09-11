@php
    $safetyNotes = collect((array) data_get($careProfileSnapshot, 'sections.important_for_safety', []))->flatten()->filter(fn ($note) => is_string($note) && trim($note) !== '')->values();
    $visitContact = $requestItem->thirdPartyContact;
@endphp
@if ($safetyNotes->isNotEmpty() || filled($requestItem->home_access_notes) || $visitContact)
<section class="hc-surface p-5 sm:p-6" aria-labelledby="visit-care-essentials">
    <div class="flex flex-wrap items-center justify-between gap-2"><h2 id="visit-care-essentials" class="text-xl font-semibold">Important for this visit</h2><button type="button" wire:click="setActiveTab('overview')" class="hc-care-text-link">Full care instructions</button></div>
    <div class="mt-4 grid gap-5 md:grid-cols-3">
        @if ($safetyNotes->isNotEmpty())<div><h3 class="text-base font-semibold">Safety & support</h3><ul class="mt-2 space-y-2 text-sm">@foreach($safetyNotes as $note)<li class="whitespace-pre-line">{{ $note }}</li>@endforeach</ul></div>@endif
        @if (filled($requestItem->home_access_notes))<div><h3 class="text-base font-semibold">Home access</h3><p class="mt-2 whitespace-pre-line text-sm">{{ $requestItem->home_access_notes }}</p></div>@endif
        @if ($visitContact)<div><h3 class="text-base font-semibold">Care contact</h3><p class="mt-2 text-sm">{{ $visitContact->full_name }}</p>@if($visitContact->phone)<a href="tel:{{ $visitContact->phone }}" class="hc-care-text-link">{{ $visitContact->phone }}</a>@endif @if($visitContact->email)<p><a href="mailto:{{ $visitContact->email }}" class="hc-care-text-link break-all">{{ $visitContact->email }}</a></p>@endif</div>@endif
    </div>
</section>
@endif
