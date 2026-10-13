<?php

namespace App\Services\Screening\Imports;

use App\Models\ScreeningDatasetImport;
use Illuminate\Cache\Lock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ScreeningDatasetRetentionService
{
    private const GRACE_HOURS = 48;

    private const BATCH_SIZE = 1000;

    /** @return array<int, array{import_id:int, source:string, names:int, records:int}> */
    public function preview(?string $source = null): array
    {
        $results = [];
        $cutoff = now()->subHours(self::GRACE_HOURS);
        foreach ($this->sources($source) as $source) {
            foreach ($this->eligible($source, $cutoff)->orderBy('id')->get(['id', 'source']) as $import) {
                $result = $this->counts($import);
                if ($result['names'] + $result['records'] > 0) {
                    $results[] = $result;
                }
            }
        }

        return $results;
    }

    /** @return array<int, array{import_id:int, source:string, names:int, records:int}> */
    public function execute(?string $source = null): array
    {
        $results = [];
        $cutoff = now()->subHours(self::GRACE_HOURS);
        foreach ($this->sources($source) as $source) {
            // Use the importer's lock, so maintenance cannot overlap source activation.
            $lock = Cache::lock('screening-import:'.$source, 21600);
            if (! $lock->get()) {
                throw new RuntimeException("An import or index cleanup for {$source} is already running.");
            }
            try {
                $ids = $this->eligible($source, $cutoff)->orderBy('id')->pluck('id');
                foreach ($ids as $id) {
                    $result = ['import_id' => (int) $id, 'source' => $source, 'names' => 0, 'records' => 0];
                    foreach (['screening_record_names' => 'names', 'screening_records' => 'records'] as $table => $counter) {
                        do {
                            $deleted = $this->deleteBatch((int) $id, $source, $table, $cutoff, $lock);
                            $result[$counter] += $deleted;
                        } while ($deleted > 0);
                    }
                    if ($result['names'] + $result['records'] > 0) {
                        $results[] = $result;
                    }
                }
            } finally {
                $lock->release();
            }
        }

        return $results;
    }

    private function deleteBatch(int $id, string $source, string $table, Carbon $cutoff, Lock $lock): int
    {
        return DB::transaction(function () use ($id, $source, $table, $cutoff, $lock): int {
            if (! $lock->isOwnedByCurrentProcess()) {
                throw new RuntimeException('The source maintenance lock expired; rerun cleanup to resume safely.');
            }
            $import = ScreeningDatasetImport::whereKey($id)->lockForUpdate()->first();
            if (! $import || $import->source !== $source || $import->is_active || $import->status !== 'ready'
                || ! $import->validated_at || ! $import->activated_at || ! $import->updated_at
                || $import->activated_at->gt($cutoff) || $import->updated_at->gt($cutoff)
                || $id === $this->previousId($source, true)) {
                return 0;
            }
            $ids = DB::table($table)->where('import_id', $id)->orderBy('id')->limit(self::BATCH_SIZE)->pluck('id');
            if ($ids->isEmpty()) {
                return 0;
            }

            return DB::table($table)->where('import_id', $id)->whereIn('id', $ids)->delete();
        }, 3);
    }

    private function eligible(string $source, Carbon $cutoff): Builder
    {
        $previousId = $this->previousId($source);

        return ScreeningDatasetImport::where('source', $source)->where('is_active', false)->where('status', 'ready')
            ->whereNotNull('validated_at')->whereNotNull('activated_at')
            // The importer updates updated_at when it deactivates a version. A later
            // metadata update conservatively restarts this grace period.
            ->where('activated_at', '<=', $cutoff)->where('updated_at', '<=', $cutoff)
            ->when($previousId, fn (Builder $query) => $query->whereKeyNot($previousId));
    }

    private function previousId(string $source, bool $lock = false): ?int
    {
        $id = ScreeningDatasetImport::where('source', $source)->where('is_active', false)->where('status', 'ready')
            ->whereNotNull('validated_at')->whereNotNull('activated_at')
            ->orderByDesc('activated_at')->orderByDesc('id')
            ->when($lock, fn (Builder $query) => $query->lockForUpdate())->value('id');

        return $id === null ? null : (int) $id;
    }

    private function counts(ScreeningDatasetImport $import): array
    {
        return ['import_id' => (int) $import->id, 'source' => $import->source,
            'names' => DB::table('screening_record_names')->where('import_id', $import->id)->count(),
            'records' => DB::table('screening_records')->where('import_id', $import->id)->count()];
    }

    private function sources(?string $source): array
    {
        if ($source !== null && ! in_array($source, ['nc_dac', 'nc_sbi'], true)) {
            throw new RuntimeException('Choose nc_dac or nc_sbi, or omit the source to review both.');
        }

        return $source === null ? ['nc_dac', 'nc_sbi'] : [$source];
    }
}
