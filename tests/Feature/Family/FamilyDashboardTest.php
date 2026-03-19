<?php

namespace Tests\Feature\Family;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FamilyDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_family_user_sees_family_dashboard_sections(): void
    {
        $family = User::factory()->create([
            'role' => 'family',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($family)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Family Dashboard');
        $response->assertSee('Create request');
        $response->assertSee('Start with AI Copilot');
        $response->assertSee('Use manual form');
        $response->assertSee('Priority request board');
        $response->assertSee('Operations signal');
    }

    public function test_caregiver_user_sees_caregiver_dashboard_sections(): void
    {
        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($caregiver)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Caregiver Dashboard');
        $response->assertSee('Work inbox');
        $response->assertSee('Task comfort selection');
        $response->assertSee('Payout setup');
        $response->assertSee('Insurance setup');
    }
}
