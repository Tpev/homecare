<?php

namespace App\Console\Commands;

use App\Jobs\RunCaregiverScreening;
use App\Models\CaregiverProfile;
use App\Models\CaregiverScreeningCase;
use App\Services\Screening\CaregiverScreeningService;
use App\Services\Screening\DiditVerifiedIdentityService;
use Illuminate\Console\Command;
use Throwable;

class SyncCaregiverScreenings extends Command
{
    protected $signature = 'screening:sync {--dry-run : Preview queue enrollment without changing anything} {--refresh-identities : Reconcile missing verified names using Didit}';

    protected $description = 'Enroll legacy and new caregivers, enqueue six-month reviews, and retry screening searches without changing work access';

    public function handle(CaregiverScreeningService $screening, DiditVerifiedIdentityService $identity): int
    {
        $counts = ['profiles' => 0, 'legacy_access' => 0, 'verified_identity' => 0, 'open_or_due' => 0];
        $errors = 0;
        CaregiverProfile::orderBy('id')->chunkById(100, function ($profiles) use ($screening, $identity, &$counts, &$errors): void {
            foreach ($profiles as $profile) {
                $counts['profiles']++;
                $counts['legacy_access'] += (int) $profile->screening_legacy_access;
                if (! $this->option('dry-run') && $this->option('refresh-identities') && ! $identity->snapshot($profile)) {
                    try {
                        $identity->reconcile($profile);
                        $profile->refresh();
                    } catch (Throwable $error) {
                        $errors++;
                        $this->warn('Identity reconciliation failed for profile '.$profile->id.' ('.$error::class.').');
                    }
                }
                $counts['verified_identity'] += (int) ($identity->snapshot($profile) !== null);
                if ($this->option('dry-run')) {
                    $counts['open_or_due'] += (int) (CaregiverScreeningCase::where('open_profile_id', $profile->id)->exists()
                        || ! CaregiverScreeningCase::where('caregiver_profile_id', $profile->id)->exists()
                        || $profile->screening_next_due_at?->lte(now()));

                    continue;
                }
                $case = $screening->sync($profile);
                if ($case) {
                    $counts['open_or_due']++;
                    if ($case->search_status !== 'completed' && $case->status !== 'awaiting_kyc') {
                        RunCaregiverScreening::dispatch($case->id);
                    }
                }
            }
        });
        $this->table(['Metric', 'Count'], collect($counts)->map(fn ($count, $key) => [$key, $count])->values()->all());
        $this->info($this->option('dry-run') ? 'Preview only; no profiles or cases changed.' : 'Queue synchronized. Existing work access was not changed.');

        return $errors ? self::FAILURE : self::SUCCESS;
    }
}
