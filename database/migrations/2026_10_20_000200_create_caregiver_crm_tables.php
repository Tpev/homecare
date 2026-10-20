<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caregiver_crm_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('caregiver_user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority', 16)->default('normal')->index();
            $table->text('notes')->nullable();
            $table->string('next_action')->nullable();
            $table->timestamp('next_follow_up_at')->nullable()->index();
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
        });

        Schema::create('caregiver_crm_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('caregiver_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 24);
            $table->string('call_outcome', 24)->nullable();
            $table->text('body');
            $table->timestamp('occurred_at');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['caregiver_user_id', 'occurred_at']);
            $table->index(['caregiver_user_id', 'type', 'call_outcome'], 'caregiver_crm_contact_outcome_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caregiver_crm_activities');
        Schema::dropIfExists('caregiver_crm_records');
    }
};
