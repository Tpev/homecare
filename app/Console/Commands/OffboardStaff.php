<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Admin\StaffOffboardingService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class OffboardStaff extends Command
{
    protected $signature = 'staff:offboard
        {user : Exact user ID}
        {--email= : Expected account email, required}
        {--by= : Administrator ID or email, required}
        {--alias=* : Additional confirmed email address to block}
        {--reason= : Offboarding reason, required when applying}
        {--apply : Revoke access and return assignments to the unassigned queues}';

    protected $description = 'Preview or permanently revoke an SDR/sales account, credentials and notifications while retaining business history';

    public function handle(StaffOffboardingService $service): int
    {
        $by = trim((string) $this->option('by'));
        $admin = ctype_digit($by) ? User::find((int) $by) : User::where('email', $by)->first();
        if (! ctype_digit((string) $this->argument('user')) || ! $admin?->isAdministrator() || ! $this->option('email')) {
            $this->error('Provide an exact user ID, --email and --by identifying a current administrator.');

            return self::FAILURE;
        }

        try {
            $args = [$admin, (int) $this->argument('user'), (string) $this->option('email'), $this->option('alias')];
            $report = $this->option('apply')
                ? $service->offboard(...[...$args, (string) $this->option('reason')])
                : $service->preview(...$args);
        } catch (ValidationException $exception) {
            $this->error(implode(' ', $exception->validator->errors()->all()));

            return self::FAILURE;
        }

        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $this->info($this->option('apply') ? 'Staff access revoked. Business history retained.' : 'Preview only. Nothing changed. Use --apply with --reason to execute.');

        return self::SUCCESS;
    }
}
