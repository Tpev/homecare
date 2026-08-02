<?php

namespace App\Mail\Ops;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VoiceCallReportOpsAlertMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public array $report) {}

    public function envelope(): Envelope
    {
        $callSid = (string) ($this->report['call_sid'] ?? 'unknown');
        $outcome = (string) ($this->report['outcome'] ?? 'unknown');

        return new Envelope(
            from: new Address((string) config('mail.from.address'), 'LoLo Care'),
            subject: sprintf('[LoLo Care] Voice call report: %s (%s)', $callSid, $outcome),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ops.voice-call-report-alert',
            text: 'emails.ops.alert-text',
            with: [
                'heading' => 'Voice call report',
                'summary' => 'A LoLo Care voice call completed.',
                'details' => [
                    ['label' => 'Call SID', 'value' => (string) ($this->report['call_sid'] ?? 'Not available')],
                    ['label' => 'Caller', 'value' => (string) ($this->report['name'] ?? 'Not provided')],
                    ['label' => 'Phone', 'value' => (string) ($this->report['phone'] ?? 'Not provided')],
                    ['label' => 'Outcome', 'value' => ucfirst(str_replace('_', ' ', (string) ($this->report['outcome'] ?? 'unknown')))],
                ],
                'actionUrl' => route('admin.voice-ai.index'),
            ],
        );
    }
}
