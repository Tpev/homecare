<?php

namespace App\Notifications;

use App\Models\CareHourPurchase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CareHourPurchaseNotification extends Notification
{
    public function __construct(public readonly CareHourPurchase $purchase, public readonly bool $receipt = true, public readonly string $kind = 'success') {}

    public function via(object $notifiable): array
    {
        return $this->receipt ? ['mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $purchase = $this->purchase;
        $recipientName = $purchase->care_hour_contribution_link_id ? $purchase->contributionLink->display_name : ($purchase->familyAccount?->owner?->name ?? 'your family');
        $balanceLabel = $recipientName === 'Family care balance' ? 'the family balance' : 'the family balance for '.$recipientName;
        $receiptUrl = $purchase->buyer_user_id ? url('/family/care-hours/purchases/'.$purchase->public_id) : url('/care-hours/receipts/'.$purchase->public_id);
        if ($this->kind === 'correction') {
            return (new MailMessage)->subject('Your LoLo purchase was corrected')
                ->line('We could not complete this care-hour purchase. No hours were issued.')
                ->line('$'.number_format($purchase->amount_cents / 100, 2).' USD was returned to the original payment method. Your provider determines when it appears.')
                ->action('View purchase', $receiptUrl);
        }
        if ($this->kind === 'attention') {
            return (new MailMessage)->subject('Your LoLo care-hours payment needs attention')
                ->line('Your care-hour purchase has not completed. Review the payment to continue.')
                ->line('No care hours have been added for this attempt.')
                ->action('Review purchase', $receiptUrl);
        }

        return (new MailMessage)->subject('Your LoLo care-hours receipt')
            ->greeting('Thank you, '.$purchase->buyer_name.'.')
            ->line(($purchase->total_minutes / 60).' care hours were added to '.$balanceLabel.'.')
            ->line('Paid: $'.number_format($purchase->amount_cents / 100, 2).' USD. Includes the hourly family fee.')
            ->line($purchase->bonus_minutes ? 'Includes '.($purchase->bonus_minutes / 60).' free welcome hours.' : 'The care hours are available for eligible visits in the family account.')
            ->line('Non-refundable. No expiry. Each hour covers an hour of eligible care, even if standard prices rise.')
            ->action('View receipt', $receiptUrl);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'event_key' => $this->kind === 'hold' ? 'care_hours_on_hold' : 'care_hours_added',
            'title' => $this->kind === 'hold' ? 'Care hours need review' : 'Care hours added',
            'body' => $this->kind === 'hold' ? 'Some hours in your family balance are on hold. Contact LoLo for help with affected visits.'
                : ($this->purchase->total_minutes / 60).' hours were added to your family balance.',
            'url' => url('/family/care-hours/'.$this->purchase->care_hour_account_id),
            'care_hour_purchase_id' => $this->purchase->public_id,
        ];
    }
}
