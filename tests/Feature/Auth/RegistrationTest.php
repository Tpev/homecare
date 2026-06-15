<?php

namespace Tests\Feature\Auth;

use App\Mail\Ops\FamilyRegisteredOpsAlertMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;
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
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->set('phone', '(984) 400-4008')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->set('accept_terms', true);

        $component->call('register');

        $component->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'phone' => '(984) 400-4008',
        ]);

        Mail::assertSent(FamilyRegisteredOpsAlertMail::class, function (FamilyRegisteredOpsAlertMail $mail) {
            return $mail->hasTo('peverelli.t@gmail.com')
                && $mail->hasTo('cpetrinipoli@hub.healthcare')
                && $mail->user->email === 'test@example.com';
        });
    }

    public function test_family_registration_requires_phone_number(): void
    {
        Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->set('accept_terms', true)
            ->call('register')
            ->assertHasErrors(['phone' => 'required']);
    }
}
