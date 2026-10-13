<?php

namespace Tests\Feature\Screening;

use App\Models\CaregiverScreeningCandidate;
use App\Models\CaregiverScreeningCase;
use App\Models\ScreeningAuditEvent;
use App\Models\ScreeningDatasetImport;
use App\Services\Screening\Imports\ScreeningDatasetRetentionService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class ScreeningDatasetRetentionTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        // Never connect this destructive-maintenance suite to any existing database.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        $this->travelTo(now()->startOfSecond());
        Schema::create('users', fn (Blueprint $table) => $table->id());
        Schema::create('caregiver_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('status');
        });
        Schema::create('caregiver_identity_verifications', fn (Blueprint $table) => $table->id());
        (require database_path('migrations/2026_10_13_000000_create_caregiver_screening.php'))->up();
        (require database_path('migrations/2026_10_13_000001_create_screening_datasets.php'))->up();
        $this->directory = storage_path('framework/testing/screening-retention-'.bin2hex(random_bytes(8)));
        File::makeDirectory($this->directory, 0700, true);
        config(['screening.private_directory' => $this->directory, 'screening.audit_directory' => $this->directory.'/audit']);
    }

    protected function tearDown(): void
    {
        if (isset($this->directory)) {
            File::deleteDirectory($this->directory);
        }
        $this->travelBack();
        parent::tearDown();
    }

    public function test_default_command_only_previews_eligible_rows(): void
    {
        $old = $this->import('nc_dac', 8);
        $this->import('nc_dac', 4);
        $this->import('nc_dac', 1, ['is_active' => true]);
        $this->rows($old, 2);

        $this->assertSame([['import_id' => $old->id, 'source' => 'nc_dac', 'names' => 2, 'records' => 2]], app(ScreeningDatasetRetentionService::class)->preview());
        $this->artisan('screening:prune-indexes')->expectsOutput('Preview only; no data changed. Add --execute to remove the listed searchable rows.')->assertSuccessful();
        $this->assertSame(2, DB::table('screening_records')->count());
        $this->assertSame(2, DB::table('screening_record_names')->count());
    }

    public function test_no_eligible_rows_is_a_successful_no_op(): void
    {
        $previous = $this->import('nc_dac', 10);
        $this->rows($previous);
        $this->artisan('screening:prune-indexes', ['--execute' => true])->expectsOutput('No eligible inactive dataset indexes.')->assertSuccessful();
        $this->assertSame(1, DB::table('screening_records')->count());
    }

    public function test_active_previous_recent_failed_importing_and_unvalidated_versions_are_protected(): void
    {
        $old = $this->import('nc_dac', 12);
        $protected = [
            $this->import('nc_dac', 10, ['is_active' => true]),
            $this->import('nc_dac', 3),
            $this->import('nc_dac', 8, ['updated_at' => now()->subHours(47)]),
            $this->import('nc_dac', 15, ['status' => 'importing']),
            $this->import('nc_dac', 15, ['status' => 'failed']),
            $this->import('nc_dac', 15, ['validated_at' => null]),
            $this->import('nc_dac', 15, ['activated_at' => null]),
            $this->import('nc_sbi', 15),
        ];
        foreach ([$old, ...$protected] as $import) {
            $this->rows($import);
        }

        $this->assertSame([['import_id' => $old->id, 'source' => 'nc_dac', 'names' => 1, 'records' => 1]], app(ScreeningDatasetRetentionService::class)->execute());
        foreach ($protected as $import) {
            $this->assertSame(1, DB::table('screening_records')->where('import_id', $import->id)->count());
            $this->assertSame(1, DB::table('screening_record_names')->where('import_id', $import->id)->count());
        }
    }

    public function test_interruption_commits_only_bounded_batches_and_retry_removes_the_remainder(): void
    {
        $old = $this->import('nc_dac', 8);
        $this->import('nc_dac', 4);
        $this->rows($old, 1001);
        $deletes = 0;
        DB::listen(function (QueryExecuted $query) use (&$deletes): void {
            if (str_starts_with($query->sql, 'delete from "screening_record_names"') && ++$deletes === 2) {
                throw new RuntimeException('Simulated interruption during the second batch.');
            }
        });
        try {
            app(ScreeningDatasetRetentionService::class)->execute('nc_dac');
            $this->fail('Expected the simulated batch interruption.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Simulated interruption', $error->getMessage());
        }
        $this->assertSame(1, DB::table('screening_record_names')->where('import_id', $old->id)->count());
        $this->assertSame(1001, DB::table('screening_records')->where('import_id', $old->id)->count());
        $result = app(ScreeningDatasetRetentionService::class)->execute('nc_dac');
        $this->assertSame(1, $result[0]['names']);
        $this->assertSame(1001, $result[0]['records']);
        $this->assertSame(0, DB::table('screening_records')->count());
        $this->assertSame([], app(ScreeningDatasetRetentionService::class)->execute('nc_dac'));
    }

    public function test_archives_metadata_case_snapshots_and_audit_evidence_survive_cleanup(): void
    {
        $old = $this->import('nc_dac', 8, ['file_manifest' => ['name_coverage' => ['profiles_without_names' => 2]], 'checksum' => str_repeat('a', 64)]);
        $this->import('nc_dac', 4);
        $this->rows($old);
        $case = CaregiverScreeningCase::create(['kind' => 'legacy', 'status' => 'completed', 'due_at' => now(), 'source_versions' => ['nc_dac' => $old->id]]);
        $snapshot = ['profile' => ['fields' => ['name' => 'Jane Example']], 'related' => ['sentence' => [['fields' => ['evidence' => 'Retained evidence']]]], 'import_id' => $old->id];
        $candidate = CaregiverScreeningCandidate::create(['screening_case_id' => $case->id, 'source' => 'nc_dac', 'source_record_id' => 'person-1', 'matched_name' => 'Jane Example', 'match_reason' => 'Exact name', 'record_snapshot' => $snapshot]);
        $audit = ScreeningAuditEvent::create(['event_id' => (string) Str::uuid(), 'screening_case_id' => $case->id, 'event_type' => 'search_completed', 'occurred_at' => now(), 'payload' => ['candidates' => [$snapshot], 'source_versions' => ['nc_dac' => $old->id]]]);
        File::makeDirectory($this->directory.'/imports/'.$old->id, 0700, true);
        File::makeDirectory($this->directory.'/audit', 0700, true);
        File::put($this->directory.'/imports/'.$old->id.'/OFNT3AA1.zip', 'original archive sentinel');
        File::put($this->directory.'/audit/evidence.jsonl', "original audit sentinel\n");
        $metadata = $old->fresh()->getRawOriginal();
        $auditBefore = $audit->fresh()->getRawOriginal();

        app(ScreeningDatasetRetentionService::class)->execute('nc_dac');

        $this->assertSame(0, DB::table('screening_records')->where('import_id', $old->id)->count());
        $this->assertSame($metadata, $old->fresh()->getRawOriginal());
        $this->assertSame(['nc_dac' => $old->id], $case->fresh()->source_versions);
        $this->assertSame($snapshot, $candidate->fresh()->record_snapshot);
        $this->assertSame($auditBefore, $audit->fresh()->getRawOriginal());
        $this->assertSame('original archive sentinel', File::get($this->directory.'/imports/'.$old->id.'/OFNT3AA1.zip'));
        $this->assertSame("original audit sentinel\n", File::get($this->directory.'/audit/evidence.jsonl'));
    }

    public function test_import_lock_contention_prevents_deletion(): void
    {
        $old = $this->import('nc_dac', 8);
        $this->import('nc_dac', 4);
        $this->rows($old);
        $lock = Cache::lock('screening-import:nc_dac', 21600);
        $this->assertTrue($lock->get());
        try {
            $this->artisan('screening:prune-indexes', ['source' => 'nc_dac', '--execute' => true])
                ->expectsOutput('An import or index cleanup for nc_dac is already running.')->assertFailed();
            $this->assertSame(1, DB::table('screening_records')->count());
            $this->assertSame(1, DB::table('screening_record_names')->count());
        } finally {
            $lock->release();
        }
    }

    public function test_row_state_is_rechecked_after_the_eligible_list_is_built(): void
    {
        $old = $this->import('nc_dac', 8);
        $this->import('nc_dac', 4);
        $this->rows($old);
        $changed = false;
        DB::listen(function (QueryExecuted $query) use ($old, &$changed): void {
            if (! $changed && str_starts_with($query->sql, 'select "id" from "screening_dataset_imports"') && str_contains($query->sql, '"updated_at" <=')) {
                $changed = true;
                DB::table('screening_dataset_imports')->where('id', $old->id)->update(['is_active' => true]);
            }
        });

        $this->assertSame([], app(ScreeningDatasetRetentionService::class)->execute('nc_dac'));
        $this->assertTrue($changed);
        $this->assertSame(1, DB::table('screening_records')->count());
        $this->assertSame(1, DB::table('screening_record_names')->count());
    }

    private function import(string $source, int $daysAgo, array $overrides = []): ScreeningDatasetImport
    {
        $date = now()->subDays($daysAgo);

        return ScreeningDatasetImport::create(array_merge(['source' => $source, 'status' => 'ready', 'is_active' => false,
            'validated_at' => $date, 'activated_at' => $date, 'downloaded_at' => $date, 'record_count' => 1,
            'created_at' => $date, 'updated_at' => $date], $overrides));
    }

    private function rows(ScreeningDatasetImport $import, int $count = 1): void
    {
        $records = [];
        for ($i = 1; $i <= $count; $i++) {
            $records[] = ['import_id' => $import->id, 'record_type' => 'profile', 'source_record_id' => 'person-'.$i, 'data' => json_encode(['name' => 'Jane Example'], JSON_THROW_ON_ERROR)];
        }
        foreach (array_chunk($records, 500) as $batch) {
            DB::table('screening_records')->insert($batch);
        }
        $names = DB::table('screening_records')->where('import_id', $import->id)->pluck('id')->map(fn ($id) => ['import_id' => $import->id, 'record_id' => $id, 'display_name' => 'Jane Example', 'normalized_name' => 'jane example', 'first_name' => 'jane', 'last_name' => 'example'])->all();
        foreach (array_chunk($names, 500) as $batch) {
            DB::table('screening_record_names')->insert($batch);
        }
    }
}
