<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CaregiverLaunchEmail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public const SUBJECT = 'HomeCare Hub is now LoLo Care';

    public const PREVIEW_TEXT = "We're launching on June 1, 2026, and we'll share more details early next week.";

    public const WEBSITE_URL = 'https://carelolo.com/';

    public const LOGO_PATH = 'images/marketing/lolo/lolo-lockup-warm-1024.png';

    public const LAUNCH_DATE = 'June 1, 2026';

    public const FACEBOOK_URL = 'https://www.facebook.com/lolo.homecare';

    public const INSTAGRAM_URL = 'https://www.instagram.com/get.lolocare/';

    public const PHONE = '(919) 593-2721';

    public function __construct(public ?User $caregiver = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), 'LoLo Care'),
            subject: self::SUBJECT,
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.caregiver-launch-html',
            text: 'emails.caregiver-launch-text',
            with: [
                'firstName' => $this->firstName(),
                'previewText' => self::PREVIEW_TEXT,
                'logoUrl' => asset(self::LOGO_PATH),
                'launchDate' => self::LAUNCH_DATE,
                'websiteUrl' => self::WEBSITE_URL,
                'facebookUrl' => self::FACEBOOK_URL,
                'instagramUrl' => self::INSTAGRAM_URL,
                'phone' => self::PHONE,
            ],
        );
    }

    private function firstName(): string
    {
        $name = trim((string) ($this->caregiver?->name ?? ''));

        if ($name === '') {
            return '';
        }

        return (string) preg_split('/\s+/', $name)[0];
    }
}
