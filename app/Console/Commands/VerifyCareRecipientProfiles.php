<?php

namespace App\Console\Commands;

use App\Models\CareRecipientProfileVersion;
use App\Services\CareRecipientProfiles\CareRecipientProfileSnapshotBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyCareRecipientProfiles extends Command
{
    protected $signature = 'care-profiles:verify';

    protected $description = 'Verify care-profile ownership, attachments, versions, and candidate privacy';

    public function handle(CareRecipientProfileSnapshotBuilder $snapshots): int
    {
        $checks = [
            'profiles_without_family_account' => DB::table('care_recipient_profiles as p')
                ->leftJoin('family_accounts as a', 'a.id', '=', 'p.family_account_id')
                ->whereNull('a.id')->count(),
            'default_profile_account_mismatch' => DB::table('family_accounts as a')
                ->join('care_recipient_profiles as p', 'p.id', '=', 'a.default_care_recipient_profile_id')
                ->whereColumn('p.family_account_id', '!=', 'a.id')->count(),
            'request_attachment_account_mismatch' => DB::table('care_request_recipients as r')
                ->join('care_requests as q', 'q.id', '=', 'r.care_request_id')
                ->join('care_recipient_profiles as p', 'p.id', '=', 'r.care_recipient_profile_id')
                ->whereColumn('q.family_account_id', '!=', 'p.family_account_id')->count(),
            'request_version_profile_mismatch' => DB::table('care_request_recipients as r')
                ->join('care_recipient_profile_versions as v', 'v.id', '=', 'r.care_recipient_profile_version_id')
                ->whereColumn('r.care_recipient_profile_id', '!=', 'v.care_recipient_profile_id')->count(),
            'request_one_sided_attachment' => DB::table('care_request_recipients')
                ->where(function ($query) {
                    $query->whereNull('care_recipient_profile_id')->whereNotNull('care_recipient_profile_version_id');
                })
                ->orWhere(function ($query) {
                    $query->whereNotNull('care_recipient_profile_id')->whereNull('care_recipient_profile_version_id');
                })->count(),
            'relationship_attachment_account_mismatch' => DB::table('care_relationships as r')
                ->join('care_recipient_profiles as p', 'p.id', '=', 'r.care_recipient_profile_id')
                ->whereColumn('r.family_account_id', '!=', 'p.family_account_id')->count(),
            'care_plan_attachment_account_mismatch' => DB::table('care_plans as c')
                ->join('care_recipient_profiles as p', 'p.id', '=', 'c.care_recipient_profile_id')
                ->whereColumn('c.family_account_id', '!=', 'p.family_account_id')->count(),
            'care_plan_version_profile_mismatch' => DB::table('care_plans as c')
                ->join('care_recipient_profile_versions as v', 'v.id', '=', 'c.care_recipient_profile_version_id')
                ->whereColumn('c.care_recipient_profile_id', '!=', 'v.care_recipient_profile_id')->count(),
            'care_plan_one_sided_attachment' => DB::table('care_plans')
                ->where(function ($query) {
                    $query->whereNull('care_recipient_profile_id')->whereNotNull('care_recipient_profile_version_id');
                })
                ->orWhere(function ($query) {
                    $query->whereNotNull('care_recipient_profile_id')->whereNull('care_recipient_profile_version_id');
                })->count(),
            'coverage_attachment_account_mismatch' => DB::table('continuous_coverage_plans as c')
                ->join('care_recipient_profiles as p', 'p.id', '=', 'c.care_recipient_profile_id')
                ->whereColumn('c.family_account_id', '!=', 'p.family_account_id')->count(),
            'coverage_version_profile_mismatch' => DB::table('continuous_coverage_plans as c')
                ->join('care_recipient_profile_versions as v', 'v.id', '=', 'c.care_recipient_profile_version_id')
                ->whereColumn('c.care_recipient_profile_id', '!=', 'v.care_recipient_profile_id')->count(),
            'coverage_one_sided_attachment' => DB::table('continuous_coverage_plans')
                ->where(function ($query) {
                    $query->whereNull('care_recipient_profile_id')->whereNotNull('care_recipient_profile_version_id');
                })
                ->orWhere(function ($query) {
                    $query->whereNotNull('care_recipient_profile_id')->whereNull('care_recipient_profile_version_id');
                })->count(),
            'latest_version_profile_mismatch' => DB::table('care_recipient_profiles as p')
                ->join('care_recipient_profile_versions as v', 'v.id', '=', 'p.latest_ready_version_id')
                ->whereColumn('p.id', '!=', 'v.care_recipient_profile_id')->count(),
            'ready_profiles_without_latest_version' => DB::table('care_recipient_profiles')
                ->where('status', 'ready')
                ->whereNull('latest_ready_version_id')
                ->count(),
        ];

        $unsafeSnapshots = 0;
        CareRecipientProfileVersion::query()->orderBy('id')->each(function ($version) use ($snapshots, &$unsafeSnapshots): void {
            try {
                $snapshots->assertCandidateSafe($version->candidate_snapshot ?? []);
            } catch (\Throwable) {
                $unsafeSnapshots++;
            }
        });
        $checks['candidate_snapshots_with_prohibited_keys'] = $unsafeSnapshots;

        $this->table(['Check', 'Failures'], collect($checks)->map(fn ($count, $check) => [$check, $count])->values()->all());
        $failed = array_sum($checks);
        if ($failed > 0) {
            $this->error('Care-profile verification failed with '.$failed.' violation(s).');

            return self::FAILURE;
        }

        $this->info('Care-profile verification passed.');

        return self::SUCCESS;
    }
}
