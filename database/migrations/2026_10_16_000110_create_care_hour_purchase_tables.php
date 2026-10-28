<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_hour_contribution_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('care_hour_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('display_name', 120);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
        Schema::create('care_hour_contribution_verifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('care_hour_contribution_link_id')->constrained(indexName: 'ch_contribution_verifications_link_fk')->restrictOnDelete();
            $table->string('email');
            $table->string('name', 120);
            $table->string('code_hash');
            $table->string('token_hash', 64)->nullable()->unique();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamps();
        });
        Schema::create('care_hour_purchases', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('request_key', 64)->unique();
            $table->foreignId('care_hour_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('family_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('buyer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('care_hour_contribution_link_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('buyer_email');
            $table->string('buyer_name', 120);
            $table->unsignedInteger('paid_minutes');
            $table->unsignedInteger('bonus_minutes')->default(0);
            $table->unsignedInteger('total_minutes');
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('usd');
            $table->string('status', 32)->default('pending')->index();
            $table->string('terms_version', 64);
            $table->json('quote');
            $table->timestamp('terms_accepted_at');
            $table->string('stripe_checkout_session_id')->nullable()->unique();
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->string('stripe_charge_id')->nullable()->unique();
            $table->text('checkout_url')->nullable();
            $table->text('success_url');
            $table->text('cancel_url');
            $table->boolean('livemode')->nullable();
            $table->unsignedInteger('processing_fee_cents')->nullable();
            $table->json('fee_components')->nullable();
            $table->unsignedInteger('amount_reversed_cents')->default(0);
            $table->string('dispute_id')->nullable()->index();
            $table->string('dispute_status', 32)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('last_reconciled_at')->nullable();
            $table->timestamp('checkout_requested_at')->nullable();
            $table->string('correction_state', 24)->nullable();
            $table->string('correction_refund_id')->nullable()->unique();
            $table->foreignId('corrected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('correction_reason')->nullable();
            $table->timestamp('correction_requested_at')->nullable();
            $table->timestamp('corrected_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
        Schema::create('care_hour_welcome_claims', function (Blueprint $table): void {
            $table->foreignId('care_hour_account_id')->primary()->constrained()->restrictOnDelete();
            $table->foreignId('care_hour_purchase_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 24)->default('available');
            $table->timestamps();
        });
        Schema::create('care_hour_purchase_notices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('care_hour_purchase_id')->constrained()->restrictOnDelete();
            $table->string('kind', 32);
            $table->string('recipient');
            $table->string('dedupe_key', 100)->unique();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_hour_purchase_notices');
        Schema::dropIfExists('care_hour_welcome_claims');
        Schema::dropIfExists('care_hour_purchases');
        Schema::dropIfExists('care_hour_contribution_verifications');
        Schema::dropIfExists('care_hour_contribution_links');
    }
};
