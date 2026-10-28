<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_hour_plan_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_plan_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('care_hour_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('selected_by_user_id')->constrained('users')->restrictOnDelete();
            $table->boolean('enabled')->default(true);
            $table->timestamp('selected_at');
            $table->timestamps();
        });
        Schema::create('care_hour_booking_fundings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_booking_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('care_hour_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('selected_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status')->default('selected')->index();
            $table->unsignedInteger('planned_minutes')->default(0);
            $table->unsignedInteger('reserved_minutes')->default(0);
            $table->unsignedInteger('shortfall_minutes')->default(0);
            $table->unsignedInteger('shortfall_cents')->default(0);
            $table->unsignedInteger('revision')->default(1);
            $table->string('currency', 3)->default('usd');
            $table->timestamp('committed_at');
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
        Schema::create('care_hour_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_booking_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('care_hour_booking_funding_id')->constrained('care_hour_booking_fundings')->restrictOnDelete();
            $table->string('status')->default('pending')->index();
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedInteger('prepaid_minutes')->default(0);
            $table->unsignedInteger('card_minutes')->default(0);
            $table->unsignedInteger('amount_due_cents')->default(0);
            $table->unsignedInteger('caregiver_gross_cents')->default(0);
            $table->unsignedInteger('package_fee_cents')->nullable();
            $table->unsignedInteger('card_fee_cents')->nullable();
            $table->unsignedInteger('caregiver_net_cents')->nullable();
            $table->unsignedInteger('payable_cents')->nullable();
            $table->unsignedInteger('paid_cents')->default(0);
            $table->string('currency', 3)->default('usd');
            $table->json('allocation_snapshot')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
        Schema::create('care_hour_payment_operations', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('care_hour_booking_funding_id')->constrained('care_hour_booking_fundings', indexName: 'ch_operations_funding_fk')->restrictOnDelete();
            $table->foreignId('care_hour_settlement_id')->nullable()->constrained('care_hour_settlements')->restrictOnDelete();
            $table->string('operation_key')->unique();
            $table->string('kind')->index();
            $table->boolean('livemode')->default(false);
            $table->string('status')->default('pending')->index();
            $table->unsignedInteger('amount_cents');
            $table->unsignedInteger('captured_cents')->default(0);
            $table->unsignedInteger('refunded_cents')->default(0);
            $table->unsignedInteger('processing_fee_cents')->nullable();
            $table->string('currency', 3)->default('usd');
            $table->string('stripe_checkout_session_id')->nullable()->unique();
            $table->text('checkout_url')->nullable();
            $table->string('stripe_payment_intent_id')->nullable()->index();
            $table->string('stripe_charge_id')->nullable()->index();
            $table->string('stripe_transfer_id')->nullable()->index();
            $table->string('stripe_refund_id')->nullable()->index();
            $table->string('source_charge_id')->nullable()->index();
            $table->unsignedBigInteger('source_purchase_id')->nullable()->index();
            $table->string('destination_account_id')->nullable();
            $table->timestamp('authorization_expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_hour_payment_operations');
        Schema::dropIfExists('care_hour_settlements');
        Schema::dropIfExists('care_hour_booking_fundings');
        Schema::dropIfExists('care_hour_plan_preferences');
    }
};
