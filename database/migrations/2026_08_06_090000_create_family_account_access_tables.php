<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_accounts', function (Blueprint $table) {
            $table->id();
            // A member may have previously owned an empty account that was closed
            // when they joined another family, then later receive a support-assisted
            // ownership transfer. Uniqueness applies to active ownership in service
            // logic, not to immutable historical rows.
            $table->foreignId('owner_user_id')->index()->constrained('users')->restrictOnDelete();
            $table->string('stripe_customer_id')->nullable()->index();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('family_account_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('access_level', 20);
            $table->string('status', 20)->default('active');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('ended_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['family_account_id', 'user_id'], 'family_account_member_unique');
            $table->index(['user_id', 'status'], 'family_member_user_status_index');
            $table->index(['family_account_id', 'status', 'access_level'], 'family_member_account_status_level_index');
        });

        Schema::create('family_account_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('email_normalized');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('canceled_at')->nullable();
            $table->foreignId('canceled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['family_account_id', 'email_normalized'], 'family_account_invitation_email_unique');
            $table->index(['family_account_id', 'expires_at'], 'family_invitation_account_expiry_index');
        });

        Schema::create('family_conversation_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_request_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['care_request_conversation_id', 'user_id'], 'family_conversation_user_read_unique');
        });

        Schema::create('family_support_ticket_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['support_ticket_id', 'user_id'], 'family_support_ticket_user_read_unique');
        });

        Schema::create('family_account_activity_logs', function (Blueprint $table) {
            $table->id();
            // Audit history prevents destructive account deletion. Accounts are
            // closed in place so membership and actor history remains recoverable.
            $table->foreignId('family_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->foreignId('subject_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['family_account_id', 'created_at'], 'family_activity_account_created_index');
            $table->index(['action', 'created_at'], 'family_activity_action_created_index');
        });
    }

    public function down(): void
    {
        // Intentionally irreversible: membership and actor history are audit records.
    }
};
