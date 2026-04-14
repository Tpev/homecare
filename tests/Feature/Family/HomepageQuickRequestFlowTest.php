<?php

namespace Tests\Feature\Family;

use App\Livewire\Family\CreateCareRequestWizard;
use App\Livewire\Family\HomepageQuickRequest;
use App\Models\CareTask;
use App\Models\User;
use App\Support\FamilyQuickRequestDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class HomepageQuickRequestFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_shows_direct_message_and_quick_request_entry(): void
    {
        CareTask::query()->create(['name' => 'Companionship']);

        $this->get(route('landing'))
            ->assertOk()
            ->assertSeeText('Trusted help for mom or dad, from quick support to full coverage.')
            ->assertSeeText('Call or text (984) 400-4008')
            ->assertSee('Start your care request now.');
    }

    public function test_guest_can_start_quick_request_and_is_redirected_to_register_with_saved_draft(): void
    {
        $task = CareTask::query()->create(['name' => 'Companionship']);

        $component = Livewire::test(HomepageQuickRequest::class)
            ->set('recipient_name', 'Margaret Johnson')
            ->set('selectedTasks', [$task->id])
            ->set('requested_start_at', now()->addDay()->setTime(9, 0)->format('Y-m-d\TH:i'))
            ->set('requested_end_at', now()->addDay()->setTime(13, 0)->format('Y-m-d\TH:i'))
            ->set('address_line1', '101 Oak Street')
            ->set('city', 'Raleigh')
            ->set('state', 'NC')
            ->set('zip', '27601')
            ->set('additional_info', 'Just needs lunch and companionship while I am at work.')
            ->set('step', 3);

        $component->call('startAccountHandoff')
            ->assertRedirect(route('register', absolute: false));

        $draft = session(FamilyQuickRequestDraft::SESSION_KEY);

        $this->assertIsArray($draft);
        $this->assertSame('Margaret Johnson', $draft['recipient_full_name']);
        $this->assertSame([$task->id], $draft['selectedTasks']);
        $this->assertSame('Raleigh', $draft['city']);
    }

    public function test_family_request_wizard_prefills_from_homepage_quick_request_draft(): void
    {
        $task = CareTask::query()->create(['name' => 'Companionship']);
        $family = User::factory()->create(['role' => 'family']);

        $this->actingAs($family);
        session([
            FamilyQuickRequestDraft::SESSION_KEY => [
                'request_mode' => CreateCareRequestWizard::MODE_FAST_TRACK,
                'modeChosen' => true,
                'step' => 4,
                'request_type' => 'one_time',
                'recipient_full_name' => 'Margaret Johnson',
                'selectedTasks' => [$task->id],
                'additional_info' => 'Lunch and companionship.',
                'requested_start_at' => now()->addDay()->setTime(9, 0)->format('Y-m-d H:i:s'),
                'requested_end_at' => now()->addDay()->setTime(13, 0)->format('Y-m-d H:i:s'),
                'address_line1' => '101 Oak Street',
                'city' => 'Raleigh',
                'state' => 'NC',
                'zip' => '27601',
            ],
        ]);

        Livewire::actingAs($family)
            ->test(CreateCareRequestWizard::class)
            ->assertSet('modeChosen', true)
            ->assertSet('step', 4)
            ->assertSet('recipient_full_name', 'Margaret Johnson')
            ->assertSet('selectedTasks', [$task->id])
            ->assertSet('city', 'Raleigh')
            ->assertSet('zip', '27601');
    }

    public function test_registration_redirects_to_request_wizard_when_quick_request_draft_exists(): void
    {
        session([
            FamilyQuickRequestDraft::SESSION_KEY => [
                'recipient_full_name' => 'Margaret Johnson',
            ],
        ]);

        $component = Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->set('accept_terms', true);

        $component->call('register')
            ->assertRedirect(route('family.requests.create', absolute: false));
    }

    public function test_login_redirects_to_request_wizard_when_quick_request_draft_exists_for_family(): void
    {
        $user = User::factory()->create(['role' => 'family']);

        session([
            FamilyQuickRequestDraft::SESSION_KEY => [
                'recipient_full_name' => 'Margaret Johnson',
            ],
        ]);

        Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertRedirect(route('family.requests.create', absolute: false));
    }
}
