<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caregiver_skill', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caregiver_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['caregiver_profile_id', 'skill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caregiver_skill');
    }
};
