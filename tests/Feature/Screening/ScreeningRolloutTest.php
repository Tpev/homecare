<?php

namespace Tests\Feature\Screening;

use App\Models\ScreeningAuditEvent;
use App\Services\Screening\ScreeningAuditService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class ScreeningRolloutTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        // This rollout suite must never connect to an existing application database.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        $this->travelTo(now()->startOfSecond());
        Queue::fake();
        Schema::create('users', fn (Blueprint $table) => $table->id());
        Schema::create('caregiver_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('status');
        });
        Schema::create('caregiver_identity_verifications', fn (Blueprint $table) => $table->id());
        (require database_path('migrations/2026_10_13_000000_create_caregiver_screening.php'))->up();
        (require database_path('migrations/2026_10_13_000002_create_screening_rollout_state.php'))->up();
        $this->directory = storage_path('framework/testing/screening-rollout-'.bin2hex(random_bytes(8)));
        config(['screening.audit_directory' => $this->directory]);
    }

    protected function tearDown(): void
    {
        if (isset($this->directory) && is_dir($this->directory)) {
            File::deleteDirectory($this->directory);
        }
        $this->travelBack();
        parent::tearDown();
    }

    public function test_status_is_exact_and_read_only_before_and_after_migration(): void
    {
        $profile = $this->profile();
        $before = $this->row($profile);
        $this->assertSame(0, Artisan::call('screening:rollout', ['--status' => true]));
        $this->assertSame('pending'.PHP_EOL, Artisan::output());
        $this->assertSame(0, DB::table('screening_rollout_states')->count());
        $this->assertSame(0, ScreeningAuditEvent::count());
        $this->assertEquals($before, $this->row($profile));

        Schema::drop('screening_rollout_states');
        $this->assertSame(0, Artisan::call('screening:rollout', ['--status' => true]));
        $this->assertSame('pending'.PHP_EOL, Artisan::output());
        $this->assertFalse(Schema::hasTable('screening_rollout_states'));
    }

    public function test_prepare_grants_only_eligible_active_profiles_and_preserves_restrictions(): void
    {
        $active = $this->profile();
        $pending = $this->profile(['status' => 'under_review']);
        $draft = $this->profile(['status' => 'draft']);
        $suspended = $this->profile(['status' => 'suspended']);
        $explicitlySuspended = $this->profile(['screening_suspended_at' => now(), 'screening_suspension_case_id' => 42]);
        $initialApproved = $this->profile(['screening_initial_approved_at' => now()]);
        $existingDate = now()->subDay()->toDateTimeString();
        $existingGrant = $this->profile(['status' => 'suspended', 'screening_legacy_at' => $existingDate, 'screening_legacy_access' => true]);

        $this->artisan('screening:rollout', ['--prepare' => true])->expectsOutput('prepared')->assertSuccessful();

        $this->assertTrue((bool) $this->row($active)->screening_legacy_access);
        foreach ([$pending, $draft, $suspended, $explicitlySuspended, $initialApproved] as $profile) {
            $this->assertFalse((bool) $this->row($profile)->screening_legacy_access);
            $this->assertNotNull($this->row($profile)->screening_legacy_at);
        }
        $this->assertSame('under_review', $this->row($pending)->status);
        $this->assertSame('suspended', $this->row($suspended)->status);
        $this->assertNotNull($this->row($explicitlySuspended)->screening_suspended_at);
        $this->assertSame(42, $this->row($explicitlySuspended)->screening_suspension_case_id);
        $this->assertNotNull($this->row($initialApproved)->screening_initial_approved_at);
        $this->assertTrue((bool) $this->row($existingGrant)->screening_legacy_access);
        $this->assertSame($existingDate, $this->row($existingGrant)->screening_legacy_at);
        $this->assertNull($this->row($active)->screening_initial_approved_at);
        $event = ScreeningAuditEvent::sole();
        $this->assertSame('screening_rollout_prepared', $event->event_type);
        $this->assertSame(['actor_id' => null, 'profiles' => 7, 'legacy_marked' => 6, 'legacy_access_granted' => 1], $event->payload);
        $this->assertNull($event->screening_case_id);
        $this->assertNotNull($event->exported_at);
        $this->assertSame(0, DB::table('caregiver_screening_cases')->count());
        Queue::assertNothingPushed();
    }

    public function test_pending_retry_captures_old_release_approvals_without_rewriting_existing_grants(): void
    {
        $pending = $this->profile(['status' => 'under_review']);
        $this->artisan('screening:rollout', ['--prepare' => true])->assertSuccessful();
        $legacyDate = $this->row($pending)->screening_legacy_at;
        $this->assertFalse((bool) $this->row($pending)->screening_legacy_access);

        // A failed deployment restored old writers, which approved and created profiles.
        $this->travel(2)->hours();
        DB::table('caregiver_profiles')->where('id', $pending)->update(['status' => 'active']);
        $createdDuringRetry = $this->profile();
        $this->artisan('screening:rollout', ['--prepare' => true])->assertSuccessful();
        $this->assertTrue((bool) $this->row($pending)->screening_legacy_access);
        $this->assertTrue((bool) $this->row($createdDuringRetry)->screening_legacy_access);
        $this->assertSame($legacyDate, $this->row($pending)->screening_legacy_at);
        $this->assertSame(2, ScreeningAuditEvent::latest('id')->firstOrFail()->payload['legacy_access_granted']);

        $before = DB::table('caregiver_profiles')->orderBy('id')->get()->all();
        $this->artisan('screening:rollout', ['--prepare' => true])->assertSuccessful();
        $this->assertEquals($before, DB::table('caregiver_profiles')->orderBy('id')->get()->all());
        $this->assertSame(['actor_id' => null, 'profiles' => 2, 'legacy_marked' => 0, 'legacy_access_granted' => 0], ScreeningAuditEvent::latest('id')->firstOrFail()->payload);
        $this->assertSame(1, DB::table('screening_rollout_states')->count());
        $this->assertSame(0, Artisan::call('screening:rollout', ['--status' => true]));
        $this->assertSame('pending'.PHP_EOL, Artisan::output());
    }

    public function test_activation_requires_preparation_and_is_idempotent(): void
    {
        $this->artisan('screening:rollout', ['--activate' => true])
            ->expectsOutput('Screening rollout must be prepared before activation.')->assertFailed();
        $this->assertSame(0, DB::table('screening_rollout_states')->count());
        $this->assertSame(0, ScreeningAuditEvent::count());

        $this->artisan('screening:rollout', ['--prepare' => true])->assertSuccessful();
        $this->artisan('screening:rollout', ['--activate' => true])->expectsOutput('active')->assertSuccessful();
        $state = DB::table('screening_rollout_states')->sole();
        $this->assertNotNull($state->prepared_at);
        $this->assertNotNull($state->activated_at);
        $this->travel(1)->hour();
        $this->artisan('screening:rollout', ['--activate' => true])->expectsOutput('active')->assertSuccessful();
        $this->assertEquals($state, DB::table('screening_rollout_states')->sole());
        $this->assertSame(1, ScreeningAuditEvent::where('event_type', 'screening_rollout_activated')->count());
        $this->assertSame(0, Artisan::call('screening:rollout', ['--status' => true]));
        $this->assertSame('active'.PHP_EOL, Artisan::output());
    }

    public function test_active_rollout_never_grandfathers_future_profiles_or_late_approvals(): void
    {
        $pending = $this->profile(['status' => 'under_review']);
        $this->artisan('screening:rollout', ['--prepare' => true])->assertSuccessful();
        $this->artisan('screening:rollout', ['--activate' => true])->assertSuccessful();
        $new = $this->profile();
        DB::table('caregiver_profiles')->where('id', $pending)->update(['status' => 'active']);
        $before = DB::table('caregiver_profiles')->orderBy('id')->get()->all();
        $auditCount = ScreeningAuditEvent::count();

        $this->artisan('screening:rollout', ['--prepare' => true])->expectsOutput('active')->assertSuccessful();

        $this->assertEquals($before, DB::table('caregiver_profiles')->orderBy('id')->get()->all());
        $this->assertFalse((bool) $this->row($pending)->screening_legacy_access);
        $this->assertFalse((bool) $this->row($new)->screening_legacy_access);
        $this->assertNull($this->row($new)->screening_legacy_at);
        $this->assertSame($auditCount, ScreeningAuditEvent::count());
    }

    public function test_failed_preparation_rolls_back_profiles_state_and_audit_then_can_retry(): void
    {
        $profile = $this->profile();
        $this->mock(ScreeningAuditService::class)->shouldReceive('record')->once()
            ->andThrow(new RuntimeException('Simulated audit persistence failure.'));
        $this->artisan('screening:rollout', ['--prepare' => true])->assertFailed();
        $this->assertNull($this->row($profile)->screening_legacy_at);
        $this->assertFalse((bool) $this->row($profile)->screening_legacy_access);
        $this->assertSame(0, DB::table('screening_rollout_states')->count());
        $this->assertSame(0, ScreeningAuditEvent::count());

        $this->app->forgetInstance(ScreeningAuditService::class);
        $this->artisan('screening:rollout', ['--prepare' => true])->assertSuccessful();
        $this->assertTrue((bool) $this->row($profile)->screening_legacy_access);
        $this->assertSame(1, ScreeningAuditEvent::count());
    }

    public function test_ambiguous_or_missing_modes_do_not_mutate_the_rollout(): void
    {
        $this->artisan('screening:rollout')->expectsOutput('Choose exactly one of --status, --prepare, or --activate.')->assertFailed();
        $this->artisan('screening:rollout', ['--status' => true, '--prepare' => true])->assertFailed();
        $this->assertSame(0, DB::table('screening_rollout_states')->count());
        $this->assertSame(0, ScreeningAuditEvent::count());
    }

    public function test_every_existing_profile_is_reconciled_across_batches(): void
    {
        DB::table('caregiver_profiles')->insert(array_fill(0, 125, ['status' => 'active']));

        $this->artisan('screening:rollout', ['--prepare' => true])->assertSuccessful();

        $this->assertSame(125, DB::table('caregiver_profiles')->whereNotNull('screening_legacy_at')->where('screening_legacy_access', true)->count());
        $this->assertSame(['actor_id' => null, 'profiles' => 125, 'legacy_marked' => 125, 'legacy_access_granted' => 125], ScreeningAuditEvent::sole()->payload);
    }

    public function test_activation_audit_failure_leaves_the_prepared_rollout_retryable(): void
    {
        $this->artisan('screening:rollout', ['--prepare' => true])->assertSuccessful();
        $state = DB::table('screening_rollout_states')->sole();
        $this->mock(ScreeningAuditService::class)->shouldReceive('record')->once()
            ->andThrow(new RuntimeException('Simulated activation audit failure.'));

        $this->artisan('screening:rollout', ['--activate' => true])->assertFailed();

        $this->assertEquals($state, DB::table('screening_rollout_states')->sole());
        $this->assertSame(0, ScreeningAuditEvent::where('event_type', 'screening_rollout_activated')->count());
        $this->app->forgetInstance(ScreeningAuditService::class);
        $this->artisan('screening:rollout', ['--activate' => true])->expectsOutput('active')->assertSuccessful();
        $this->assertNotNull(DB::table('screening_rollout_states')->sole()->activated_at);
    }

    private function profile(array $attributes = []): int
    {
        return DB::table('caregiver_profiles')->insertGetId(['status' => 'active', ...$attributes]);
    }

    private function row(int $profile): object
    {
        return DB::table('caregiver_profiles')->where('id', $profile)->first();
    }
}
