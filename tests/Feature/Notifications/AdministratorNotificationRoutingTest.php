<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Notifications\Auth\LoLoCareResetPasswordNotification;
use App\Notifications\Auth\LoLoCareVerifyEmailNotification;
use App\Notifications\MarketplaceEventNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\MailManager;
use Illuminate\Mail\Transport\SesTransport;
use Tests\TestCase;

class AdministratorNotificationRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketplace_alerts_use_an_administrators_separate_notification_email(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'login@example.com',
            'notification_email' => 'alerts@example.com',
        ]);

        $this->assertSame('alerts@example.com', $admin->routeNotificationFor('mail', $this->marketplaceNotification()));
    }

    public function test_account_security_notifications_keep_using_the_login_email(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'login@example.com',
            'notification_email' => 'alerts@example.com',
        ]);

        $this->assertSame('login@example.com', $admin->routeNotificationFor('mail', new LoLoCareResetPasswordNotification('token')));
        $this->assertSame('login@example.com', $admin->routeNotificationFor('mail', new LoLoCareVerifyEmailNotification));
    }

    public function test_non_administrators_cannot_redirect_marketplace_mail_with_the_field(): void
    {
        $family = User::factory()->create([
            'role' => 'family',
            'email' => 'family@example.com',
            'notification_email' => 'other@example.com',
        ]);

        $this->assertSame('family@example.com', $family->routeNotificationFor('mail', $this->marketplaceNotification()));
    }

    public function test_configured_ses_mailer_can_construct_its_transport(): void
    {
        config([
            'mail.default' => 'ses',
            'services.ses' => [
                'key' => 'test-key',
                'secret' => 'test-secret',
                'region' => 'us-east-1',
            ],
        ]);

        $manager = app(MailManager::class);
        $manager->purge('ses');

        $this->assertInstanceOf(SesTransport::class, $manager->mailer('ses')->getSymfonyTransport());
    }

    private function marketplaceNotification(): MarketplaceEventNotification
    {
        return new MarketplaceEventNotification(
            eventKey: 'operations.test',
            title: 'Operational test',
            body: 'Content-free test.',
            url: null,
            channels: ['mail'],
        );
    }
}
