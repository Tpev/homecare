<?php

namespace App\Mail\Ops;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserRegisteredOpsAlertMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), 'LoLo Care'),
            subject: '[LoLo Care] New user registration: '.$this->user->email,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ops.user-registered-alert',
            text: 'emails.ops.alert-text',
            with: [
                'heading' => 'New user registration',
                'summary' => 'A new '.($this->user->role ?: 'user').' account was created on LoLo Care.',
                'details' => [
                    ['label' => 'User ID', 'value' => '#'.$this->user->id],
                    ['label' => 'Name', 'value' => $this->user->name],
                    ['label' => 'Email', 'value' => $this->user->email],
                    ['label' => 'Role', 'value' => ucfirst((string) ($this->user->role ?: 'family'))],
                ],
                'actionUrl' => route('admin.users.show', $this->user),
            ],
        );
    }
}
