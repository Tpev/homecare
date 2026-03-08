<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('caregiver_language', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caregiver_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('language_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['caregiver_profile_id', 'language_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caregiver_language');
        Schema::dropIfExists('languages');
    }
};
