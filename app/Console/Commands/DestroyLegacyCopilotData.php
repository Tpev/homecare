<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DestroyLegacyCopilotData extends Command
{
    protected $signature = 'ai-support:destroy-legacy-copilot-data
        {--environment= : Exact running application environment}
        {--execute : Permanently delete the inventoried primary-database rows}
        {--confirm= : Exact database-bound confirmation phrase}
        {--operator= : Named operator responsible for execution}
        {--approver=* : Named approver; production requires two distinct approvers}
        {--derived-targets-verified : Assert that the external target checklist was completed}
        {--backup-status= : Backup destruction/expiry and restore-control status}
        {--exceptions= : Approved exception references only}
        {--code-version= : Deployed commit or release identifier}';

    protected $description = 'Dry-run or execute the guarded destruction of retired AI care-request copilot data.';

    private const MIGRATION_VERSION = '2026_08_13_100100';

    public function handle(): int
    {
        $runningEnvironment = app()->environment();
        $requestedEnvironment = trim((string) $this->option('environment'));

        if ($requestedEnvironment === '' || ! hash_equals($runningEnvironment, $requestedEnvironment)) {
            $this->error('The --environment value must exactly match the running application environment.');

            return self::FAILURE;
        }

        $databaseName = (string) config(
            'database.connections.'.config('database.default').'.database',
            'unknown-database'
        );
        $expectedConfirmation = 'DESTROY-LEGACY-COPILOT-DATA:'.$runningEnvironment.':'.$databaseName;
        $before = $this->currentCounts();

        $this->table(
            ['Target', 'Rows'],
            collect($before)->map(fn (int $count, string $target): array => [$target, $count])->values()->all()
        );

        if (! $this->option('execute')) {
            $this->warn('Dry run only. No rows were changed.');
            $this->line('Required confirmation: '.$expectedConfirmation);

            return self::SUCCESS;
        }

        $operator = trim((string) $this->option('operator'));
        $approvers = collect((array) $this->option('approver'))
            ->map(fn (mixed $name): string => trim((string) $name))
            ->filter()
            ->unique()
            ->values();
        $backupStatus = trim((string) $this->option('backup-status'));
        $codeVersion = trim((string) $this->option('code-version'));

        if (! hash_equals($expectedConfirmation, (string) $this->option('confirm'))) {
            $this->error('The database-bound --confirm value is missing or incorrect.');

            return self::FAILURE;
        }

        if ($operator === '' || $approvers->count() < 2 || $backupStatus === '' || $codeVersion === '') {
            $this->error('Execution requires a named operator, two distinct approvers, backup status, and code version.');

            return self::FAILURE;
        }

        if (! $this->option('derived-targets-verified')) {
            $this->error('Execution requires the production topology and derived-target checklist assertion.');

            return self::FAILURE;
        }

        if (! Schema::hasTable('legacy_copilot_destruction_runs')) {
            $this->error('Apply the destruction-audit migration before executing deletion.');

            return self::FAILURE;
        }

        try {
            $after = DB::transaction(function () use (
                $runningEnvironment,
                $databaseName,
                $operator,
                $approvers,
                $backupStatus,
                $codeVersion,
                $before,
            ): array {
                $preservedBefore = $this->preservationCounts();

                if (Schema::hasTable('ai_request_messages')) {
                    DB::table('ai_request_messages')->delete();
                }

                if (Schema::hasTable('ai_request_sessions')) {
                    DB::table('ai_request_sessions')->delete();
                }

                $after = $this->currentCounts();
                $preservedAfter = $this->preservationCounts();

                if (array_sum($after) !== 0) {
                    throw new \RuntimeException('Legacy target verification did not reach zero rows.');
                }

                if ($preservedBefore !== $preservedAfter) {
                    throw new \RuntimeException('A preserved domain count changed; the transaction was rolled back.');
                }

                DB::table('legacy_copilot_destruction_runs')->insert([
                    'environment' => $runningEnvironment,
                    'database_reference_hash' => hash('sha256', $databaseName),
                    'operator_name' => $operator,
                    'approver_names' => json_encode($approvers->all(), JSON_THROW_ON_ERROR),
                    'code_version' => $codeVersion,
                    'migration_version' => self::MIGRATION_VERSION,
                    'before_counts' => json_encode([...$before, ...$preservedBefore], JSON_THROW_ON_ERROR),
                    'after_counts' => json_encode([...$after, ...$preservedAfter], JSON_THROW_ON_ERROR),
                    'target_checklist' => json_encode([
                        'primary_database' => 'verified_and_destroyed',
                        'derived_targets' => 'operator_verified',
                    ], JSON_THROW_ON_ERROR),
                    'verification_result' => 'passed',
                    'backup_extinction_status' => $backupStatus,
                    'approved_exceptions' => $this->nullableString($this->option('exceptions')),
                    'executed_at' => now(),
                    'created_at' => now(),
                ]);

                return $after;
            }, 3);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Destruction failed and was rolled back: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Target', 'Rows after'],
            collect($after)->map(fn (int $count, string $target): array => [$target, $count])->values()->all()
        );
        $this->info('Primary legacy copilot data was destroyed and content-free evidence was recorded.');
        $this->warn('Phase 0 remains open until every derived target and containing backup reaches verified extinction.');

        return self::SUCCESS;
    }

    /** @return array<string, int> */
    private function currentCounts(): array
    {
        return [
            'ai_request_messages' => $this->countIfPresent('ai_request_messages'),
            'ai_request_sessions' => $this->countIfPresent('ai_request_sessions'),
        ];
    }

    /** @return array<string, int> */
    private function preservationCounts(): array
    {
        return [
            'care_requests_preserved' => $this->countIfPresent('care_requests'),
            'support_tickets_preserved' => $this->countIfPresent('support_tickets'),
            'support_ticket_messages_preserved' => $this->countIfPresent('support_ticket_messages'),
        ];
    }

    private function countIfPresent(string $table): int
    {
        return Schema::hasTable($table) ? DB::table($table)->count() : 0;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
