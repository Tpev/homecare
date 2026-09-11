<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\Auth\LoLoCareResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public static function passwordRecoveryForms(): array
    {
        return [
            'request reset link' => ['/forgot-password', 'sendPasswordResetLink', 'Send reset link'],
            'choose new password' => ['/reset-password/test-token', 'resetPassword', 'Reset Password'],
        ];
    }

    #[DataProvider('passwordRecoveryForms')]
    public function test_password_recovery_action_submits_its_form(string $path, string $action, string $label): void
    {
        $response = $this->get($path)->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());

        $buttons = (new \DOMXPath($document))->query(
            '//form[@*[name()="wire:submit"]="'.$action.'"]//button[@type="submit"]'
        );

        $this->assertCount(1, $buttons, 'The password recovery form must have a submit button.');
        $this->assertSame($label, trim($buttons->item(0)->textContent));
    }

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response
            ->assertSeeVolt('pages.auth.forgot-password')
            ->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        Volt::test('pages.auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendPasswordResetLink');

        Notification::assertSentTo($user, LoLoCareResetPasswordNotification::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        Volt::test('pages.auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendPasswordResetLink');

        Notification::assertSentTo($user, LoLoCareResetPasswordNotification::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response
                ->assertSeeVolt('pages.auth.reset-password')
                ->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        Volt::test('pages.auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendPasswordResetLink');

        Notification::assertSentTo($user, LoLoCareResetPasswordNotification::class, function ($notification) use ($user) {
            $component = Volt::test('pages.auth.reset-password', ['token' => $notification->token])
                ->set('email', $user->email)
                ->set('password', 'password')
                ->set('password_confirmation', 'password');

            $component->call('resetPassword');

            $component
                ->assertRedirect('/login')
                ->assertHasNoErrors();

            return true;
        });
    }
}
