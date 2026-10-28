<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CareHourVerificationNotification extends Notification
{
    public function __construct(public readonly string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Your LoLo care-hours verification code')
            ->line('Use this code to confirm your email address:')
            ->line($this->code)->line('This code expires in '.(int) config('care_hours.verification_minutes', 20).' minutes. If you did not request it, you can ignore this email.');
    }
}
