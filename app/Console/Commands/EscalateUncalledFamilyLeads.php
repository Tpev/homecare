<?php

namespace App\Console\Commands;

use App\Models\FamilyAcquisitionSetting;
use App\Models\Lead;
use App\Services\FamilyAcquisition\FamilyLeadAlertService;
use App\Support\FamilyLeadOutreach;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class EscalateUncalledFamilyLeads extends Command
{
    protected $signature = 'family-leads:escalate-uncalled {--limit=100}';

    protected $description = 'Email management when a family lead misses the first-call SLA';

    public function handle(FamilyLeadAlertService $alerts): int
    {
        $settings = FamilyAcquisitionSetting::query()->first();
        if (! $settings?->alerts_enabled || $settings->escalationRecipients() === []) {
            $this->components->info('Family lead escalation alerts are not configured.');

            return self::SUCCESS;
        }

        $cutoff = now()->subMinutes(max(5, (int) $settings->first_call_sla_minutes));
        $sent = 0;

        Lead::query()
            ->where('lead_type', Lead::TYPE_FAMILY)
            ->whereNotIn('status', FamilyLeadOutreach::TERMINAL_STAGES)
            ->whereNull('first_call_at')
            ->whereNull('first_call_escalated_at')
            ->whereNull('do_not_contact_at')
            ->whereNotNull('submitted_at')
            ->where('submitted_at', '<=', $cutoff)
            ->where(function (Builder $query): void {
                $query->whereNull('external_source')
                    ->orWhere('external_source', '!=', 'demo_family_acquisition');
            })
            ->oldest('submitted_at')
            ->limit(max(1, (int) $this->option('limit')))
            ->get()
            ->each(function (Lead $lead) use ($alerts, &$sent): void {
                if ($alerts->notifyFirstCallEscalation($lead)) {
                    $sent++;
                }
            });

        $this->components->info("Sent {$sent} family lead escalation alert(s).");

        return self::SUCCESS;
    }
}
