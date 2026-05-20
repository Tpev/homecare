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

    public function __construct(public Lead $lead)
    {
    }

    public function envelope(): Envelope
    {
        $callbackTime = (string) data_get($this->lead->data, 'callback_time_label', 'callback requested');
        $name = $this->lead->name ?: 'New family lead';

        return new Envelope(
            subject: sprintf('[LoLo] Callback request: %s (%s)', $name, $callbackTime),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ops.callback-request-alert',
        );
    }
}
