<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('business_name');
            $table->string('website', 2048)->nullable();
            $table->text('description')->nullable();
            $table->string('contact_name');
            $table->string('contact_email');
            $table->string('contact_phone', 30);
            $table->json('categories');
            $table->json('coverage_states');
            $table->string('coverage_type', 24);
            $table->text('coverage_areas')->nullable();
            $table->text('customer_notes')->nullable();
            $table->string('status', 20)->default('new')->index();
            $table->text('internal_notes')->nullable();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_profiles');
    }
};
