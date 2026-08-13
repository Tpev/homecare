<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_copilot_destruction_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('environment', 64);
            $table->string('database_reference_hash', 64);
            $table->string('operator_name');
            $table->json('approver_names');
            $table->string('code_version');
            $table->string('migration_version');
            $table->json('before_counts');
            $table->json('after_counts');
            $table->json('target_checklist');
            $table->string('verification_result', 32);
            $table->text('backup_extinction_status');
            $table->text('approved_exceptions')->nullable();
            $table->timestamp('executed_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['environment', 'executed_at'], 'lcdr_environment_executed_idx');
            $table->index(['verification_result', 'executed_at'], 'lcdr_verification_executed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_copilot_destruction_runs');
    }
};
