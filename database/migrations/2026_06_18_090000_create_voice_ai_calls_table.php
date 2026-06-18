<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_ai_calls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('direction', 20)->default('outbound');
            $table->string('status', 32)->default('draft')->index();
            $table->string('to_phone', 40)->index();
            $table->string('from_phone', 40)->nullable();
            $table->string('twilio_call_sid', 80)->nullable()->unique();
            $table->string('twilio_account_sid', 80)->nullable()->index();
            $table->string('twilio_status', 80)->nullable();
            $table->string('current_step', 60)->default('intro');
            $table->string('gathered_name')->nullable();
            $table->string('gathered_relationship')->nullable();
            $table->string('gathered_phone', 40)->nullable();
            $table->string('gathered_location')->nullable();
            $table->string('gathered_urgency')->nullable();
            $table->string('gathered_callback_time')->nullable();
            $table->text('gathered_care_needs')->nullable();
            $table->boolean('callback_requested')->default(false);
            $table->boolean('signup_link_requested')->default(false);
            $table->json('transcript')->nullable();
            $table->longText('transcript_text')->nullable();
            $table->text('summary')->nullable();
            $table->json('raw_payload')->nullable();
            $table->json('metadata')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamps();

            $table->index(['created_at', 'status'], 'voice_ai_calls_created_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_ai_calls');
    }
};
