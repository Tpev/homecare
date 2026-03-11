<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\CaregiverRegister;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CaregiverOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_caregiver_registration_screen_can_be_rendered(): void
    {
        $this->get('/caregiver/register')->assertOk();
    }

    public function test_new_caregiver_can_register(): void
    {
        Livewire::test(CaregiverRegister::class)
            ->set('name', 'Care Giver')
            ->set('email', 'caregiver@example.com')
            ->set('phone', '555-000-1111')
            ->set('city', 'Raleigh')
            ->set('state', 'NC')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->set('accept_terms', true)
            ->set('accept_independent_contractor', true)
            ->call('register')
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'caregiver@example.com',
            'role' => 'caregiver',
        ]);
    }

    public function test_onboarding_route_requires_caregiver_role(): void
    {
        Skill::query()->create(['name' => 'Companionship']);
        $user = User::factory()->create(['role' => 'family']);

        $this->actingAs($user)->get('/caregiver/onboarding')->assertForbidden();
    }
}
