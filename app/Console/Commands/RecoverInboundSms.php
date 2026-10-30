<?php

namespace App\Console\Commands;

use App\Services\Messaging\InboundSmsRecoveryService;
use Illuminate\Console\Command;
use Throwable;

class RecoverInboundSms extends Command
{
    protected $signature = 'sms:recover-inbound {messageSid : The SM or MM Message SID from Twilio}';

    protected $description = 'Recover one missing incoming Twilio message into the inbox and CRM without sending notifications';

    public function handle(InboundSmsRecoveryService $recovery): int
    {
        try {
            $message = $recovery->recover((string) $this->argument('messageSid'));
        } catch (Throwable $exception) {
            $this->error('Recovery failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info(($message->wasRecentlyCreated ? 'Recovered' : 'Already present').': '.$message->twilio_sid.' (inbox #'.$message->id.').');

        return self::SUCCESS;
    }
}
