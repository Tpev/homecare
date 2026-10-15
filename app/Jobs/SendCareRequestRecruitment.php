<?php

namespace App\Jobs;

use App\Models\CareRequestRecruitment;
use App\Services\Marketplace\CareRequestRecruitmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendCareRequestRecruitment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    public function __construct(public int $recruitmentId) {}

    public function handle(CareRequestRecruitmentService $service): void
    {
        $recruitment = CareRequestRecruitment::query()->find($this->recruitmentId);
        if ($recruitment) {
            $service->send($recruitment);
        }
    }

    public function failed(?Throwable $exception): void
    {
        CareRequestRecruitment::query()->whereKey($this->recruitmentId)
            ->whereIn('status', [CareRequestRecruitment::STATUS_QUEUED, CareRequestRecruitment::STATUS_SENDING])
            ->update([
                'status' => CareRequestRecruitment::STATUS_FAILED,
                'status_reason' => 'The email worker failed. Check the queue and delivery logs before retrying.',
            ]);
    }
}
