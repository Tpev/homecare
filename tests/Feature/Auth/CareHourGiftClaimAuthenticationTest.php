<?php

namespace Tests\Feature\Auth;

use App\Models\FamilyAccountMember;
use App\Models\User;
use App\Services\Family\FamilyOnboardingService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Support\CareHourGiftClaimIntent;
use App\Support\FamilyQuickRequestDraft;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CareHourGiftClaimAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const CODE = 'GIFT-TEST-PRIVATE-CLAIM';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        Queue::fake();
        $this->freezeTime();
    }

    public function test_intent_encrypts_code_limits_summary_and_survives_session_rotation_until_expiry(): void
    {
        CareHourGiftClaimIntent::put(self::CODE, [
            'paid_minutes' => 600, 'conditional_bonus_minutes' => 60,
            'buyer_email' => 'private@example.test', 'recipient_name' => 'Do not expose',
        ]);
        $payload = session(CareHourGiftClaimIntent::SESSION_KEY);
        $this->assertStringNotContainsString(self::CODE, json_encode($payload));
        $this->assertStringNotContainsString('private@example.test', json_encode($payload));
        $this->assertSame(['paid_minutes' => 600, 'conditional_bonus_minutes' => 60], CareHourGiftClaimIntent::summary());
        $oldSession = session()->getId();
        session()->regenerate();
        $this->assertNotSame($oldSession, session()->getId());
        $this->assertTrue(CareHourGiftClaimIntent::active());
        $this->assertSame(self::CODE, CareHourGiftClaimIntent::code());
        $this->travel(59)->minutes();
        $this->assertTrue(CareHourGiftClaimIntent::active());
        $this->travel(1)->minutes();
        $this->assertFalse(CareHourGiftClaimIntent::active());
        $this->assertNull(CareHourGiftClaimIntent::code());
        $this->assertNull(CareHourGiftClaimIntent::summary());
        $this->assertFalse(session()->has(CareHourGiftClaimIntent::SESSION_KEY));
    }

    public function test_tampered_intent_is_cleared_without_showing_or_using_a_code(): void
    {
        $this->intent();
        $payload = session(CareHourGiftClaimIntent::SESSION_KEY);
        $payload['encrypted_code'] = self::CODE;
        session([CareHourGiftClaimIntent::SESSION_KEY => $payload]);
        $this->assertFalse(CareHourGiftClaimIntent::active());
        $this->assertNull(CareHourGiftClaimIntent::code());
        $this->assertFalse(session()->has(CareHourGiftClaimIntent::SESSION_KEY));
    }

    public function test_gift_signup_keeps_validation_with_optional_terms_then_creates_only_the_family_account(): void
    {
        $this->intent();
        FamilyQuickRequestDraft::put(['recipient_full_name' => 'Existing request draft']);
        Event::fake([Registered::class]);
        $component = Volt::test('pages.auth.register')
            ->assertSee('Create your account to claim your gift')
            ->assertSee('Your gift is saved. No card needed.')
            ->assertDontSee('Are you offering care?')->assertDontSee('Phone number (optional)')
            ->assertDontSee(self::CODE)
            ->set('name', 'Gift Recipient')->set('email', 'gift-recipient@example.test')
            ->set('password', 'password')->set('password_confirmation', 'different')
            ->call('register')->assertHasErrors(['password'])->assertHasNoErrors(['accept_terms']);
        $this->assertGuest();
        $this->assertTrue(CareHourGiftClaimIntent::active());
        $this->assertDatabaseCount('users', 0);
        $oldSession = session()->getId();

        $component->set('password_confirmation', 'password')->assertSet('accept_terms', false)
            ->call('register')->assertHasNoErrors()
            ->assertRedirect(route('family.care-hours.claim', absolute: false));
        $user = User::where('email', 'gift-recipient@example.test')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSession, session()->getId());
        $this->assertSame(self::CODE, CareHourGiftClaimIntent::code());
        $this->assertTrue(FamilyQuickRequestDraft::has());
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->phone);
        $this->assertFalse($user->sms_opt_in);
        $this->assertDatabaseHas('family_account_members', ['user_id' => $user->id, 'status' => 'active', 'access_level' => 'owner']);
        $this->assertDatabaseCount('family_accounts', 1);
        $this->assertDatabaseCount('family_onboardings', 0);
        $this->assertDatabaseCount('care_requests', 0);
        $this->assertDatabaseCount('care_recipient_profiles', 0);
        Event::assertDispatched(Registered::class, fn ($event) => $event->user->is($user));
    }

    public function test_gift_auth_layout_is_focused_and_never_renders_the_bearer_code(): void
    {
        $this->intent();
        $this->get(route('register'))->assertOk()
            ->assertSee('Create your account to claim your gift')->assertSee('Your gift is saved. No card needed.')
            ->assertDontSee('Care coordination that feels lighter.')->assertDontSee('Register as a caregiver')
            ->assertDontSee('Phone number (optional)')->assertDontSee(self::CODE);
        $this->get(route('login'))->assertOk()->assertSee('Sign in to claim your gift')
            ->assertDontSee('See upcoming visits and care plans.')->assertDontSee(self::CODE);
    }

    public function test_gift_login_retries_preserve_intent_and_return_before_existing_request_onboarding(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $onboarding = app(FamilyOnboardingService::class)->enrollRegistration($family);
        $this->intent();
        $component = Volt::test('pages.auth.login')->set('form.email', $family->email)
            ->set('form.password', 'incorrect')->call('login')->assertHasErrors('form.email')->assertNoRedirect();
        $this->assertGuest();
        $this->assertSame(self::CODE, CareHourGiftClaimIntent::code());
        $oldSession = session()->getId();
        $component->set('form.password', 'password')->call('login')->assertHasNoErrors()
            ->assertRedirect(route('family.care-hours.claim', absolute: false));
        $this->assertAuthenticatedAs($family);
        $this->assertNotSame($oldSession, session()->getId());
        $this->assertSame(self::CODE, CareHourGiftClaimIntent::code());
        $this->assertSame('in_progress', $onboarding->fresh()->status);
        $this->assertTrue(app(FamilyOnboardingService::class)->pending($family));
    }

    public function test_expired_gift_signup_uses_the_existing_registration_and_onboarding_flow(): void
    {
        $this->intent();
        $this->travel(1)->hours();
        Volt::test('pages.auth.register')->assertSee('Create your family account')
            ->set('name', 'Ordinary Signup')->set('email', 'ordinary@example.test')
            ->set('password', 'password')->set('password_confirmation', 'password')->set('accept_terms', true)
            ->call('register')->assertHasNoErrors()->assertRedirect(route('family.onboarding', absolute: false));
        $this->assertDatabaseCount('family_onboardings', 1);
        $this->assertFalse(CareHourGiftClaimIntent::active());
    }

    public function test_expired_gift_login_preserves_the_existing_intended_visit(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $destination = route('family.requests.show', ['careRequest' => 123, 'tab' => 'visit']);
        session(['url.intended' => $destination]);
        $this->intent();
        $this->travel(1)->hours();
        Volt::test('pages.auth.login')->set('form.email', $family->email)->set('form.password', 'password')
            ->call('login')->assertHasNoErrors()->assertRedirect($destination);
        $this->assertFalse(CareHourGiftClaimIntent::active());
    }

    public function test_an_intended_family_invitation_takes_priority_without_discarding_the_gift(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        app(FamilyAccountContext::class)->account($family);
        $destination = route('family.invitations.review', ['token' => 'invitation-test-token']);
        session(['url.intended' => $destination]);
        $this->intent();
        Volt::test('pages.auth.login')->set('form.email', $family->email)->set('form.password', 'password')
            ->call('login')->assertHasNoErrors()->assertRedirect($destination);
        $this->assertSame(self::CODE, CareHourGiftClaimIntent::code());
    }

    public function test_gift_intent_cannot_restore_ended_family_access(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $membership = app(FamilyAccountContext::class)->membership($family);
        $membership->update(['status' => FamilyAccountMember::STATUS_REMOVED, 'ended_at' => now()]);
        $this->intent();
        Volt::test('pages.auth.login')->set('form.email', $family->email)->set('form.password', 'password')
            ->call('login')->assertHasNoErrors()->assertRedirect(route('family.access.ended', absolute: false));
        $this->assertDatabaseCount('family_accounts', 1);
        $this->assertNull(app(FamilyAccountContext::class)->membershipFor($family));
    }

    public function test_caregiver_login_clears_gift_intent_and_preserves_its_own_destination(): void
    {
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $destination = route('care-requests.apply', ['careRequest' => 123]);
        session(['url.intended' => $destination]);
        $this->intent();
        Volt::test('pages.auth.login')->set('form.email', $caregiver->email)->set('form.password', 'password')
            ->call('login')->assertHasNoErrors()->assertRedirect($destination);
        $this->assertAuthenticatedAs($caregiver);
        $this->assertFalse(CareHourGiftClaimIntent::active());
        $this->assertDatabaseCount('family_accounts', 0);
    }

    private function intent(): void
    {
        // The public gift entry validates the paid gift before handing this intent to auth.
        CareHourGiftClaimIntent::put(self::CODE, ['paid_minutes' => 600, 'conditional_bonus_minutes' => 60]);
    }
}
