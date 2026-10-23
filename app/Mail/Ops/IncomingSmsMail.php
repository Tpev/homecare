<?php

namespace App\Mail\Ops;

use App\Models\Lead;
use App\Models\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class IncomingSmsMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public SmsMessage $sms) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[LoLo Care] SMS from '.($this->sms->lead?->name ?: $this->sms->from_phone));
    }

    public function content(): Content
    {
        $lead = $this->sms->lead;
        $url = $lead
            ? ($lead->lead_type === Lead::TYPE_FAMILY
                ? route('admin.family-acquisition.leads', ['lead' => $lead->id])
                : route('admin.crm.index', ['pipeline' => $lead->lead_type, 'lead' => $lead->id]))
            : route('admin.crm.index');
        $history = SmsMessage::query()->where('id', '<', $this->sms->id)
            ->when($lead, fn ($query) => $query->where('lead_id', $lead->id), fn ($query) => $query->whereNull('lead_id'))
            ->where(function ($query) {
                $query->where(fn ($q) => $q->where('from_phone', $this->sms->from_phone)->where('to_phone', $this->sms->to_phone))
                    ->orWhere(fn ($q) => $q->where('from_phone', $this->sms->to_phone)->where('to_phone', $this->sms->from_phone));
            })->latest('id')->limit(10)->get()->reverse();

        return new Content(view: 'emails.ops.incoming-sms', with: compact('lead', 'url', 'history'));
    }
}
