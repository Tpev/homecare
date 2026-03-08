<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_caregiver_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('caregiver_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['family_user_id', 'caregiver_user_id'], 'fcf_family_caregiver_uniq');
            $table->index(['family_user_id', 'created_at'], 'fcf_family_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_caregiver_favorites');
    }
};
