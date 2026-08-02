<?php

namespace App\Mail\Ops;

use App\Models\SupportTicket;
use App\Support\MarketplaceNotificationPresentation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupportTicketCreatedOpsAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SupportTicket $ticket) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), 'LoLo Care'),
            subject: '[LoLo Care] '.$this->priorityLabel().' support request #'.$this->ticket->id,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ops.support-ticket-created-alert',
            text: 'emails.ops.alert-text',
            with: [
                'brandName' => MarketplaceNotificationPresentation::BRAND_NAME,
                'logoUrl' => asset(MarketplaceNotificationPresentation::LOGO_PATH),
                'homeUrl' => route('dashboard'),
                'supportUrl' => route('admin.support.tickets.show', $this->ticket),
                'year' => now()->year,
                'heading' => 'New support request #'.$this->ticket->id,
                'summary' => 'A LoLo Care user opened a support request that needs operations review.',
                'details' => [
                    ['label' => 'Opened by', 'value' => $this->ticket->opener?->name ?: 'Former user'],
                    ['label' => 'Category', 'value' => ucfirst(str_replace('_', ' ', (string) $this->ticket->category))],
                    ['label' => 'Priority', 'value' => $this->priorityLabel()],
                    ['label' => 'Subject', 'value' => $this->ticket->subject],
                ],
                'actionUrl' => route('admin.support.tickets.show', $this->ticket),
            ],
        );
    }

    private function priorityLabel(): string
    {
        return ucfirst((string) ($this->ticket->priority ?: 'normal'));
    }
}
