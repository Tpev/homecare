<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('care_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewee_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['care_booking_id', 'reviewer_user_id'], 'crv_booking_reviewer_uniq');
            $table->index(['reviewee_user_id', 'created_at'], 'crv_reviewee_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_reviews');
    }
};
