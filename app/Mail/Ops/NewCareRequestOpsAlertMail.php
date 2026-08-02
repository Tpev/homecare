<?php

namespace App\Mail\Ops;

use App\Models\CareRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewCareRequestOpsAlertMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public CareRequest $careRequest) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), 'LoLo Care'),
            subject: '[LoLo Care] New care request #'.$this->careRequest->id,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ops.new-care-request-alert',
            text: 'emails.ops.alert-text',
            with: [
                'heading' => 'New care request #'.$this->careRequest->id,
                'summary' => 'A family created a care request on LoLo Care.',
                'details' => [
                    ['label' => 'Title', 'value' => $this->careRequest->title],
                    ['label' => 'Type', 'value' => ucfirst(str_replace('_', ' ', (string) $this->careRequest->request_type))],
                    ['label' => 'Status', 'value' => ucfirst((string) $this->careRequest->status)],
                    ['label' => 'Location', 'value' => collect([$this->careRequest->city, $this->careRequest->state, $this->careRequest->zip])->filter()->implode(', ') ?: 'Not provided'],
                ],
                'actionUrl' => route('admin.requests.show', $this->careRequest),
            ],
        );
    }
}
