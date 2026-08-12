<?php

namespace App\Console\Commands;

use App\Models\BlogPostEvent;
use Illuminate\Console\Command;

class PruneBlogEvents extends Command
{
    protected $signature = 'content:prune-events';

    protected $description = 'Remove expired content analytics and unlink old attribution events from user accounts';

    public function handle(): int
    {
        $identityCutoff = now()->subDays(max(1, (int) config('content.analytics.identity_retention_days', 30)));
        $retentionCutoff = now()->subDays(max(1, (int) config('content.analytics.retention_days', 395)));

        $anonymized = BlogPostEvent::query()
            ->whereNotNull('user_id')
            ->where('occurred_at', '<', $identityCutoff)
            ->update(['user_id' => null]);
        $deleted = BlogPostEvent::query()
            ->where('occurred_at', '<', $retentionCutoff)
            ->delete();

        $this->info("Anonymized {$anonymized} event(s); deleted {$deleted} expired event(s).");

        return self::SUCCESS;
    }
}
