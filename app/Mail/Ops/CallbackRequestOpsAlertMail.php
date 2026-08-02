<?php

namespace App\Mail\Ops;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CallbackRequestOpsAlertMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Lead $lead) {}

    public function envelope(): Envelope
    {
        $callbackTime = (string) data_get($this->lead->data, 'callback_time_label', data_get($this->lead->data, 'callback_time', 'callback requested'));
        $name = $this->lead->name ?: 'New caller';
        $requestedContact = (string) data_get($this->lead->data, 'requested_contact', 'LoLo Care team');

        return new Envelope(
            from: new Address((string) config('mail.from.address'), 'LoLo Care'),
            subject: sprintf('[LoLo Care] %s callback request: %s (%s)', $requestedContact, $name, $callbackTime),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ops.callback-request-alert',
            text: 'emails.ops.alert-text',
            with: [
                'heading' => 'New callback request',
                'summary' => ($this->lead->name ?: 'A caller').' requested a LoLo Care callback.',
                'details' => [
                    ['label' => 'Lead ID', 'value' => '#'.$this->lead->id],
                    ['label' => 'Name', 'value' => $this->lead->name ?: 'Not provided'],
                    ['label' => 'Phone', 'value' => $this->lead->phone ?: 'Not provided'],
                    ['label' => 'Email', 'value' => $this->lead->email ?: 'Not provided'],
                    ['label' => 'Best time', 'value' => (string) data_get($this->lead->data, 'callback_time_label', data_get($this->lead->data, 'callback_time', 'Not provided'))],
                ],
                'actionUrl' => route('admin.crm.index'),
            ],
        );
    }
}
