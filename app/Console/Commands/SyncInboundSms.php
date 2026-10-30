<?php

namespace App\Console\Commands;

use App\Models\SmsMessage;
use App\Services\Messaging\InboundSmsRecoveryService;
use App\Services\Messaging\TwilioSmsClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncInboundSms extends Command
{
    protected $signature = 'sms:sync-inbound
        {--days=7 : Look back this many days, between 1 and 30}
        {--max-pages=20 : Maximum Twilio pages to inspect, between 1 and 100}
        {--dry-run : List missing message SIDs without importing them}';

    protected $description = 'Recover missing incoming texts from Twilio without duplicates or sending messages';

    public function handle(TwilioSmsClient $twilio, InboundSmsRecoveryService $recovery): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 30]]);
        $maxPages = filter_var($this->option('max-pages'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        if ($days === false || $maxPages === false) {
            $this->error('Use --days=1..30 and --max-pages=1..100.');

            return self::FAILURE;
        }
        $recovered = $existing = $failed = $missing = 0;
        try {
            foreach ($twilio->inboundMessageSidsSince(now()->subDays($days), $maxPages) as $sid) {
                $message = SmsMessage::query()->where('twilio_sid', $sid)->first();
                if ($message && $message->direction === SmsMessage::DIRECTION_INCOMING
                    && $message->twilio_account_sid === config('services.twilio.account_sid')) {
                    $existing++;

                    continue;
                }
                $missing++;
                if ($this->option('dry-run')) {
                    $this->line('Would recover: '.$sid);

                    continue;
                }
                try {
                    $message = $recovery->recover($sid);
                    $message->wasRecentlyCreated ? $recovered++ : $existing++;
                } catch (Throwable $exception) {
                    $failed++;
                    $this->warn('Could not recover '.$sid.': '.$exception->getMessage());
                    Log::warning('Incoming SMS recovery failed', ['message_sid' => $sid, 'error' => $exception->getMessage()]);
                }
            }
        } catch (Throwable $exception) {
            $failed++;
            $this->error('Sync incomplete: '.$exception->getMessage());
            Log::warning('Incoming SMS sync incomplete', ['error' => $exception->getMessage()]);
        }
        $this->info($this->option('dry-run')
            ? "{$missing} missing, {$existing} already present, {$failed} errors. No messages imported."
            : "{$recovered} recovered, {$existing} already present, {$failed} errors.");
        if ($recovered > 0) {
            Log::info('Incoming SMS sync recovered missing messages', ['recovered' => $recovered, 'failed' => $failed]);
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
