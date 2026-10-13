<?php

namespace Tests\Feature\Screening;

use App\Models\ScreeningDatasetImport;
use App\Services\Screening\Imports\PublicDataReader;
use App\Services\Screening\Imports\SbiPublicDataDownloader;
use App\Services\Screening\Imports\SbiPublicDataReader;
use App\Services\Screening\Imports\ScreeningDatasetImporter;
use App\Services\Screening\ScreeningMatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class ScreeningDatasetImportTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        // This suite never migrates or imports into the application's configured database.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        (require database_path('migrations/2026_10_13_000001_create_screening_datasets.php'))->up();
        $this->directory = storage_path('framework/testing/screening-'.bin2hex(random_bytes(8)));
        File::makeDirectory($this->directory, 0700, true);
        config([
            'screening.private_directory' => $this->directory.'/private',
            'screening.sources.nc_dac.minimum_records' => 1,
            'screening.sources.nc_dac.descriptor_hashes' => [],
            'screening.sources.nc_sbi.minimum_records' => 1,
            'screening.sources.nc_sbi.files.public.filename' => 'public-data.zip',
            'screening.sources.nc_dac.fields' => [
                'OFNT3AA1' => ['offender_id' => 'CMDORNUM', 'first_name' => 'FIRST', 'last_name' => 'LAST', 'aliases' => ['ALIAS']],
                'OFNT3BB1' => ['offender_id' => 'CMDORNUM'],
                'OFNT3CE1' => ['offender_id' => 'CMDORNUM'],
                'APPT7AA1' => ['offender_id' => 'CMDORNUM'],
            ],
            'screening.sources.nc_sbi.layout' => ['format' => 'csv', 'member' => 'public.csv', 'headers' => ['ID', 'FIRST', 'LAST', 'ALIAS'], 'fields' => ['offender_id' => 'ID', 'first_name' => 'FIRST', 'last_name' => 'LAST', 'aliases' => ['ALIAS']]],
        ]);
        $this->fixtures();
    }

    protected function tearDown(): void
    {
        if (isset($this->directory)) {
            File::deleteDirectory($this->directory);
        }
        parent::tearDown();
    }

    public function test_both_sources_are_staged_validated_and_joined_for_explainable_alias_search(): void
    {
        $dac = $this->import('nc_dac');
        $sbi = $this->import('nc_sbi');
        $result = app(ScreeningMatcher::class)->search(['full_name' => 'Jane O’Neil', 'first_name' => 'Jane', 'last_name' => 'ONeil']);
        $this->assertSame(['nc_dac' => $dac->id, 'nc_sbi' => $sbi->id], $result['source_versions']);
        $this->assertCount(2, $result['candidates']);
        $candidate = collect($result['candidates'])->firstWhere('source', 'nc_dac');
        $this->assertStringContainsString('additional name supplied by the source', $candidate['match_reason']);
        $this->assertCount(1, $candidate['record_snapshot']['related']['commitment']);
        $this->assertCount(1, $candidate['record_snapshot']['related']['sentence']);
        $this->assertCount(1, $candidate['record_snapshot']['related']['probation']);
        $this->assertSame('0000001', $candidate['source_record_id']);
        $this->assertSame(64, strlen($dac->checksum));
        $this->assertFileExists($this->directory.'/private/imports/'.$dac->id.'/OFNT3AA1.zip');
    }

    public function test_zero_hits_are_returned_only_after_every_required_source_is_usable(): void
    {
        $this->import('nc_dac');
        try {
            app(ScreeningMatcher::class)->search(['full_name' => 'Nobody Else']);
            $this->fail('Missing SBI must not appear as No Match.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('nc_sbi', $exception->getMessage());
        }
        $this->import('nc_sbi');
        $this->assertSame([], app(ScreeningMatcher::class)->search(['full_name' => 'Nobody Else'])['candidates']);
    }

    public function test_offset_download_timestamps_keep_their_instant_in_a_non_utc_application(): void
    {
        $originalTimezone = date_default_timezone_get();
        config(['app.timezone' => 'America/New_York']);
        date_default_timezone_set('America/New_York');
        $this->travelTo(\Carbon\Carbon::parse('2026-10-13T11:00:00Z')->setTimezone('America/New_York'));
        try {
            foreach (['nc_dac' => '2026-10-13T10:55:00Z', 'nc_sbi' => '2026-10-13T12:55:00+02:00'] as $source => $timestamp) {
                $import = app(ScreeningDatasetImporter::class)->import($source, $this->directory, $timestamp)->fresh();
                $this->assertSame(\Carbon\Carbon::parse($timestamp)->timestamp, $import->downloaded_at->timestamp);
                $this->assertSame('2026-10-13 06:55:00', $import->getRawOriginal('downloaded_at'));
                $this->assertFalse($import->downloaded_at->isFuture());
            }
            $this->assertSame([], app(ScreeningMatcher::class)->search(['full_name' => 'Nobody Else'])['candidates']);
        } finally {
            $this->travelBack();
            date_default_timezone_set($originalTimezone);
        }
    }

    public function test_invalid_replacement_retains_previous_snapshot_and_cleans_partial_rows(): void
    {
        $previous = $this->import('nc_dac');
        $this->zip('OFNT3CE1.zip', ['OFNT3CE1.des' => 'Unknown layout', 'OFNT3CE1.dat' => 'bad']);
        try {
            $this->import('nc_dac');
            $this->fail('Unknown layouts must fail.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('descriptor', $exception->getMessage());
        }
        $failed = ScreeningDatasetImport::latest('id')->first();
        $this->assertSame('failed', $failed->status);
        $this->assertTrue($previous->fresh()->is_active);
        $this->assertSame(0, DB::table('screening_records')->where('import_id', $failed->id)->count());
        $this->assertSame(0, DB::table('screening_record_names')->where('import_id', $failed->id)->count());
    }

    public function test_missing_primary_record_is_preserved_with_explicit_name_coverage(): void
    {
        $this->zip('OFNT3BB1.zip', ['OFNT3BB1.des' => $this->detailDescriptor(), 'OFNT3BB1.dat' => '9999999AB'."\n"]);
        $import = $this->import('nc_dac');
        $coverage = $import->file_manifest['name_coverage'];
        $this->assertSame(2, $coverage['profile_count']);
        $this->assertSame(1, $coverage['source_only_profiles']);
        $this->assertSame(1, $coverage['profiles_without_names']);
        $this->assertCount(2, $coverage['limitations']);
        $this->assertDatabaseHas('screening_records', ['import_id' => $import->id, 'record_type' => 'profile', 'source_record_id' => '9999999']);
    }

    public function test_source_only_profiles_cannot_hide_a_truncated_primary_file(): void
    {
        config(['screening.sources.nc_dac.minimum_records' => 2]);
        $this->zip('OFNT3BB1.zip', ['OFNT3BB1.des' => $this->detailDescriptor(), 'OFNT3BB1.dat' => '9999999AB'."\n"]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Primary profile count');
        $this->import('nc_dac');
    }

    public function test_identical_full_names_with_different_components_keep_both_first_last_search_paths(): void
    {
        $this->zip('public-data.zip', ['public.csv' => "ID,FIRST,LAST,ALIAS\nSBI001,Mary Ann,Smith,Mary Ann Smith\n"]);
        $this->import('nc_dac');
        $sbi = $this->import('nc_sbi');
        $this->assertSame(2, DB::table('screening_record_names')->where('import_id', $sbi->id)->where('normalized_name', 'mary ann smith')->count());
        foreach (['Mary', 'Mary Ann'] as $first) {
            $result = app(ScreeningMatcher::class)->search(['full_name' => $first.' Smith', 'first_name' => $first, 'last_name' => 'Smith']);
            $this->assertCount(1, $result['candidates']);
            $this->assertSame('SBI001', $result['candidates'][0]['source_record_id']);
        }
    }

    public function test_actual_dac_layout_indexes_names_from_supporting_tables_and_retains_nameless_ids(): void
    {
        $defaults = require config_path('screening.php');
        config(['screening.sources.nc_dac.fields' => $defaults['sources']['nc_dac']['fields'], 'screening.sources.nc_dac.descriptor_hashes' => $defaults['sources']['nc_dac']['descriptor_hashes']]);
        $rows = [
            'OFNT3AA1' => [['CMDORNUM' => '0000001', 'CMCLBRTH' => '1980-01-01']],
            'OFNT3BB1' => [
                ['CMDORNUM' => '0000001', 'CMSCFSTN' => 'JANE', 'CMSCMIDN' => 'M', 'CMSCLSTN' => 'DOE'],
                ['CMDORNUM' => '9999999', 'CMSCFSTN' => 'SYNTHETIC', 'CMSCLSTN' => 'PERSON'],
            ],
            'OFNT3CE1' => [['CMDORNUM' => '0000001'], ['CMDORNUM' => '8888888']],
            'APPT7AA1' => [['CDDORNUM' => '0000001', 'CDSFSTNM' => 'JANE', 'CDSLSTNM' => 'SMITH']],
        ];
        foreach ($rows as $table => $records) {
            $path = base_path('tests/Fixtures/Screening/'.$table.'.des');
            $schema = app(PublicDataReader::class)->descriptor($path);
            $width = max(array_map(fn ($field) => $field['start'] + $field['length'], $schema));
            $data = '';
            foreach ($records as $record) {
                $line = str_repeat(' ', $width);
                foreach ($record as $field => $value) {
                    $line = substr_replace($line, str_pad($value, $schema[$field]['length']), $schema[$field]['start'], $schema[$field]['length']);
                }
                $data .= $line."\n";
            }
            $this->zip($table.'.zip', [$table.'.des' => file_get_contents($path), $table.'.dat' => $data]);
        }
        $dac = $this->import('nc_dac');
        $this->import('nc_sbi');
        $coverage = $dac->file_manifest['name_coverage'];
        $this->assertSame(3, $coverage['profile_count']);
        $this->assertSame(2, $coverage['source_only_profiles']);
        $this->assertSame(2, $coverage['searchable_profiles']);
        $this->assertSame(1, $coverage['profiles_without_names']);
        $result = app(ScreeningMatcher::class)->search(['full_name' => 'Synthetic Person']);
        $this->assertSame($coverage, $result['source_coverage']['nc_dac']);
        $this->assertCount(1, $result['candidates']);
        $this->assertTrue($result['candidates'][0]['record_snapshot']['missing_primary_record']);
        $this->assertNotEmpty($result['candidates'][0]['record_snapshot']['coverage_warning']);
        $this->assertNull($result['candidates'][0]['record_snapshot']['profile']['name']);
        $this->assertNotEmpty($result['candidates'][0]['record_snapshot']['field_labels']['OFNT3BB1']['CMSCFSTN']);
        $this->assertCount(1, app(ScreeningMatcher::class)->search(['full_name' => 'Jane Smith'])['candidates']);
        $this->assertCount(1, app(ScreeningMatcher::class)->search(['full_name' => 'Jane M Doe'])['candidates']);
    }

    public function test_actual_sbi_public_fixed_width_layout_indexes_aliases_and_reports_source_only_coverage(): void
    {
        config(['screening.sources.nc_sbi.layout' => ['format' => 'sbi_public_fixed_width'], 'screening.sources.nc_sbi.files.public.filename' => 'publicsor.zip']);
        $contents = [];
        foreach (SbiPublicDataReader::TABLES as $member => $table) {
            $columns = $table['columns'];
            $header = implode(' ', array_map(fn ($name, $length) => str_pad($name, $length), array_keys($columns), $columns));
            $ruler = implode(' ', array_map(fn ($length) => str_repeat('-', $length), $columns));
            $row = ['SexRegistrationNumber' => 'SYNTHETIC001', 'FullName' => 'DOE,JANE', 'PrimaryBirthDate' => 'Jan  1 1980 12:00AM'];
            $records = [$row];
            if ($table['type'] === 'alias') {
                $records[] = ['SexRegistrationNumber' => 'SYNTHETIC002', 'FullName' => 'PERSON,SYNTHETIC'];
            }
            if ($table['type'] === 'violation') {
                $records[] = ['SexRegistrationNumber' => 'SYNTHETIC003', 'ViolationDescription' => 'SYNTHETIC TEST EVIDENCE'];
            }
            $contents[$member] = $header."\r\n".$ruler."\r\n";
            foreach ($records as $record) {
                $contents[$member] .= implode(' ', array_map(fn ($name, $length) => str_pad($record[$name] ?? '', $length), array_keys($columns), $columns))."\r\n";
            }
        }
        $this->zip('publicsor.zip', $contents);
        $this->import('nc_dac');
        $sbi = $this->import('nc_sbi');
        $this->assertCount(9, $sbi->row_counts);
        $this->assertSame(3, $sbi->record_count);
        $this->assertSame(1, $sbi->file_manifest['name_coverage']['profiles_without_names']);
        $result = app(ScreeningMatcher::class)->search(['full_name' => 'Synthetic Person']);
        $this->assertCount(1, $result['candidates']);
        $this->assertTrue($result['candidates'][0]['record_snapshot']['missing_primary_record']);
        $this->assertSame('SYNTHETIC002', $result['candidates'][0]['source_record_id']);
        $this->assertCount(1, $result['candidates'][0]['record_snapshot']['related']['alias']);
        $this->assertSame(1, DB::table('screening_record_names')->where('import_id', $sbi->id)->where('normalized_name', 'jane doe')->count());
    }

    public function test_bom_before_a_quoted_csv_header_is_parsed_without_hiding_the_header_quotes(): void
    {
        $this->zip('public-data.zip', ['public.csv' => "\xEF\xBB\xBF\"ID\",\"FIRST\",\"LAST\",\"ALIAS\"\r\n\"1\",\"Jane\",\"Doe\",\"\"\r\n"]);
        $this->assertTrue($this->import('nc_sbi')->is_active);
    }

    public function test_single_token_identity_with_missing_surname_cannot_produce_a_zero_hit_result(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('verified legal name');
        app(ScreeningMatcher::class)->search(['full_name' => 'Jane', 'first_name' => 'Jane', 'last_name' => '']);
    }

    public function test_sbi_import_uses_official_postback_adapter_when_no_direct_url_is_configured(): void
    {
        config(['screening.sources.nc_sbi.files.public.url' => null]);
        $this->mock(SbiPublicDataDownloader::class)->shouldReceive('download')->once()->andReturnUsing(function (string $destination): void {
            copy($this->directory.'/public-data.zip', $destination);
        });
        $import = app(ScreeningDatasetImporter::class)->import('nc_sbi');
        $this->assertTrue($import->is_active);
        $this->assertSame('aspnet_postback', $import->file_manifest['public']['download_method']);
        $this->assertSame(SbiPublicDataDownloader::PAGE_URL, $import->file_manifest['public']['source_url']);
        $this->assertSame(SbiPublicDataDownloader::PAGE_URL, $import->source_urls['public']);
    }

    public function test_import_command_does_not_log_source_records_from_database_exceptions(): void
    {
        $exception = new \Illuminate\Database\QueryException('sqlite', 'insert into example values (?)', ['PRIVATE_SOURCE_ROW'], new \RuntimeException('database issue'));
        $this->mock(ScreeningDatasetImporter::class)->shouldReceive('import')->once()->andThrow($exception);
        \Illuminate\Support\Facades\Log::shouldReceive('error')->once()->withArgs(function (string $message, array $context) use ($exception): bool {
            return $context['source'] === 'nc_sbi'
                && $context['exception_class'] === \Illuminate\Database\QueryException::class
                && $context['exception_file'] === basename($exception->getFile())
                && $context['exception_line'] === $exception->getLine()
                && ! str_contains(json_encode([$message, $context]), 'PRIVATE_SOURCE_ROW');
        });
        $this->artisan('screening:import', ['source' => 'nc_sbi'])
            ->expectsOutput('Import failed during download, parsing, or database write. The previous snapshot remains active.')
            ->assertFailed();
    }

    public function test_unsafe_zip_paths_are_rejected_without_writing_outside_archive_directory(): void
    {
        $this->zip('public-data.zip', ['../escaped.csv' => 'bad', 'public.csv' => "ID,FIRST,LAST,ALIAS\n1,Jane,Doe,\n"]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsafe path');
        $this->import('nc_sbi');
    }

    public function test_stale_and_changed_versions_cannot_finalize_a_review(): void
    {
        $dac = $this->import('nc_dac');
        $sbi = $this->import('nc_sbi');
        $versions = ['nc_dac' => $dac->id, 'nc_sbi' => $sbi->id];
        app(ScreeningMatcher::class)->assertUsableVersions($versions);
        $this->import('nc_sbi');
        try {
            app(ScreeningMatcher::class)->assertUsableVersions($versions);
            $this->fail('Changed versions must require another search.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('changed', $exception->getMessage());
        }
        $dac->update(['downloaded_at' => now()->subDays(15)]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('stale');
        app(ScreeningMatcher::class)->search(['full_name' => 'Jane Doe']);
    }

    public function test_candidate_overflow_is_an_incomplete_search_instead_of_silent_truncation(): void
    {
        $this->import('nc_dac');
        $this->import('nc_sbi');
        config(['screening.max_candidates' => 1]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Too many name candidates');
        app(ScreeningMatcher::class)->search(['full_name' => 'Jane ONeil']);
    }

    public function test_overlapping_full_and_component_lookups_count_each_name_only_once(): void
    {
        $this->import('nc_dac');
        $this->import('nc_sbi');
        config(['screening.max_candidates' => 1]);
        $result = app(ScreeningMatcher::class)->search(['full_name' => 'Jane Doe', 'first_name' => 'Jane', 'last_name' => 'Doe']);
        $this->assertCount(1, $result['candidates']);
        $this->assertCount(1, $result['candidates'][0]['record_snapshot']['matched_names']);
    }

    public function test_combined_disjoint_full_and_component_hits_enforce_the_complete_search_limit(): void
    {
        $this->zip('public-data.zip', ['public.csv' => "ID,FIRST,LAST,ALIAS\nSBI001,Mary Ann,Smith,\nSBI002,Mary,Smith,\n"]);
        $this->import('nc_dac');
        $this->import('nc_sbi');
        config(['screening.max_candidates' => 1]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Too many name candidates');
        app(ScreeningMatcher::class)->search(['full_name' => 'Mary Ann Smith', 'first_name' => 'Mary', 'last_name' => 'Smith']);
    }

    public function test_local_import_requires_the_source_download_date(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('--downloaded-at');
        app(ScreeningDatasetImporter::class)->import('nc_dac', $this->directory);
    }

    public function test_large_record_count_drop_requires_review_and_preserves_previous_snapshot(): void
    {
        $first = $this->import('nc_sbi');
        $first->update(['row_counts' => ['public' => 100]]);
        try {
            $this->import('nc_sbi');
            $this->fail('A large count drop must not be activated.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('dropped unexpectedly', $exception->getMessage());
        }
        $this->assertTrue($first->fresh()->is_active);
    }

    public function test_malformed_csv_is_not_skipped_and_does_not_produce_a_clean_snapshot(): void
    {
        $this->zip('public-data.zip', ['public.csv' => "ID,FIRST,LAST,ALIAS\n1,Jane,Doe,\n2,Missing\n"]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('column count');
        $this->import('nc_sbi');
    }

    public function test_unterminated_csv_quote_cannot_be_silently_accepted_by_the_csv_parser(): void
    {
        $this->zip('public-data.zip', ['public.csv' => "ID,FIRST,LAST,ALIAS\n1,Jane,Doe,\"Unfinished\n"]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unterminated SBI CSV');
        $this->import('nc_sbi');
    }

    public function test_xml_source_can_be_imported_only_with_exact_flat_fields(): void
    {
        config(['screening.sources.nc_sbi.layout.format' => 'xml', 'screening.sources.nc_sbi.layout.member' => 'public.xml', 'screening.sources.nc_sbi.layout.record_element' => 'Offender']);
        $this->zip('public-data.zip', ['public.xml' => '<Offenders><Offender><ID>SBI1</ID><FIRST>Jane</FIRST><LAST>Doe</LAST><ALIAS>Jane ONeil</ALIAS></Offender></Offenders>']);
        $import = $this->import('nc_sbi');
        $this->assertTrue($import->is_active);
        $this->assertSame(1, $import->record_count);
    }

    public function test_xml_dtd_cannot_load_entities_or_activate_a_snapshot(): void
    {
        config(['screening.sources.nc_sbi.layout.format' => 'xml', 'screening.sources.nc_sbi.layout.member' => 'public.xml', 'screening.sources.nc_sbi.layout.record_element' => 'Offender']);
        $this->zip('public-data.zip', ['public.xml' => '<!DOCTYPE Offenders [<!ENTITY test "ENTITY NAME">]><Offenders><Offender><ID>SBI1</ID><FIRST>&test;</FIRST><LAST>Doe</LAST><ALIAS /></Offender></Offenders>']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DTD/entity');
        $this->import('nc_sbi');
    }

    public function test_download_rejects_nonofficial_hosts_before_sending_any_request(): void
    {
        \Illuminate\Support\Facades\Http::fake();
        try {
            app(\App\Services\Screening\Imports\PublicDataFiles::class)->download('https://example.com/public.zip', $this->directory.'/download.zip', ['sexoffender.ncsbi.gov']);
            $this->fail('Nonofficial hosts must not be downloaded.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('official source host', $exception->getMessage());
        }
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    private function import(string $source): ScreeningDatasetImport
    {
        return app(ScreeningDatasetImporter::class)->import($source, $this->directory, now()->subMinute()->toIso8601String());
    }

    private function fixtures(): void
    {
        // Synthetic, deliberately small fixtures, not copies of public personal records.
        $descriptor = "CMDORNUM  OFFENDER NC DOC ID NUMBER  CHAR  1  7\nFIRST  FIRST NAME  CHAR  8  10\nLAST  LAST NAME  CHAR  18  10\nALIAS  ALIAS NAME  CHAR  28  30\n";
        $line = '0000001'.str_pad('Jane', 10).str_pad('Doe', 10).str_pad("Jane O'Neil", 30)."\n";
        $this->zip('OFNT3AA1.zip', ['OFNT3AA1.des' => $descriptor, 'OFNT3AA1.dat' => $line]);
        foreach (['OFNT3BB1', 'OFNT3CE1', 'APPT7AA1'] as $key) {
            $this->zip($key.'.zip', [$key.'.des' => $this->detailDescriptor(), $key.'.dat' => "0000001AB\n"]);
        }
        $this->zip('public-data.zip', ['public.csv' => "ID,FIRST,LAST,ALIAS\nSBI001,Jane,ONeil,\n"]);
    }

    private function detailDescriptor(): string
    {
        return "CMDORNUM  OFFENDER NC DOC ID NUMBER  CHAR  1  7\nDETAIL  PUBLIC DETAIL CODE  CHAR  8  2\n";
    }

    private function zip(string $filename, array $contents): void
    {
        $zip = new ZipArchive;
        $zip->open($this->directory.'/'.$filename, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($contents as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
    }
}
