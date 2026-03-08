<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opener_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('counterparty_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('care_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('care_booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 30)->default('general'); // general, dispute, incident, cancellation, billing
            $table->string('status', 20)->default('open'); // open, in_progress, resolved, closed
            $table->string('priority', 20)->default('normal'); // low, normal, high, urgent
            $table->string('subject', 160);
            $table->text('description');
            $table->text('admin_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('assigned_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['opener_user_id', 'status', 'created_at'], 'st_opener_status_idx');
            $table->index(['status', 'priority', 'created_at'], 'st_status_priority_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
