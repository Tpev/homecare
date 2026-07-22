<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('homecare:dispatch-notifications --type=all')->everyTenMinutes();
Schedule::command('homecare:auto-approve-timesheets')->hourly();
Schedule::command('homecare:retry-payout-transfers --limit=100')->hourly();
Schedule::command('homecare:generate-regular-care-visits')->dailyAt('02:15')->withoutOverlapping();
Schedule::command('homecare:prepare-regular-care-payments')->hourly()->withoutOverlapping();
