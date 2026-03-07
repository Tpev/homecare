<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();

            // Which landing funnel this came from
            $table->string('lead_type', 30)->index(); // agency, caregiver, family, general

            // Common searchable fields
            $table->string('name')->nullable()->index();     // contactName / fullName
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable()->index();

            $table->string('company')->nullable()->index();  // agencyName
            $table->string('location')->nullable()->index(); // location / serviceArea
            $table->string('zip')->nullable()->index();

            // Full payload (all fields from the landing form)
            $table->json('data')->nullable();

            // Basic pipeline
            $table->string('status', 30)->default('new')->index(); // new/contacted/qualified/closed

            // Tracking / metadata
            $table->string('source_url')->nullable();
            $table->string('referrer_url')->nullable();
            $table->string('ip')->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};