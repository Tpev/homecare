<?php

namespace App\Services\Notifications;

use App\Models\MarketplaceNotificationDelivery;
use App\Models\FamilyAccount;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\MarketplaceEventNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

class MarketplaceNotificationService
{
    public function __construct(
        private readonly NotificationPreferenceResolver $preferences,
        private readonly MarketplaceNotificationContextBuilder $context,
    ) {}

    /**
     * @param  User|Collection<int, User>|array<int, User>  $recipients
     * @param  array<string, mixed>  $payload
     * @param  array<string, bool>  $channelOverrides
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
        array $channelOverrides = [],
    ): void {
        $users = $recipients instanceof User
            ? collect([$recipients])
            : collect($recipients);

        $users = $this->expandFamilyRecipients($users, $subject);

        $users
            ->filter(fn ($user) => $user instanceof User)
            ->unique('id')
            ->each(function (User $user) use ($eventKey, $title, $body, $url, $payload, $subject, $dedupeKey, $channelOverrides): void {
                $this->notifyUser($user, $eventKey, $title, $body, $url, $payload, $subject, $dedupeKey, $channelOverrides);
            });
    }

    /**
     * Shared-care events addressed to the legacy family owner fan out to every
     * active account member. Personal and owner-only events remain private.
     *
     * @param  Collection<int, mixed>  $users
     * @return Collection<int, mixed>
     */
    private function expandFamilyRecipients(Collection $users, ?Model $subject): Collection
    {
        if (! $subject || ($subject instanceof SupportTicket && $subject->family_visibility !== 'shared_care')) {
            return $users;
        }

        $visited = [];
        $accountId = $this->resolveFamilyAccountId($subject, $visited);
        if ($accountId < 1) {
            return $users;
        }

        $account = FamilyAccount::query()
            ->with('activeMemberships.user')
            ->find($accountId);

        if (! $account || ! $users->contains(fn ($user) => $user instanceof User && $user->role === 'family'
            && ((int) $user->id === (int) $account->owner_user_id
                || $account->activeMemberships->contains('user_id', $user->id)))) {
            return $users;
        }

        return $users
            ->reject(fn ($user) => $user instanceof User && $user->role === 'family')
            ->concat($account->activeMemberships->pluck('user'));
    }

    /**
     * Notifications are often attached to a child record (an application,
     * schedule change, shift, or offer) rather than the family-owned root.
     * Resolve only through known ownership relations and stop after a few hops.
     *
     * @param  array<string, true>  $visited
     */
    private function resolveFamilyAccountId(Model $subject, array &$visited, int $depth = 0): int
    {
        $key = $subject::class.':'.($subject->getKey() ?? spl_object_id($subject));
        if (isset($visited[$key]) || $depth > 4) {
            return 0;
        }
        $visited[$key] = true;

        if ($subject instanceof FamilyAccount) {
            return (int) $subject->id;
        }

        $directId = (int) ($subject->getAttribute('family_account_id') ?? 0);
        if ($directId > 0) {
            return $directId;
        }

        foreach (['careRequest', 'booking', 'carePlan', 'plan', 'sourceCareRequest', 'invitation', 'conversation', 'shift', 'template', 'rosterMember', 'ticket'] as $relationName) {
            if (! method_exists($subject, $relationName)) {
                continue;
            }

            $relation = $subject->{$relationName}();
            if (! $relation instanceof Relation) {
                continue;
            }

            $related = $subject->getRelationValue($relationName);
            if ($related instanceof Model) {
                $accountId = $this->resolveFamilyAccountId($related, $visited, $depth + 1);
                if ($accountId > 0) {
                    return $accountId;
                }
            }
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, bool>  $channelOverrides
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
        array $channelOverrides,
    ): void {
        $normalizedUrl = $this->normalizeUrl($url);
        $payload = $this->context->enrich($user, $eventKey, $title, $body, $payload, $subject);
        $channels = array_replace($this->preferences->resolve($user, $eventKey), $channelOverrides);
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
