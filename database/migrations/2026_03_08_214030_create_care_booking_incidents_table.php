<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_booking_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reporter_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('severity', 20)->default('medium');
            $table->string('status', 20)->default('open');
            $table->string('title', 160);
            $table->text('description');
            $table->timestamp('reported_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['care_booking_id', 'status'], 'cbi_booking_status_idx');
            $table->index(['severity', 'reported_at'], 'cbi_severity_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_booking_incidents');
    }
};

