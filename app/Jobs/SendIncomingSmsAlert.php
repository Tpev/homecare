<?php

namespace App\Jobs;

use App\Mail\Ops\IncomingSmsMail;
use App\Models\SmsMessage;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Mail;

class SendIncomingSmsAlert implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    public function __construct(public int $smsMessageId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('incoming-sms:'.$this->smsMessageId))->releaseAfter(30)->expireAfter(120)];
    }

    public function backoff(): array
    {
        return [30, 60, 300, 900];
    }

    public function handle(): void
    {
        $message = SmsMessage::with('lead.assignedAdmin')->find($this->smsMessageId);
        if (! $message || $message->direction !== SmsMessage::DIRECTION_INCOMING || $message->inbound_notified_at) {
            return;
        }

        $recipients = User::query()->whereIn('role', ['admin', 'sdr'])->pluck('email')
            ->push('peverelli.t@gmail.com')
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))->unique()->values()->all();
        if ($recipients === []) {
            return;
        }

        Mail::to($recipients)->send(new IncomingSmsMail($message));
        $message->update(['inbound_notified_at' => now()]);
    }
}
