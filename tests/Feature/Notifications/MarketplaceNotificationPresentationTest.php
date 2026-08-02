<?php

namespace Tests\Feature\Notifications;

use App\Support\MarketplaceEvent;
use App\Support\MarketplaceNotificationPresentation;
use Tests\TestCase;

class MarketplaceNotificationPresentationTest extends TestCase
{
    public function test_every_marketplace_event_has_a_specific_presentation_and_recipient_role(): void
    {
        $events = MarketplaceEvent::all();
        $roleEvents = collect(['family', 'caregiver', 'admin'])
            ->flatMap(fn (string $role): array => MarketplaceNotificationPresentation::eventsForRole($role))
            ->unique()
            ->values();

        $this->assertCount(count(array_unique($events)), $events, 'Marketplace event keys must be unique.');

        foreach ($events as $event) {
            $this->assertNotSame('LoLo Care update', MarketplaceNotificationPresentation::label($event), "Missing label for {$event}.");
            $this->assertNotSame('Open LoLo Care', MarketplaceNotificationPresentation::actionLabel($event), "Missing CTA for {$event}.");
            $this->assertContains($event, $roleEvents, "No recipient role includes {$event}.");
            $this->assertContains(MarketplaceNotificationPresentation::tone($event), ['success', 'info', 'warning', 'neutral']);
        }

        $this->assertSame('LoLo Care', MarketplaceNotificationPresentation::BRAND_NAME);
        $this->assertFileExists(public_path(MarketplaceNotificationPresentation::LOGO_PATH));
    }
}
