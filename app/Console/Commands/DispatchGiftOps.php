<?php

namespace App\Console\Commands;

use App\Jobs\SendGiftOpsNotification;
use App\Models\CareHourGiftEvent;
use App\Services\Ops\SlackOpsNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class DispatchGiftOps extends Command
{
    protected $signature = 'ops:gifts:dispatch {--status : Show notification counts without sending messages}';

    protected $description = 'Recover pending gift-card notifications through the existing Ops Slack webhook';

    public function handle(): int
    {
        if (! Schema::hasTable('care_hour_gift_events')) {
            $this->info('Gift tracking migration is not installed yet.');

            return self::SUCCESS;
        }
        if ($this->option('status')) {
            $this->table(['Status', 'Events'], CareHourGiftEvent::whereNotNull('slack_status')
                ->selectRaw('slack_status, count(*) as total')->groupBy('slack_status')
                ->get()->map(fn ($row) => [$row->slack_status, $row->total])->all());

            return self::SUCCESS;
        }
        if (! app(SlackOpsNotificationService::class)->enabled()) {
            return self::SUCCESS;
        }
        CareHourGiftEvent::where('slack_status', 'sending')->where('sending_at', '<', now()->subMinutes(5))
            ->update(['slack_status' => 'uncertain', 'last_error' => 'Worker stopped during delivery. Check Ops before replaying.']);
        CareHourGiftEvent::where('slack_status', 'pending')->where('available_at', '<=', now())->orderBy('id')->limit(200)
            ->get()->each(fn ($event) => SendGiftOpsNotification::dispatch($event->id));

        return self::SUCCESS;
    }
}
