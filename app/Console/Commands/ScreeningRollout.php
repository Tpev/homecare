<?php

namespace App\Console\Commands;

use App\Services\Screening\ScreeningAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class ScreeningRollout extends Command
{
    protected $signature = 'screening:rollout
        {--status : Read pending or active without changing anything}
        {--prepare : Reconcile legacy access after old application writers have drained}
        {--activate : Permanently close legacy enrollment after preparation}';

    protected $description = 'Coordinate the one-time screening cutover with atomic application deployment';

    public function handle(ScreeningAuditService $audit): int
    {
        if (count(array_filter([$this->option('status'), $this->option('prepare'), $this->option('activate')])) !== 1) {
            $this->error('Choose exactly one of --status, --prepare, or --activate.');

            return self::FAILURE;
        }

        try {
            if ($this->option('status')) {
                $active = Schema::hasTable('screening_rollout_states')
                    && DB::table('screening_rollout_states')->where('id', 1)->whereNotNull('activated_at')->exists();
                $this->line($active ? 'active' : 'pending');

                return self::SUCCESS;
            }

            if (! Schema::hasTable('screening_rollout_states')) {
                throw new RuntimeException('Run the screening migrations before preparing the rollout.');
            }

            $result = DB::transaction(function () use ($audit): string {
                // Creating and locking the same singleton serializes concurrent deploy commands.
                DB::table('screening_rollout_states')->insertOrIgnore([
                    'id' => 1, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $state = DB::table('screening_rollout_states')->where('id', 1)->lockForUpdate()->first();
                if ($state->activated_at !== null) {
                    return 'active';
                }

                if ($this->option('activate')) {
                    if ($state->prepared_at === null) {
                        throw new RuntimeException('Screening rollout must be prepared before activation.');
                    }
                    DB::table('screening_rollout_states')->where('id', 1)->update([
                        'activated_at' => now(), 'updated_at' => now(),
                    ]);
                    $audit->record(null, null, 'screening_rollout_activated');

                    return 'active';
                }

                // Deployment must first drain old HTTP, queue, and scheduler writers.
                // A retry before activation intentionally captures intervening old-code approvals.
                $counts = ['profiles' => 0, 'legacy_marked' => 0, 'legacy_access_granted' => 0];
                DB::table('caregiver_profiles')->select([
                    'id', 'status', 'screening_legacy_at', 'screening_legacy_access',
                    'screening_suspended_at', 'screening_initial_approved_at',
                ])->orderBy('id')->lockForUpdate()->chunkById(100, function ($profiles) use (&$counts): void {
                    foreach ($profiles as $profile) {
                        $counts['profiles']++;
                        $changes = [];
                        if ($profile->screening_legacy_at === null) {
                            $changes['screening_legacy_at'] = now();
                            $counts['legacy_marked']++;
                        }
                        if (! $profile->screening_legacy_access && $profile->status === 'active'
                            && $profile->screening_suspended_at === null && $profile->screening_initial_approved_at === null) {
                            $changes['screening_legacy_access'] = true;
                            $counts['legacy_access_granted']++;
                        }
                        if ($changes !== []) {
                            DB::table('caregiver_profiles')->where('id', $profile->id)->update($changes);
                        }
                    }
                });
                DB::table('screening_rollout_states')->where('id', 1)->update([
                    'prepared_at' => now(), 'updated_at' => now(),
                ]);
                $audit->record(null, null, 'screening_rollout_prepared', $counts);

                return 'prepared';
            }, 3);
            $this->line($result);

            return self::SUCCESS;
        } catch (Throwable $error) {
            $this->error(get_class($error) === RuntimeException::class
                ? $error->getMessage() : 'Screening rollout failed; no incomplete transaction was committed.');

            return self::FAILURE;
        }
    }
}
