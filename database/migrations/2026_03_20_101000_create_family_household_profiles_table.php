<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_household_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('address_line1', 255)->nullable();
            $table->string('address_line2', 255)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 2)->nullable();
            $table->string('zip', 15)->nullable();
            $table->text('home_access_notes')->nullable();
            $table->string('time_expectations', 255)->nullable();
            $table->unsignedTinyInteger('preferred_response_hours')->default(12);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_household_profiles');
    }
};

