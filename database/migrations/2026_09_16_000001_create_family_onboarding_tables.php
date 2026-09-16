<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_onboardings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_account_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('initiated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('care_recipient_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 40)->default('registration');
            $table->string('status', 30)->default('in_progress')->index();
            $table->string('exemption_reason')->nullable();
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->unsignedSmallInteger('current_step')->default(1);
            $table->unsignedInteger('revision')->default(0);
            $table->json('draft');
            $table->json('request_context')->nullable();
            $table->json('submitted_snapshot')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamp('request_handoff_completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('family_welcome_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_onboarding_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('preferred_date');
            $table->string('preferred_range', 20);
            $table->string('timezone', 64);
            $table->timestamp('window_start_at');
            $table->timestamp('window_end_at');
            $table->string('phone', 30);
            $table->string('status', 30)->default('requested')->index();
            $table->timestamp('confirmed_start_at')->nullable();
            $table->text('staff_note')->nullable();
            $table->foreignId('handled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->timestamps();
        });

        Schema::create('family_onboarding_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_onboarding_id')->constrained()->cascadeOnDelete();
            $table->string('recipient');
            $table->string('status', 30)->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->string('last_error_code')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->timestamps();
            $table->unique(['family_onboarding_id', 'recipient'], 'family_onboarding_delivery_recipient_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_onboarding_deliveries');
        Schema::dropIfExists('family_welcome_visits');
        Schema::dropIfExists('family_onboardings');
    }
};
