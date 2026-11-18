<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_profiles', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->unique();
            $table->json('payment_method_summary')->nullable();
            $table->timestamp('payment_method_updated_at')->nullable();
        });
        Schema::create('partner_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('plan', 20);
            $table->foreignId('partner_profile_id')->constrained()->restrictOnDelete();
            $table->string('stripe_customer_id');
            $table->string('stripe_subscription_id')->nullable()->unique();
            $table->string('stripe_checkout_session_id')->nullable()->unique();
            $table->text('checkout_url')->nullable();
            $table->timestamp('checkout_expires_at')->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price_cents');
            $table->string('currency', 3);
            $table->string('status', 30)->default('checkout_creating')->index();
            $table->string('stripe_price_id')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('canceled_at')->nullable();
            $table->boolean('livemode')->nullable();
            $table->timestamps();
            $table->index(['partner_profile_id', 'status']);
        });
        Schema::create('partner_lead_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('partner_subscription_id')->constrained()->restrictOnDelete();
            $table->string('stripe_invoice_id')->unique();
            $table->unsignedBigInteger('purchase_once_key')->nullable()->unique();
            $table->string('stripe_payment_intent_id')->nullable()->index();
            $table->string('stripe_charge_id')->nullable()->index();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('used_quantity')->default(0);
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3);
            $table->string('invoice_number')->nullable();
            $table->text('hosted_url')->nullable();
            $table->text('pdf_url')->nullable();
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->timestamp('paid_at');
            $table->timestamp('suspended_at')->nullable();
            $table->string('suspension_reason')->nullable();
            $table->timestamps();
            $table->unique(['partner_subscription_id', 'period_start', 'period_end'], 'partner_credit_period_unique');
        });
        Schema::create('partner_payment_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_profile_id')->constrained()->restrictOnDelete();
            $table->string('stripe_charge_id')->unique();
            $table->string('reason');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_payment_holds');
        Schema::dropIfExists('partner_lead_credits');
        Schema::dropIfExists('partner_subscriptions');
        Schema::table('partner_profiles', function (Blueprint $table) {
            $table->dropUnique(['stripe_customer_id']);
            $table->dropColumn(['stripe_customer_id', 'payment_method_summary', 'payment_method_updated_at']);
        });
    }
};
