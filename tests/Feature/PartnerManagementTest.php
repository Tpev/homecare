<?php

namespace Tests\Feature;

use App\Livewire\Admin\PartnersIndex;
use App\Livewire\Partner\Dashboard;
use App\Livewire\Partner\ProfileEditor;
use App\Livewire\Partner\Register;
use App\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PartnerManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Queue::fake();
    }

    private function profileData(array $overrides = []): array
    {
        return array_replace([
            'business_name' => 'Carolina Meal Partners',
            'website' => 'https://example.test',
            'description' => 'Fresh meals delivered to families.',
            'contact_name' => 'Alex Example',
            'contact_email' => 'alex@example.test',
            'contact_phone' => '(919) 555-0100',
            'categories' => ['meal_delivery'],
            'coverage_states' => ['NC'],
            'coverage_type' => 'specific_areas',
            'coverage_areas' => 'Wake County, 27601',
            'customer_notes' => 'Serving older adults and their families.',
        ], $overrides);
    }

    private function partner(array $overrides = []): PartnerProfile
    {
        $user = User::factory()->create(['role' => 'partner']);

        return PartnerProfile::create($this->profileData(array_replace([
            'user_id' => $user->id,
            'contact_email' => $user->email,
            'status' => 'new',
            'submitted_at' => now(),
            'terms_accepted_at' => now(),
        ], $overrides)));
    }

    private function filledRegistration(array $overrides = [])
    {
        $component = Livewire::test(Register::class);
        foreach ($this->profileData($overrides) as $field => $value) {
            $component->set('form.'.$field, $value);
        }

        return $component->set('password', 'A-strong-password-2026')
            ->set('password_confirmation', 'A-strong-password-2026')
            ->set('accept_terms', true);
    }

    public function test_partner_registration_is_public_and_private_pages_require_login(): void
    {
        $this->get(route('partner.register'))->assertOk()->assertSeeLivewire(Register::class);
        $this->get(route('partner.dashboard'))->assertRedirect(route('login'));
        $this->get(route('partner.profile.edit'))->assertRedirect(route('login'));
        $this->get(route('admin.partners.index'))->assertRedirect(route('login'));
    }

    public function test_registration_walks_through_steps_and_creates_one_partner_account(): void
    {
        $component = $this->filledRegistration([
            'business_name' => ' Carolina Meal Partners ',
            'contact_email' => 'ALEX@EXAMPLE.TEST',
            'website' => 'example.test',
            'coverage_states' => ['nc', 'SC'],
        ]);
        $component->call('nextStep')->assertHasNoErrors()->assertSet('step', 2)
            ->call('nextStep')->assertHasNoErrors()->assertSet('step', 3)
            ->call('previousStep')->assertSet('step', 2)
            ->call('nextStep')->assertSet('step', 3)
            ->call('register')->assertHasNoErrors()
            ->assertRedirect(route('partner.dashboard', absolute: false));

        $this->assertDatabaseCount('partner_profiles', 1);
        $user = User::query()->where('email', 'alex@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('partner', $user->role);
        $this->assertTrue(Hash::check('A-strong-password-2026', $user->password));
        $profile = $user->partnerProfile;
        $this->assertSame('Carolina Meal Partners', $profile->business_name);
        $this->assertSame('https://example.test', $profile->website);
        $this->assertSame(['NC', 'SC'], $profile->coverage_states);
        $this->assertSame('new', $profile->status);
        $this->assertNotNull($profile->submitted_at);
        $this->assertNotNull($profile->terms_accepted_at);
        $this->assertNull($profile->internal_notes);
        Mail::assertNothingSent();
    }

    public function test_registration_validates_all_fields_even_when_steps_are_skipped(): void
    {
        Livewire::test(Register::class)->call('register')
            ->assertHasErrors(['form.business_name', 'form.contact_email', 'form.categories', 'form.coverage_states']);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('partner_profiles', 0);
    }

    public function test_registration_requires_valid_service_geography_and_terms(): void
    {
        $this->filledRegistration([
            'categories' => ['not_a_service'],
            'coverage_states' => ['XX'],
            'coverage_areas' => '',
            'website' => 'javascript:alert(1)',
        ])->call('register')->assertHasErrors([
            'form.categories.0', 'form.coverage_states.0', 'form.coverage_areas', 'form.website',
        ]);
        $this->filledRegistration()->set('accept_terms', false)->call('register')->assertHasErrors('accept_terms');
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('partner_profiles', 0);
    }

    public function test_registration_rejects_duplicate_email_case_insensitively(): void
    {
        User::factory()->create(['email' => 'Alex@Example.Test']);
        $this->filledRegistration(['contact_email' => ' ALEX@example.test '])
            ->call('nextStep')->assertHasErrors('form.contact_email')
            ->call('register')->assertHasErrors('form.contact_email');

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('partner_profiles', 0);
        $this->assertGuest();
    }

    public function test_registration_rolls_back_account_when_profile_cannot_be_saved(): void
    {
        Event::listen('eloquent.creating: '.PartnerProfile::class, function (): void {
            throw new \RuntimeException('Simulated profile persistence failure');
        });
        try {
            $this->filledRegistration()->call('register');
            $this->fail('The simulated profile failure should be raised.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated profile persistence failure', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('partner_profiles', 0);
        $this->assertGuest();
    }

    public function test_existing_users_cannot_create_another_account_through_livewire_registration(): void
    {
        $user = User::factory()->create(['role' => 'family']);
        Livewire::actingAs($user)->test(Register::class)->assertForbidden();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_nonpartners_cannot_access_partner_pages_or_livewire_components(): void
    {
        foreach (['family', 'caregiver', 'sales', 'sdr', 'admin'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('partner.dashboard'))->assertForbidden();
            $this->get(route('partner.profile.edit'))->assertForbidden();
            Livewire::actingAs($user)->test(Dashboard::class)->assertForbidden();
            Livewire::actingAs($user)->test(ProfileEditor::class)->assertForbidden();
        }
    }

    public function test_partner_dashboard_only_shows_own_profile_and_no_admin_notes(): void
    {
        $own = $this->partner(['business_name' => 'Own Partner Business', 'internal_notes' => 'Private admin review notes']);
        $other = $this->partner(['business_name' => 'Another Partner Business']);

        $this->actingAs($own->user)->get(route('partner.dashboard', ['partner' => $other->id]))
            ->assertOk()->assertSee('Own Partner Business')->assertSee('Meal delivery')
            ->assertDontSee('Another Partner Business')->assertDontSee('Private admin review notes');
    }

    public function test_partner_profile_edit_is_scoped_to_owner_and_preserves_admin_fields(): void
    {
        $own = $this->partner(['status' => 'approved', 'internal_notes' => 'Keep this private']);
        $other = $this->partner(['business_name' => 'Unchanged other business']);
        $loginEmail = $own->user->email;

        Livewire::actingAs($own->user)->withQueryParams(['partner' => $other->id])->test(ProfileEditor::class)
            ->set('form.business_name', 'Updated own business')
            ->set('form.contact_email', 'operations@example.test')
            ->set('form.categories', ['meal_delivery', 'pharmacy_delivery'])
            ->set('form.coverage_type', 'entire_states')
            ->call('save')->assertHasNoErrors()->assertRedirect(route('partner.dashboard', absolute: false));

        $own->refresh();
        $this->assertSame('Updated own business', $own->business_name);
        $this->assertSame('approved', $own->status);
        $this->assertSame('Keep this private', $own->internal_notes);
        $this->assertNull($own->coverage_areas);
        $this->assertSame('operations@example.test', $own->contact_email);
        $this->assertSame($loginEmail, $own->user->email);
        $this->assertSame('Unchanged other business', $other->fresh()->business_name);
    }

    public function test_partner_profile_edit_rechecks_role_on_later_requests(): void
    {
        $profile = $this->partner();
        $component = Livewire::actingAs($profile->user)->test(ProfileEditor::class)
            ->set('form.business_name', 'Must not save');
        $profile->user->update(['role' => 'family']);
        $component->call('save')->assertForbidden();
        $this->assertSame('Carolina Meal Partners', $profile->fresh()->business_name);
    }

    public function test_admin_directory_filters_search_category_state_and_status(): void
    {
        $this->partner(['business_name' => 'Carolina Meal Partners', 'status' => 'approved']);
        $this->partner(['business_name' => 'Coastal Pharmacy', 'categories' => ['pharmacy_delivery'], 'coverage_states' => ['SC'], 'status' => 'new']);
        $this->partner(['business_name' => 'Mountain Home Safety', 'categories' => ['home_safety'], 'coverage_states' => ['NC'], 'status' => 'paused']);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.partners.index'))->assertOk();

        $component = Livewire::actingAs($admin)->test(PartnersIndex::class)
            ->assertSee('Carolina Meal Partners')->assertSee('Coastal Pharmacy')->assertSee('Mountain Home Safety');
        $component->set('category', 'meal_delivery')->assertSee('Carolina Meal Partners')->assertDontSee('Coastal Pharmacy')
            ->call('clearFilters')->set('state', 'SC')->assertSee('Coastal Pharmacy')->assertDontSee('Carolina Meal Partners')
            ->call('clearFilters')->set('status', 'paused')->assertSee('Mountain Home Safety')->assertDontSee('Carolina Meal Partners')
            ->call('clearFilters')->set('q', 'Coastal')->assertSee('Coastal Pharmacy')->assertDontSee('Mountain Home Safety')
            ->call('clearFilters')->set('q', 'Wake County')->assertSee('Carolina Meal Partners');
    }

    public function test_admin_can_save_status_and_internal_notes_without_changing_profile(): void
    {
        $partner = $this->partner();
        $admin = User::factory()->create(['role' => 'admin']);
        Livewire::actingAs($admin)->test(PartnersIndex::class)
            ->call('selectPartner', $partner->id)->set('partnerStatus', 'approved')
            ->set('internalNotes', ' Ready for a conversation. ')->call('savePartner')->assertHasNoErrors();

        $this->assertSame('approved', $partner->fresh()->status);
        $this->assertSame('Ready for a conversation.', $partner->fresh()->internal_notes);
        $this->assertSame('Carolina Meal Partners', $partner->fresh()->business_name);
        Livewire::actingAs($admin)->test(PartnersIndex::class)
            ->call('selectPartner', $partner->id)->set('partnerStatus', 'invented_status')
            ->call('savePartner')->assertHasErrors('partnerStatus');
        $this->assertSame('approved', $partner->fresh()->status);
    }

    public function test_nonadmins_cannot_access_or_mutate_partner_directory(): void
    {
        $partner = $this->partner();
        foreach (['partner', 'family', 'caregiver', 'sales', 'sdr'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('admin.partners.index'))->assertForbidden();
            Livewire::actingAs($user)->test(PartnersIndex::class)->assertForbidden();
        }
        $admin = User::factory()->create(['role' => 'admin']);
        $component = Livewire::actingAs($admin)->test(PartnersIndex::class)
            ->call('selectPartner', $partner->id)->set('partnerStatus', 'approved');
        $admin->update(['role' => 'family']);
        Auth::setUser($admin->fresh());
        $component->call('savePartner')->assertForbidden();
        $this->assertSame('new', $partner->fresh()->status);
    }

    public function test_normal_login_sends_partner_to_small_dashboard(): void
    {
        $partner = $this->partner();
        Volt::test('pages.auth.login')
            ->set('form.email', $partner->user->email)->set('form.password', 'password')
            ->call('login')->assertHasNoErrors()->assertRedirect(route('partner.dashboard', absolute: false));
        $this->assertAuthenticatedAs($partner->user);
    }
}
