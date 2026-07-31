<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('completed_extra_visit_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('client_request_id')->unique();
            $table->foreignId('care_plan_id')->constrained('care_plans')->cascadeOnDelete();
            $table->foreignId('family_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('caregiver_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('care_booking_id')->nullable()->constrained('care_bookings')->nullOnDelete();
            $table->foreignId('supersedes_id')->nullable()->constrained('completed_extra_visit_requests')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('support_ticket_id')->nullable()->constrained('support_tickets')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 40)->default('pending_family');
            $table->string('reason_code', 64);
            $table->text('explanation');
            $table->text('care_notes')->nullable();
            $table->string('timezone', 64);
            $table->dateTime('proposed_started_at');
            $table->dateTime('proposed_completed_at');
            $table->unsignedInteger('proposed_break_minutes')->default(0);
            $table->unsignedInteger('proposed_worked_minutes');
            $table->json('financial_preview');
            $table->json('final_financial_preview')->nullable();
            $table->text('family_response_note')->nullable();
            $table->unsignedInteger('processing_attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('changes_requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('payment_action_required_at')->nullable();
            $table->timestamp('disputed_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->unique('care_booking_id', 'completed_extra_visit_booking_unique');
            $table->unique(['care_plan_id', 'version'], 'completed_extra_visit_plan_version_unique');
            $table->index(['care_plan_id', 'status'], 'completed_extra_visit_plan_status_idx');
            $table->index(['family_user_id', 'status'], 'completed_extra_visit_family_status_idx');
            $table->index(['caregiver_user_id', 'status'], 'completed_extra_visit_caregiver_status_idx');
            $table->index(['status', 'processing_started_at'], 'completed_extra_visit_processing_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('completed_extra_visit_requests');
    }
};
