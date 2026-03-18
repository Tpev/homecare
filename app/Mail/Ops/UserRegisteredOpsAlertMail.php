<?php

namespace App\Mail\Ops;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserRegisteredOpsAlertMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public User $user)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[HomeCare] New user registration: '.$this->user->email,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ops.user-registered-alert',
        );
    }
}

