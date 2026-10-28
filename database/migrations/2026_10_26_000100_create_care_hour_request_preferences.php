<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_hour_request_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_request_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('care_hour_account_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('enabled')->default(false);
            $table->foreignId('selected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_hour_request_preferences');
    }
};
