<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_support_guided_tasks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('support_ticket_id');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->unsignedBigInteger('family_account_id')->nullable();
            $table->string('task_type', 64);
            $table->string('state', 32)->index();
            $table->string('navigation_target_id', 120);
            $table->longText('payload')->nullable();
            $table->string('initial_state_hash', 64)->nullable();
            $table->string('result_state_hash', 64)->nullable();
            $table->string('last_result_code', 120)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('in_progress_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('presented_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->index(['actor_user_id', 'state', 'expires_at'], 'asgt_actor_state_exp_idx');
            $table->index(['support_ticket_id', 'created_at'], 'asgt_ticket_created_idx');
            $table->foreign('support_ticket_id', 'asgt_ticket_fk')->references('id')->on('support_tickets')->cascadeOnDelete();
            $table->foreign('actor_user_id', 'asgt_actor_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('family_account_id', 'asgt_account_fk')->references('id')->on('family_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_support_guided_tasks');
    }
};
