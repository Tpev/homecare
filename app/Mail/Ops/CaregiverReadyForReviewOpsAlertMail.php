<?php

namespace App\Mail\Ops;

use App\Models\CaregiverProfile;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CaregiverReadyForReviewOpsAlertMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public User $user, public CaregiverProfile $profile) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), 'LoLo Care'),
            subject: '[LoLo Care] Caregiver ready for review: '.$this->user->email,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ops.caregiver-ready-for-review-alert',
            text: 'emails.ops.alert-text',
            with: [
                'heading' => 'Caregiver ready for review',
                'summary' => 'A caregiver completed onboarding and is waiting for operations review.',
                'details' => [
                    ['label' => 'Caregiver', 'value' => $this->user->name],
                    ['label' => 'Email', 'value' => $this->user->email],
                    ['label' => 'Profile ID', 'value' => '#'.$this->profile->id],
                    ['label' => 'Status', 'value' => ucfirst((string) $this->profile->status)],
                ],
                'actionUrl' => route('admin.users.show', $this->user),
            ],
        );
    }
}
