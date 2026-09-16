<?php

namespace App\Mail\Ops;

use App\Models\FamilyOnboarding;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class FamilyOnboardingCompletedMail extends Mailable
{
    public function __construct(public FamilyOnboarding $onboarding) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[LoLo Care] Family onboarding completed #'.$this->onboarding->family_account_id);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.ops.family-onboarding-completed', text: 'emails.ops.alert-text', with: [
            'heading' => 'Family onboarding completed',
            'summary' => 'A family has completed onboarding. Review their details and welcome visit preference.',
            'details' => $this->onboarding->submissionDetails(),
            'actionUrl' => route('admin.family-onboarding.show', $this->onboarding),
        ]);
    }
}
