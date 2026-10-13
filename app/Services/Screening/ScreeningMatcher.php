<?php

namespace App\Services\Screening;

use App\Models\ScreeningDatasetImport;
use App\Models\ScreeningRecord;
use App\Models\ScreeningRecordName;
use App\Services\Screening\Imports\NameNormalizer;
use RuntimeException;

class ScreeningMatcher
{
    public function search(array $identity): array
    {
        $first = NameNormalizer::normalize($identity['first_name'] ?? '');
        $last = NameNormalizer::normalize($identity['last_name'] ?? '');
        $full = NameNormalizer::normalize($identity['full_name'] ?? trim($first.' '.$last));
        if ($full === '' || (! str_contains($full, ' ') && ($first === '' || $last === ''))) {
            throw new RuntimeException('A verified legal name is required to run screening.');
        }
        $imports = $this->usableImports();
        $versions = $imports->pluck('id', 'source')->all();
        $limit = (int) config('screening.max_candidates', 1000);
        // Separate bounded lookups keep both composite indexes selective on large snapshots.
        $nameIds = ScreeningRecordName::whereIn('import_id', array_values($versions))->where('normalized_name', $full)->orderBy('id')->limit($limit + 1)->pluck('id');
        if ($first !== '' && $last !== '') {
            $nameIds = $nameIds->merge(ScreeningRecordName::whereIn('import_id', array_values($versions))->where('first_name', $first)->where('last_name', $last)->orderBy('id')->limit($limit + 1)->pluck('id'));
        }
        $nameIds = $nameIds->unique();
        if ($nameIds->count() > $limit) {
            throw new RuntimeException('Too many name candidates for a complete search. Review the verified identity before retrying; no result was finalized.');
        }
        $names = ScreeningRecordName::with('record')->whereIn('id', $nameIds)->orderBy('id')->get();
        $candidates = [];
        foreach ($names as $name) {
            $record = $name->record;
            if (! $record || $record->record_type !== 'profile') {
                throw new RuntimeException('A searchable record is incomplete; reimport this source before screening.');
            }
            $import = $imports->firstWhere('id', $name->import_id);
            $key = $import->source.':'.$record->source_record_id;
            $reason = $name->normalized_name === $full ? 'Exact normalized full name' : 'Exact normalized first and last name; middle names may differ';
            $reason .= $name->is_alias ? ' (additional name supplied by the source). Identity requires admin confirmation.' : '. Identity requires admin confirmation.';
            if (isset($candidates[$key])) {
                $candidates[$key]['record_snapshot']['matched_names'][] = ['name' => $name->display_name, 'reason' => $reason];

                continue;
            }
            $related = ScreeningRecord::where('import_id', $import->id)->where('parent_record_id', $record->source_record_id)->orderBy('id')->get()->groupBy('record_type')->map(fn ($records) => $records->pluck('data')->all())->all();
            $missingPrimary = (bool) ($record->data['missing_primary_record'] ?? false);
            $fieldLabels = [];
            foreach ($import->file_manifest ?? [] as $table => $metadata) {
                if (isset($metadata['descriptor'])) {
                    $fieldLabels[$table] = array_map(fn ($field) => $field['description'], $metadata['descriptor']);
                }
            }
            $candidates[$key] = ['source' => $import->source, 'source_record_id' => $record->source_record_id, 'matched_name' => $name->display_name, 'match_reason' => $reason, 'record_snapshot' => ['profile' => $record->data, 'related' => $related, 'field_labels' => $fieldLabels, 'source_url' => config("screening.sources.{$import->source}.page_url"), 'import_id' => $import->id, 'downloaded_at' => $import->downloaded_at->toIso8601String(), 'matched_names' => [['name' => $name->display_name, 'reason' => $reason]], 'missing_primary_record' => $missingPrimary, 'coverage_warning' => $missingPrimary ? 'The official extract contains related records for this identifier but no primary record. Confirm the identity and current record status with the source.' : null, 'source_coverage' => $import->file_manifest['name_coverage'] ?? null]];
            $candidates[$key]['record_snapshot']['date_of_birth'] = $record->data['date_of_birth'] ?? null;
        }
        // Reject a race with activation rather than recording a search across changing sources.
        $this->assertUsableVersions($versions);

        return ['source_versions' => $versions, 'source_coverage' => $imports->mapWithKeys(fn ($import) => [$import->source => $import->file_manifest['name_coverage'] ?? null])->all(), 'candidates' => array_values($candidates), 'searched_at' => now()->toIso8601String()];
    }

    public function assertUsableVersions(array $versions): void
    {
        // In a decision transaction these locks keep source activation from racing finalization.
        $current = $this->usableImports(true)->pluck('id', 'source')->all();
        ksort($versions);
        ksort($current);
        if (array_map('intval', $versions) !== array_map('intval', $current)) {
            throw new RuntimeException('Source datasets changed since this screening. Run the search again before deciding.');
        }
    }

    private function usableImports(bool $lock = false): \Illuminate\Database\Eloquent\Collection
    {
        $sources = config('screening.required_sources', ['nc_dac', 'nc_sbi']);
        if ($sources === []) {
            throw new RuntimeException('No required screening sources are configured.');
        }
        $imports = ScreeningDatasetImport::whereIn('source', $sources)->where('is_active', true)->where('status', 'ready')->when($lock, fn ($query) => $query->lockForUpdate())->get();
        foreach ($sources as $source) {
            $matches = $imports->where('source', $source);
            $import = $matches->first();
            if ($matches->count() !== 1 || ! $import?->validated_at || ! $import->downloaded_at || $import->record_count < 1 || $import->downloaded_at->isFuture() || $import->downloaded_at->lt(now()->subDays(max(1, (int) config('screening.max_age_days', 14))))) {
                throw new RuntimeException("The {$source} dataset is missing, invalid, or stale. Import a fresh validated snapshot before screening.");
            }
        }

        return $imports;
    }
}
