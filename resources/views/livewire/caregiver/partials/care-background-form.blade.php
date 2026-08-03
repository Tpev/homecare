@php
    $fieldIdPrefix = $fieldIdPrefix ?? 'care-background';
    $selectedExperienceValues = array_map('strval', $selectedExperienceTypes);
    $selectedCertificationValues = array_map('strval', $selectedCertificationTypes);
    $selectedCertificationIds = collect($selectedCertificationTypes)
        ->reject(fn ($value) => (string) $value === \App\Services\Caregiver\CaregiverBackgroundService::NONE)
        ->map(fn ($value) => (int) $value)
        ->values();
@endphp

<div class="space-y-7">
    <fieldset>
        <legend class="text-base font-semibold text-[#17313F]">Which care needs have you supported through hands-on, non-medical care?</legend>
        <p class="mt-1 text-sm text-[#607080]">Select all that apply. Share only general experience—never include a previous client’s name, diagnosis, or private information.</p>

        <div class="mt-3 grid grid-cols-1 gap-2 md:grid-cols-2">
            @foreach ($experienceOptions as $option)
                @php $selected = in_array((string) $option['id'], $selectedExperienceValues, true); @endphp
                <label for="{{ $fieldIdPrefix }}-experience-{{ $option['id'] }}" class="flex min-h-11 cursor-pointer items-start gap-3 rounded-xl border px-3 py-3 text-sm transition {{ $selected ? 'border-[#4F6FAF] bg-[#EEF4FF] text-[#17313F]' : 'border-[#DED6CA] bg-white text-[#324457] hover:bg-[#F5F1EB]' }}">
                    <input
                        id="{{ $fieldIdPrefix }}-experience-{{ $option['id'] }}"
                        type="checkbox"
                        value="{{ $option['id'] }}"
                        wire:model.live="selectedExperienceTypes"
                        class="mt-0.5 rounded border-[#B7ADA0] text-[#0F3D3E] focus:ring-[#4F6FAF]"
                    >
                    <span>{{ $option['label'] }}</span>
                </label>
            @endforeach

            @php $noneExperienceSelected = in_array(\App\Services\Caregiver\CaregiverBackgroundService::NONE, $selectedExperienceValues, true); @endphp
            <label for="{{ $fieldIdPrefix }}-experience-none" class="flex min-h-11 cursor-pointer items-start gap-3 rounded-xl border px-3 py-3 text-sm transition {{ $noneExperienceSelected ? 'border-[#4F6FAF] bg-[#EEF4FF] text-[#17313F]' : 'border-[#DED6CA] bg-white text-[#324457] hover:bg-[#F5F1EB]' }}">
                <input
                    id="{{ $fieldIdPrefix }}-experience-none"
                    type="checkbox"
                    value="none"
                    wire:model.live="selectedExperienceTypes"
                    class="mt-0.5 rounded border-[#B7ADA0] text-[#0F3D3E] focus:ring-[#4F6FAF]"
                >
                <span>I don’t have specialized care experience yet</span>
            </label>
        </div>
        @error('selectedExperienceTypes') <p class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
        @error('selectedExperienceTypes.*') <p class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror

        @if (! $noneExperienceSelected)
            <div class="mt-4">
                <x-textarea
                    label="Tell families briefly about this experience (optional)"
                    wire:model="care_experience_notes"
                    hint="Describe the kind of support you provided without identifying anyone. Maximum 1,000 characters."
                />
                @error('care_experience_notes') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
        @endif
    </fieldset>

    <fieldset class="border-t border-[#E4DDD3] pt-6">
        <legend class="text-base font-semibold text-[#17313F]">Which current certifications or formal care training do you hold?</legend>
        <p class="mt-1 text-sm text-[#607080]">Choose all that apply. Supporting documents are optional and are never shown to families.</p>

        <div class="mt-3 grid grid-cols-1 gap-2 md:grid-cols-2">
            @foreach ($certificationOptions as $option)
                @php $selected = in_array((string) $option['id'], $selectedCertificationValues, true); @endphp
                <label for="{{ $fieldIdPrefix }}-certification-{{ $option['id'] }}" class="flex min-h-11 cursor-pointer items-start gap-3 rounded-xl border px-3 py-3 text-sm transition {{ $selected ? 'border-[#4F6FAF] bg-[#EEF4FF] text-[#17313F]' : 'border-[#DED6CA] bg-white text-[#324457] hover:bg-[#F5F1EB]' }}">
                    <input
                        id="{{ $fieldIdPrefix }}-certification-{{ $option['id'] }}"
                        type="checkbox"
                        value="{{ $option['id'] }}"
                        wire:model.live="selectedCertificationTypes"
                        class="mt-0.5 rounded border-[#B7ADA0] text-[#0F3D3E] focus:ring-[#4F6FAF]"
                    >
                    <span>{{ $option['label'] }}</span>
                </label>
            @endforeach

            @php $noCertificationsSelected = in_array(\App\Services\Caregiver\CaregiverBackgroundService::NONE, $selectedCertificationValues, true); @endphp
            <label for="{{ $fieldIdPrefix }}-certification-none" class="flex min-h-11 cursor-pointer items-start gap-3 rounded-xl border px-3 py-3 text-sm transition {{ $noCertificationsSelected ? 'border-[#4F6FAF] bg-[#EEF4FF] text-[#17313F]' : 'border-[#DED6CA] bg-white text-[#324457] hover:bg-[#F5F1EB]' }}">
                <input
                    id="{{ $fieldIdPrefix }}-certification-none"
                    type="checkbox"
                    value="none"
                    wire:model.live="selectedCertificationTypes"
                    class="mt-0.5 rounded border-[#B7ADA0] text-[#0F3D3E] focus:ring-[#4F6FAF]"
                >
                <span>No current certifications</span>
            </label>
        </div>
        @error('selectedCertificationTypes') <p class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
        @error('selectedCertificationTypes.*') <p class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror

        @if ($selectedCertificationIds->isNotEmpty())
            <div class="mt-5 space-y-3">
                <div>
                    <h3 class="text-sm font-semibold text-[#17313F]">Add helpful details</h3>
                    <p class="mt-1 text-xs text-[#607080]">Details and proof are optional, except that “Other” needs a name. A document lets LoLo Care review the credential.</p>
                </div>

                @foreach ($selectedCertificationIds as $typeId)
                    @php
                        $option = collect($certificationOptions)->first(fn ($row) => (int) $row['id'] === (int) $typeId);
                        $record = $existingCertificationRecords[$typeId] ?? null;
                        $document = ($record['has_document'] ?? false) ? $record : null;
                        $documentRemoved = (bool) ($certificationDocumentsToRemove[$typeId] ?? false);
                        $statusLabel = ($record['expired'] ?? false) ? 'Expired' : match ($record['status'] ?? null) {
                            \App\Models\CaregiverCertification::STATUS_VERIFIED => 'Verified credential',
                            \App\Models\CaregiverCertification::STATUS_PENDING => 'Pending review',
                            \App\Models\CaregiverCertification::STATUS_REJECTED => 'Needs attention',
                            default => 'Credential reported by caregiver',
                        };
                    @endphp

                    <section class="rounded-2xl border border-[#DED6CA] bg-[#FFFCF8] p-4" wire:key="{{ $fieldIdPrefix }}-certification-details-{{ $typeId }}">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <h4 class="font-semibold text-[#17313F]">{{ $option['label'] ?? 'Certification or training' }}</h4>
                            @if ($record)
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ ($record['expired'] ?? false) ? 'bg-amber-100 text-amber-900' : (($record['status'] ?? null) === \App\Models\CaregiverCertification::STATUS_VERIFIED ? 'bg-emerald-100 text-emerald-800' : (($record['status'] ?? null) === \App\Models\CaregiverCertification::STATUS_REJECTED ? 'bg-rose-100 text-rose-800' : 'bg-[#F0E9E1] text-[#4B5B6B]')) }}">{{ $statusLabel }}</span>
                            @endif
                        </div>

                        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                            <x-input
                                label="Exact credential or training name{{ ($option['slug'] ?? null) === 'other' ? '' : ' (optional)' }}"
                                wire:model="certificationDetails.{{ $typeId }}.custom_name"
                            />
                            <x-input label="Issuing organization (optional)" wire:model="certificationDetails.{{ $typeId }}.issuer" />
                            <x-input label="Issuing state (optional)" maxlength="2" wire:model="certificationDetails.{{ $typeId }}.issuing_state" />
                            <x-input type="date" label="Expiration date (optional)" wire:model="certificationDetails.{{ $typeId }}.expires_at" />
                        </div>

                        @foreach (['custom_name', 'issuer', 'issuing_state', 'expires_at'] as $field)
                            @error("certificationDetails.$typeId.$field") <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
                        @endforeach

                        @if (($record['status'] ?? null) === \App\Models\CaregiverCertification::STATUS_REJECTED && filled($record['rejection_reason'] ?? null))
                            <p class="mt-3 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">LoLo Care review note: {{ $record['rejection_reason'] }}</p>
                        @endif

                        <div class="mt-4 rounded-xl border border-dashed border-[#C8BFB2] bg-white p-3">
                            @if ($document && ! $documentRemoved)
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-[#17313F]">{{ $document['name'] }}</p>
                                        <a href="{{ route('caregiver.certifications.document', $document['id']) }}" class="text-xs font-medium text-[#315B98] underline" target="_blank" rel="noopener">Open current document</a>
                                    </div>
                                    <button type="button" wire:click="removeCertificationDocument({{ $typeId }})" class="min-h-11 rounded-xl border border-red-200 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Remove document</button>
                                </div>
                            @elseif ($documentRemoved)
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <p class="text-sm text-amber-800">The current document will be removed when you save.</p>
                                    <button type="button" wire:click="undoCertificationDocumentRemoval({{ $typeId }})" class="min-h-11 rounded-xl border border-[#B7ADA0] px-3 py-2 text-sm font-semibold text-[#0F3D3E]">Undo</button>
                                </div>
                            @endif

                            <label for="{{ $fieldIdPrefix }}-certification-document-{{ $typeId }}" class="mt-3 block text-sm font-medium text-[#324457]">{{ $document && ! $documentRemoved ? 'Replace supporting document' : 'Supporting document (optional)' }}</label>
                            <input
                                id="{{ $fieldIdPrefix }}-certification-document-{{ $typeId }}"
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                wire:model="certificationDocuments.{{ $typeId }}"
                                class="mt-1 block min-h-11 w-full rounded-xl border border-[#DED6CA] bg-white px-3 py-2 text-sm text-[#324457] file:mr-3 file:rounded-lg file:border-0 file:bg-[#EAF6F6] file:px-3 file:py-2 file:font-semibold file:text-[#0F3D3E]"
                            >
                            <p class="mt-1 text-xs text-[#7B8794]">PDF, JPG, or PNG up to 6 MB. Stored privately for LoLo Care review.</p>
                            <div wire:loading wire:target="certificationDocuments.{{ $typeId }}" class="mt-1 text-xs font-medium text-[#315B98]">Uploading…</div>
                            @error("certificationDocuments.$typeId") <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
                        </div>
                    </section>
                @endforeach
            </div>
        @endif

        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Certifications do not expand the non-medical services offered through LoLo Care.
        </div>
    </fieldset>
</div>
