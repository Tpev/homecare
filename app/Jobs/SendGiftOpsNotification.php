<?php

namespace App\Jobs;

use App\Services\Ops\GiftOpsDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendGiftOpsNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public int $timeout = 20;

    public function __construct(public readonly int $eventId) {}

    public function handle(GiftOpsDelivery $delivery): void
    {
        $delivery->deliver($this->eventId);
    }

    public function backoff(): array
    {
        return [10, 60, 300];
    }
}
