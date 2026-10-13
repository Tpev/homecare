<section class="mt-5 border-t border-slate-200 pt-5" aria-label="Candidate records">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="font-semibold text-slate-900">Candidate records</h3>
        <span class="text-xs text-slate-500">{{ $selectedCase->candidates->count() }} possible {{ str('record')->plural($selectedCase->candidates->count()) }}</span>
    </div>
    <p class="mt-2 text-xs text-slate-500">A name match is a candidate, including exact names. Compare identifying details before confirming the record belongs to this caregiver. Name matching covers only the available names in the imported source versions; records without names cannot be matched.</p>
    @foreach ($selectedCase->source_versions ?? [] as $searchedSource => $searchedVersion)
        @php
            $searchedImport = $datasetImports->firstWhere('id', (int) $searchedVersion);
            $searchedCoverage = data_get($searchedImport?->file_manifest, 'name_coverage');
        @endphp
        @if (is_array($searchedCoverage) && (($searchedCoverage['source_only_profiles'] ?? 0) > 0 || ($searchedCoverage['profiles_without_names'] ?? 0) > 0 || ! empty($searchedCoverage['limitations'])))
            @include('livewire.admin.partials.screening-source-coverage', ['coverage' => $searchedCoverage, 'coverageHeading' => (['nc_dac' => 'NC DAC', 'nc_sbi' => 'NC SBI'][$searchedSource] ?? $searchedSource).' · searched version #'.$searchedVersion.' coverage'])
        @endif
    @endforeach
    <div class="mt-3 space-y-3">
        @forelse ($selectedCase->candidates as $candidate)
            <article wire:key="screening-candidate-{{ $candidate->id }}" class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <div class="flex flex-wrap justify-between gap-2">
                    <h4 class="font-semibold text-slate-900">{{ $candidate->matched_name }}</h4>
                    <span class="text-xs font-semibold text-amber-900">{{ ['nc_dac' => 'NC DAC', 'nc_sbi' => 'NC SBI'][$candidate->source] ?? $candidate->source }} · {{ $candidate->source_record_id }}</span>
                </div>
                <p class="mt-1 text-sm text-slate-700">{{ $candidate->match_reason }}</p>
                @if (data_get($candidate->record_snapshot, 'missing_primary_record') === true)
                    <div class="mt-3 rounded-lg border border-amber-400 bg-white p-3 text-sm text-amber-950" role="note">
                        <p class="font-semibold">Primary individual record missing from this source</p>
                        <p class="mt-1">{{ data_get($candidate->record_snapshot, 'coverage_warning') ?: 'This candidate is based on an available name in supporting source records. Review the linked evidence before confirming identity.' }}</p>
                    </div>
                @endif
                @if (data_get($candidate->record_snapshot, 'date_of_birth'))
                    <p class="mt-2 text-sm"><strong>Date of birth:</strong> {{ data_get($candidate->record_snapshot, 'date_of_birth') }}</p>
                @endif
                <details class="mt-3 rounded-lg border border-amber-200 bg-white px-3 py-2">
                    <summary class="cursor-pointer text-sm font-semibold text-slate-700">View source record and linked details</summary>
                    @include('livewire.admin.partials.screening-record', ['record' => collect($candidate->record_snapshot ?? [])->except('field_labels')->all(), 'fieldLabels' => data_get($candidate->record_snapshot, 'field_labels', []), 'recordTable' => null, 'depth' => 0])
                </details>
            </article>
        @empty
            <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-600">{{ $selectedCase->search_status === 'completed' ? 'No available name matched in the imported source versions. Review source coverage limits before recording No Match.' : 'Complete a public-record search to obtain candidates. An uncompleted search is not a No Match result.' }}</p>
        @endforelse
    </div>
</section>
