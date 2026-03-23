<?php

namespace App\Observers;

use App\Jobs\InjectPrelaunchStaffApplication;
use App\Models\CareRequest;
use App\Services\Ops\OpsAlertService;

class CareRequestObserver
{
    public function __construct(private readonly OpsAlertService $opsAlertService)
    {
    }

    public function created(CareRequest $careRequest): void
    {
        $careRequest = $careRequest->fresh() ?? $careRequest;

        $this->opsAlertService->notifyCareRequestCreated($careRequest);
        $this->queuePrelaunchStaffApplications($careRequest);
    }

    private function queuePrelaunchStaffApplications(CareRequest $careRequest): void
    {
        if (! config('marketplace.family_prelaunch_auto_applicants.enabled', false)) {
            return;
        }

        $emails = collect((array) config('marketplace.family_prelaunch_auto_applicants.emails', []))
            ->map(static fn ($email) => trim((string) $email))
            ->filter(static fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values();

        if ($emails->isEmpty()) {
            return;
        }

        $delays = collect((array) config('marketplace.family_prelaunch_auto_applicants.delays_minutes', []))
            ->map(static fn ($minutes): int => max(0, (int) $minutes))
            ->values()
            ->all();

        foreach ($emails as $index => $email) {
            $delayMinutes = (int) ($delays[$index] ?? (10 + ($index * 5)));

            InjectPrelaunchStaffApplication::dispatch($careRequest->id, $email, $delayMinutes)
                ->delay(now()->addMinutes($delayMinutes));
        }
    }
}
