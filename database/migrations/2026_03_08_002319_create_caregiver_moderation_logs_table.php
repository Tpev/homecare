<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caregiver_moderation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caregiver_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // submitted, approved, rejected, suspended, unsuspended, edited
            $table->text('note')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['caregiver_profile_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caregiver_moderation_logs');
    }
};
