<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caregiver_rosters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('care_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->text('context')->nullable();
            $table->string('urgency', 20)->default('normal');
            $table->string('status', 24)->default('queued')->index();
            $table->string('request_fingerprint', 64);
            $table->text('summary')->nullable();
            $table->json('questions')->nullable();
            $table->json('coverage')->nullable();
            $table->json('usage')->nullable();
            $table->json('generation_data')->nullable();
            $table->string('model')->nullable();
            $table->string('prompt_version', 64)->default('care-roster-v1');
            $table->timestamp('generated_at')->nullable();
            $table->uuid('review_token')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users');
            $table->text('error')->nullable();
            $table->timestamps();
        });
        Schema::create('caregiver_roster_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('caregiver_roster_id')->constrained()->cascadeOnDelete();
            $table->foreignId('caregiver_user_id')->constrained('users');
            $table->unsignedInteger('rank');
            $table->string('action', 24);
            $table->json('assessment');
            $table->json('evidence');
            $table->text('invitation_body');
            $table->text('sms_body');
            $table->boolean('selected')->default(false);
            $table->boolean('send_sms')->default(false);
            $table->string('review_fingerprint', 64)->nullable();
            $table->uuid('sms_request_key')->unique();
            $table->string('dispatch_status', 32)->default('draft');
            $table->text('dispatch_note')->nullable();
            $table->foreignId('care_request_invitation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sms_message_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['caregiver_roster_id', 'caregiver_user_id'], 'roster_caregiver_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caregiver_roster_entries');
        Schema::dropIfExists('caregiver_rosters');
    }
};
