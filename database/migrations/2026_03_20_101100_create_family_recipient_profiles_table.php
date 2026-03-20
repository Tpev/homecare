<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_recipient_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('full_name', 120)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 40)->nullable();
            $table->string('mobility_level', 60)->nullable();
            $table->string('relationship_to_family', 120)->nullable();
            $table->text('care_notes')->nullable();
            $table->boolean('include_third_party_contact')->default(false);
            $table->string('third_party_full_name', 120)->nullable();
            $table->string('third_party_relationship_to_recipient', 120)->nullable();
            $table->string('third_party_phone', 30)->nullable();
            $table->string('third_party_email', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_recipient_profiles');
    }
};

