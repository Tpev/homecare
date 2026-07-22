<?php

namespace App\Console\Commands;

use App\Models\CarePlan;
use App\Models\CareRequest;
use App\Models\User;
use App\Services\RegularCare\RegularCareMigrationService;
use Illuminate\Console\Command;

class AuditRegularCare extends Command
{
    protected $signature = 'homecare:regular-care-audit {--request=} {--plan=} {--email=}';

    protected $description = 'Read-only audit of one recurring-care customer and its bookings/payments.';

    public function handle(RegularCareMigrationService $migration): int
    {
        $request = $this->resolveRequest();
        if (! $request) {
            $this->error('No matching recurring request or care plan was found.');

            return self::FAILURE;
        }
        $report = $migration->inspect($request);
        $this->renderReport($report);
        $this->info('Read-only audit complete. No database or Stripe changes were made.');

        return self::SUCCESS;
    }

    /** @param array<string,mixed> $report */
    protected function renderReport(array $report): void
    {
        $request = $report['request'];
        $plan = $report['plan'];
        $booking = $report['booking'];
        $this->table(['Item', 'Value'], [
            ['Request', '#'.$request->id.' · '.$request->status.' · '.$request->request_type],
            ['Family', $request->family?->name.' · '.$request->family?->email],
            ['Caregiver', $report['caregiver']?->name.' · '.$report['caregiver']?->email],
            ['Plan', $plan ? '#'.$plan->id.' · '.$plan->status : 'Would create one active plan'],
            ['Stored schedule', implode(', ', $report['current_schedule']['days'] ?? []).' · '.data_get($report, 'current_schedule.start_time').'-'.data_get($report, 'current_schedule.end_time').' · '.data_get($report, 'current_schedule.timezone')],
            ['Confirmed schedule', $report['confirmed_schedule'] ? implode(', ', $report['confirmed_schedule']['days']).' · '.$report['confirmed_schedule']['start_time'].'-'.$report['confirmed_schedule']['end_time'].' · '.$report['confirmed_schedule']['timezone'] : 'Not supplied'],
            ['Retained booking', $booking ? '#'.$booking->id.' · '.$booking->status.' · '.$booking->scheduled_start_at : 'None'],
            ['Payment action', $report['payment_action']],
            ['Duplicate starts', (string) $report['duplicates']->count()],
        ]);
        $this->line('Future occurrence preview:');
        foreach (array_slice($report['futureOccurrences'], 0, 20) as $occurrence) {
            $this->line('  '.$occurrence['start']->format('D, M j Y g:i A').' to '.$occurrence['end']->format('g:i A').' · '.$occurrence['key']);
        }
        foreach ($report['warnings'] as $warning) {
            $this->warn($warning);
        }
    }

    protected function resolveRequest(): ?CareRequest
    {
        if ($id = $this->option('request')) {
            return CareRequest::query()->find((int) $id);
        }
        if ($id = $this->option('plan')) {
            return CarePlan::query()->find((int) $id)?->sourceCareRequest;
        }
        if ($email = $this->option('email')) {
            $family = User::query()->whereRaw('LOWER(email) = ?', [strtolower(trim((string) $email))])->first();

            return $family ? CareRequest::query()->where('family_user_id', $family->id)->where('request_type', CareRequest::TYPE_RECURRING)->latest()->first() : null;
        }

        return null;
    }
}
