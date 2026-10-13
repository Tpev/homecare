@if ($selectedCase->candidates->isNotEmpty())
<section class="mt-5 border-t border-slate-200 pt-5" aria-label="Potential matches">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="font-semibold text-slate-900">Potential matches</h3>
        <span class="text-xs text-slate-500">{{ $selectedCase->candidates->count() }} {{ str('record')->plural($selectedCase->candidates->count()) }}</span>
    </div>
    <div class="mt-3 space-y-3">
        @foreach ($selectedCase->candidates as $candidate)
            <article wire:key="screening-candidate-{{ $candidate->id }}" class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <div class="flex flex-wrap justify-between gap-2">
                    <h4 class="font-semibold text-slate-900">{{ $candidate->matched_name }}</h4>
                    <span class="text-xs font-semibold text-amber-900">{{ ['nc_dac' => 'NC DAC', 'nc_sbi' => 'NC SBI'][$candidate->source] ?? $candidate->source }} · {{ $candidate->source_record_id }}</span>
                </div>
                <p class="mt-1 text-sm text-slate-700">{{ $candidate->match_reason }}</p>
                @if (data_get($candidate->record_snapshot, 'missing_primary_record') === true)
                    <div class="mt-3 rounded-lg border border-amber-400 bg-white p-3 text-sm text-amber-950" role="note">
                        <p>Primary identity record unavailable. Compare the supporting records before confirming this match.</p>
                    </div>
                @endif
                @if (data_get($candidate->record_snapshot, 'date_of_birth'))
                    <p class="mt-2 text-sm"><strong>Date of birth:</strong> {{ data_get($candidate->record_snapshot, 'date_of_birth') }}</p>
                @endif
                <details class="mt-3 rounded-lg border border-amber-200 bg-white px-3 py-2">
                    <summary class="cursor-pointer text-sm font-semibold text-slate-700">View matched record</summary>
                    @include('livewire.admin.partials.screening-record', ['record' => collect($candidate->record_snapshot ?? [])->except(['field_labels', 'coverage_warning', 'missing_primary_record', 'source_coverage'])->all(), 'fieldLabels' => data_get($candidate->record_snapshot, 'field_labels', []), 'recordTable' => null, 'depth' => 0])
                </details>
            </article>
        @endforeach
    </div>
</section>
@endif
