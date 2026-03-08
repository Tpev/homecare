<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caregiver_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('profile_photo_path')->nullable();
            $table->text('bio')->nullable();
            $table->decimal('hourly_rate', 8, 2)->nullable();
            $table->unsignedTinyInteger('years_experience')->nullable();
            $table->string('languages')->nullable();
            $table->string('service_area_zip', 15)->nullable();
            $table->unsignedTinyInteger('service_radius_miles')->nullable();
            $table->boolean('is_accepting_new_clients')->default(true);
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->unique('user_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caregiver_profiles');
    }
};
