<?php

namespace Tests\Feature\AiSupport;

use App\Livewire\Admin\AiSupport\UserPilotCard;
use App\Models\AiSupportPilotGrant;
use App\Models\FamilyAccount;
use App\Models\FamilyAccountMember;
use App\Models\User;
use App\Services\AiSupport\AiSupportControlService;
use App\Services\AiSupport\AiSupportEligibilityService;
use App\Services\AiSupport\AiSupportPilotGrantService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PilotAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_deployment_and_missing_controls_deny_customer_ai(): void
    {
        $family = User::factory()->create(['role' => 'family']);

        $result = app(AiSupportEligibilityService::class)->evaluate($family);
        $this->assertFalse($result->allowed);
        $this->assertSame('runtime_deployment_guard_off', $result->reasonCode);

        config(['ai_support.runtime_available' => true]);
        $result = app(AiSupportEligibilityService::class)->evaluate($family);
        $this->assertFalse($result->allowed);
        $this->assertSame('master_enabled_off', $result->reasonCode);

        $this->actingAs($family)->get(route('profile'))
            ->assertOk()
            ->assertDontSee('AI pilot access')
            ->assertDontSee('Enable AI pilot');
    }

    public function test_exact_user_grant_does_not_extend_to_another_member_of_same_family_account(): void
    {
        config(['ai_support.runtime_available' => true]);
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'family']);
        $member = User::factory()->create(['role' => 'family']);
        $account = FamilyAccount::query()->create([
            'owner_user_id' => $owner->id,
            'status' => FamilyAccount::STATUS_ACTIVE,
        ]);
        FamilyAccountMember::query()->create([
            'family_account_id' => $account->id,
            'user_id' => $owner->id,
            'access_level' => FamilyAccountMember::ACCESS_OWNER,
            'status' => FamilyAccountMember::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);
        FamilyAccountMember::query()->create([
            'family_account_id' => $account->id,
            'user_id' => $member->id,
            'access_level' => FamilyAccountMember::ACCESS_MEMBER,
            'status' => FamilyAccountMember::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);

        $this->openFamilyAnswerControls($admin);
        $eligibility = app(AiSupportEligibilityService::class);
        $this->assertSame('no_active_exact_user_grant', $eligibility->evaluate($owner)->reasonCode);

        $grant = app(AiSupportPilotGrantService::class)->grant(
            $admin,
            $owner,
            'family_support_v1',
            CarbonImmutable::now(),
            CarbonImmutable::now()->addDays(14),
            'Named usability pilot',
            (string) Str::uuid(),
        );

        $this->assertTrue($eligibility->evaluate($owner)->allowed);
        $this->assertSame($grant->id, $eligibility->evaluate($owner)->grantId);
        $this->assertFalse($eligibility->evaluate($member)->allowed);
        $this->assertSame('no_active_exact_user_grant', $eligibility->evaluate($member)->reasonCode);
        $this->assertDatabaseCount('ai_support_pilot_grants', 1);
        $this->assertDatabaseHas('ai_support_admin_audit_events', [
            'action' => 'pilot_grant_created',
            'target_user_id' => $owner->id,
            'result' => 'succeeded',
        ]);
    }

    public function test_expiry_role_change_and_revocation_take_effect_immediately(): void
    {
        $now = CarbonImmutable::parse('2026-08-13 10:00:00');
        CarbonImmutable::setTestNow($now);
        config(['ai_support.runtime_available' => true]);
        $admin = User::factory()->create(['role' => 'admin']);
        $family = User::factory()->create(['role' => 'family']);
        $this->openFamilyAnswerControls($admin);

        $service = app(AiSupportPilotGrantService::class);
        $grant = $service->grant(
            $admin,
            $family,
            'family_support_v1',
            $now,
            $now->addHour(),
            'Short controlled evaluation',
            (string) Str::uuid(),
        );
        $eligibility = app(AiSupportEligibilityService::class);
        $this->assertTrue($eligibility->evaluate($family)->allowed);

        $family->forceFill(['role' => 'sales'])->save();
        $this->assertSame('unsupported_role', $eligibility->evaluate($family->fresh())->reasonCode);
        $family->forceFill(['role' => 'family'])->save();

        $service->revoke($admin, $grant, 'Pilot session completed');
        $this->assertSame('no_active_exact_user_grant', $eligibility->evaluate($family->fresh())->reasonCode);
        $this->assertNotNull($grant->fresh()->retain_until);

        $renewal = $service->grant(
            $admin,
            $family->fresh(),
            'family_support_v1',
            $now,
            $now->addHour(),
            'Second bounded evaluation',
            (string) Str::uuid(),
        );
        $this->assertTrue($eligibility->evaluate($family->fresh())->allowed);

        CarbonImmutable::setTestNow($now->addHour());
        $this->assertSame('no_active_exact_user_grant', $eligibility->evaluate($family->fresh())->reasonCode);
        $this->assertSame('expired', $renewal->fresh()->status());
    }

    public function test_grant_is_idempotent_and_audit_failure_rolls_back_mutation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $family = User::factory()->create(['role' => 'family']);
        $requestKey = (string) Str::uuid();
        $service = app(AiSupportPilotGrantService::class);

        $first = $service->grant(
            $admin,
            $family,
            'family_support_v1',
            CarbonImmutable::now(),
            CarbonImmutable::now()->addDays(14),
            'Idempotency evaluation',
            $requestKey,
        );
        $again = $service->grant(
            $admin,
            $family,
            'family_support_v1',
            CarbonImmutable::now(),
            CarbonImmutable::now()->addDays(14),
            'Idempotency evaluation',
            $requestKey,
        );
        $this->assertSame($first->id, $again->id);
        $this->assertDatabaseCount('ai_support_pilot_grants', 1);
        $this->assertDatabaseCount('ai_support_admin_audit_events', 1);

        $other = User::factory()->create(['role' => 'family']);
        Schema::drop('ai_support_admin_audit_events');

        try {
            $service->grant(
                $admin,
                $other,
                'family_support_v1',
                CarbonImmutable::now(),
                CarbonImmutable::now()->addDays(14),
                'Must roll back without audit',
                (string) Str::uuid(),
            );
            $this->fail('Expected audit storage failure.');
        } catch (\Throwable) {
            $this->assertDatabaseMissing('ai_support_pilot_grants', ['user_id' => $other->id]);
        }
    }

    public function test_non_admin_cannot_mutate_grants_or_open_admin_control_plane(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $other = User::factory()->create(['role' => 'family']);

        $this->actingAs($family)->get(route('admin.ai-support.index'))->assertForbidden();

        $this->expectException(AuthorizationException::class);
        app(AiSupportPilotGrantService::class)->grant(
            $family,
            $other,
            'family_support_v1',
            CarbonImmutable::now(),
            CarbonImmutable::now()->addDays(14),
            'Unauthorized direct service call',
            (string) Str::uuid(),
        );
    }

    public function test_admin_user_card_creates_default_fourteen_day_grant_and_can_revoke_it(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-13 10:00:00'));
        $admin = User::factory()->create(['role' => 'admin']);
        $family = User::factory()->create(['role' => 'family']);

        $this->actingAs($admin)->get(route('admin.users.show', $family))
            ->assertOk()
            ->assertSee('AI pilot access')
            ->assertSee('Enable AI pilot');

        Livewire::actingAs($admin)
            ->test(UserPilotCard::class, ['user' => $family])
            ->assertSet('grantExpiresAt', '2026-08-27T10:00')
            ->set('grantReason', 'Admin controlled pilot')
            ->set('grantImpactConfirmed', true)
            ->call('enablePilot')
            ->assertHasNoErrors()
            ->assertSee('Disable now')
            ->set('revocationReason', 'Pilot observation finished')
            ->set('revocationImpactConfirmed', true)
            ->call('disablePilot')
            ->assertHasNoErrors();

        $grant = AiSupportPilotGrant::query()->sole();
        $this->assertNotNull($grant->revoked_at);
        $this->assertEquals(14, $grant->starts_at->diffInDays($grant->expires_at));
    }

    public function test_control_store_failure_denies_eligibility(): void
    {
        config(['ai_support.runtime_available' => true]);
        $family = User::factory()->create(['role' => 'family']);
        Schema::drop('ai_support_control_versions');

        $result = app(AiSupportEligibilityService::class)->evaluate($family);
        $this->assertFalse($result->allowed);
        $this->assertSame('eligibility_store_unavailable', $result->reasonCode);
    }

    private function openFamilyAnswerControls(User $admin): void
    {
        $controls = app(AiSupportControlService::class);
        $controls->set($admin, 'master_enabled', true, 'Open named pilot master control');
        $controls->set($admin, 'user_visible_enabled', true, 'Permit named pilot user experience');
        $controls->set($admin, 'human_only', false, 'Permit only otherwise eligible conversations');
        $controls->set($admin, 'role.family', true, 'Release family support role for pilot');
        $controls->set($admin, 'capability.support_answers_v1', true, 'Release answer-only support capability');
    }
}
