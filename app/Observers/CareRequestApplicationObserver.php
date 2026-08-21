<?php

namespace App\Observers;

use App\Models\CareRequestApplication;
use App\Services\Ops\SlackOpsNotificationService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class CareRequestApplicationObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly SlackOpsNotificationService $slackNotifications) {}

    public function updated(CareRequestApplication $application): void
    {
        if (! $application->wasChanged('status') || $application->status !== CareRequestApplication::STATUS_HIRED) {
            return;
        }

        $this->slackNotifications->queueCaregiverHired($application);
    }
}
