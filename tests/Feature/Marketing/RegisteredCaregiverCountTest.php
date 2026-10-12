<?php

namespace Tests\Feature\Marketing;

use App\Livewire\Auth\CaregiverRegister;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
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
        User::factory()->create(['role' => 'caregiver']);
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

        DB::table('users')->where('role', 'caregiver')->delete();
        $this->assertSavedCount(0);
    }

    public function test_bulk_role_changes_keep_the_saved_count_accurate(): void
    {
        $families = User::factory()->count(2)->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $this->assertSavedCount(1);

        DB::table('users')->whereIn('id', $families->modelKeys())->update(['role' => 'caregiver']);
        $this->assertSavedCount(3);

        DB::table('users')->where('role', 'caregiver')->update(['role' => 'caregiver']);
        $this->assertSavedCount(3);

        DB::table('users')->where('id', $caregiver->id)->update(['role' => 'admin']);
        $this->assertSavedCount(2);

        DB::table('users')->whereIn('id', $families->modelKeys())->update(['role' => 'family']);
        $this->assertSavedCount(0);
    }

    public function test_account_changes_and_counter_updates_roll_back_together(): void
    {
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $family = User::factory()->create(['role' => 'family']);

        DB::beginTransaction();

        try {
            User::factory()->create(['role' => 'caregiver']);
            DB::table('users')->where('id', $family->id)->update(['role' => 'caregiver']);
            DB::table('users')->where('id', $caregiver->id)->delete();
            $this->assertSavedCount(2);
        } finally {
            DB::rollBack();
        }

        $this->assertSavedCount(1);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseHas('users', ['id' => $caregiver->id, 'role' => 'caregiver']);
        $this->assertDatabaseHas('users', ['id' => $family->id, 'role' => 'family']);
    }

    public function test_migration_backfills_existing_accounts_and_removes_triggers_on_rollback(): void
    {
        $migration = require database_path('migrations/2026_10_12_120000_create_marketplace_statistics.php');
        $migration->down();

        $this->assertFalse(Schema::hasTable('marketplace_statistics'));
        $caregivers = User::factory()->count(2)->create(['role' => 'caregiver']);
        User::factory()->create(['role' => 'family']);
        User::factory()->create(['role' => 'admin']);

        $migration->up();
        $this->assertSavedCount(2);

        DB::table('users')->where('id', $caregivers->first()->id)->delete();
        $this->assertSavedCount(1);

        $migration->down();
        User::factory()->create(['role' => 'caregiver']);
        $migration->up();
        $this->assertSavedCount(2);
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
}
