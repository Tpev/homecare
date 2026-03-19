<?php

namespace App\Notifications;

use App\Support\MarketplaceEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MarketplaceEventNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param list<string> $channels
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly string $eventKey,
        private readonly string $title,
        private readonly string $body,
        private readonly ?string $url,
        private readonly array $channels,
        private readonly array $payload = [],
    ) {
    }

    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = (string) config('app.name', 'HomeCare');
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
            ->subject('['.$appName.'] '.$this->title)
            ->view($view, [
                'appName' => $appName,
                'eventLabel' => $this->eventLabel(),
                'title' => $this->title,
                'body' => $this->body,
                'url' => $trackedUrl,
                'rawUrl' => $resolvedUrl,
                'ctaLabel' => $this->actionLabel(),
                'supportUrl' => $supportUrl,
                'homeUrl' => $homeUrl,
                'year' => now()->year,
                'openTrackingUrl' => $openTrackingUrl,
                'preheader' => (string) ($this->payload['preheader'] ?? $this->title),
                'checklist' => array_values(array_filter((array) ($this->payload['checklist'] ?? []), fn ($item) => is_string($item) && trim($item) !== '')),
                'firstName' => (string) ($this->payload['first_name'] ?? ''),
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
        return match ($this->eventKey) {
            'new_applicant' => 'New applicant',
            'invite_accepted' => 'Invitation accepted',
            'invite_declined' => 'Invitation declined',
            'message_received' => 'New message',
            'caregiver_hired' => 'Caregiver hired',
            'shift_starting_soon' => 'Shift reminder',
            'shift_started' => 'Shift started',
            'shift_completed' => 'Shift completed',
            'review_received' => 'New review',
            'matching_request_reminder' => 'Recommended request',
            'payment_authorized' => 'Payment authorized',
            'payment_authorization_failed' => 'Payment authorization failed',
            'payment_action_required' => 'Payment action required',
            'payment_captured' => 'Payment captured',
            'payment_refunded' => 'Payment refunded',
            'payout_transferred' => 'Payout transferred',
            'payout_transfer_failed' => 'Payout transfer delayed',
            MarketplaceEvent::CAREGIVER_WELCOME => 'Welcome to HomeCare',
            MarketplaceEvent::CAREGIVER_ONBOARDING_REMINDER_24H => 'Setup reminder',
            default => 'Marketplace update',
        };
    }

    private function actionLabel(): string
    {
        return match ($this->eventKey) {
            'new_applicant' => 'Review applicant',
            'invite_accepted' => 'Open request',
            'invite_declined' => 'Open request',
            'message_received' => 'Open conversation',
            'caregiver_hired' => 'View booking',
            'shift_starting_soon' => 'Open shift plan',
            'shift_started' => 'Track shift',
            'shift_completed' => 'Review shift',
            'review_received' => 'View shift',
            'matching_request_reminder' => 'Review request',
            'payment_authorized' => 'View request',
            'payment_authorization_failed' => 'Open billing',
            'payment_action_required' => 'Open billing',
            'payment_captured' => 'View request',
            'payment_refunded' => 'View request',
            'payout_transferred' => 'View shift',
            'payout_transfer_failed' => 'View request',
            MarketplaceEvent::CAREGIVER_WELCOME => 'Complete my profile',
            MarketplaceEvent::CAREGIVER_ONBOARDING_REMINDER_24H => 'Finish setup now',
            default => 'Open HomeCare',
        };
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
}
