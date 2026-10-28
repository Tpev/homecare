<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\CaregiverRegister;
use App\Mail\Ops\UserRegisteredOpsAlertMail;
use App\Models\Skill;
use App\Models\User;
use App\Services\Caregiver\CaregiverBackgroundService;
use App\Support\MarketplaceEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
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
        config([
            'marketplace.ops_alert_recipients' => [
                'peverelli.t@gmail.com',
                'hello@carelolo.com',
            ],
        ]);
        Mail::fake();

        Livewire::test(CaregiverRegister::class)
            ->assertSet('sms_opt_in', false)
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
            'phone' => '555-000-1111',
            'sms_opt_in' => false,
            'sms_opted_in_at' => null,
            'sms_opt_in_phone' => null,
        ]);
        $this->assertDatabaseHas('caregiver_profiles', [
            'user_id' => User::query()->where('email', 'caregiver@example.com')->value('id'),
            'care_background_schema_version' => CaregiverBackgroundService::SCHEMA_VERSION,
        ]);
        $this->assertDatabaseHas('marketplace_notification_deliveries', [
            'user_id' => User::query()->where('email', 'caregiver@example.com')->value('id'),
            'event_key' => MarketplaceEvent::CAREGIVER_WELCOME,
            'channel' => 'email',
            'status' => 'sent',
        ]);

        Mail::assertSent(UserRegisteredOpsAlertMail::class, function (UserRegisteredOpsAlertMail $mail) {
            return $mail->hasTo('peverelli.t@gmail.com')
                && $mail->hasTo('hello@carelolo.com')
                && $mail->user->email === 'caregiver@example.com';
        });
        Mail::assertSent(UserRegisteredOpsAlertMail::class, 1);
    }

    public static function smsConsentChoices(): array
    {
        return [
            'no phone or consent' => ['', false, null, false],
            'checked without a phone' => ['', true, null, false],
            'checked with a blank phone' => ['   ', true, null, false],
            'explicit consent with a phone' => ['555-000-1111', true, '555-000-1111', true],
        ];
    }

    #[DataProvider('smsConsentChoices')]
    public function test_caregiver_registration_saves_sms_consent_only_with_a_phone_and_explicit_opt_in(
        string $phone,
        bool $checked,
        ?string $savedPhone,
        bool $consented,
    ): void {
        Mail::fake();
        $this->freezeTime();

        Livewire::test(CaregiverRegister::class)
            ->set('name', 'Care Giver')
            ->set('email', 'caregiver@example.com')
            ->set('phone', $phone)
            ->set('sms_opt_in', $checked)
            ->set('city', 'Raleigh')
            ->set('state', 'NC')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->set('accept_terms', true)
            ->set('accept_independent_contractor', true)
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $user = User::query()->where('email', 'caregiver@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertSame($savedPhone, $user->phone);
        $this->assertSame($consented, $user->sms_opt_in);
        $this->assertSame($consented ? $savedPhone : null, $user->sms_opt_in_phone);
        $this->assertSame($consented ? now()->toDateTimeString() : null, $user->sms_opted_in_at?->toDateTimeString());
    }

    public function test_onboarding_route_requires_caregiver_role(): void
    {
        Skill::query()->create(['name' => 'Companionship']);
        $user = User::factory()->create(['role' => 'family']);

        $this->actingAs($user)->get('/caregiver/onboarding')->assertForbidden();
    }
}
