<?php

namespace App\Console\Commands;

use App\Models\CompletedExtraVisitRequest;
use App\Services\RegularCare\CompletedExtraVisitService;
use Illuminate\Console\Command;

class ProcessCompletedExtraVisits extends Command
{
    protected $signature = 'homecare:process-completed-extra-visits {--dry-run}';

    protected $description = 'Safely retry approved completed-extra-visit reports that did not finish processing';

    public function handle(CompletedExtraVisitService $service): int
    {
        if (! $service->enabled()) {
            $this->line('Completed extra visits are disabled.');

            return self::SUCCESS;
        }

        $requests = CompletedExtraVisitRequest::query()
            ->whereIn('status', [
                CompletedExtraVisitRequest::STATUS_APPROVED_PROCESSING,
                CompletedExtraVisitRequest::STATUS_FAILED,
            ])
            ->where(function ($query): void {
                $query->whereNull('processing_started_at')
                    ->orWhere('processing_started_at', '<=', now()->subMinutes(10));
            })
            ->orderBy('processing_started_at')
            ->limit(100)
            ->get();

        if ($this->option('dry-run')) {
            $this->info($requests->count().' completed extra visit report(s) are eligible for safe retry.');

            return self::SUCCESS;
        }

        foreach ($requests as $request) {
            $result = $service->retryProcessing($request);
            $this->line('Report #'.$request->id.' is '.$result->status.'.');
        }

        return self::SUCCESS;
    }
}
