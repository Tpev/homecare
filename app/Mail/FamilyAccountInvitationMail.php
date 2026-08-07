<?php

namespace App\Mail;

use App\Models\FamilyAccountInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class FamilyAccountInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public FamilyAccountInvitation $invitation,
        #[\SensitiveParameter] public string $token,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->ownerFirstName().' invited you to help manage care on LoLo');
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.family-account-invitation-html',
            text: 'emails.family-account-invitation-text',
            with: [
                'ownerFirstName' => $this->ownerFirstName(),
                'acceptUrl' => route('family.invitations.show', ['token' => $this->token]),
                'expiresAt' => $this->invitation->expires_at,
            ],
        );
    }

    private function ownerFirstName(): string
    {
        return Str::of((string) $this->invitation->familyAccount?->owner?->name)->trim()->before(' ')->value() ?: 'Someone you trust';
    }
}
