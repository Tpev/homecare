<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Notifications\Auth\LoLoCareResetPasswordNotification;
use App\Notifications\Auth\LoLoCareVerifyEmailNotification;
use App\Notifications\MarketplaceEventNotification;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Support\MarketplaceEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MarketplaceEmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketplace_email_uses_branded_template_and_deep_links(): void
    {
        $user = User::factory()->create(['role' => 'family']);
        Notification::fake();

        app(MarketplaceNotificationService::class)->notify(
            recipients: $user,
            eventKey: MarketplaceEvent::MESSAGE_RECEIVED,
            title: 'New message',
            body: 'You have a new message from a caregiver.',
            url: route('messages.index'),
            payload: ['conversation_id' => 10],
            dedupeKey: 'msg-test-1'
        );

        Notification::assertSentTo($user, MarketplaceEventNotification::class, function (MarketplaceEventNotification $notification, array $channels) use ($user): bool {
            $mail = $notification->toMail($user);

            return in_array('mail', $channels, true)
                && is_array($mail->view)
                && ($mail->view['html'] ?? null) === 'emails.notifications.marketplace-event-html'
                && ($mail->view['text'] ?? null) === 'emails.notifications.marketplace-event-text'
                && is_string($mail->viewData['url'] ?? null)
                && str_contains((string) $mail->viewData['url'], '/notifications/email/click/')
                && ($mail->viewData['rawUrl'] ?? null) === route('messages.index')
                && ($mail->viewData['supportUrl'] ?? null) === route('support.index')
                && is_string($mail->viewData['openTrackingUrl'] ?? null)
                && str_contains((string) $mail->viewData['openTrackingUrl'], '/notifications/email/open/')
                && ($mail->viewData['ctaLabel'] ?? null) === 'Read message';
        });

        $this->assertDatabaseHas('marketplace_notification_deliveries', [
            'user_id' => $user->id,
            'event_key' => MarketplaceEvent::MESSAGE_RECEIVED,
            'channel' => 'email',
            'status' => 'queued',
            'open_count' => 0,
            'click_count' => 0,
        ]);
    }

    public function test_dedupe_prevents_duplicate_email_for_same_event_key(): void
    {
        $user = User::factory()->create(['role' => 'caregiver']);
        Notification::fake();

        $service = app(MarketplaceNotificationService::class);

        $service->notify(
            recipients: $user,
            eventKey: MarketplaceEvent::SHIFT_STARTING_SOON,
            title: 'Shift starting soon',
            body: 'Your shift starts in about one hour.',
            url: '/care-requests/12/apply',
            payload: ['care_booking_id' => 12],
            dedupeKey: 'shift-soon:booking-12-user-'.$user->id
        );

        $service->notify(
            recipients: $user,
            eventKey: MarketplaceEvent::SHIFT_STARTING_SOON,
            title: 'Shift starting soon',
            body: 'Your shift starts in about one hour.',
            url: '/care-requests/12/apply',
            payload: ['care_booking_id' => 12],
            dedupeKey: 'shift-soon:booking-12-user-'.$user->id
        );

        Notification::assertSentToTimes($user, MarketplaceEventNotification::class, 2);

        $this->assertDatabaseCount('marketplace_notification_deliveries', 2);
        $this->assertDatabaseHas('marketplace_notification_deliveries', [
            'user_id' => $user->id,
            'event_key' => MarketplaceEvent::SHIFT_STARTING_SOON,
            'channel' => 'email',
            'dedupe_key' => 'shift-soon:booking-12-user-'.$user->id.':email',
        ]);
        $this->assertDatabaseHas('marketplace_notification_deliveries', [
            'user_id' => $user->id,
            'event_key' => MarketplaceEvent::SHIFT_STARTING_SOON,
            'channel' => 'in_app',
            'dedupe_key' => 'shift-soon:booking-12-user-'.$user->id.':in_app',
        ]);
    }

    public function test_auth_emails_use_the_same_lolo_care_brand_and_accessible_fallback_links(): void
    {
        $user = User::factory()->unverified()->create(['name' => 'Barbara Pearl']);

        foreach ([
            new LoLoCareResetPasswordNotification('secure-test-token'),
            new LoLoCareVerifyEmailNotification,
        ] as $notification) {
            $mail = $notification->toMail($user);
            $html = view($mail->view['html'], $mail->viewData)->render();
            $text = view($mail->view['text'], $mail->viewData)->render();

            $this->assertStringStartsWith('[LoLo Care]', (string) $mail->subject);
            $this->assertSame('LoLo Care', $mail->from[1] ?? null);
            $this->assertStringContainsString('LoLo Care', $html);
            $this->assertStringContainsString('lolo-lockup-warm-1024.png', $html);
            $this->assertStringContainsString('If the button does not open', $html);
            $this->assertStringContainsString('LoLo Care', $text);
            $this->assertStringNotContainsString('HomeCare Hub', $html);
        }
    }
}
