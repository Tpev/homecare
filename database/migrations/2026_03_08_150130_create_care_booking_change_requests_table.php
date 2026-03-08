<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_booking_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requester_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20); // cancel, reschedule
            $table->string('status', 20)->default('pending'); // pending, accepted, rejected, withdrawn
            $table->text('reason');
            $table->dateTime('proposed_start_at')->nullable();
            $table->dateTime('proposed_end_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->index(['care_booking_id', 'status', 'created_at'], 'cbcr_booking_status_idx');
            $table->index(['requester_user_id', 'status', 'created_at'], 'cbcr_requester_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_booking_change_requests');
    }
};
