<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('homecare:dispatch-notifications --type=all')->everyTenMinutes();
Schedule::command('homecare:auto-approve-timesheets')->hourly();
Schedule::command('homecare:process-time-corrections')->hourly()->withoutOverlapping();
Schedule::command('homecare:process-completed-extra-visits')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('homecare:retry-payout-transfers --limit=100')->hourly()->withoutOverlapping();
Schedule::command('homecare:reconcile-payment-ledger-v2 --limit=100')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('homecare:generate-regular-care-visits')->dailyAt('02:15')->withoutOverlapping();
Schedule::command('homecare:prepare-regular-care-payments')->hourly()->withoutOverlapping();
Schedule::command('homecare:process-continuous-coverage')->hourly()->withoutOverlapping();
Schedule::command('content:publish-scheduled')->everyMinute()->withoutOverlapping();
Schedule::command('content:verify-public --fail-on-issues')->dailyAt('09:20')->withoutOverlapping()->appendOutputTo(storage_path('logs/content-public-verification.log'));
Schedule::command('content:audit')->weeklyOn(1, '08:00')->withoutOverlapping()->appendOutputTo(storage_path('logs/content-audit.log'));
Schedule::command('content:prune-events')->dailyAt('03:20')->withoutOverlapping();
Schedule::command('content-mcp:prune')->dailyAt('03:25')->withoutOverlapping();
if (config('ai_support.retention_execution_enabled', false)) {
    Schedule::command('ai-support:apply-retention --execute')->dailyAt('03:40')->withoutOverlapping();
}
Schedule::command('ai-support:monitor-health')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('model:prune', ['--model' => [\App\Models\ContentApiIdempotencyKey::class]])
    ->dailyAt('03:30')
    ->withoutOverlapping();
