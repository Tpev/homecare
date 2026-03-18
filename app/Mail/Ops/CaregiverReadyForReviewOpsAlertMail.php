<?php

namespace App\Mail\Ops;

use App\Models\CaregiverProfile;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CaregiverReadyForReviewOpsAlertMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public User $user, public CaregiverProfile $profile)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[HomeCare] Caregiver ready for review: '.$this->user->email,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ops.caregiver-ready-for-review-alert',
        );
    }
}

