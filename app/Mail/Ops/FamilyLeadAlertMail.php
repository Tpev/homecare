<?php

namespace App\Mail\Ops;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FamilyLeadAlertMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public const TYPE_NEW = 'new';

    public const TYPE_ESCALATION = 'escalation';

    public function __construct(public Lead $lead, public string $alertType = self::TYPE_NEW) {}

    public function envelope(): Envelope
    {
        $name = $this->lead->name ?: 'New family';
        $subject = $this->alertType === self::TYPE_ESCALATION
            ? sprintf('[LoLo Care] First-call SLA missed: %s', $name)
            : sprintf('[LoLo Care] New family lead: call %s', $name);

        return new Envelope(
            from: new Address((string) config('mail.from.address'), 'LoLo Care'),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $form = (array) data_get($this->lead->data, 'form_answers', []);
        $isEscalation = $this->alertType === self::TYPE_ESCALATION;
        $heading = $isEscalation ? 'First-call SLA needs attention' : 'A new family lead is waiting';
        $summary = $isEscalation
            ? ($this->lead->name ?: 'A family lead').' has not received a first call within the configured SLA.'
            : ($this->lead->name ?: 'A family').' just asked LoLo Care for help. Please call as soon as possible.';
        $actionUrl = $isEscalation
            ? route('admin.family-acquisition.leads')
            : route('sdr.family-calling');

        return new Content(
            view: 'emails.ops.family-lead-alert',
            text: 'emails.ops.alert-text',
            with: [
                'heading' => $heading,
                'summary' => $summary,
                'details' => [
                    ['label' => 'Lead ID', 'value' => '#'.$this->lead->id],
                    ['label' => 'Name', 'value' => $this->lead->name ?: 'Not provided'],
                    ['label' => 'Phone', 'value' => $this->lead->phone ?: 'Not provided'],
                    ['label' => 'Location', 'value' => $this->lead->location ?: $this->lead->zip ?: 'Not provided'],
                    ['label' => 'Care for', 'value' => (string) ($form['care_for'] ?? data_get($this->lead->data, 'recipient_relationship', 'Not provided'))],
                    ['label' => 'Urgency', 'value' => (string) ($form['urgency'] ?? data_get($this->lead->data, 'start_time_label', 'Not provided'))],
                    ['label' => 'Preferred call time', 'value' => (string) ($form['preferred_call_time'] ?? data_get($this->lead->data, 'callback_time_label', 'Not provided'))],
                    ['label' => 'Source', 'value' => $this->lead->sourceLabel()],
                    ['label' => 'Received', 'value' => optional($this->lead->submitted_at ?: $this->lead->created_at)->format('F j, Y · g:i A T') ?: 'Not provided'],
                ],
                'actionUrl' => $actionUrl,
            ],
        );
    }
}
