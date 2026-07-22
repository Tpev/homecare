<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_booking_corrections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('client_request_id')->unique();
            $table->foreignId('care_booking_id')->constrained('care_bookings')->cascadeOnDelete();
            $table->foreignId('support_ticket_id')->nullable()->constrained('support_tickets')->nullOnDelete();
            $table->foreignId('actor_admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->unsignedInteger('previous_charge_cents')->default(0);
            $table->unsignedInteger('target_charge_cents')->default(0);
            $table->integer('payment_delta_cents')->default(0);
            $table->integer('caregiver_delta_cents')->default(0);
            $table->timestamp('family_approval_confirmed_at')->nullable();
            $table->text('reason');
            $table->json('before_snapshot');
            $table->json('requested_changes');
            $table->json('preview');
            $table->json('after_snapshot')->nullable();
            $table->json('provider_payload')->nullable();
            $table->text('last_error')->nullable();
            $table->uuid('internal_note_client_id');
            $table->uuid('public_reply_client_id');
            $table->timestamp('booking_applied_at')->nullable();
            $table->timestamp('payout_applied_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['care_booking_id', 'created_at'], 'cbc_booking_created_idx');
            $table->index(['support_ticket_id', 'status'], 'cbc_ticket_status_idx');
            $table->index(['status', 'updated_at'], 'cbc_status_updated_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_booking_corrections');
    }
};
