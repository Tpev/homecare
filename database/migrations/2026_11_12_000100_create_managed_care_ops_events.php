<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('managed_care_ops_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 100);
            $table->string('dedupe_key', 100)->unique();
            $table->unsignedBigInteger('care_request_id')->index();
            $table->unsignedBigInteger('care_booking_id')->nullable();
            $table->json('payload');
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at');
            $table->timestamp('sending_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
            $table->index(['status', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('managed_care_ops_events');
    }
};
