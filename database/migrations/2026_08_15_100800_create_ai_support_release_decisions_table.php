<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_support_release_decisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('scope', 64);
            $table->string('policy_version', 120);
            $table->string('status', 32);
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500);
            $table->char('release_commit', 40);
            $table->char('snapshot_sha256', 64);
            $table->json('approved_user_ids');
            $table->timestamp('starts_at');
            $table->timestamp('expires_at');
            $table->timestamp('superseded_at')->nullable();
            $table->timestamp('retain_until')->index();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['scope', 'superseded_at'], 'asrd_scope_current_idx');
            $table->index(['status', 'expires_at'], 'asrd_status_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_support_release_decisions');
    }
};
