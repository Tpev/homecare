@if($requestItem->isRecurring() && ! $requestItem->is_system_generated)
    <fieldset class="my-4 rounded-2xl border border-emerald-200 bg-emerald-50/50 p-4">
        <legend class="px-1 font-semibold text-[#17313F]">Which days can you do every week?</legend>
        <p class="mb-3 text-sm text-slate-600">Choose some or all available days. Each selection means the full hours shown, every week.</p>
        <div class="grid gap-2 sm:grid-cols-2">
            @foreach(app(\App\Services\RegularCare\SharedCareService::class)->availableSlots($requestItem) as $slot)
                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white p-3">
                    <input type="checkbox" wire:model.live="selectedDays" value="{{ $slot['day'] }}" class="rounded border-slate-300 text-emerald-700">
                    <span class="font-medium">{{ \App\Support\WeeklySchedule::label([$slot]) }}</span>
                </label>
            @endforeach
        </div>
        <p class="mt-3 text-sm font-medium">{{ count($selectedDays) }} days selected</p>
        @if($selectedDays)
            @php
                try { $selectedOffer = app(\App\Services\RegularCare\SharedCareService::class)->offer($requestItem, $selectedDays); }
                catch (\Illuminate\Validation\ValidationException $exception) { $selectedOffer = null; }
            @endphp
            @if($selectedOffer)<p class="mt-2 text-sm">First visit: {{ \Illuminate\Support\Carbon::parse($selectedOffer['starts_on'])->format('l, F j, Y') }}. Repeats weekly{{ $selectedOffer['ends_on'] ? ' through '.\Illuminate\Support\Carbon::parse($selectedOffer['ends_on'])->format('F j, Y') : ' until the agreement ends' }}. Times in {{ $selectedOffer['timezone'] }}.</p>@endif
        @endif
        @error('selectedDays') <p role="alert" class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
    </fieldset>
@endif
