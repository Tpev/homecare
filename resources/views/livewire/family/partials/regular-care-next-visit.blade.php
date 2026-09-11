<section class="hc-surface overflow-hidden">
    <div class="border-b border-[#A4B3A7] bg-[#FFF7EA] px-5 py-4 sm:px-6">
        <h2 class="hc-care-title">{{ in_array($nextVisit->status, [\App\Models\CareBooking::STATUS_IN_PROGRESS, \App\Models\CareBooking::STATUS_PAUSED], true) ? 'Current visit' : ($isEnded ? 'Your remaining booked visit' : 'Next booked visit') }}</h2>
        <p class="mt-1 text-sm">Open this date for its care, check-in, hours and payment.</p>
    </div>
    @include('livewire.family.partials.regular-care-visit-card', ['visit' => $nextVisit, 'prominent' => true])
    <div class="border-t border-[#E3D6C5] px-5 py-3 sm:px-6">
        <button type="button" wire:click="setVisitFilter('upcoming')" class="hc-care-text-link min-h-11">View all {{ $upcomingVisits->count() }} booked {{ Str::plural('visit', $upcomingVisits->count()) }} →</button>
    </div>
</section>
