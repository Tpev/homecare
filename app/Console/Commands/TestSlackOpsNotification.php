<?php

namespace App\Console\Commands;

use App\Services\Ops\SlackOpsNotificationService;
use Illuminate\Console\Command;
use RuntimeException;

class TestSlackOpsNotification extends Command
{
    protected $signature = 'ops:slack:test';

    protected $description = 'Send a privacy-safe test message to the configured Slack operations channel';

    public function handle(SlackOpsNotificationService $notifications): int
    {
        try {
            $notifications->sendTest();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Slack operations test notification delivered.');

        return self::SUCCESS;
    }
}
