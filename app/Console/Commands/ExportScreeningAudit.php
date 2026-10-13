<?php

namespace App\Console\Commands;

use App\Services\Screening\ScreeningAuditService;
use Illuminate\Console\Command;

class ExportScreeningAudit extends Command
{
    protected $signature = 'screening:export-audit';

    protected $description = 'Retry pending screening audit events into private append-only JSONL files';

    public function handle(ScreeningAuditService $audit): int
    {
        $this->info('Exported '.$audit->exportPending().' audit events.');

        return self::SUCCESS;
    }
}
