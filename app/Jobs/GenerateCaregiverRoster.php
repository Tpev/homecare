<?php

namespace App\Jobs;

use App\Models\CaregiverRoster;
use App\Services\CustomerCare\CareRosterAi;
use App\Services\CustomerCare\CareRosterService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateCaregiverRoster implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout;

    public int $backoff = 30;

    public function __construct(public int $rosterId)
    {
        $this->timeout = CareRosterAi::requestTimeout() + 20;
    }

    public function handle(CareRosterService $service): void
    {
        if ($service->generate($this->rosterId)) {
            self::dispatch($this->rosterId)->afterCommit();
        }
    }

    public function failed(?\Throwable $exception): void
    {
        CaregiverRoster::whereKey($this->rosterId)->whereIn('status', ['queued', 'generating'])
            ->update(['status' => 'failed', 'generation_data' => null, 'error' => 'Generation did not finish. Please generate a fresh roster.']);
    }
}
