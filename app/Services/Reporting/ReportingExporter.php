<?php

namespace App\Services\Reporting;

use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use RuntimeException;

final class ReportingExporter
{
    public function catalog(Connection $db): array
    {
        $schema = $db->getSchemaBuilder();
        $tables = $schema->getTableListing(null, false);
        $result = [];
        foreach (ReportingCatalog::datasets() as $name => $definition) {
            $columns = in_array($name, $tables, true) ? $schema->getColumnListing($name) : [];
            $available = $columns !== [] && array_diff($definition['key'], $columns) === [];
            $missing = array_values(array_diff($definition['fields'], $columns));
            if ($definition['essential_fields'] && (! $available || array_diff($definition['essential_fields'], $columns))) {
                throw new RuntimeException('Required reporting schema is unavailable.');
            }
            $result[$name] = $definition + ['available' => $available, 'missing_fields' => $missing];
            $result[$name]['fields'] = $available ? array_values(array_intersect($definition['fields'], $columns)) : [];
        }

        return $result;
    }

    public function prepare(Connection $db, ReportingConnection $connections): array
    {
        $db->disableQueryLog();
        $connections->beginSnapshot($db, array_keys(ReportingCatalog::datasets()));

        return [
            'type' => 'manifest',
            'schema_version' => ReportingCatalog::VERSION,
            'snapshot_id' => (string) Str::uuid(),
            'started_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'source_host' => parse_url((string) config('app.url'), PHP_URL_HOST),
            'database_timezone' => config('app.timezone'),
            'application_timezone' => config('app.timezone'),
            'timestamp_format' => 'stored; database_timezone unless listed in utc_fields; dates/schedules stay local',
            'redaction_policy' => 'business-fields-v1; URLs/recognized credentials removed; unknown JSON keys omitted',
            'datasets' => $this->catalog($db),
        ];
    }

    /** The final checksum covers every preceding byte, including newline delimiters. */
    public function stream(Connection $db, array $manifest, callable $write): void
    {
        $started = microtime(true);
        $globalHash = hash_init('sha256');
        $emit = static function (array $frame) use ($write, $globalHash): string {
            $line = json_encode($frame, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
            hash_update($globalHash, $line);
            $write($line);

            return $line;
        };
        $checkBudget = static function () use ($started): void {
            if (microtime(true) - $started > max(1, (int) config('reporting.max_seconds')) || connection_aborted()) {
                throw new RuntimeException('Reporting export interrupted.');
            }
        };
        $emit($manifest);
        $counts = [];
        foreach ($manifest['datasets'] as $name => $definition) {
            $checkBudget();
            $count = 0;
            $hash = hash_init('sha256');
            $redactor = new ReportingRedactor;
            if ($definition['available']) {
                $query = $db->table($name)->select($definition['fields']);
                foreach ($definition['key'] as $key) {
                    $query->orderBy($key);
                }
                // PDO cursors can buffer MySQL results; keyset chunks bound PHP memory instead.
                if ($definition['key'] === ['id']) {
                    $records = $query->lazyById((int) config('reporting.chunk_size', 500));
                } else {
                    // Small pivot tables use ordered chunks within the same immutable read view.
                    $records = $query->lazy((int) config('reporting.chunk_size', 500));
                }
                foreach ($records as $row) {
                    $checkBudget();
                    $line = $emit(['type' => 'record', 'dataset' => $name, 'data' => $redactor->record($row, $definition['json_fields'])]);
                    hash_update($hash, $line);
                    $count++;
                }
            }
            $counts[$name] = $count;
            $emit(['type' => 'dataset_end', 'dataset' => $name, 'count' => $count,
                'sha256' => hash_final($hash), 'omitted_json_keys' => $redactor->omittedKeys,
                'redacted_strings' => $redactor->redactedStrings]);
        }
        $write(json_encode(['type' => 'complete', 'snapshot_id' => $manifest['snapshot_id'],
            'finished_at' => gmdate('Y-m-d\TH:i:s\Z'), 'counts' => $counts,
            'sha256' => hash_final($globalHash)], JSON_THROW_ON_ERROR)."\n");
    }
}
