<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Family\FamilyOnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response
            ->assertOk()
            ->assertSeeVolt('pages.auth.login')
            ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-WJG3HG6EG6', false)
            ->assertSee("gtag('config', 'G-WJG3HG6EG6');", false);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password');

        $component->call('login');

        $component
            ->assertHasNoErrors()
            ->assertRedirect(route('family.requests.index', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_family_login_replaces_a_saved_old_dashboard_destination(): void
    {
        $user = User::factory()->create(['role' => 'family']);
        session(['url.intended' => route('dashboard')]);

        Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('family.requests.index', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertFalse(session()->has('url.intended'));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'wrong-password');

        $component->call('login');

        $component
            ->assertHasErrors()
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_existing_family_session_visiting_login_goes_directly_to_care(): void
    {
        $family = User::factory()->create(['role' => 'family']);

        $this->actingAs($family)->get('/login')->assertRedirect(route('family.requests.index'));
    }

    public function test_remembered_family_session_visiting_login_goes_directly_to_care(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $cookieName = Auth::guard('web')->getRecallerName();
        $cookieValue = implode('|', [$family->id, $family->remember_token, $family->getAuthPassword()]);

        $this->withCookie($cookieName, $cookieValue)->get('/login')
            ->assertRedirect(route('family.requests.index'));

        $this->assertAuthenticatedAs($family);
        $this->assertTrue(Auth::guard('web')->viaRemember());
    }

    public function test_old_family_dashboard_url_redirects_to_care(): void
    {
        $family = User::factory()->create(['role' => 'family']);

        $this->actingAs($family)->get('/dashboard')->assertRedirect(route('family.requests.index'));
    }

    public function test_family_login_preserves_a_specific_visit_destination(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $destination = route('family.requests.show', ['careRequest' => 123, 'tab' => 'visit']);
        session(['url.intended' => $destination]);

        Volt::test('pages.auth.login')
            ->set('form.email', $family->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect($destination);
    }

    public function test_existing_family_session_still_requires_unfinished_onboarding(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        app(FamilyOnboardingService::class)->enrollRegistration($family);

        $this->actingAs($family)->get('/login')->assertRedirect(route('family.requests.index'));
        $this->get(route('family.requests.index'))->assertRedirect(route('family.onboarding'));
        $this->get('/dashboard')->assertRedirect(route('family.onboarding'));
    }

    public function test_other_roles_keep_their_existing_login_fallback(): void
    {
        foreach (['admin', 'caregiver', 'sales', 'sdr'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get('/login')->assertRedirect(route('dashboard'));
        }
    }

    public function test_navigation_menu_can_be_rendered(): void
    {
        $user = User::factory()->create(['role' => 'family']);

        $this->actingAs($user);

        $response = $this->get(route('family.requests.index'));

        $response
            ->assertOk()
            ->assertSeeVolt('layout.navigation')
            ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-WJG3HG6EG6', false)
            ->assertSee("gtag('config', 'G-WJG3HG6EG6');", false);
    }

    public function test_compact_admin_navigation_is_not_rendered_for_family_caregiver_or_sdr_users(): void
    {
        foreach (['family', 'caregiver', 'sdr'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user);

            Volt::test('layout.navigation')
                ->assertDontSeeHtml('data-admin-compact-navigation')
                ->assertDontSeeHtml('data-admin-all-tools');
        }
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('layout.navigation');

        $component->call('logout');

        $component
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
    }
}
