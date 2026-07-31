<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_booking_time_corrections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('client_request_id')->unique();
            $table->foreignId('care_booking_id')->constrained('care_bookings')->cascadeOnDelete();
            $table->foreignId('requester_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('family_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('supersedes_id')->nullable()->constrained('care_booking_time_corrections')->nullOnDelete();
            $table->string('status', 40);
            $table->string('reason_code', 40);
            $table->text('explanation');
            $table->timestamp('proposed_started_at');
            $table->timestamp('proposed_completed_at');
            $table->unsignedInteger('proposed_break_minutes')->default(0);
            $table->unsignedInteger('proposed_worked_minutes');
            $table->json('original_snapshot');
            $table->json('financial_preview');
            $table->text('family_response_note')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('support_ticket_id')->nullable()->constrained('support_tickets')->nullOnDelete();
            $table->unsignedInteger('processing_attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('changes_requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('payment_action_required_at')->nullable();
            $table->timestamp('first_reminded_at')->nullable();
            $table->timestamp('second_reminded_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->unique(['care_booking_id', 'version'], 'cbtc_booking_version_unique');
            $table->index(['care_booking_id', 'status'], 'cbtc_booking_status_idx');
            $table->index(['status', 'submitted_at'], 'cbtc_status_submitted_idx');
            $table->index(['family_user_id', 'status'], 'cbtc_family_status_idx');
        });

        Schema::table('care_booking_corrections', function (Blueprint $table): void {
            $table->string('source', 40)->default('admin')->after('actor_admin_user_id');
            $table->foreignId('time_correction_request_id')
                ->nullable()
                ->unique()
                ->after('source')
                ->constrained('care_booking_time_corrections')
                ->nullOnDelete();
            $table->foreignId('requester_user_id')
                ->nullable()
                ->after('time_correction_request_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('approved_by_user_id')
                ->nullable()
                ->after('requester_user_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('care_booking_corrections', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropConstrainedForeignId('requester_user_id');
            $table->dropConstrainedForeignId('time_correction_request_id');
            $table->dropColumn('source');
        });

        Schema::dropIfExists('care_booking_time_corrections');
    }
};
