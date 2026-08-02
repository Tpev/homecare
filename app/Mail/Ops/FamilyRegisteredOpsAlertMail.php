<?php

namespace App\Mail\Ops;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FamilyRegisteredOpsAlertMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), 'LoLo Care'),
            subject: '[LoLo Care] New family registration: '.$this->user->email,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ops.family-registered-alert',
            text: 'emails.ops.alert-text',
            with: [
                'heading' => 'New family registration',
                'summary' => 'A new family account was created on LoLo Care.',
                'details' => [
                    ['label' => 'User ID', 'value' => '#'.$this->user->id],
                    ['label' => 'Name', 'value' => $this->user->name],
                    ['label' => 'Email', 'value' => $this->user->email],
                    ['label' => 'Phone', 'value' => $this->user->phone ?: 'Not provided'],
                ],
                'actionUrl' => route('admin.users.show', $this->user),
            ],
        );
    }
}
