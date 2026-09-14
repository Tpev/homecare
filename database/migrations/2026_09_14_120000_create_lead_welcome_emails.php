<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('family_acquisition_settings', function (Blueprint $table) {
            $table->boolean('welcome_email_enabled')->default(false);
            $table->string('welcome_email_subject')->nullable();
            $table->text('welcome_email_body')->nullable();
            $table->string('welcome_email_reply_to')->nullable();
        });

        Schema::create('lead_welcome_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('email')->nullable();
            $table->string('dedupe_key', 64)->nullable()->unique();
            $table->string('status')->index();
            $table->string('reason')->nullable();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('reply_to')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('previewed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->foreignId('account_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('account_created_at')->nullable();
            $table->timestamp('account_linked_at')->nullable();
            $table->foreignId('care_request_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('request_posted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_welcome_emails');
        Schema::table('family_acquisition_settings', fn (Blueprint $table) => $table->dropColumn([
            'welcome_email_enabled', 'welcome_email_subject', 'welcome_email_body', 'welcome_email_reply_to',
        ]));
    }
};
