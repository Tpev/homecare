<section aria-label="Public dataset health" class="grid grid-cols-1 gap-3 md:grid-cols-2">
    @foreach (['nc_dac' => 'NC DAC · offender, court, sentence & supervision records', 'nc_sbi' => 'NC SBI · sex offender public records'] as $source => $label)
        @php
            $activeImport = $datasetImports->first(fn ($import) => $import->source === $source && $import->is_active && $import->status === 'ready');
            $latestImport = $datasetImports->firstWhere('source', $source);
            $stale = $activeImport && (! $activeImport->downloaded_at || $activeImport->downloaded_at->lt(now()->subDays((int) config('screening.max_age_days', 14))));
            $coverage = data_get($activeImport?->file_manifest, 'name_coverage');
            $limitedCoverage = is_array($coverage) && (($coverage['source_only_profiles'] ?? 0) > 0 || ($coverage['profiles_without_names'] ?? 0) > 0 || ! empty($coverage['limitations']));
        @endphp
        <div class="rounded-xl border p-4 {{ ! $activeImport || $stale || $limitedCoverage ? 'border-amber-200 bg-amber-50' : 'border-slate-200 bg-white' }}">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-slate-900">{{ $label }}</h2>
                <span class="text-xs font-semibold {{ ! $activeImport || $stale || $limitedCoverage ? 'text-amber-900' : 'text-emerald-800' }}">{{ ! $activeImport ? 'No validated import' : ($stale ? 'Refresh required' : ($limitedCoverage ? 'Ready · source coverage limits' : 'Ready')) }}</span>
            </div>
            @if ($activeImport)
                <p class="mt-2 text-xs text-slate-600">Version #{{ $activeImport->id }} · {{ number_format($activeImport->record_count) }} source IDs · Downloaded {{ $activeImport->downloaded_at?->format('M j, Y g:i A') ?? 'unknown' }}</p>
                @if ($limitedCoverage)
                    @include('livewire.admin.partials.screening-source-coverage', ['coverage' => $coverage, 'coverageHeading' => 'Source coverage limits'])
                @endif
            @else
                <p class="mt-2 text-xs text-amber-900">Search and approval require a validated dataset. Existing account access is unchanged.</p>
            @endif
            @if ($latestImport?->status === 'failed')
                <details class="mt-2 text-xs text-rose-800"><summary class="cursor-pointer font-medium">Last refresh failed · {{ $latestImport->created_at?->format('M j, Y g:i A') }}</summary><p class="mt-1 break-words">{{ $latestImport->error }}</p></details>
            @elseif ($latestImport?->status === 'importing')
                <p class="mt-2 text-xs text-blue-800">A dataset import is in progress.</p>
            @endif
        </div>
    @endforeach
</section>
