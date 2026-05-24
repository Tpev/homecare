<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->string('direction', 20)->index();
            $table->string('status', 40)->default('received')->index();
            $table->string('from_phone', 40)->index();
            $table->string('to_phone', 40)->index();
            $table->text('body');
            $table->string('twilio_sid', 80)->nullable()->unique();
            $table->string('twilio_account_sid', 80)->nullable()->index();
            $table->string('twilio_status', 80)->nullable();
            $table->string('error_code', 80)->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('num_media')->default(0);
            $table->json('media')->nullable();
            $table->json('raw_payload')->nullable();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['direction', 'created_at'], 'sms_messages_direction_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
    }
};
