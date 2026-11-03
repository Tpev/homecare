<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_recorded_visits', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_token')->unique();
            $table->char('input_hash', 64);
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('care_booking_id')->unique()->constrained('care_bookings')->restrictOnDelete();
            $table->json('input');
            $table->json('quote');
            $table->string('payment_mode', 20);
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_recorded_visits');
    }
};
