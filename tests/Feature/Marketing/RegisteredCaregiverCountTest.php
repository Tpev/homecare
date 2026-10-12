<?php

namespace Tests\Feature\Marketing;

use App\Livewire\Auth\CaregiverRegister;
use App\Models\User;
use App\Services\Marketing\RegisteredCaregiverCount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class RegisteredCaregiverCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_registered_caregiver_accounts_contribute_to_the_count(): void
    {
        $this->assertSavedCount(0);

        $family = User::factory()->create(['role' => 'family']);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->assertSavedCount(0);

        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $secondCaregiver = User::factory()->create(['role' => 'caregiver']);
        $this->assertSavedCount(2);

        // Accounts count before they have created or completed a public profile.
        $this->assertDatabaseCount('caregiver_profiles', 0);
        $caregiver->update(['name' => 'Updated caregiver name']);
        $caregiver->update(['role' => 'caregiver']);
        $this->assertSavedCount(2);

        $family->delete();
        $admin->delete();
        $this->assertSavedCount(2);

        $caregiver->delete();
        $this->assertSavedCount(1);

        $secondCaregiver->delete();
        $this->assertSavedCount(0);
    }

    public function test_model_role_changes_keep_the_saved_count_accurate(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $this->assertSavedCount(1);

        $family->update(['role' => 'caregiver']);
        $this->assertSavedCount(2);

        $family->update(['role' => 'caregiver']);
        $this->assertSavedCount(2);

        $caregiver->update(['role' => 'admin']);
        $this->assertSavedCount(1);

        $family->update(['role' => 'family']);
        $this->assertSavedCount(0);
    }

    public function test_refresh_command_reconciles_bulk_changes_that_bypass_model_events(): void
    {
        $families = User::factory()->count(2)->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);

        DB::table('users')->whereIn('id', $families->modelKeys())->update(['role' => 'caregiver']);
        $this->assertSavedCount(1);

        $this->artisan('homecare:refresh-caregiver-count')->assertSuccessful();
        $this->assertSavedCount(3);

        DB::table('users')->where('id', $caregiver->id)->delete();
        $this->assertSavedCount(3);

        $this->artisan('homecare:refresh-caregiver-count')->assertSuccessful();
        $this->assertSavedCount(2);

        DB::table('users')->whereIn('id', $families->modelKeys())->update(['role' => 'family']);
        $this->artisan('homecare:refresh-caregiver-count')->assertSuccessful();
        $this->assertSavedCount(0);
    }

    public function test_counter_refresh_waits_until_account_changes_are_committed(): void
    {
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $family = User::factory()->create(['role' => 'family']);

        DB::transaction(function () use ($caregiver, $family): void {
            User::factory()->create(['role' => 'caregiver']);
            $family->update(['role' => 'caregiver']);
            $caregiver->delete();
            $this->assertSavedCount(1);
        });

        $this->assertSavedCount(2);
    }

    public function test_role_change_is_refreshed_even_if_the_same_model_is_saved_again_before_commit(): void
    {
        $family = User::factory()->create(['role' => 'family']);

        DB::transaction(function () use ($family): void {
            $family->update(['role' => 'caregiver']);
            $family->update(['name' => 'Updated caregiver name']);
            $this->assertSavedCount(0);
        });

        $this->assertSavedCount(1);
    }

    public function test_rolled_back_account_changes_do_not_refresh_the_count(): void
    {
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $family = User::factory()->create(['role' => 'family']);

        DB::beginTransaction();

        try {
            User::factory()->create(['role' => 'caregiver']);
            $family->update(['role' => 'caregiver']);
            $caregiver->delete();
            $this->assertSavedCount(1);
        } finally {
            DB::rollBack();
        }

        $this->assertSavedCount(1);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseHas('users', ['id' => $caregiver->id, 'role' => 'caregiver']);
        $this->assertDatabaseHas('users', ['id' => $family->id, 'role' => 'family']);
    }

    public function test_migration_backfills_existing_accounts_without_creating_triggers(): void
    {
        $migration = require database_path('migrations/2026_10_12_120000_create_marketplace_statistics.php');
        $migration->down();

        $this->assertFalse(Schema::hasTable('marketplace_statistics'));
        User::withoutEvents(function (): void {
            User::factory()->count(2)->create(['role' => 'caregiver']);
            User::factory()->create(['role' => 'family']);
            User::factory()->create(['role' => 'admin']);
        });

        $migration->up();
        $this->assertSavedCount(2);
        $this->assertNoCaregiverCountTriggers();
    }

    public function test_migration_recovers_a_partial_deployment_with_an_existing_zero_counter(): void
    {
        User::factory()->count(2)->create(['role' => 'caregiver']);
        DB::table('marketplace_statistics')
            ->where('name', 'registered_caregivers')->update(['value' => 0]);

        // MySQL can retain the table and zero row when CREATE TRIGGER fails.
        $migration = require database_path('migrations/2026_10_12_120000_create_marketplace_statistics.php');
        $migration->up();
        $this->assertSavedCount(2);
        $this->assertNoCaregiverCountTriggers();

        $migration->up();
        $this->assertSavedCount(2);
        $this->assertDatabaseCount('marketplace_statistics', 1);

        DB::table('marketplace_statistics')->where('name', 'registered_caregivers')->delete();
        $migration->up();
        $this->assertSavedCount(2);
    }

    public function test_followup_migration_removes_legacy_triggers_and_reconciles_the_total(): void
    {
        User::factory()->count(2)->create(['role' => 'caregiver']);
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER marketplace_caregivers_insert AFTER INSERT ON users
            FOR EACH ROW
            BEGIN
                UPDATE marketplace_statistics SET value = value + 1
                WHERE name = 'registered_caregivers' AND NEW.role = 'caregiver';
            END
            SQL);
        DB::table('marketplace_statistics')
            ->where('name', 'registered_caregivers')->update(['value' => 0]);

        $migration = require database_path('migrations/2026_10_12_130000_remove_marketplace_statistics_triggers.php');
        $migration->up();

        $this->assertNoCaregiverCountTriggers();
        $this->assertSavedCount(2);
        User::factory()->create(['role' => 'caregiver']);
        $this->assertSavedCount(3);

        // Retrying cleanup or rolling it back must not restore privileged triggers.
        $migration->up();
        $migration->down();
        $this->assertNoCaregiverCountTriggers();
        $this->assertSavedCount(3);
    }

    public function test_successful_signup_increments_the_count_once_and_failed_signup_does_not(): void
    {
        Mail::fake();

        $registration = Livewire::test(CaregiverRegister::class)
            ->set('name', 'New Caregiver')
            ->set('email', 'counted-caregiver@example.com')
            ->set('phone', '555-000-1111')
            ->set('city', 'Raleigh')
            ->set('state', 'NC')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->set('accept_terms', true);

        $registration->call('register')->assertHasErrors(['accept_independent_contractor']);
        $this->assertSavedCount(0);
        $this->assertDatabaseMissing('users', ['email' => 'counted-caregiver@example.com']);

        $registration
            ->set('accept_independent_contractor', true)
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertSavedCount(1);
        $this->assertDatabaseHas('caregiver_profiles', [
            'user_id' => User::query()->where('email', 'counted-caregiver@example.com')->value('id'),
            'status' => 'draft',
        ]);

        $registration->call('register')->assertHasErrors(['email']);
        $this->assertSavedCount(1);
    }

    public function test_failed_counter_refresh_is_reported_without_interrupting_signup_and_can_be_recovered(): void
    {
        Mail::fake();
        Exceptions::fake();
        $failure = new RuntimeException('Temporary statistics connection failure.');
        $this->mock(RegisteredCaregiverCount::class)
            ->shouldReceive('refresh')->once()->andThrow($failure);

        Livewire::test(CaregiverRegister::class)
            ->set('name', 'Caregiver With Delayed Count')
            ->set('email', 'delayed-count-caregiver@example.com')
            ->set('phone', '555-000-1111')
            ->set('city', 'Raleigh')
            ->set('state', 'NC')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->set('accept_terms', true)
            ->set('accept_independent_contractor', true)
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $caregiver = User::query()->where('email', 'delayed-count-caregiver@example.com')->firstOrFail();
        $this->assertSame('caregiver', $caregiver->role);
        $this->assertAuthenticatedAs($caregiver);
        $this->assertDatabaseHas('caregiver_profiles', [
            'user_id' => $caregiver->id,
            'status' => 'draft',
        ]);
        $this->assertSavedCount(0);
        Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception === $failure);

        $this->app->forgetInstance(RegisteredCaregiverCount::class);
        $this->artisan('homecare:refresh-caregiver-count')->assertSuccessful();
        $this->assertSavedCount(1);
    }

    public function test_homepage_reads_the_saved_total_without_recounting_accounts(): void
    {
        config(['marketplace.caregiver_prelaunch_mode' => false]);
        User::factory()->create(['role' => 'caregiver']);
        DB::table('marketplace_statistics')
            ->where('name', 'registered_caregivers')->update(['value' => 1413]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $response = $this->get(route('landing'))->assertOk();
            $queries = collect(DB::getQueryLog())->pluck('query');
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        $response->assertSeeInOrder([
            'id="caregivers"',
            'Find someone who fits your family.',
            '<strong>1,413</strong> caregivers on LoLo',
        ], false);

        $this->assertCount(1, $queries->filter(
            fn (string $query): bool => str_contains($query, 'marketplace_statistics')
        ));
        $this->assertFalse($queries->contains(
            fn (string $query): bool => preg_match('/\bcount\s*\(/i', $query)
                && preg_match('/\b(users|caregiver_profiles)\b/i', $query)
        ), 'The homepage must read the saved total without aggregating accounts or profiles.');
    }

    public function test_homepage_formats_zero_and_one_registered_caregiver(): void
    {
        $this->get(route('landing'))->assertOk()
            ->assertSee('<strong>0</strong> caregivers on LoLo', false);

        User::factory()->create(['role' => 'caregiver']);

        $this->get(route('landing'))->assertOk()
            ->assertSee('<strong>1</strong> caregiver on LoLo', false)
            ->assertDontSee('<strong>1</strong> caregivers on LoLo', false);
    }

    private function assertSavedCount(int $expected): void
    {
        $this->assertDatabaseHas('marketplace_statistics', [
            'name' => 'registered_caregivers',
            'value' => $expected,
        ]);
    }

    private function assertNoCaregiverCountTriggers(): void
    {
        $this->assertSame([], DB::table('sqlite_master')
            ->where('type', 'trigger')
            ->whereIn('name', [
                'marketplace_caregivers_insert',
                'marketplace_caregivers_update',
                'marketplace_caregivers_delete',
            ])->pluck('name')->all());
    }
}
