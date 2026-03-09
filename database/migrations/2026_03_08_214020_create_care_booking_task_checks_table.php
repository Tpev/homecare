<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_booking_task_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('care_task_id')->nullable()->constrained('care_tasks')->nullOnDelete();
            $table->string('label', 140);
            $table->text('notes')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['care_booking_id', 'is_completed'], 'cbtc_booking_done_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_booking_task_checks');
    }
};

