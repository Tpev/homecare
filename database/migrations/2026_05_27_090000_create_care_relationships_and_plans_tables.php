<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('caregiver_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('source_care_request_id')->nullable()->constrained('care_requests')->nullOnDelete();
            $table->foreignId('last_care_request_id')->nullable()->constrained('care_requests')->nullOnDelete();
            $table->foreignId('last_care_booking_id')->nullable()->constrained('care_bookings')->nullOnDelete();
            $table->string('recipient_name')->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamp('last_visit_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['family_user_id', 'status'], 'cr_family_status_idx');
            $table->index(['caregiver_user_id', 'status'], 'cr_caregiver_status_idx');
            $table->index(['family_user_id', 'caregiver_user_id'], 'cr_family_caregiver_idx');
        });

        Schema::create('care_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_relationship_id')->nullable()->constrained('care_relationships')->nullOnDelete();
            $table->foreignId('family_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('caregiver_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('source_care_request_id')->nullable()->constrained('care_requests')->nullOnDelete();
            $table->foreignId('source_care_booking_id')->nullable()->constrained('care_bookings')->nullOnDelete();
            $table->foreignId('next_booking_id')->nullable()->constrained('care_bookings')->nullOnDelete();
            $table->string('status', 32)->default('draft');
            $table->string('title');
            $table->json('recipient_snapshot')->nullable();
            $table->json('address_snapshot')->nullable();
            $table->json('task_snapshot')->nullable();
            $table->text('care_notes')->nullable();
            $table->json('schedule_days');
            $table->time('schedule_start_time');
            $table->time('schedule_end_time');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->json('counter_schedule_days')->nullable();
            $table->time('counter_schedule_start_time')->nullable();
            $table->time('counter_schedule_end_time')->nullable();
            $table->date('counter_starts_on')->nullable();
            $table->text('counter_note')->nullable();
            $table->decimal('hourly_rate', 8, 2);
            $table->text('family_message')->nullable();
            $table->text('caregiver_note')->nullable();
            $table->timestamp('offered_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_generated_at')->nullable();
            $table->string('payment_status', 32)->default('unchecked');
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['family_user_id', 'status'], 'cp_family_status_idx');
            $table->index(['caregiver_user_id', 'status', 'expires_at'], 'cp_caregiver_status_exp_idx');
            $table->index(['source_care_request_id', 'status'], 'cp_source_request_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_plans');
        Schema::dropIfExists('care_relationships');
    }
};
