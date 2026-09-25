<?php

namespace App\Services\Notifications;

use App\Support\MarketplaceEvent;

final class NotificationDeliveryPolicy
{
    public static function allowsEmail(string $eventKey): bool
    {
        return ! in_array($eventKey, [
            MarketplaceEvent::INVITATION_SENT,
            MarketplaceEvent::APPLICATION_SUBMITTED,
            MarketplaceEvent::HIRE_CONFIRMED,
        ], true);
    }

    public static function isMessage(string $eventKey): bool
    {
        return in_array($eventKey, [MarketplaceEvent::MESSAGE_RECEIVED, MarketplaceEvent::SUPPORT_TICKET_REPLY], true);
    }
}
