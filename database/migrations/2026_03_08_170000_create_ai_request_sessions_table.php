<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_request_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('family_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('drafting');
            $table->json('draft_json')->nullable();
            $table->json('missing_required_json')->nullable();
            $table->unsignedTinyInteger('quality_score')->default(0);
            $table->string('model', 80)->nullable();
            $table->foreignId('published_care_request_id')->nullable()->constrained('care_requests')->nullOnDelete();
            $table->timestamp('last_ai_at')->nullable();
            $table->timestamps();

            $table->index(['family_user_id', 'status'], 'ai_req_sessions_user_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_request_sessions');
    }
};

