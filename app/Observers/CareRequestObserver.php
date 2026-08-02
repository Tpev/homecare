<?php

namespace App\Observers;

use App\Models\CareRequest;
use App\Services\Ops\OpsAlertService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class CareRequestObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly OpsAlertService $opsAlertService) {}

    public function created(CareRequest $careRequest): void
    {
        $careRequest = $careRequest->fresh() ?? $careRequest;

        $this->opsAlertService->notifyCareRequestCreated($careRequest);
    }
}
