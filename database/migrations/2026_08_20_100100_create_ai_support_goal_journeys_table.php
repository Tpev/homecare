<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_support_goal_journeys', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('support_ticket_id');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->unsignedBigInteger('family_account_id')->nullable();
            $table->string('journey_type', 64);
            $table->string('journey_version', 32);
            $table->string('intent_id', 40)->nullable();
            $table->string('state', 24)->index();
            $table->string('step_key', 80);
            $table->unsignedSmallInteger('progress_current')->default(1);
            $table->unsignedSmallInteger('progress_total')->default(1);
            $table->longText('context')->nullable();
            $table->string('last_result_code', 120)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('started_at');
            $table->timestamp('last_activity_at')->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('transferred_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->index(['support_ticket_id', 'actor_user_id', 'state'], 'asgj_ticket_actor_state_idx');
            $table->index(['actor_user_id', 'state', 'expires_at'], 'asgj_actor_state_exp_idx');
            $table->foreign('support_ticket_id', 'asgj_ticket_fk')->references('id')->on('support_tickets')->cascadeOnDelete();
            $table->foreign('actor_user_id', 'asgj_actor_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('family_account_id', 'asgj_account_fk')->references('id')->on('family_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_support_goal_journeys');
    }
};
