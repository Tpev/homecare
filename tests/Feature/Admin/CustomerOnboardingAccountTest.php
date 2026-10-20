<?php

namespace Tests\Feature\Admin;

use App\Models\FamilyAccountActivityLog;
use App\Models\FamilyAccountMember;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\Auth\LoLoCareResetPasswordNotification;
use App\Services\CustomerOnboarding\CustomerOnboardingAccountService;
use App\Services\FamilyAccounts\FamilyAccountProvisioner;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CustomerOnboardingAccountTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        Queue::fake();
        Event::fake([Registered::class]);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->admin);
    }

    public function test_creates_unverified_owner_and_clean_onboarding_without_sending_messages(): void
    {
        $lead = $this->lead('New.Owner@example.com');
        $account = $this->service()->create($this->admin, [
            'name' => 'New Owner', 'email' => ' NEW.OWNER@example.com ', 'phone' => '+19845550123',
        ], $lead->id);

        $this->assertSame('new.owner@example.com', $account->owner->email);
        $this->assertSame('family', $account->owner->role);
        $this->assertNull($account->owner->email_verified_at);
        $this->assertNull($account->owner->onboarding_completed_at);
        $this->assertFalse(Hash::check('password', $account->owner->password));
        $this->assertSame($account->id, $lead->fresh()->family_account_id);
        $this->assertSame('admin_customer_onboarding', $account->onboarding->source);
        $this->assertSame('in_progress', $account->onboarding->status);
        $this->assertSame('', $account->onboarding->draft['recipient_name']);
        $this->assertSame('+19845550123', $account->onboarding->draft['phone']);
        $this->assertDatabaseHas('family_account_members', [
            'family_account_id' => $account->id, 'user_id' => $account->owner_user_id, 'access_level' => 'owner', 'status' => 'active',
        ]);
        $this->assertDatabaseHas('family_account_activity_logs', [
            'family_account_id' => $account->id, 'actor_user_id' => $this->admin->id, 'action' => 'customer_onboarding_account_created',
        ]);
        $this->assertDatabaseCount('family_onboarding_deliveries', 0);
        Event::assertNotDispatched(Registered::class);
        Notification::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_case_insensitive_reuse_preserves_existing_profile_password_and_onboarding_state(): void
    {
        $owner = User::factory()->create(['role' => 'family', 'email' => 'Existing.Owner@example.com', 'name' => 'Original Name']);
        $existing = app(FamilyAccountProvisioner::class)->provisionOwner($owner);
        $password = $owner->password;
        $lead = $this->lead('existing.owner@example.com');

        $result = $this->service()->create($this->admin, ['name' => 'Changed Name', 'email' => 'EXISTING.OWNER@example.com'], $lead->id);

        $this->assertSame($existing->id, $result->id);
        $this->assertSame('Original Name', $result->owner->name);
        $this->assertSame($password, $result->owner->password);
        $this->assertNull($result->onboarding);
        $this->assertSame($existing->id, $lead->fresh()->family_account_id);
        $this->assertDatabaseCount('family_accounts', 1);
        Notification::assertNothingSent();
    }

    public function test_repeated_creation_reuses_account_and_does_not_duplicate_lead_or_enrollment(): void
    {
        $input = ['name' => 'New Owner', 'email' => 'new@example.com'];
        $first = $this->service()->create($this->admin, $input);
        $again = $this->service()->create($this->admin, $input);

        $this->assertSame($first->id, $again->id);
        $this->assertDatabaseCount('family_accounts', 1);
        $this->assertDatabaseCount('family_onboardings', 1);
        $this->assertDatabaseCount('leads', 1);
    }

    public function test_rejects_other_roles_without_creating_family_account(): void
    {
        User::factory()->create(['role' => 'caregiver', 'email' => 'existing@example.com']);
        $this->assertCreationRejected('existing@example.com');
        $this->assertDatabaseCount('family_accounts', 0);
    }

    public function test_rejects_active_family_member_email(): void
    {
        $owner = User::factory()->create(['role' => 'family']);
        $account = app(FamilyAccountProvisioner::class)->provisionOwner($owner);
        $member = User::factory()->create(['role' => 'family']);
        FamilyAccountMember::create([
            'family_account_id' => $account->id, 'user_id' => $member->id,
            'access_level' => 'member', 'status' => 'active', 'joined_at' => now(),
        ]);

        $this->assertCreationRejected($member->email);
        $this->assertDatabaseCount('family_accounts', 1);
    }

    public function test_rejects_closed_owner_account(): void
    {
        $owner = User::factory()->create(['role' => 'family']);
        $account = app(FamilyAccountProvisioner::class)->provisionOwner($owner);
        $account->update(['status' => 'closed']);

        $this->assertCreationRejected($owner->email);
        $this->assertSame('closed', $account->fresh()->status);
        $this->assertDatabaseCount('family_accounts', 1);
    }

    public function test_rejects_ambiguous_case_insensitive_duplicate_users(): void
    {
        User::factory()->create(['role' => 'family', 'email' => 'duplicate@example.com']);
        User::factory()->create(['role' => 'family', 'email' => 'DUPLICATE@example.com']);

        $this->assertCreationRejected('duplicate@example.com');
        $this->assertDatabaseCount('family_accounts', 0);
    }

    public function test_lead_email_mismatch_rolls_back_creation(): void
    {
        $lead = $this->lead('real@example.com');
        try {
            $this->service()->create($this->admin, ['name' => 'New', 'email' => 'wrong@example.com'], $lead->id);
            $this->fail('Mismatched lead email should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
        }
        $this->assertDatabaseMissing('users', ['email' => 'wrong@example.com']);
        $this->assertNull($lead->fresh()->family_account_id);
    }

    public function test_cannot_reassign_an_already_linked_lead_and_rolls_back_new_owner(): void
    {
        $existing = app(FamilyAccountProvisioner::class)->provisionOwner(User::factory()->create(['role' => 'family']));
        $lead = $this->lead('new@example.com', ['family_account_id' => $existing->id]);
        try {
            $this->service()->create($this->admin, ['name' => 'New', 'email' => 'new@example.com'], $lead->id);
            $this->fail('An existing link must not be reassigned.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
        }
        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
        $this->assertSame($existing->id, $lead->fresh()->family_account_id);
        $this->assertDatabaseCount('family_accounts', 1);
    }

    public function test_access_email_is_explicit_rate_limited_and_audited_without_verifying_email(): void
    {
        $account = $this->service()->create($this->admin, ['name' => 'New', 'email' => 'new@example.com']);
        Notification::assertNothingSent();
        $result = $this->service()->sendAccessEmail($this->admin, $account);

        $this->assertSame(Password::RESET_LINK_SENT, $result);
        Notification::assertSentTo($account->owner, LoLoCareResetPasswordNotification::class);
        $this->assertNull($account->owner->fresh()->email_verified_at);
        $this->assertSame(1, FamilyAccountActivityLog::where('action', 'customer_onboarding_access_email_sent')->count());
        try {
            $this->service()->sendAccessEmail($this->admin, $account);
            $this->fail('Repeated sends should be rate limited.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('access', $exception->errors());
        }
        Notification::assertSentToTimes($account->owner, LoLoCareResetPasswordNotification::class, 1);
    }

    public function test_non_admin_cannot_create_account(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $this->expectException(HttpException::class);
        $this->service()->create($sales, ['name' => 'New', 'email' => 'new@example.com']);
    }

    public function test_non_admin_cannot_send_access_email(): void
    {
        $account = $this->service()->create($this->admin, ['name' => 'New', 'email' => 'new@example.com']);
        $sales = User::factory()->create(['role' => 'sales']);
        $this->expectException(HttpException::class);
        $this->service()->sendAccessEmail($sales, $account);
    }

    private function assertCreationRejected(string $email): void
    {
        try {
            $this->service()->create($this->admin, ['name' => 'New Owner', 'email' => $email]);
            $this->fail('Unsafe existing account should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
        }
    }

    private function lead(string $email, array $overrides = []): Lead
    {
        return Lead::withoutEvents(fn () => Lead::create([
            'lead_type' => Lead::TYPE_FAMILY, 'name' => 'Family lead', 'email' => $email,
            'status' => 'new', 'source' => 'manual', ...$overrides,
        ]));
    }

    private function service(): CustomerOnboardingAccountService
    {
        return app(CustomerOnboardingAccountService::class);
    }
}
