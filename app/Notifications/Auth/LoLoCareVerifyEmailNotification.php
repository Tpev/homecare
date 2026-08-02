<?php

namespace App\Notifications\Auth;

use App\Support\MarketplaceNotificationPresentation;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class LoLoCareVerifyEmailNotification extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);
        $minutes = (int) config('auth.verification.expire', 60);

        return (new MailMessage)
            ->from((string) config('mail.from.address'), MarketplaceNotificationPresentation::BRAND_NAME)
            ->subject('[LoLo Care] Verify your email address')
            ->view([
                'html' => 'emails.notifications.marketplace-event-html',
                'text' => 'emails.notifications.marketplace-event-text',
            ], [
                'appName' => MarketplaceNotificationPresentation::BRAND_NAME,
                'eventLabel' => 'Account security',
                'title' => 'Verify your email address',
                'body' => 'Confirm that this email address belongs to you so you can securely use your LoLo Care account.',
                'url' => $url,
                'rawUrl' => $url,
                'ctaLabel' => 'Verify email address',
                'supportUrl' => route('support.index'),
                'homeUrl' => route('landing'),
                'year' => now()->year,
                'logoUrl' => asset(MarketplaceNotificationPresentation::LOGO_PATH),
                'openTrackingUrl' => null,
                'preheader' => 'Verify your email address for LoLo Care.',
                'firstName' => str((string) ($notifiable->name ?? ''))->before(' ')->toString(),
                'emailDetails' => [['label' => 'Link expires', 'value' => $minutes.' minutes']],
                'nextSteps' => [
                    'Select the secure button below to verify your email address.',
                    'If you did not create or update this account, contact LoLo Care support.',
                ],
            ]);
    }
}
