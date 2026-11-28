<?php

namespace App\Jobs;

use App\Services\CustomerCare\CareRosterService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendCaregiverRosterEntry implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 150;

    public int $backoff = 20;

    public function __construct(public int $entryId) {}

    public function handle(CareRosterService $service): void
    {
        $service->dispatch($this->entryId);
    }

    public function failed(?\Throwable $exception): void
    {
        $entry = \App\Models\CaregiverRosterEntry::find($this->entryId);
        if (! $entry || ! in_array($entry->dispatch_status, ['queued', 'sending'])) {
            return;
        }
        $message = \App\Models\SmsMessage::where('request_key', $entry->sms_request_key)->first();
        $entry->update(['dispatch_status' => $message ? 'completed' : 'blocked', 'sms_message_id' => $message?->id,
            'dispatch_note' => 'The worker stopped. Check invitation and SMS history before approving any new outreach.']);
    }
}
