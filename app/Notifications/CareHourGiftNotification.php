<?php

namespace App\Notifications;

use App\Models\CareHourPurchase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Buyer receipts never disclose the recipient who eventually claims the gift. */
class CareHourGiftNotification extends Notification
{
    public function __construct(public readonly CareHourPurchase $purchase, public readonly string $kind = 'success') {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->kind === 'hold' ? 'Your LoLo gift needs review' : 'Your LoLo care-hours gift')
            ->greeting('Hello, '.$this->purchase->buyer_name.'.');
        if (in_array($this->kind, ['hold', 'attention'], true)) {
            $mail->line('This gift payment needs attention. Review its current status before sharing it.');
        } else {
            $mail->line('Your gift purchase of '.($this->purchase->paid_minutes / 60).' care hours is confirmed.')
                ->line('Paid: $'.number_format($this->purchase->amount_cents / 100, 2).' USD. Includes the hourly family fee.');
            if ($this->purchase->gift?->claimed_at) {
                $mail->line('This gift has already been claimed. Your private purchase receipt remains available.');
            } else {
                $mail->line('Open your private receipt and verify your email to view the gift code. Share it privately with the family you choose.')
                    ->line('They can create a free LoLo account, then claim for themselves or someone they already care for. Any eligible welcome hours are confirmed when claimed.');
            }
            $mail->line('Non-refundable. No expiry.');
        }

        return $mail->action('View gift receipt', url('/care-hours/gifts/receipts/'.$this->purchase->public_id));
    }
}
