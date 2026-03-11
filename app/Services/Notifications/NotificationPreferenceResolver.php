<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Models\UserNotificationPreference;

class NotificationPreferenceResolver
{
    /**
     * @return array<string, bool>
     */
    public function resolve(User $user, string $eventKey): array
    {
        $defaults = [
            NotificationChannels::IN_APP => true,
            NotificationChannels::EMAIL => true,
            NotificationChannels::SMS => false,
            NotificationChannels::PUSH => false,
        ];

        $preference = UserNotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('event_key', $eventKey)
            ->first();

        if (! $preference) {
            return $defaults;
        }

        return [
            NotificationChannels::IN_APP => (bool) $preference->in_app_enabled,
            NotificationChannels::EMAIL => (bool) $preference->email_enabled,
            NotificationChannels::SMS => (bool) $preference->sms_enabled,
            NotificationChannels::PUSH => (bool) $preference->push_enabled,
        ];
    }
}
