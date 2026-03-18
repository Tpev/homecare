<?php

namespace Tests\Feature\Auth;

use App\Mail\Ops\UserRegisteredOpsAlertMail;
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
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->set('accept_terms', true);

        $component->call('register');

        $component->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();

        Mail::assertSent(UserRegisteredOpsAlertMail::class, function (UserRegisteredOpsAlertMail $mail) {
            return $mail->hasTo('peverelli.t@gmail.com')
                && $mail->hasTo('cpetrinipoli@hub.healthcare')
                && $mail->user->email === 'test@example.com';
        });
    }
}
