<?php

namespace App\Services\Notifications;

use App\Models\MarketplaceNotificationDelivery;
use App\Models\User;
use App\Notifications\MarketplaceEventNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class MarketplaceNotificationService
{
    public function __construct(
        private readonly NotificationPreferenceResolver $preferences,
    ) {
    }

    /**
     * @param User|Collection<int, User>|array<int, User> $recipients
     * @param array<string, mixed> $payload
     */
    public function notify(
        User|Collection|array $recipients,
        string $eventKey,
        string $title,
        string $body,
        ?string $url = null,
        array $payload = [],
        ?Model $subject = null,
        ?string $dedupeKey = null,
    ): void {
        $users = $recipients instanceof User
            ? collect([$recipients])
            : collect($recipients);

        $users
            ->filter(fn ($user) => $user instanceof User)
            ->unique('id')
            ->each(function (User $user) use ($eventKey, $title, $body, $url, $payload, $subject, $dedupeKey): void {
                $this->notifyUser($user, $eventKey, $title, $body, $url, $payload, $subject, $dedupeKey);
            });
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function notifyUser(
        User $user,
        string $eventKey,
        string $title,
        string $body,
        ?string $url,
        array $payload,
        ?Model $subject,
        ?string $dedupeKey,
    ): void {
        $normalizedUrl = $this->normalizeUrl($url);
        $channels = $this->preferences->resolve($user, $eventKey);
        $subjectType = $subject?->getMorphClass();
        $subjectId = $subject?->getKey();

        $laravelChannels = [];
        if ($channels[NotificationChannels::IN_APP] ?? false) {
            $laravelChannels[] = 'database';
        }
        if ($channels[NotificationChannels::EMAIL] ?? false) {
            $laravelChannels[] = 'mail';
        }

        if ($laravelChannels !== [] && $this->shouldDispatchForLaravelChannels($user->id, $dedupeKey, $laravelChannels)) {
            $user->notify(new MarketplaceEventNotification(
                eventKey: $eventKey,
                title: $title,
                body: $body,
                url: $normalizedUrl,
                channels: $laravelChannels,
                payload: $payload,
            ));

            foreach ($laravelChannels as $channel) {
                $resolvedChannel = $channel === 'mail'
                    ? NotificationChannels::EMAIL
                    : NotificationChannels::IN_APP;

                $this->logDelivery(
                    userId: $user->id,
                    eventKey: $eventKey,
                    channel: $resolvedChannel,
                    status: 'sent',
                    subjectType: $subjectType,
                    subjectId: $subjectId,
                    dedupeKey: $dedupeKey ? $dedupeKey.':'.$resolvedChannel : null,
                    payload: $payload
                );
            }
        }

        $this->dispatchPlaceholderChannel(
            userId: $user->id,
            eventKey: $eventKey,
            channel: NotificationChannels::SMS,
            enabled: (bool) ($channels[NotificationChannels::SMS] ?? false),
            subjectType: $subjectType,
            subjectId: $subjectId,
            dedupeKey: $dedupeKey,
            payload: $payload
        );

        $this->dispatchPlaceholderChannel(
            userId: $user->id,
            eventKey: $eventKey,
            channel: NotificationChannels::PUSH,
            enabled: (bool) ($channels[NotificationChannels::PUSH] ?? false),
            subjectType: $subjectType,
            subjectId: $subjectId,
            dedupeKey: $dedupeKey,
            payload: $payload
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function dispatchPlaceholderChannel(
        int $userId,
        string $eventKey,
        string $channel,
        bool $enabled,
        ?string $subjectType,
        ?int $subjectId,
        ?string $dedupeKey,
        array $payload,
    ): void {
        if (! $enabled || ! $this->shouldDispatch($userId, $dedupeKey, $channel)) {
            return;
        }

        $this->logDelivery(
            userId: $userId,
            eventKey: $eventKey,
            channel: $channel,
            status: 'enabled_stub_pending_provider',
            subjectType: $subjectType,
            subjectId: $subjectId,
            dedupeKey: $dedupeKey ? $dedupeKey.':'.$channel : null,
            payload: $payload
        );
    }

    /**
     * @param list<string> $laravelChannels
     */
    private function shouldDispatchForLaravelChannels(int $userId, ?string $dedupeKey, array $laravelChannels): bool
    {
        if (! $dedupeKey) {
            return true;
        }

        $channelKeys = collect($laravelChannels)
            ->map(fn (string $channel) => $dedupeKey.':'.($channel === 'mail' ? NotificationChannels::EMAIL : NotificationChannels::IN_APP))
            ->values()
            ->all();

        return ! MarketplaceNotificationDelivery::query()
            ->where('user_id', $userId)
            ->whereIn('dedupe_key', $channelKeys)
            ->exists();
    }

    private function shouldDispatch(int $userId, ?string $dedupeKey, string $channel): bool
    {
        if (! $dedupeKey) {
            return true;
        }

        return ! MarketplaceNotificationDelivery::query()
            ->where('user_id', $userId)
            ->where('dedupe_key', $dedupeKey.':'.$channel)
            ->exists();
    }

    private function normalizeUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }

        $baseUrl = rtrim((string) config('app.url', ''), '/');

        if (str_starts_with($url, '/')) {
            return $baseUrl.$url;
        }

        return $baseUrl.'/'.$url;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function logDelivery(
        int $userId,
        string $eventKey,
        string $channel,
        string $status,
        ?string $subjectType,
        ?int $subjectId,
        ?string $dedupeKey,
        array $payload,
    ): void {
        MarketplaceNotificationDelivery::query()->create([
            'user_id' => $userId,
            'event_key' => $eventKey,
            'channel' => $channel,
            'status' => $status,
            'notifiable_type' => $subjectType,
            'notifiable_id' => $subjectId,
            'dedupe_key' => $dedupeKey,
            'payload' => $payload,
            'sent_at' => now(),
        ]);
    }
}
