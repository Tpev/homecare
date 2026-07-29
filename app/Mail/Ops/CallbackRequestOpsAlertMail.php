<?php

namespace App\Mail\Ops;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
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
            subject: sprintf('[LoLo Care] %s callback request: %s (%s)', $requestedContact, $name, $callbackTime),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ops.callback-request-alert',
        );
    }
}
