<?php

namespace Tests\Feature\AiSupport;

use App\Livewire\Admin\AiSupport\Settings;
use App\Models\AiSupportControlVersion;
use App\Models\User;
use App\Services\AiSupport\AiSupportControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AdminSettingsControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_settings_omits_shadow_and_rejects_a_forged_shadow_change(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.ai-support.settings'))
            ->assertOk()
            ->assertDontSee('Shadow Enabled')
            ->assertDontSee('shadow_enabled');

        Livewire::actingAs($admin)
            ->test(Settings::class)
            ->assertDontSee('Shadow Enabled')
            ->set('controlKey', 'shadow_enabled')
            ->set('desiredEnabled', false)
            ->set('controlReason', 'Attempt a hidden operator control change')
            ->set('impactConfirmed', true)
            ->set('confirmationText', 'APPLY')
            ->call('changeControl')
            ->assertHasErrors(['controlKey']);

        $this->assertDatabaseCount('ai_support_control_versions', 0);
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
}
