<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Ops\OpsAlertService;
use Illuminate\Auth\Events\Registered;

class SendOpsUserRegisteredAlert
{
    public function __construct(private readonly OpsAlertService $opsAlertService)
    {
    }

    public function handle(Registered $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->opsAlertService->notifyUserRegistered($event->user);
    }
}

