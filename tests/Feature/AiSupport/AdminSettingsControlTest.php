<?php

namespace Tests\Feature\AiSupport;

use App\Livewire\Admin\AiSupport\Settings;
use App\Models\AiSupportControlVersion;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\AiSupport\AiSupportControlService;
use App\Services\AiSupport\AiSupportEligibilityService;
use App\Services\AiSupport\AiSupportPilotGrantService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AdminSettingsControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_has_one_simple_availability_switch_without_release_readiness_workflow(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.ai-support.settings'))
            ->assertOk()
            ->assertSee('AI availability')
            ->assertSee('Make live for everyone')
            ->assertDontSee('Release readiness')
            ->assertDontSee('Record a control change')
            ->assertDontSee('Type APPLY')
            ->assertDontSee('Shadow Enabled');

        $this->actingAs($admin)
            ->get(route('admin.ai-support.readiness'))
            ->assertRedirect(route('admin.ai-support.settings'));
    }

    public function test_one_switch_enables_role_appropriate_ai_for_everyone_without_grants(): void
    {
        config([
            'ai_support.runtime_available' => true,
            'ai_support.provider_enabled' => true,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);

        Livewire::actingAs($admin)
            ->test(Settings::class)
            ->call('enableForEveryone')
            ->assertHasNoErrors()
            ->assertSee('Live for everyone')
            ->assertSee('Switch back to pilot only');

        $controls = app(AiSupportControlService::class);
        $this->assertTrue($controls->enabled('general_release_enabled'));
        $this->assertFalse($controls->enabled('human_only'));
        $this->assertTrue($controls->enabled('role.family'));
        $this->assertTrue($controls->enabled('role.caregiver'));
        $this->assertTrue($controls->enabled('tool.care-request.publish.one-time'));
        $this->assertTrue($controls->enabled('tool.care-request.publish.recurring'));
        $this->assertDatabaseCount('ai_support_pilot_grants', 0);

        $eligibility = app(AiSupportEligibilityService::class);
        $this->assertTrue($eligibility->evaluate($family)->allowed);
        $this->assertNull($eligibility->evaluate($family)->grantId);
        $this->assertTrue($eligibility->evaluate(
            $family,
            'care_request_publish_v1',
            toolId: 'care-request.publish.recurring',
        )->allowed);
        $this->assertTrue($eligibility->evaluate($caregiver)->allowed);
        $this->assertSame(
            'capability_not_available_for_role',
            $eligibility->evaluate($caregiver, 'care_request_draft_v1')->reasonCode,
        );
    }

    public function test_switching_back_to_pilot_and_emergency_stop_take_effect_immediately(): void
    {
        config(['ai_support.runtime_available' => true]);
        $admin = User::factory()->create(['role' => 'admin']);
        $family = User::factory()->create(['role' => 'family']);
        $controls = app(AiSupportControlService::class);
        $eligibility = app(AiSupportEligibilityService::class);

        $controls->enableForEveryone($admin);
        $this->assertTrue($eligibility->evaluate($family)->allowed);

        $controls->stopAllAutomation($admin);
        $this->assertSame('human_only_mode', $eligibility->evaluate($family)->reasonCode);

        $controls->resumeAutomation($admin);
        $this->assertTrue($eligibility->evaluate($family)->allowed);

        $controls->usePilotOnly($admin);
        $this->assertSame('no_active_exact_user_grant', $eligibility->evaluate($family)->reasonCode);
        $this->assertFalse($controls->enabled('general_release_enabled'));
    }

    public function test_switching_back_to_pilot_keeps_pilot_chat_automated_and_returns_everyone_else_to_humans(): void
    {
        config(['ai_support.runtime_available' => true]);
        $admin = User::factory()->create(['role' => 'admin']);
        $pilot = User::factory()->create(['role' => 'family']);
        $nonPilot = User::factory()->create(['role' => 'family']);
        $controls = app(AiSupportControlService::class);
        $controls->enableForEveryone($admin);
        app(AiSupportPilotGrantService::class)->grant(
            $admin,
            $pilot,
            'family_support_v1',
            CarbonImmutable::now(),
            CarbonImmutable::now()->addDays(14),
            'Keep this user in the two-user pilot',
            (string) Str::uuid(),
        );

        $pilotTicket = $this->automatedTicket($pilot);
        $nonPilotTicket = $this->automatedTicket($nonPilot);

        $controls->usePilotOnly($admin);

        $this->assertSame(SupportTicket::RESPONDER_MODE_AUTOMATED, $pilotTicket->fresh()->responder_mode);
        $this->assertSame(SupportTicket::RESPONDER_MODE_HUMAN_ONLY, $nonPilotTicket->fresh()->responder_mode);
        $this->assertSame('general_release_ended', $nonPilotTicket->fresh()->handoff_reason_code);
    }

    public function test_shadow_remains_known_to_the_server_and_cannot_be_enabled(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $controls = app(AiSupportControlService::class);

        $this->assertContains('shadow_enabled', $controls->keys());

        try {
            $controls->set($admin, 'shadow_enabled', true, 'Attempt to enable excluded shadow mode');
            $this->fail('The permanent no-shadow server policy must reject enablement.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['Shadow mode is intentionally disabled under DEC-047.'],
                $exception->errors()['controlKey'],
            );
        }

        $this->assertSame(0, AiSupportControlVersion::query()->count());
    }

    private function automatedTicket(User $opener): SupportTicket
    {
        return SupportTicket::query()->create([
            'opener_user_id' => $opener->id,
            'source' => SupportTicket::SOURCE_CHAT_WIDGET,
            'responder_mode' => SupportTicket::RESPONDER_MODE_AUTOMATED,
            'category' => 'general',
            'status' => SupportTicket::STATUS_OPEN,
            'priority' => 'normal',
            'subject' => 'Need help',
            'description' => 'Please help me use LoLo.',
        ]);
    }
}
