<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_request_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('ai_request_session_id');
            $table->string('role', 16);
            $table->longText('content_text');
            $table->json('structured_json')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->timestamps();

            $table->foreign('ai_request_session_id')
                ->references('id')
                ->on('ai_request_sessions')
                ->cascadeOnDelete();
            $table->index(['ai_request_session_id', 'created_at'], 'ai_req_messages_session_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_request_messages');
    }
};

