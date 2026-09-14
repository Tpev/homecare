<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class FamilyLeadWelcomeMail extends Mailable
{
    public function __construct(
        public string $firstName,
        public string $emailSubject,
        public string $emailBody,
        public string $startUrl,
        public string $unsubscribeUrl,
        public string $replyAddress,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), 'LoLo Care'),
            replyTo: [new Address($this->replyAddress, 'LoLo Care')],
            subject: $this->emailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.family-lead-welcome');
    }
}
