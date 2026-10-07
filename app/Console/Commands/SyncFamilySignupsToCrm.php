<?php

namespace App\Console\Commands;

use App\Models\FamilyAccount;
use App\Models\FamilyOnboarding;
use App\Services\FamilyAcquisition\FamilyCrmSyncService;
use Illuminate\Console\Command;

class SyncFamilySignupsToCrm extends Command
{
    protected $signature = 'family-crm:sync-signups {--dry-run : Count eligible signups without changing records}';

    protected $description = 'Backfill CRM leads and onboarding notes for existing family signups without sending email';

    public function handle(FamilyCrmSyncService $crm): int
    {
        $query = FamilyOnboarding::query()->whereIn('status', ['in_progress', 'completed'])->whereHas('familyAccount', fn ($q) => $q
            ->where('status', FamilyAccount::STATUS_ACTIVE)
            ->whereColumn('owner_user_id', 'family_onboardings.initiated_by_user_id')
            ->whereHas('owner', fn ($owner) => $owner->where('role', 'family')));
        if ($this->option('dry-run')) {
            $this->info($query->count().' eligible family signups. No records changed.');

            return self::SUCCESS;
        }
        $synced = 0;
        $query->chunkById(100, function ($records) use ($crm, &$synced) {
            foreach ($records as $record) {
                if ($crm->sync($record)) {
                    $synced++;
                }
            }
        });
        $this->info("Synced {$synced} family signups. Existing links and notes were reused; no email was sent.");

        return self::SUCCESS;
    }
}
