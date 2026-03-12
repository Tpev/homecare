<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('care_booking_payments', function (Blueprint $table): void {
            $table->string('stripe_overage_payment_intent_id')->nullable()->after('stripe_payment_intent_id');
            $table->string('stripe_last_refund_id')->nullable()->after('stripe_transfer_id');
            $table->string('stripe_last_transfer_reversal_id')->nullable()->after('stripe_last_refund_id');

            $table->unsignedInteger('amount_refunded_cents')->default(0)->after('amount_captured_cents');
            $table->unsignedInteger('amount_overage_cents')->default(0)->after('amount_refunded_cents');
            $table->unsignedInteger('overage_pending_cents')->default(0)->after('amount_overage_cents');

            $table->timestamp('reauthorized_at')->nullable()->after('authorized_at');

            $table->unique('stripe_overage_payment_intent_id', 'cbp_overage_intent_uq');
            $table->unique('stripe_last_refund_id', 'cbp_last_refund_uq');
            $table->unique('stripe_last_transfer_reversal_id', 'cbp_last_transfer_reversal_uq');
        });
    }

    public function down(): void
    {
        Schema::table('care_booking_payments', function (Blueprint $table): void {
            $table->dropUnique('cbp_overage_intent_uq');
            $table->dropUnique('cbp_last_refund_uq');
            $table->dropUnique('cbp_last_transfer_reversal_uq');

            $table->dropColumn([
                'stripe_overage_payment_intent_id',
                'stripe_last_refund_id',
                'stripe_last_transfer_reversal_id',
                'amount_refunded_cents',
                'amount_overage_cents',
                'overage_pending_cents',
                'reauthorized_at',
            ]);
        });
    }
};
