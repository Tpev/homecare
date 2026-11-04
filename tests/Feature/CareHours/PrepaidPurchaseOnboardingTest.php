<?php

namespace Tests\Feature\CareHours;

use App\Livewire\Family\OnboardingWizard;
use App\Models\CareHourAccount;
use App\Models\FamilyOnboarding;
use App\Models\User;
use App\Services\Family\FamilyOnboardingService;
use App\Support\CareHourGiftClaimIntent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PrepaidPurchaseOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['care_hours.enabled' => true, 'care_hours.sales_enabled' => true,
            'care_hours.enrollment_enabled' => true, 'care_hours.pilot_only' => false,
            'care_hours.pilot_emails' => []]);
        Mail::fake();
        Notification::fake();
        Queue::fake();
    }

    public function test_guest_can_register_finish_normal_onboarding_and_return_to_buy_prepaid_care(): void
    {
        $this->get(route('prepaid-care-hours'))->assertOk()
            ->assertViewHas('purchaseUrl', route('family.care-hours.buy'));
        $this->get(route('family.care-hours.buy'))->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk()->assertSee(route('register'), false);
        Volt::test('pages.auth.register')->set('name', 'Jane Taylor')->set('email', 'jane@example.test')
            ->set('password', 'password')->set('password_confirmation', 'password')
            ->set('accept_terms', true)->call('register')->assertHasNoErrors()
            ->assertRedirect(route('family.onboarding', absolute: false));
        $this->assertSame(route('family.care-hours.buy'), session('url.intended'));
        $this->assertDatabaseCount('care_hour_accounts', 0);

        Livewire::test(OnboardingWizard::class)
            ->set('form.care_for', 'me')->call('next')->assertHasNoErrors()
            ->set('form.address_line1', '123 Oak Street')->set('form.city', 'Raleigh')
            ->set('form.state', 'NC')->set('form.zip', '27601')->call('next')->assertHasNoErrors()
            ->call('next')->assertHasNoErrors()->set('form.welcome_visit', 'no')->call('next')->assertHasNoErrors()
            ->assertSee('Choose prepaid hours')->assertDontSee('Create my first request')
            ->call('next')->assertHasNoErrors()->assertRedirect(route('family.care-hours.buy'));
        $this->assertSame('completed', FamilyOnboarding::sole()->status);
        $this->assertFalse(session()->has('url.intended'));
        $this->get(route('family.care-hours.buy'))->assertRedirect(route('family.care-hours.index'));
        $this->post(route('family.care-hours.buy.start'))->assertRedirect(route('family.care-hours.packages', CareHourAccount::sole()));
        $this->assertDatabaseCount('care_requests', 0);
        $this->assertDatabaseCount('care_hour_purchases', 0);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_existing_family_sign_in_returns_to_the_requested_buy_screen(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $this->get(route('family.care-hours.buy'))->assertRedirect(route('login'));
        Volt::test('pages.auth.login')->set('form.email', $family->email)->set('form.password', 'password')
            ->call('login')->assertHasNoErrors()->assertRedirect(route('family.care-hours.buy'));
        $this->assertAuthenticatedAs($family);
        $this->assertDatabaseCount('care_hour_accounts', 0);
    }

    public function test_untrusted_destination_sales_pause_and_gift_intent_do_not_override_normal_onboarding(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $service = app(FamilyOnboardingService::class);
        $record = $service->enrollRegistration($family)->fresh();
        $answers = ['care_for' => 'me', 'address_line1' => '123 Oak Street', 'city' => 'Raleigh',
            'state' => 'NC', 'zip' => '27601', 'welcome_visit' => 'no'];
        for ($step = 1; $step <= 4; $step++) {
            $record = $service->saveStep($family, $answers, $step, $record->revision);
        }
        $this->actingAs($family);
        session(['url.intended' => 'https://example.test/family/care-hours/buy']);
        Livewire::test(OnboardingWizard::class)->assertSee('Create my first request')->assertDontSee('Choose prepaid hours');
        session(['url.intended' => route('family.care-hours.buy')]);
        config(['care_hours.sales_enabled' => false]);
        Livewire::test(OnboardingWizard::class)->assertSee('Create my first request');
        config(['care_hours.sales_enabled' => true]);
        CareHourGiftClaimIntent::put('LOLO-TEST-CODE', ['paid_minutes' => 600, 'conditional_bonus_minutes' => 60]);
        Livewire::test(OnboardingWizard::class)->assertSee('Create my first request');
        CareHourGiftClaimIntent::clear();
        config(['care_hours.enrollment_enabled' => false]);
        Livewire::test(OnboardingWizard::class)->assertSee('Create my first request')
            ->call('next')->assertHasNoErrors()->assertRedirect(route('family.requests.create'));
        $this->assertDatabaseCount('care_hour_accounts', 0);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }
}
