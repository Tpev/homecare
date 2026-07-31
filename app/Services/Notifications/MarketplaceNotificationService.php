<?php

namespace App\Services\Notifications;

use App\Models\MarketplaceNotificationDelivery;
use App\Models\User;
use App\Notifications\MarketplaceEventNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

class MarketplaceNotificationService
{
    public function __construct(
        private readonly NotificationPreferenceResolver $preferences,
    ) {}

    /**
     * @param  User|Collection<int, User>|array<int, User>  $recipients
     * @param  array<string, mixed>  $payload
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
     * @param  array<string, mixed>  $payload
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
        if ($channels[NotificationChannels::EMAIL] ?? false) {
            $trackingToken = Str::random(48);
            $mailPayload = array_merge($payload, [
                'tracking' => [
                    'token' => $trackingToken,
                    'target_url' => $normalizedUrl,
                ],
            ]);
            $mailDelivery = $this->reserveDelivery(
                userId: $user->id,
                eventKey: $eventKey,
                channel: NotificationChannels::EMAIL,
                subjectType: $subjectType,
                subjectId: $subjectId,
                dedupeKey: $dedupeKey,
                payload: $mailPayload,
            );

            if ($mailDelivery) {
                $notificationPayload = array_merge($mailPayload, [
                    'tracking' => [
                        'token' => $trackingToken,
                        'target_url' => $normalizedUrl,
                        'delivery_id' => $mailDelivery->id,
                    ],
                ]);

                try {
                    Notification::sendNow($user, new MarketplaceEventNotification(
                        eventKey: $eventKey,
                        title: $title,
                        body: $body,
                        url: $normalizedUrl,
                        channels: ['mail'],
                        payload: $notificationPayload,
                    ));
                    // Queued means handed to Laravel's queue. Provider delivery is not proven here.
                } catch (Throwable $exception) {
                    $this->markFailed($mailDelivery, $exception);
                }
            }
        }

        if ($channels[NotificationChannels::IN_APP] ?? false) {
            $inAppDelivery = $this->reserveDelivery(
                userId: $user->id,
                eventKey: $eventKey,
                channel: NotificationChannels::IN_APP,
                subjectType: $subjectType,
                subjectId: $subjectId,
                dedupeKey: $dedupeKey,
                payload: $payload,
            );

            if ($inAppDelivery) {
                try {
                    $user->notify(new MarketplaceEventNotification(
                        eventKey: $eventKey,
                        title: $title,
                        body: $body,
                        url: $normalizedUrl,
                        channels: ['database'],
                        payload: $payload,
                    ));
                    $inAppDelivery->forceFill(['status' => 'sent', 'sent_at' => now()])->save();
                } catch (Throwable $exception) {
                    $this->markFailed($inAppDelivery, $exception);
                }
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
     * @param  array<string, mixed>  $payload
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

    /** @param array<string,mixed> $payload */
    private function reserveDelivery(
        int $userId,
        string $eventKey,
        string $channel,
        ?string $subjectType,
        ?int $subjectId,
        ?string $dedupeKey,
        array $payload,
    ): ?MarketplaceNotificationDelivery {
        $channelDedupeKey = $dedupeKey ? $dedupeKey.':'.$channel : null;
        $attributes = [
            'user_id' => $userId,
            'event_key' => $eventKey,
            'channel' => $channel,
            'status' => 'queued',
            'notifiable_type' => $subjectType,
            'notifiable_id' => $subjectId,
            'dedupe_key' => $channelDedupeKey,
            'payload' => $payload,
            'sent_at' => null,
        ];

        if (! $channelDedupeKey) {
            return MarketplaceNotificationDelivery::query()->create($attributes);
        }

        try {
            $delivery = MarketplaceNotificationDelivery::query()->firstOrCreate(
                ['user_id' => $userId, 'dedupe_key' => $channelDedupeKey],
                $attributes,
            );

            return $delivery->wasRecentlyCreated ? $delivery : null;
        } catch (QueryException) {
            // A concurrent request reserved this exact recipient/channel delivery first.
            return null;
        }
    }

    private function markFailed(MarketplaceNotificationDelivery $delivery, Throwable $exception): void
    {
        $delivery->forceFill([
            'status' => 'failed',
            'payload' => array_merge($delivery->payload ?? [], ['provider_error' => $exception->getMessage()]),
            'sent_at' => now(),
        ])->save();
        report($exception);
    }

    /**
     * @param  array<string, mixed>  $payload
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
