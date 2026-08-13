<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $counts = [
            'ai_request_messages' => Schema::hasTable('ai_request_messages')
                ? DB::table('ai_request_messages')->count()
                : 0,
            'ai_request_sessions' => Schema::hasTable('ai_request_sessions')
                ? DB::table('ai_request_sessions')->count()
                : 0,
        ];

        if ($counts['ai_request_messages'] !== 0 || $counts['ai_request_sessions'] !== 0) {
            throw new \RuntimeException(
                'Legacy AI request tables are not empty. Run the reviewed '
                .'ai-support:destroy-legacy-copilot-data procedure before applying this migration.'
            );
        }

        if (app()->environment('production')) {
            $verified = Schema::hasTable('legacy_copilot_destruction_runs')
                && DB::table('legacy_copilot_destruction_runs')
                    ->where('environment', 'production')
                    ->where('verification_result', 'passed')
                    ->exists();

            if (! $verified) {
                throw new \RuntimeException(
                    'Production legacy table removal requires a successful content-free destruction audit record.'
                );
            }
        }

        Schema::dropIfExists('ai_request_messages');
        Schema::dropIfExists('ai_request_sessions');
    }

    public function down(): void
    {
        // Irreversible by design. Retired legacy content and write paths must not be recreated by rollback.
    }
};
