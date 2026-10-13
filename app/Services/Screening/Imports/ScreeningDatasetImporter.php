<?php

namespace App\Services\Screening\Imports;

use App\Models\ScreeningDatasetImport;
use App\Services\Screening\ScreeningPrivateFiles;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ScreeningDatasetImporter
{
    public function __construct(private PublicDataFiles $files, private PublicDataReader $reader) {}

    public function import(string $source, ?string $directory = null, ?string $downloadedAt = null): ScreeningDatasetImport
    {
        $config = config("screening.sources.{$source}");
        if (! is_array($config) || ! in_array($source, ['nc_dac', 'nc_sbi'], true)) {
            throw new RuntimeException('Unknown screening data source.');
        }
        $lock = Cache::lock('screening-import:'.$source, 21600);
        if (! $lock->get()) {
            throw new RuntimeException('An import for this source is already running.');
        }
        $import = null;
        $activated = false;
        try {
            $import = ScreeningDatasetImport::create(['source' => $source, 'status' => 'importing', 'source_urls' => array_map(fn ($file) => $file['url'] ?: ($config['page_url'] ?? null), $config['files'])]);
            $this->validateConfiguration($source, $config);
            if ($directory !== null && $downloadedAt === null) {
                throw new RuntimeException('Local imports require --downloaded-at with the actual source download date.');
            }
            // Eloquent stores timestamps without an offset and reads them in the application timezone.
            $observedAt = ($downloadedAt ? CarbonImmutable::parse($downloadedAt) : CarbonImmutable::now())->setTimezone(config('app.timezone', 'UTC'));
            if ($observedAt->isFuture()) {
                throw new RuntimeException('The source download date cannot be in the future.');
            }
            $root = rtrim(config('screening.private_directory'), '/\\').'/imports/'.$import->id;
            $private = app(ScreeningPrivateFiles::class);
            $private->directory($root);
            $manifest = [];
            $counts = [];
            foreach ($config['files'] as $key => $file) {
                $archive = $root.'/'.$file['filename'];
                if ($directory !== null) {
                    $input = rtrim($directory, '/\\').'/'.$file['filename'];
                    if (! is_file($input) || ! is_readable($input) || filesize($input) === 0 || filesize($input) > (int) config('screening.max_download_bytes')) {
                        throw new RuntimeException("Missing, oversized, or unreadable local file {$file['filename']}.");
                    }
                    $private->copy($input, $archive);
                } else {
                    if ($source === 'nc_sbi' && empty($file['url'])) {
                        app(SbiPublicDataDownloader::class)->download($archive);
                    } else {
                        $this->files->download($file['url'], $archive, $config['allowed_hosts']);
                    }
                }
                $manifest[$key] = ['filename' => $file['filename'], 'sha256' => hash_file('sha256', $archive), 'bytes' => filesize($archive), 'source_url' => $file['url'] ?: ($config['page_url'] ?? null), 'local_import' => $directory !== null, 'download_method' => $directory !== null ? 'local_file' : ($source === 'nc_sbi' && empty($file['url']) ? 'aspnet_postback' : 'direct_https')];
                $import->update(['file_manifest' => $manifest]);
                if ($source === 'nc_dac') {
                    $paths = $this->files->extract($archive, ['descriptor' => $key.'.des', 'data' => $key.'.dat'], $root);
                    $schema = $this->reader->descriptor($paths['descriptor'], $config['descriptor_hashes'][$key] ?? null);
                    $fields = $config['fields'][$key];
                    $this->validateFields(array_keys($schema), $fields, $file['type']);
                    $manifest[$key]['descriptor_sha256'] = hash_file('sha256', $paths['descriptor']);
                    $manifest[$key]['descriptor'] = $schema;
                    $rows = $this->reader->fixedWidth($paths['data'], $schema, $fields['encoding'] ?? 'Windows-1252');
                } elseif (($config['layout']['format'] ?? '') === 'sbi_public_fixed_width') {
                    $sbi = new SbiPublicDataReader;
                    $members = array_keys(SbiPublicDataReader::TABLES);
                    $paths = $this->files->extract($archive, array_combine($members, $members), $root);
                    foreach (SbiPublicDataReader::TABLES as $member => $table) {
                        $counts[$member] = $this->storeRows($import->id, $member, $table['type'], $sbi->rows($paths[$member], $member), $sbi->fields($table['type']));
                        unlink($paths[$member]);
                    }
                    $manifest[$key]['layout'] = 'sbi_public_fixed_width_2014_10_17';

                    continue;
                } else {
                    $layout = $config['layout'];
                    $paths = ! empty($layout['member']) ? $this->files->extract($archive, ['data' => $layout['member']], $root) : ['data' => $archive];
                    $fields = $layout['fields'];
                    $this->validateFields($layout['headers'], $fields, 'profile');
                    $rows = $layout['format'] === 'csv' ? $this->reader->csv($paths['data'], $layout) : $this->reader->xml($paths['data'], $layout);
                }
                $counts[$key] = $this->storeRows($import->id, $key, $file['type'], $rows, $fields);
                foreach ($paths as $path) {
                    if ($path !== $archive) {
                        unlink($path);
                    }
                }
            }
            $recordCount = DB::table('screening_records')->where('import_id', $import->id)->where('record_type', 'profile')->count();
            foreach ($counts as $key => $count) {
                if ($count < 1) {
                    throw new RuntimeException("Dataset {$key} is empty; import not activated.");
                }
            }
            $orphans = DB::table('screening_records as child')->where('child.import_id', $import->id)->whereNotNull('child.parent_record_id')->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('screening_records as parent')->whereColumn('parent.import_id', 'child.import_id')->where('parent.record_type', 'profile')->whereColumn('parent.source_record_id', 'child.parent_record_id');
            })->exists();
            if ($orphans) {
                throw new RuntimeException('Source supporting records refer to missing profiles; import not activated.');
            }
            $missingNames = DB::table('screening_records as profile')->where('profile.import_id', $import->id)->where('profile.record_type', 'profile')->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('screening_record_names as names')->whereColumn('names.record_id', 'profile.id');
            })->count();
            $sourceOnly = DB::table('screening_records')->where('import_id', $import->id)->where('record_type', 'profile')->where('data->missing_primary_record', true)->count();
            if ($recordCount - $sourceOnly < (int) $config['minimum_records']) {
                throw new RuntimeException('Primary profile count is below the configured source minimum; import not activated.');
            }
            $limitations = [];
            if ($sourceOnly) {
                $limitations[] = "{$sourceOnly} source identifiers have related records but no primary record in the official extract; their available names and evidence are retained.";
            }
            if ($missingNames) {
                $limitations[] = "{$missingNames} source identifiers have no published name usable by this name search. A zero-hit result covers available names only.";
            }
            $manifest['name_coverage'] = ['profile_count' => $recordCount, 'primary_profiles' => $recordCount - $sourceOnly, 'source_only_profiles' => $sourceOnly, 'searchable_profiles' => $recordCount - $missingNames, 'profiles_without_names' => $missingNames, 'limitations' => $limitations];
            $import->update(['file_manifest' => $manifest, 'row_counts' => $counts, 'record_count' => $recordCount, 'checksum' => hash('sha256', json_encode(array_column($manifest, 'sha256'), JSON_THROW_ON_ERROR)), 'downloaded_at' => $observedAt, 'validated_at' => now()]);
            DB::transaction(function () use ($import, $counts) {
                $previous = ScreeningDatasetImport::where('source', $import->source)->where('is_active', true)->lockForUpdate()->first();
                foreach ($previous?->row_counts ?? [] as $key => $previousCount) {
                    if (($counts[$key] ?? 0) < $previousCount * (float) config('screening.minimum_retained_ratio', 0.8)) {
                        throw new RuntimeException('Dataset record count dropped unexpectedly; review before activation.');
                    }
                }
                ScreeningDatasetImport::where('source', $import->source)->where('is_active', true)->update(['is_active' => false]);
                $import->update(['status' => 'ready', 'is_active' => true, 'activated_at' => now()]);
            });
            $activated = true;

            return $import;
        } catch (\Throwable $exception) {
            // Keep raw input and the failed attempt for diagnosis, but not partial searchable rows.
            if ($import && ! $activated) {
                DB::table('screening_record_names')->where('import_id', $import->id)->delete();
                DB::table('screening_records')->where('import_id', $import->id)->delete();
                // Query exceptions can include source personal data in their SQL bindings.
                $message = get_class($exception) === RuntimeException::class ? $exception->getMessage() : 'Import failed during download, parsing, or database write; inspect server diagnostics.';
                $import->update(['status' => 'failed', 'is_active' => false, 'error' => mb_substr($message, 0, 2000)]);
            }
            throw $exception;
        } finally {
            $lock->release();
        }
    }

    private function validateConfiguration(string $source, array $config): void
    {
        if ($source === 'nc_dac') {
            foreach ($config['files'] as $key => $file) {
                if (empty($config['fields'][$key]['offender_id'])) {
                    throw new RuntimeException("Configure verified DAC field mappings for {$key} in SCREENING_DAC_FIELDS.");
                }
            }
            if (! collect($config['fields'])->contains(fn ($fields) => ! empty($fields['full_name']) || (! empty($fields['first_name']) && ! empty($fields['last_name'])))) {
                throw new RuntimeException('Configure verified DAC name fields on at least one source table before importing.');
            }
        } else {
            $layout = $config['layout'] ?? [];
            if (($layout['format'] ?? '') === 'sbi_public_fixed_width') {
                return;
            }
            if (! in_array($layout['format'] ?? '', ['csv', 'xml'], true) || empty($layout['headers']) || empty($layout['fields']['offender_id']) || (($layout['format'] ?? '') === 'xml' && empty($layout['record_element']))) {
                throw new RuntimeException('Configure the verified official SBI layout in SCREENING_SBI_LAYOUT before importing.');
            }
            if (empty($layout['fields']['full_name']) && (empty($layout['fields']['first_name']) || empty($layout['fields']['last_name']))) {
                throw new RuntimeException('Configure verified SBI name fields before importing.');
            }
        }
    }

    private function validateFields(array $columns, array $fields, string $type): void
    {
        $required = [$fields['offender_id']];
        if ($type === 'profile' || ! empty($fields['full_name']) || ! empty($fields['first_name'])) {
            foreach (['first_name', 'middle_name', 'last_name', 'full_name', 'date_of_birth'] as $key) {
                if (! empty($fields[$key])) {
                    $required[] = $fields[$key];
                }
            }
            $required = array_merge($required, $fields['aliases'] ?? []);
        }
        if (array_diff($required, $columns)) {
            throw new RuntimeException('Configured identity fields are missing from the public-data schema.');
        }
    }

    private function storeRows(int $importId, string $table, string $type, iterable $rows, array $fields): int
    {
        $batch = [];
        $count = 0;
        foreach ($rows as $row) {
            $count++;
            $offenderId = trim($row[$fields['offender_id']] ?? '');
            if ($offenderId === '' || strlen($offenderId) > 100 || preg_match('/[\x00-\x1f]/', $offenderId)) {
                throw new RuntimeException("Invalid offender identifier in {$table} record {$count}.");
            }
            $hasNames = ! empty($fields['full_name']) || (! empty($fields['first_name']) && ! empty($fields['last_name']));
            $availableName = trim(implode(' ', array_map(fn ($key) => $row[$fields[$key] ?? ''] ?? '', ['full_name', 'first_name', 'middle_name', 'last_name'])));
            $names = $hasNames && $availableName !== '' && $availableName !== 'NULL' ? NameNormalizer::names($row, $fields) : [];
            if ($type !== 'profile') {
                foreach ($names as &$name) {
                    $name['is_alias'] = true;
                }
                unset($name);
            }
            // Preserve repeated official rows, with a stable ordinal within this source file.
            $sourceId = $type === 'profile' ? $offenderId : hash('sha256', $table.'|'.$count);
            $data = ['table' => $table, 'fields' => $row];
            if ($type === 'profile') {
                $data['name'] = $names[0]['display_name'] ?? null;
                $data['date_of_birth'] = $row[$fields['date_of_birth'] ?? ''] ?? null;
            }
            $batch[] = ['offender_id' => $offenderId, 'record' => ['import_id' => $importId, 'record_type' => $type, 'source_record_id' => $sourceId, 'parent_record_id' => $type === 'profile' ? null : $offenderId, 'data' => json_encode($data, JSON_THROW_ON_ERROR)], 'names' => $names];
            if (count($batch) === 500) {
                $this->flush($batch, $importId, $type);
                $batch = [];
            }
        }
        $this->flush($batch, $importId, $type);

        return $count;
    }

    private function flush(array $batch, int $importId, string $type): void
    {
        if ($batch === []) {
            return;
        }
        DB::transaction(function () use ($batch, $importId) {
            DB::table('screening_records')->insert(array_column($batch, 'record'));
            $ids = DB::table('screening_records')->where('import_id', $importId)->where('record_type', 'profile')->whereIn('source_record_id', array_column($batch, 'offender_id'))->pluck('id', 'source_record_id');
            // Official public exports contain related IDs absent from the primary table.
            // Preserve these identities and their evidence explicitly; never discard their names.
            $missing = array_diff(array_unique(array_column($batch, 'offender_id')), $ids->keys()->all());
            foreach ($missing as $offenderId) {
                $ids[$offenderId] = DB::table('screening_records')->insertGetId([
                    'import_id' => $importId, 'record_type' => 'profile', 'source_record_id' => $offenderId,
                    'parent_record_id' => null, 'data' => json_encode(['missing_primary_record' => true, 'name' => null, 'date_of_birth' => null, 'fields' => [], 'source_record_id' => $offenderId], JSON_THROW_ON_ERROR),
                ]);
            }
            $names = [];
            foreach ($batch as $entry) {
                foreach ($entry['names'] as $name) {
                    if (! isset($ids[$entry['offender_id']])) {
                        throw new RuntimeException('Source names refer to missing profiles; import not activated.');
                    }
                    $names[] = array_merge($name, ['import_id' => $importId, 'record_id' => $ids[$entry['offender_id']]]);
                }
            }
            foreach (array_chunk($names, 500) as $chunk) {
                // Repeated commitment/alias names point to one searchable identity per profile.
                DB::table('screening_record_names')->insertOrIgnore($chunk);
            }
        });
    }
}
