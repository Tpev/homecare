<?php

namespace App\Notifications;

use App\Support\MarketplaceEvent;
use App\Support\MarketplaceNotificationPresentation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MarketplaceEventNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $channels
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        private readonly string $eventKey,
        private readonly string $title,
        private readonly string $body,
        private readonly ?string $url,
        private readonly array $channels,
        private readonly array $payload = [],
    ) {}

    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = MarketplaceNotificationPresentation::BRAND_NAME;
        $homeUrl = route('dashboard');
        $supportUrl = route('support.index');
        $resolvedUrl = $this->url ?: $homeUrl;
        $trackedUrl = $this->resolveTrackedClickUrl($resolvedUrl);
        $openTrackingUrl = $this->resolveOpenTrackingUrl();
        $view = $this->isCaregiverOnboardingEmail()
            ? [
                'html' => 'emails.notifications.caregiver-onboarding-html',
                'text' => 'emails.notifications.caregiver-onboarding-text',
            ]
            : [
                'html' => 'emails.notifications.marketplace-event-html',
                'text' => 'emails.notifications.marketplace-event-text',
            ];

        return (new MailMessage)
            ->from((string) config('mail.from.address'), $appName)
            ->subject('['.MarketplaceNotificationPresentation::BRAND_NAME.'] '.$this->title)
            ->view($view, [
                'appName' => $appName,
                'eventLabel' => $this->eventLabel(),
                'title' => $this->title,
                'body' => $this->body,
                'url' => $trackedUrl,
                'rawUrl' => $resolvedUrl,
                'ctaLabel' => $this->actionLabel(),
                'supportUrl' => $supportUrl,
                'preferencesUrl' => $this->preferencesUrl($notifiable),
                'homeUrl' => $homeUrl,
                'year' => now()->year,
                'logoUrl' => asset(MarketplaceNotificationPresentation::LOGO_PATH),
                'openTrackingUrl' => $openTrackingUrl,
                'preheader' => (string) ($this->payload['preheader'] ?? $this->title),
                'checklist' => array_values(array_filter((array) ($this->payload['checklist'] ?? []), fn ($item) => is_string($item) && trim($item) !== '')),
                'firstName' => (string) ($this->payload['first_name'] ?? ''),
                'nextSteps' => array_values(array_filter((array) ($this->payload['email_next_steps'] ?? []), fn ($item) => is_string($item) && trim($item) !== '')),
                'emailDetails' => collect((array) ($this->payload['email_details'] ?? []))
                    ->filter(fn ($detail) => is_array($detail) && trim((string) ($detail['label'] ?? '')) !== '' && trim((string) ($detail['value'] ?? '')) !== '')
                    ->map(fn (array $detail) => [
                        'label' => trim((string) $detail['label']),
                        'value' => trim((string) $detail['value']),
                    ])->values()->all(),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event_key' => $this->eventKey,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'payload' => $this->payload,
        ];
    }

    private function eventLabel(): string
    {
        return MarketplaceNotificationPresentation::label($this->eventKey);
    }

    private function actionLabel(): string
    {
        return MarketplaceNotificationPresentation::actionLabel($this->eventKey);
    }

    private function isCaregiverOnboardingEmail(): bool
    {
        return in_array($this->eventKey, [
            MarketplaceEvent::CAREGIVER_WELCOME,
            MarketplaceEvent::CAREGIVER_ONBOARDING_REMINDER_24H,
        ], true);
    }

    private function resolveTrackedClickUrl(string $resolvedUrl): string
    {
        $deliveryId = data_get($this->payload, 'tracking.delivery_id');
        $token = data_get($this->payload, 'tracking.token');

        if (! is_numeric($deliveryId) || ! is_string($token) || trim($token) === '') {
            return $resolvedUrl;
        }

        return route('notifications.email.click', [
            'delivery' => (int) $deliveryId,
            'token' => $token,
        ]);
    }

    private function resolveOpenTrackingUrl(): ?string
    {
        $deliveryId = data_get($this->payload, 'tracking.delivery_id');
        $token = data_get($this->payload, 'tracking.token');

        if (! is_numeric($deliveryId) || ! is_string($token) || trim($token) === '') {
            return null;
        }

        return route('notifications.email.open', [
            'delivery' => (int) $deliveryId,
            'token' => $token,
        ]);
    }

    private function preferencesUrl(object $notifiable): string
    {
        return match ((string) ($notifiable->role ?? '')) {
            '', 'family' => route('family.notifications.index'),
            'caregiver' => route('caregiver.notifications.index'),
            'admin' => route('admin.notifications.index'),
            default => $this->url ?: route('dashboard'),
        };
    }
}
