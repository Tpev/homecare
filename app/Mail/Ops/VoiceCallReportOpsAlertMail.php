<?php

namespace App\Mail\Ops;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VoiceCallReportOpsAlertMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public array $report)
    {
    }

    public function envelope(): Envelope
    {
        $callSid = (string) ($this->report['call_sid'] ?? 'unknown');
        $outcome = (string) ($this->report['outcome'] ?? 'unknown');

        return new Envelope(
            subject: sprintf('[HomeCare] Voice call report: %s (%s)', $callSid, $outcome),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ops.voice-call-report-alert',
        );
    }
}
