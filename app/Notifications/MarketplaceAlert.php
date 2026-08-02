<?php

namespace App\Notifications;

use App\Support\MarketplaceNotificationPresentation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MarketplaceAlert extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $title,
        private readonly string $body,
        private readonly ?string $url = null,
        private readonly string $kind = 'general',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->from((string) config('mail.from.address'), MarketplaceNotificationPresentation::BRAND_NAME)
            ->subject($this->title)
            ->line($this->body);

        if ($this->url) {
            $mail->action('Open LoLo Care', $this->url);
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'kind' => $this->kind,
        ];
    }
}
