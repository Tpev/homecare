<?php

namespace App\Jobs;

use App\Services\Family\FamilyOnboardingDeliveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendFamilyOnboardingEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public int $deliveryId)
    {
        $this->onConnection(config('family_onboarding.queue_connection', 'database'));
        $this->afterCommit();
    }

    public function handle(FamilyOnboardingDeliveryService $deliveries): void
    {
        $deliveries->send($this->deliveryId);
    }
}
