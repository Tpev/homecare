<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\UsersIndex;
use App\Models\CareRecipientProfile;
use App\Models\CareRequest;
use App\Models\User;
use App\Services\CareRecipientProfiles\CareRecipientProfileService;
use App\Services\Family\FamilyOnboardingService;
use App\Services\FamilyAccounts\FamilyAccountProvisioner;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminUserDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Queue::fake();
        Notification::fake();
        config(['marketplace.ops_alert_recipients' => ['ops@example.test']]);
    }

    public function test_admin_can_delete_an_unfinished_family_signup(): void
    {
        [$user, $onboarding] = $this->family();

        $this->deleteAsAdmin($user)->assertHasNoErrors()->assertSee('User account deleted.');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('family_accounts', ['id' => $onboarding->family_account_id]);
        $this->assertDatabaseMissing('family_account_members', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('family_onboardings', ['id' => $onboarding->id]);
    }

    public function test_admin_can_delete_completed_onboarding_profiles_and_an_unused_request(): void
    {
        [$user, $onboarding] = $this->family(completed: true);
        [$other, $otherOnboarding] = $this->family(completed: true);
        $profile = CareRecipientProfile::findOrFail($onboarding->care_recipient_profile_id);
        $profile = app(CareRecipientProfileService::class)->makeReady($user, $profile, [], $profile->revision, true);
        $request = $this->request($user, $onboarding->family_account_id);
        DB::table('care_request_recipients')->insert([
            'care_request_id' => $request->id, 'full_name' => 'Test recipient', 'relationship_to_family' => 'Parent',
            'care_recipient_profile_id' => $profile->id,
            'care_recipient_profile_version_id' => $profile->latest_ready_version_id,
        ]);
        DB::table('sessions')->insert(['id' => 'test-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => 'test-token']);
        $this->assertDatabaseHas('family_welcome_visits', ['family_onboarding_id' => $onboarding->id]);
        $this->assertDatabaseHas('family_onboarding_deliveries', ['family_onboarding_id' => $onboarding->id]);

        $this->deleteAsAdmin($user)->assertHasNoErrors();

        foreach ([
            'users' => ['id' => $user->id],
            'family_accounts' => ['id' => $onboarding->family_account_id],
            'family_account_activity_logs' => ['family_account_id' => $onboarding->family_account_id],
            'family_household_profiles' => ['family_user_id' => $user->id],
            'family_recipient_profiles' => ['family_user_id' => $user->id],
            'care_recipient_profiles' => ['id' => $profile->id],
            'care_recipient_profile_versions' => ['care_recipient_profile_id' => $profile->id],
            'care_requests' => ['id' => $request->id],
            'care_request_recipients' => ['care_request_id' => $request->id],
            'family_onboardings' => ['id' => $onboarding->id],
            'family_welcome_visits' => ['family_onboarding_id' => $onboarding->id],
            'family_onboarding_deliveries' => ['family_onboarding_id' => $onboarding->id],
            'sessions' => ['user_id' => $user->id],
            'password_reset_tokens' => ['email' => $user->email],
        ] as $table => $attributes) {
            $this->assertDatabaseMissing($table, $attributes);
        }
        $this->assertDatabaseHas('users', ['id' => $other->id]);
        $this->assertDatabaseHas('family_onboardings', ['id' => $otherOnboarding->id]);
        $this->assertDatabaseHas('care_recipient_profiles', ['id' => $otherOnboarding->care_recipient_profile_id]);
    }

    public function test_legacy_family_without_onboarding_can_be_deleted(): void
    {
        $user = User::factory()->create(['role' => 'family']);
        $account = app(FamilyAccountProvisioner::class)->provisionOwner($user, 'existing_account_migrated');
        $request = $this->request($user); // Legacy request without family_account_id.

        $this->deleteAsAdmin($user)->assertHasNoErrors();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('family_accounts', ['id' => $account->id]);
        $this->assertDatabaseMissing('care_requests', ['id' => $request->id]);
    }

    public function test_unaccepted_invitation_is_removed_without_deleting_the_invited_user(): void
    {
        [$user, $onboarding] = $this->family();
        [$invited] = $this->family();
        $invitationId = DB::table('family_account_invitations')->insertGetId([
            'family_account_id' => $onboarding->family_account_id, 'invited_by_user_id' => $user->id,
            'email_normalized' => $invited->email, 'token_hash' => hash('sha256', 'test-invitation'), 'expires_at' => now()->addDay(),
        ]);
        DB::table('family_account_activity_logs')->insert([
            'family_account_id' => $onboarding->family_account_id, 'actor_user_id' => $user->id, 'action' => 'invitation_sent',
        ]);

        $this->deleteAsAdmin($user)->assertHasNoErrors();

        $this->assertDatabaseMissing('family_account_invitations', ['id' => $invitationId]);
        $this->assertDatabaseHas('users', ['id' => $invited->id]);
    }

    #[DataProvider('membershipStatuses')]
    public function test_shared_family_and_membership_history_are_preserved(string $status): void
    {
        [$user, $onboarding] = $this->family();
        $member = User::factory()->create(['role' => 'family']);
        DB::table('family_account_members')->insert([
            'family_account_id' => $onboarding->family_account_id, 'user_id' => $member->id,
            'access_level' => 'member', 'status' => $status,
        ]);

        $this->deleteAsAdmin($user)->assertHasErrors('delete')->assertSee('shared family account');
        $this->deleteAsAdmin($member)->assertHasErrors('delete')->assertSee('shared family account');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('users', ['id' => $member->id]);
        $this->assertDatabaseHas('family_account_members', ['user_id' => $member->id, 'status' => $status]);
    }

    public static function membershipStatuses(): array
    {
        return [['active'], ['left'], ['removed']];
    }

    public function test_applications_protect_both_family_and_caregiver(): void
    {
        [$user, $onboarding] = $this->family(completed: true);
        $request = $this->request($user, $onboarding->family_account_id);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $applicationId = DB::table('care_request_applications')->insertGetId([
            'care_request_id' => $request->id, 'caregiver_user_id' => $caregiver->id, 'status' => 'withdrawn',
        ]);

        foreach ([$user, $caregiver] as $target) {
            $this->deleteAsAdmin($target)->assertHasErrors('delete')->assertSee('caregiver applications');
            $this->assertDatabaseHas('users', ['id' => $target->id]);
        }
        $this->assertDatabaseHas('care_request_applications', ['id' => $applicationId]);
        $this->assertDatabaseHas('family_onboardings', ['id' => $onboarding->id]);
    }

    public function test_cancelled_legacy_visit_and_refunded_payment_are_preserved(): void
    {
        [$user, $onboarding] = $this->family(completed: true);
        $request = $this->request($user);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $bookingId = DB::table('care_bookings')->insertGetId([
            'care_request_id' => $request->id, 'family_user_id' => $user->id,
            'caregiver_user_id' => $caregiver->id, 'status' => 'cancelled',
        ]);
        $paymentId = DB::table('care_booking_payments')->insertGetId([
            'care_booking_id' => $bookingId, 'family_user_id' => $user->id,
            'caregiver_user_id' => $caregiver->id, 'status' => 'refunded', 'amount_captured_cents' => 5000,
        ]);

        foreach ([$user, $caregiver] as $target) {
            $this->deleteAsAdmin($target)->assertHasErrors('delete')->assertSee('payment history');
            $this->assertDatabaseHas('users', ['id' => $target->id]);
        }
        $this->assertDatabaseHas('care_bookings', ['id' => $bookingId]);
        $this->assertDatabaseHas('care_booking_payments', ['id' => $paymentId, 'amount_captured_cents' => 5000]);
        $this->assertDatabaseHas('family_onboardings', ['id' => $onboarding->id]);
    }

    public function test_confirmed_welcome_visit_is_protected(): void
    {
        [$user, $onboarding] = $this->family(completed: true);
        DB::table('family_welcome_visits')->where('family_onboarding_id', $onboarding->id)
            ->update(['status' => 'confirmed', 'confirmed_start_at' => now()->addDays(3)]);

        $this->deleteAsAdmin($user)->assertHasErrors('delete')->assertSee('welcome visit being handled');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('family_welcome_visits', ['family_onboarding_id' => $onboarding->id, 'status' => 'confirmed']);
    }

    public function test_support_history_is_protected(): void
    {
        [$user, $onboarding] = $this->family();
        DB::table('support_tickets')->insert([
            'opener_user_id' => $user->id, 'family_account_id' => $onboarding->family_account_id,
            'category' => 'general', 'subject' => 'Help', 'description' => 'Keep support history.',
        ]);

        $this->deleteAsAdmin($user)->assertHasErrors('delete')->assertSee('support tickets');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('support_tickets', ['opener_user_id' => $user->id]);
    }

    public function test_unknown_foreign_key_failure_rolls_back_all_cleanup(): void
    {
        [$user, $onboarding] = $this->family(completed: true);
        $request = $this->request($user, $onboarding->family_account_id);
        Schema::create('admin_deletion_test_dependencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
        });
        try {
            DB::table('admin_deletion_test_dependencies')->insert(['user_id' => $user->id]);

            $this->deleteAsAdmin($user)->assertHasErrors('delete')->assertSee('Nothing was deleted.');

            $this->assertDatabaseHas('users', ['id' => $user->id]);
            $this->assertDatabaseHas('family_accounts', ['id' => $onboarding->family_account_id]);
            $this->assertDatabaseHas('family_account_members', ['user_id' => $user->id]);
            $this->assertDatabaseHas('family_onboardings', ['id' => $onboarding->id]);
            $this->assertDatabaseHas('family_welcome_visits', ['family_onboarding_id' => $onboarding->id]);
            $this->assertDatabaseHas('family_onboarding_deliveries', ['family_onboarding_id' => $onboarding->id]);
            $this->assertDatabaseHas('care_recipient_profiles', ['id' => $onboarding->care_recipient_profile_id]);
            $this->assertDatabaseHas('care_requests', ['id' => $request->id]);
        } finally {
            Schema::drop('admin_deletion_test_dependencies');
        }
    }

    public function test_cross_account_legacy_request_cannot_be_deleted(): void
    {
        [$user] = $this->family();
        [, $otherOnboarding] = $this->family();
        $request = $this->request($user, $otherOnboarding->family_account_id);

        $this->deleteAsAdmin($user)->assertHasErrors('delete')->assertSee('shared family account');

        $this->assertDatabaseHas('care_requests', ['id' => $request->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    #[DataProvider('nonAdminRoles')]
    public function test_delete_action_rejects_non_administrators(string $role): void
    {
        $actor = User::factory()->create(['role' => $role]);
        [$user] = $this->family();

        Livewire::actingAs($actor)->test(UsersIndex::class)->call('deleteUser', $user->id)->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public static function nonAdminRoles(): array
    {
        return [['family'], ['caregiver'], ['sales'], ['sdr']];
    }

    public function test_staff_cannot_be_deleted_by_an_administrator(): void
    {
        foreach (['admin', 'sales', 'sdr'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->deleteAsAdmin($user)->assertHasErrors('delete')->assertSee('Staff users cannot be deleted');
            $this->assertDatabaseHas('users', ['id' => $user->id]);
        }
    }

    private function deleteAsAdmin(User $user): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test(UsersIndex::class)->call('deleteUser', $user->id);
    }

    private function family(bool $completed = false): array
    {
        $user = User::factory()->create(['role' => 'family']);
        $service = app(FamilyOnboardingService::class);
        $record = DB::transaction(fn () => $service->enrollRegistration($user));
        $record->refresh();
        if ($completed) {
            $answers = [
                'care_for' => 'family', 'recipient_name' => 'Test recipient', 'relationship' => 'Parent',
                'address_line1' => '123 Oak Street', 'address_line2' => '', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
                'care_notes' => 'Enjoys the garden.', 'care_preferences' => 'A gentle pace.',
                'welcome_visit' => 'yes', 'visit_date' => now()->addDays(3)->toDateString(), 'visit_time' => 'morning', 'phone' => '(984) 400-4008',
            ];
            for ($step = 1; $step <= 4; $step++) {
                $record = $service->saveStep($user, $answers, $step, $record->revision);
            }
            $record = $service->complete($user, $record->revision);
        }

        return [$user, $record];
    }

    private function request(User $user, ?int $accountId = null): CareRequest
    {
        return CareRequest::withoutEvents(fn () => CareRequest::create([
            'family_user_id' => $user->id, 'family_account_id' => $accountId,
            'title' => 'Test request', 'status' => 'open', 'address_line1' => '123 Oak Street',
            'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
        ]));
    }
}
