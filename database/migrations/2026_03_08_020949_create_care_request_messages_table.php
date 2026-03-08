<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_request_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_request_conversation_id')
                ->constrained('care_request_conversations')
                ->cascadeOnDelete();
            $table->foreignId('sender_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();

            $table->index(['care_request_conversation_id', 'created_at'], 'crm_conv_created_idx');
            $table->index(['sender_user_id', 'created_at'], 'crm_sender_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_request_messages');
    }
};
