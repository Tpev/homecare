<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Family\FamilyWelcomeNotificationService;
use App\Services\Ops\OpsAlertService;
use Illuminate\Auth\Events\Registered;

class SendOpsUserRegisteredAlert
{
    public function __construct(
        private readonly OpsAlertService $opsAlertService,
        private readonly FamilyWelcomeNotificationService $familyWelcome,
    ) {}

    public function handle(Registered $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        if ($event->user->role === 'family' || $event->user->role === null || $event->user->role === '') {
            $this->opsAlertService->notifyFamilyRegistered($event->user);
            $this->familyWelcome->send($event->user);

            return;
        }

        $this->opsAlertService->notifyUserRegistered($event->user);
    }
}
