<?php

namespace App\Console\Commands;

use App\Jobs\SendManagedCareOpsNotification;
use App\Models\ManagedCareOpsEvent;
use App\Services\Ops\SlackOpsNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DispatchManagedCareOps extends Command
{
    protected $signature = 'ops:managed-care:dispatch {--status : Show delivery counts without sending messages}';

    protected $description = 'Recover pending managed-care Ops Slack deliveries';

    public function handle(): int
    {
        if (! Schema::hasTable('managed_care_ops_events')) {
            $this->info('Managed care Ops delivery schema is not installed yet.');

            return self::SUCCESS;
        }
        if ($this->option('status')) {
            $this->table(['Status', 'Events'], ManagedCareOpsEvent::selectRaw('status, count(*) as total')->groupBy('status')->get()->map(fn ($row) => [$row->status, $row->total])->all());

            return self::SUCCESS;
        }
        if (! app(SlackOpsNotificationService::class)->enabled()) {
            return self::SUCCESS;
        }
        $stale = ManagedCareOpsEvent::where('status', 'sending')->where('sending_at', '<', now()->subMinutes(5))
            ->update(['status' => 'uncertain', 'last_error' => 'Worker stopped during delivery. Check Ops before replaying.', 'updated_at' => now()]);
        if ($stale) {
            Log::warning('Managed care Slack deliveries have unknown outcomes.', ['count' => $stale]);
        }
        ManagedCareOpsEvent::where('status', 'pending')->where('available_at', '<=', now())->orderBy('id')->limit(200)->get()
            ->each(fn ($event) => SendManagedCareOpsNotification::dispatch($event->id));

        return self::SUCCESS;
    }
}
