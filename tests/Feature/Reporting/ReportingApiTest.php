<?php

namespace Tests\Feature\Reporting;

use App\Services\Reporting\ReportingCatalog;
use App\Services\Reporting\ReportingConnection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportingApiTest extends TestCase
{
    private static ?string $migratedTemplate = null;

    private string $database;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = tempnam(sys_get_temp_dir(), 'lolo-reporting-test-');
        $this->token = str_repeat('synthetic-test-token-', 3);
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->database,
            'reporting.token_sha256' => hash('sha256', $this->token),
        ]);
        DB::purge('sqlite');
        if (self::$migratedTemplate === null) {
            $this->assertSame(0, Artisan::call('migrate', ['--force' => true]), Artisan::output());
            DB::purge('sqlite');
            self::$migratedTemplate = tempnam(sys_get_temp_dir(), 'lolo-reporting-schema-');
            copy($this->database, self::$migratedTemplate);
        } else {
            copy(self::$migratedTemplate, $this->database);
        }
        DB::table('users')->insert([
            ['id' => 900001, 'name' => 'Reporting admin', 'email' => 'reporting-admin@example.test', 'password' => 'NEVER_EXPORT_PASSWORD', 'role' => 'admin', 'created_at' => '2026-11-01 10:00:00'],
            ['id' => 900002, 'name' => 'Synthetic family', 'email' => 'reporting-family@example.test', 'password' => 'NEVER_EXPORT_PASSWORD', 'role' => 'family', 'created_at' => '2026-11-20 10:00:00'],
            ['id' => 900003, 'name' => 'Synthetic caregiver', 'email' => 'reporting-caregiver@example.test', 'password' => 'NEVER_EXPORT_PASSWORD', 'role' => 'caregiver', 'created_at' => '2026-11-20 10:00:00'],
        ]);
        DB::table('family_accounts')->insert(['id' => 900001, 'owner_user_id' => 900002, 'status' => 'active']);
        DB::table('family_payment_setup_links')->insert([
            'family_account_id' => 900001, 'created_by_user_id' => 900001, 'token_hash' => hash('sha256', 'synthetic-payment-token'),
            'token' => 'DO_NOT_EXPORT_PAYMENT_TOKEN', 'stripe_checkout_url' => 'DO_NOT_EXPORT_CHECKOUT_URL',
            'expires_at' => '2026-12-01 10:00:00', 'completed_at' => '2026-11-23 10:00:00',
        ]);
        DB::table('leads')->insert([
            'id' => 900001, 'family_account_id' => 900001, 'lead_type' => 'family', 'name' => 'Synthetic lead',
            'status' => 'qualified', 'submitted_at' => '2026-11-20 10:00:00',
            'data' => json_encode(['notes' => 'Needs weekend care', 'facebook' => ['campaign_id' => 'campaign-1'], 'raw_payload' => ['secret' => 'DO_NOT_EXPORT_RAW']]),
        ]);
        DB::table('care_requests')->insert([
            'id' => 900001, 'family_account_id' => 900001, 'family_user_id' => 900002,
            'title' => 'Synthetic weekend care', 'status' => 'open', 'staff_managed' => true,
            'address_line1' => 'DO_NOT_EXPORT_HOME_ADDRESS', 'city' => 'Test City', 'state' => 'FL', 'zip' => '00000',
        ]);
        DB::table('customer_care_actions')->insert(['care_request_id' => 900001, 'actor_user_id' => 900001, 'action' => 'follow_up_note', 'note' => 'Weekend caregiver search is active']);
        DB::table('care_bookings')->insert([
            'care_request_id' => 900001, 'family_account_id' => 900001, 'family_user_id' => 900002,
            'caregiver_user_id' => 900003, 'status' => 'cancelled', 'caregiver_fee_policy' => 'marketplace_v2',
            'replacement_released_at' => '2026-11-24 10:00:00',
            'agreement_snapshot' => json_encode(['hourly_rate' => 30, 'home_access_notes' => 'DO_NOT_EXPORT_HOME_ACCESS']),
        ]);
        DB::table('care_bookings')->insert([
            'care_request_id' => 900001, 'family_account_id' => 900001, 'family_user_id' => 900002,
            'caregiver_user_id' => 900003, 'status' => 'scheduled', 'caregiver_fee_policy' => 'marketplace_v2',
            'scheduled_start_at' => '2026-11-28 10:00:00', 'scheduled_end_at' => '2026-11-28 12:00:00',
        ]);
        // More than the UI's 40-row timeline, including an old note and a recent independent note.
        for ($i = 1; $i <= 45; $i++) {
            DB::table('lead_activities')->insert([
                'lead_id' => 900001, 'actor_user_id' => 900001, 'type' => 'note', 'summary' => 'Synthetic note '.$i,
                'body' => $i === 45 ? 'Customer asked for Saturday. https://carelolo.com/payment-setup/SECRET_PATH?token=SECRET_QUERY api_key=SECRET_KEY' : 'Full retained note '.$i,
                'occurred_at' => '2026-11-21 12:00:00',
                'metadata' => json_encode(['from' => 'new', 'to' => 'qualified', 'client_secret' => 'DO_NOT_EXPORT_SECRET', 'original_lead' => ['status' => 'new', 'password' => 'DO_NOT_EXPORT_NESTED']]),
            ]);
        }
    }

    protected function tearDown(): void
    {
        app(ReportingConnection::class)->close();
        DB::purge('sqlite');
        @unlink($this->database);
        parent::tearDown();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$migratedTemplate) {
            @unlink(self::$migratedTemplate);
            self::$migratedTemplate = null;
        }
        parent::tearDownAfterClass();
    }

    public function test_export_preserves_complete_history_is_verifiable_and_never_mutates_business_data(): void
    {
        $before = DB::table('leads')->where('id', 900001)->first();
        $queries = [];
        DB::listen(function ($event) use (&$queries): void {
            $queries[] = $event->sql;
        });
        $response = $this->withToken($this->token)->get('/api/reporting/v1/export')->assertOk();
        $wire = $response->streamedContent();
        $frames = array_map(fn ($line) => json_decode($line, true, flags: JSON_THROW_ON_ERROR), explode("\n", rtrim($wire, "\n")));
        $manifest = $frames[0];
        $this->assertSame('manifest', $manifest['type']);
        $this->assertSame(config('app.timezone'), $manifest['database_timezone']);
        $this->assertCount(count(ReportingCatalog::datasets()), $manifest['datasets']);
        foreach (['customer_care_actions', 'care_request_messages', 'care_bookings', 'caregiver_crm_activities', 'care_hour_ledger_entries'] as $table) {
            $this->assertTrue($manifest['datasets'][$table]['available'], $table);
        }
        $notes = array_values(array_filter($frames, fn ($r) => ($r['dataset'] ?? '') === 'lead_activities' && $r['type'] === 'record'));
        $this->assertCount(45, $notes);
        $this->assertSame('Full retained note 1', $notes[0]['data']['body']);
        $this->assertStringContainsString('Customer asked for Saturday.', $notes[44]['data']['body']);
        foreach (['NEVER_EXPORT_PASSWORD', 'SECRET_PATH', 'SECRET_QUERY', 'SECRET_KEY', 'DO_NOT_EXPORT_RAW', 'DO_NOT_EXPORT_SECRET', 'DO_NOT_EXPORT_NESTED', 'DO_NOT_EXPORT_HOME_ADDRESS', 'DO_NOT_EXPORT_HOME_ACCESS', 'DO_NOT_EXPORT_PAYMENT_TOKEN', 'DO_NOT_EXPORT_CHECKOUT_URL'] as $secret) {
            $this->assertStringNotContainsString($secret, $wire);
        }
        $lines = explode("\n", rtrim($wire, "\n"));
        $complete = json_decode(array_pop($lines), true);
        $this->assertSame('complete', $complete['type']);
        $this->assertSame(hash('sha256', implode("\n", $lines)."\n"), $complete['sha256']);
        $this->assertSame(45, $complete['counts']['lead_activities']);
        $this->assertSame(2, $complete['counts']['care_bookings']);
        $this->assertStringContainsString('marketplace_v2', $wire);
        $this->assertStringContainsString('Weekend caregiver search is active', $wire);
        $this->assertEquals($before, DB::table('leads')->where('id', 900001)->first());
        foreach ($queries as $query) {
            $this->assertDoesNotMatchRegularExpression('/^\s*(insert|update|delete|replace|create|alter|drop)\b/i', $query);
        }
        $this->assertFalse(array_key_exists('reporting_export', DB::getConnections()));
        if ($path = getenv('LOLO_REPORTING_TEST_WIRE')) {
            file_put_contents($path, $wire); // Optional synthetic fixture for cross-language validation.
        }
    }

    public function test_authentication_fails_closed_and_methods_cannot_write(): void
    {
        $this->getJson('/api/reporting/v1/schema')->assertUnauthorized();
        $this->withToken(str_repeat('x', 64))->getJson('/api/reporting/v1/schema')->assertUnauthorized();
        $this->withToken($this->token)->getJson('/api/reporting/v1/schema')->assertOk();
        foreach (['post', 'put', 'patch', 'delete'] as $method) {
            $this->withToken($this->token)->json($method, '/api/reporting/v1/export')->assertStatus(405);
        }
        $this->withToken($this->token)->head('/api/reporting/v1/export')->assertStatus(405);
        config(['reporting.token_expires_at' => '2000-01-01T00:00:00Z']);
        $this->withToken($this->token)->getJson('/api/reporting/v1/export')->assertUnauthorized();
        config(['reporting.token_expires_at' => null]);
        $this->withToken($this->token)->getJson('/api/reporting/v1/schema')->assertOk();
        config(['reporting.token_sha256' => hash('sha256', 'a-different-long-test-credential-for-rotation')]);
        $this->withToken($this->token)->getJson('/api/reporting/v1/export')->assertUnauthorized();
        config(['reporting.enabled' => false]);
        $this->withToken($this->token)->getJson('/api/reporting/v1/export')->assertNotFound();
    }

    public function test_transaction_itself_rejects_writes_and_missing_core_schema_fails(): void
    {
        $manager = app(ReportingConnection::class);
        $db = $manager->open();
        $this->assertNotSame(DB::connection()->getPdo(), $db->getPdo());
        $this->assertSame($this->database, $db->getDatabaseName());
        try {
            $db->table('leads')->where('id', 900001)->update(['status' => 'lost']);
            $this->fail('Reporting connection allowed a write outside a snapshot.');
        } catch (\Illuminate\Database\QueryException $exception) {
            $this->assertStringContainsString('readonly', strtolower($exception->getMessage()));
        }
        $manager->beginSnapshot($db, array_keys(ReportingCatalog::datasets()));
        try {
            $db->table('leads')->where('id', 900001)->update(['status' => 'lost']);
            $this->fail('Read-only connection allowed a write.');
        } catch (\Illuminate\Database\QueryException $exception) {
            $this->assertStringContainsString('readonly', strtolower($exception->getMessage()));
        } finally {
            $manager->close();
        }
        // Read-only settings belong to the cloned connection, not the ordinary application's PDO.
        DB::table('leads')->where('id', 900001)->update(['priority' => 'high']);
        $this->assertSame('high', DB::table('leads')->where('id', 900001)->value('priority'));
        DB::statement('PRAGMA foreign_keys=OFF');
        DB::statement('DROP TABLE lead_activities');
        $this->withToken($this->token)->getJson('/api/reporting/v1/export')->assertStatus(503)->assertExactJson(['code' => 'reporting_unavailable']);
    }
}
