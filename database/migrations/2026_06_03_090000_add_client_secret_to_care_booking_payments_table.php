<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('care_booking_payments', function (Blueprint $table): void {
            $table->text('stripe_payment_intent_client_secret')->nullable()->after('stripe_payment_intent_id');
        });
    }

    public function down(): void
    {
        Schema::table('care_booking_payments', function (Blueprint $table): void {
            $table->dropColumn('stripe_payment_intent_client_secret');
        });
    }
};
