<div class="space-y-3">
    <h2 class="font-display text-lg font-semibold text-[#17313F]">{{ $distanceAvailable ? 'No care requests match this area yet' : 'Set your service ZIP to find local requests' }}</h2>
    <p class="text-sm text-[#607080]">{{ $distanceAvailable ? 'Try a wider radius or browse all distances. Any other filters you selected still apply.' : 'Update your profile location, or browse all distances while you decide where to work.' }}</p>
    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
        @if ($distanceAvailable && $nextRadius !== null && $nextRadius !== 'all')
            <button type="button" wire:click="$set('radiusMiles', '{{ $nextRadius }}')" class="hc-primary-button">Expand to {{ $nextRadius }} miles</button>
        @elseif (! $distanceAvailable)
            <a href="{{ route('caregiver.profile.edit') }}" wire:navigate class="hc-primary-button">Set service ZIP</a>
        @endif
        <button type="button" wire:click="$set('radiusMiles', 'all')" class="hc-secondary-button">Browse all distances</button>
    </div>
</div>
