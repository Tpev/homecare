<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caregiver_identity_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caregiver_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('didit_session_id', 120)->unique();
            $table->string('status', 32)->default('not_started');
            $table->text('verification_url')->nullable();
            $table->string('vendor_data', 191)->nullable();
            $table->json('session_payload')->nullable();
            $table->json('decision_payload')->nullable();
            $table->json('webhook_payload')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('last_webhook_at')->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status'], 'civ_user_status_idx');
            $table->index(['caregiver_profile_id', 'status'], 'civ_profile_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caregiver_identity_verifications');
    }
};

