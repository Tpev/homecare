<div class="my-4 rounded-xl border border-[#DED6CA] bg-white p-3">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-4">
        <label for="{{ $idPrefix }}-distance" class="text-sm font-semibold text-[#17313F]">Distance from care location</label>
        <select id="{{ $idPrefix }}-distance" wire:model.live="caregiverDistanceMiles" aria-describedby="{{ $idPrefix }}-distance-hint" class="min-h-11 w-full rounded-xl border border-[#B7ADA0] bg-white px-3 py-2 text-sm text-[#17313F] focus:border-[#4F6FAF] focus:ring-[#4F6FAF] sm:w-auto">
            <option value="">Any distance</option>
            @foreach (\App\Services\Marketplace\CaregiverDistanceFilter::MILES as $miles)
                <option value="{{ $miles }}">Within {{ $miles }} miles</option>
            @endforeach
        </select>
    </div>
    <p id="{{ $idPrefix }}-distance-hint" class="mt-2 text-sm text-[#607080]">
        @if ($caregiverDistanceAvailable)
            From {{ collect([$requestItem->city, $requestItem->state, $requestItem->zip])->filter()->implode(', ') }}. Approximate straight-line miles using ZIP areas.
            @if ($caregiverDistanceMiles !== '') Caregivers with unknown distance are excluded.@endif
        @else
            Distance is unavailable for this care location's ZIP. Choose Any distance or update the care location.
        @endif
    </p>
    @error('caregiverDistanceMiles')<p class="mt-2 text-sm text-red-700" role="alert">Choose one of the listed distances.</p>@enderror
</div>
