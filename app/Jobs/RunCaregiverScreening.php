<?php

namespace App\Jobs;

use App\Services\Screening\CaregiverScreeningService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunCaregiverScreening implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $screeningCaseId) {}

    public function handle(CaregiverScreeningService $screening): void
    {
        $screening->runSearch($this->screeningCaseId);
    }
}
