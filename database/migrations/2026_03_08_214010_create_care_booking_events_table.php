<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_booking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role', 20)->default('system');
            $table->string('event_type', 60);
            $table->json('payload')->nullable();
            $table->timestamp('happened_at');
            $table->timestamps();

            $table->index(['care_booking_id', 'happened_at'], 'cbe_booking_time_idx');
            $table->index(['event_type', 'happened_at'], 'cbe_type_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_booking_events');
    }
};

