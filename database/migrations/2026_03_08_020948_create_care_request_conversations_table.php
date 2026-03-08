<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_request_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('family_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('caregiver_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('care_request_application_id')
                ->nullable()
                ->constrained('care_request_applications')
                ->nullOnDelete();
            $table->foreignId('started_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('family_last_read_at')->nullable();
            $table->timestamp('caregiver_last_read_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->foreignId('last_message_sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['care_request_id', 'caregiver_user_id'], 'crc_req_cg_uniq');
            $table->index(['family_user_id', 'last_message_at'], 'crc_family_last_msg_idx');
            $table->index(['caregiver_user_id', 'last_message_at'], 'crc_cg_last_msg_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_request_conversations');
    }
};
