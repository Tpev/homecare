@props([
    'options',
    'selected' => [],
    'verification' => 'any_current',
    'typesModel' => 'certificationTypes',
    'verificationModel' => 'certificationVerification',
    'clearMethod' => 'clearCertificationFilters',
    'removeMethod' => 'removeCertificationFilter',
    'includeReportedMethod' => 'includeReportedCertifications',
    'idPrefix' => 'caregiver-certifications',
])

@php
    $selectedSlugs = collect($selected)->map(fn ($value) => (string) $value)->all();
    $selectedOptions = collect($options)->filter(fn ($option) => in_array((string) $option->slug, $selectedSlugs, true));
    $selectedCount = $selectedOptions->count();
    $summary = $selectedCount === 0
        ? 'Any'
        : $selectedOptions->first()->label.($selectedCount > 1 ? ' + '.($selectedCount - 1) : '');
@endphp

<div {{ $attributes->class(['space-y-3']) }}>
    <details class="group rounded-xl border border-[#DED6CA] bg-[#FFFCF8]" @if ($selectedCount > 0) open @endif>
        <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-3 rounded-xl px-3 py-2 text-sm font-semibold text-[#17313F] focus:outline-none focus:ring-2 focus:ring-[#4F6FAF] [&::-webkit-details-marker]:hidden">
            <span>Certifications &amp; training</span>
            <span class="inline-flex items-center gap-2 text-right text-[#607080]">
                <span>{{ $summary }}</span>
                <span class="transition group-open:rotate-180" aria-hidden="true">⌄</span>
            </span>
        </summary>

        <div class="border-t border-[#E4DDD3] p-3">
            <fieldset>
                <legend class="text-sm font-semibold text-[#17313F]">Required certifications &amp; training</legend>
                <p class="mt-1 text-xs leading-5 text-[#607080]">Choose what the caregiver must currently have. Select more than one to require all of them.</p>
                <div class="mt-2 grid max-h-56 gap-1 overflow-y-auto sm:grid-cols-2">
                    @foreach ($options as $option)
                        <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-lg px-2 py-2 text-sm text-[#324457] hover:bg-white" wire:key="{{ $idPrefix }}-type-{{ $option->id }}">
                            <input
                                type="checkbox"
                                value="{{ $option->slug }}"
                                wire:model.live="{{ $typesModel }}"
                                class="rounded border-[#B7ADA0] text-[#0F3D3E] focus:ring-[#4F6FAF]"
                            >
                            <span>{{ $option->label }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            @if ($selectedCount > 0)
                <fieldset class="mt-4 border-t border-[#E4DDD3] pt-3">
                    <legend class="text-sm font-semibold text-[#17313F]">Verification</legend>
                    <div class="mt-2 grid gap-2">
                        <label class="flex min-h-11 cursor-pointer items-start gap-3 rounded-xl border border-[#DED6CA] bg-white px-3 py-2 text-sm text-[#324457]">
                            <input type="radio" value="any_current" wire:model.live="{{ $verificationModel }}" class="mt-0.5 border-[#B7ADA0] text-[#0F3D3E] focus:ring-[#4F6FAF]">
                            <span><strong class="block text-[#17313F]">Any current credential</strong><span class="text-xs leading-5 text-[#607080]">Includes LoLo-verified and caregiver-reported credentials.</span></span>
                        </label>
                        <label class="flex min-h-11 cursor-pointer items-start gap-3 rounded-xl border border-[#DED6CA] bg-white px-3 py-2 text-sm text-[#324457]">
                            <input type="radio" value="verified_only" wire:model.live="{{ $verificationModel }}" class="mt-0.5 border-[#B7ADA0] text-[#0F3D3E] focus:ring-[#4F6FAF]">
                            <span><strong class="block text-[#17313F]">LoLo verified only</strong><span class="text-xs leading-5 text-[#607080]">Only current credentials reviewed by the LoLo team.</span></span>
                        </label>
                    </div>
                </fieldset>

                <button
                    type="button"
                    wire:click="{{ $clearMethod }}"
                    class="mt-3 inline-flex min-h-11 items-center rounded-xl px-2 text-sm font-semibold text-[#0F3D3E] underline underline-offset-4 focus:outline-none focus:ring-2 focus:ring-[#4F6FAF]"
                >
                    Clear certifications
                </button>
            @endif
        </div>
    </details>

    <x-caregiver-certification-filter-chips
        :options="$options"
        :selected="$selectedSlugs"
        :verification="$verification"
        :remove-method="$removeMethod"
        :include-reported-method="$includeReportedMethod"
    />
</div>
