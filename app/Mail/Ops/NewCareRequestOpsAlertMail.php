<?php

namespace App\Mail\Ops;

use App\Models\CareRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewCareRequestOpsAlertMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public CareRequest $careRequest)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[HomeCare] New care request #'.$this->careRequest->id,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ops.new-care-request-alert',
        );
    }
}

