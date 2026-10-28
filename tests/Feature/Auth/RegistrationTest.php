<?php

namespace Tests\Feature\Auth;

use App\Mail\Ops\FamilyRegisteredOpsAlertMail;
use App\Models\User;
use App\Support\MarketplaceEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response
            ->assertOk()
            ->assertSeeVolt('pages.auth.register');
    }

    public function test_new_users_can_register(): void
    {
        config([
            'marketplace.ops_alert_recipients' => [
                'peverelli.t@gmail.com',
                'cpetrinipoli@hub.healthcare',
            ],
        ]);
        Mail::fake();

        $component = Volt::test('pages.auth.register')
            ->assertSet('accept_terms', false)
            ->assertSet('sms_opt_in', false)
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->set('phone', '(984) 400-4008')
            ->set('password', 'password')
            ->set('password_confirmation', 'password');

        $component->call('register');

        $component->assertRedirect(route('family.onboarding', absolute: false));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'phone' => '(984) 400-4008',
            'sms_opt_in' => false,
            'sms_opted_in_at' => null,
            'sms_opt_in_phone' => null,
        ]);
        $familyId = User::query()->where('email', 'test@example.com')->value('id');
        $this->assertDatabaseHas('family_onboardings', ['initiated_by_user_id' => $familyId, 'status' => 'in_progress']);
        $this->assertDatabaseHas('marketplace_notification_deliveries', [
            'user_id' => $familyId,
            'event_key' => MarketplaceEvent::FAMILY_WELCOME,
            'channel' => 'email',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('marketplace_notification_deliveries', [
            'user_id' => $familyId,
            'event_key' => MarketplaceEvent::FAMILY_WELCOME,
            'channel' => 'in_app',
            'status' => 'sent',
        ]);

        Mail::assertSent(FamilyRegisteredOpsAlertMail::class, function (FamilyRegisteredOpsAlertMail $mail) {
            return $mail->hasTo('peverelli.t@gmail.com')
                && $mail->hasTo('hello@carelolo.com')
                && ! $mail->hasTo('cpetrinipoli@hub.healthcare')
                && $mail->user->email === 'test@example.com';
        });
        Mail::assertSent(FamilyRegisteredOpsAlertMail::class, 1);
    }

    public static function smsConsentChoices(): array
    {
        return [
            'no phone or consent' => ['', false, null, false],
            'checked without a phone' => ['', true, null, false],
            'checked with a blank phone' => ['   ', true, null, false],
            'explicit consent with a phone' => ['(984) 400-4008', true, '(984) 400-4008', true],
        ];
    }

    #[DataProvider('smsConsentChoices')]
    public function test_family_registration_saves_sms_consent_only_with_a_phone_and_explicit_opt_in(
        string $phone,
        bool $checked,
        ?string $savedPhone,
        bool $consented,
    ): void {
        Mail::fake();
        $this->freezeTime();

        Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->set('phone', $phone)
            ->set('sms_opt_in', $checked)
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->set('accept_terms', true)
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('family.onboarding', absolute: false));

        $user = User::query()->where('email', 'test@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertSame($savedPhone, $user->phone);
        $this->assertSame($consented, $user->sms_opt_in);
        $this->assertSame($consented ? $savedPhone : null, $user->sms_opt_in_phone);
        $this->assertSame($consented ? now()->toDateTimeString() : null, $user->sms_opted_in_at?->toDateTimeString());
    }
}
