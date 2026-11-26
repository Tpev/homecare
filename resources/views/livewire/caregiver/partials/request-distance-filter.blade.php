<section class="rounded-2xl border border-[#CFE1D8] bg-[#F2F8F4] p-4 sm:p-5" aria-labelledby="request-distance-heading">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <h2 id="request-distance-heading" class="font-display text-lg font-semibold text-[#17313F]">Find care requests near you</h2>
            <p class="mt-1 text-sm text-[#324457]" role="status" aria-live="polite">
                @if ($radiusMiles === 'all')
                    Showing new requests at all distances.
                @elseif ($distanceAvailable)
                    Within <strong>{{ $radiusMiles }} miles</strong> of your service ZIP <strong>{{ $serviceZip }}</strong>.
                @else
                    Add or update your service ZIP to see nearby care requests.
                @endif
            </p>
            <a href="{{ route('caregiver.profile.edit') }}" wire:navigate class="mt-1 inline-block text-sm font-semibold text-[#0F3D3E] underline underline-offset-4">{{ $distanceAvailable ? 'Update service ZIP' : 'Set service ZIP' }}</a>
        </div>
        <div class="w-full sm:w-auto sm:min-w-48">
            <label for="request-radius" class="block text-sm font-semibold text-[#17313F]">Search radius</label>
            <select id="request-radius" wire:model.live="radiusMiles" aria-describedby="request-radius-hint" class="mt-1 min-h-11 w-full rounded-xl border border-[#B7ADA0] bg-white px-3 py-2 text-base text-[#17313F] focus:border-[#4F6FAF] focus:ring-[#4F6FAF]">
                @foreach (\App\Services\Marketplace\CaregiverDistanceFilter::MILES as $miles)
                    <option value="{{ $miles }}">Within {{ $miles }} miles{{ $miles === 25 ? ' (default)' : '' }}</option>
                @endforeach
                <option value="all">All distances</option>
            </select>
        </div>
    </div>
    <p id="request-radius-hint" class="mt-3 text-xs leading-5 text-[#607080]">Approximate straight-line distances based on ZIP areas. This changes your browsing only. Invitations and applications remain available at every distance.</p>
    @error('radiusMiles')<p class="mt-2 text-sm text-red-700" role="alert">Choose one of the listed distances.</p>@enderror
    <p class="sr-only" wire:loading wire:target="radiusMiles" role="status">Updating nearby care requests.</p>
</section>
