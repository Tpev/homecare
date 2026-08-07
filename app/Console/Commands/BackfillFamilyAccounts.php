<?php

namespace App\Console\Commands;

use App\Services\FamilyAccounts\FamilyAccountBackfill;
use Illuminate\Console\Command;

class BackfillFamilyAccounts extends Command
{
    protected $signature = 'homecare:backfill-family-accounts';

    protected $description = 'Idempotently create Family Accounts and map existing family-owned records.';

    public function handle(FamilyAccountBackfill $backfill): int
    {
        $summary = $backfill->run(function ($user, $account, $records, $tickets): void {
            $this->line("Mapped family user {$user->id} to account {$account->id}: {$records} records, {$tickets} tickets.");
        });

        $this->info("Family account backfill complete: {$summary['users']} users, {$summary['records']} records, {$summary['support_tickets']} support tickets.");

        return self::SUCCESS;
    }
}
