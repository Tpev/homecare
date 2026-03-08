<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('care_request_application_id')->nullable()->constrained('care_request_applications')->nullOnDelete();
            $table->foreignId('family_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('caregiver_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('scheduled'); // scheduled, in_progress, completed, reviewed, cancelled
            $table->dateTime('scheduled_start_at')->nullable();
            $table->dateTime('scheduled_end_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('last_rescheduled_at')->nullable();
            $table->text('last_reschedule_reason')->nullable();
            $table->timestamps();

            $table->unique('care_request_id', 'cb_req_uniq');
            $table->index(['caregiver_user_id', 'status', 'scheduled_start_at'], 'cb_cg_status_sched_idx');
            $table->index(['family_user_id', 'status', 'scheduled_start_at'], 'cb_fam_status_sched_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_bookings');
    }
};
