<?php

namespace App\Jobs;

use App\Services\Ops\SlackOpsNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendSlackOpsNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 15;

    public function __construct(
        public readonly string $event,
        public readonly int $careRequestId,
        public readonly ?int $applicationId = null,
    ) {}

    public function handle(SlackOpsNotificationService $notifications): void
    {
        $notifications->deliver($this->event, $this->careRequestId, $this->applicationId);
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Slack operations notification could not be delivered after retries.', [
            'event' => $this->event,
            'care_request_id' => $this->careRequestId,
            'application_id' => $this->applicationId,
        ]);
    }
}
