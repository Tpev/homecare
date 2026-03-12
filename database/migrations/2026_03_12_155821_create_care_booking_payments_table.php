<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_booking_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_booking_id')->constrained('care_bookings')->cascadeOnDelete();
            $table->foreignId('family_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('caregiver_user_id')->constrained('users')->cascadeOnDelete();

            $table->string('status', 32)->default('draft');
            $table->string('currency', 3)->default('usd');

            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_payment_method_id')->nullable();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('stripe_transfer_id')->nullable();

            $table->unsignedInteger('amount_authorized_cents')->nullable();
            $table->unsignedInteger('amount_captured_cents')->nullable();
            $table->unsignedInteger('platform_fee_cents')->nullable();
            $table->unsignedInteger('caregiver_amount_cents')->nullable();

            $table->timestamp('authorization_expires_at')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('transferred_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('care_booking_id', 'cbp_booking_uq');
            $table->unique('stripe_payment_intent_id', 'cbp_payment_intent_uq');
            $table->unique('stripe_transfer_id', 'cbp_transfer_uq');
            $table->index(['family_user_id', 'status'], 'cbp_family_status_idx');
            $table->index(['caregiver_user_id', 'status'], 'cbp_caregiver_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_booking_payments');
    }
};
