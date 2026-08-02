<?php

namespace App\Notifications\Auth;

use App\Support\MarketplaceNotificationPresentation;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class LoLoCareResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = $this->resetUrl($notifiable);
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->from((string) config('mail.from.address'), MarketplaceNotificationPresentation::BRAND_NAME)
            ->subject('[LoLo Care] Reset your password')
            ->view([
                'html' => 'emails.notifications.marketplace-event-html',
                'text' => 'emails.notifications.marketplace-event-text',
            ], $this->viewData(
                notifiable: $notifiable,
                url: $url,
                title: 'Reset your LoLo Care password',
                body: 'We received a request to reset the password for your LoLo Care account.',
                ctaLabel: 'Reset password',
                details: [['label' => 'Link expires', 'value' => $minutes.' minutes']],
                nextSteps: [
                    'Use the secure link below to choose a new password.',
                    'If you did not request this, you can ignore this email; your password will not change.',
                ],
            ));
    }

    /** @return array<string,mixed> */
    private function viewData(object $notifiable, string $url, string $title, string $body, string $ctaLabel, array $details, array $nextSteps): array
    {
        return [
            'appName' => MarketplaceNotificationPresentation::BRAND_NAME,
            'eventLabel' => 'Account security',
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'rawUrl' => $url,
            'ctaLabel' => $ctaLabel,
            'supportUrl' => route('support.index'),
            'homeUrl' => route('landing'),
            'year' => now()->year,
            'logoUrl' => asset(MarketplaceNotificationPresentation::LOGO_PATH),
            'openTrackingUrl' => null,
            'preheader' => $title,
            'firstName' => str((string) ($notifiable->name ?? ''))->before(' ')->toString(),
            'emailDetails' => $details,
            'nextSteps' => $nextSteps,
        ];
    }
}
