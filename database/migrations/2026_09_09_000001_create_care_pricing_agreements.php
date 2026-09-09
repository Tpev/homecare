<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_pricing_agreements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('family_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('caregiver_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('source_booking_id')->constrained('care_bookings')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('family_care_rate_cents');
            $table->unsignedInteger('family_processing_fee_rate_cents')->default(0);
            $table->unsignedInteger('caregiver_gross_rate_cents');
            $table->string('caregiver_fee_policy');
            $table->text('reason');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['family_account_id', 'caregiver_user_id'], 'care_pricing_agreement_pair_unique');
        });

        Schema::table('care_bookings', function (Blueprint $table): void {
            $table->foreignId('pricing_agreement_id')->nullable()->constrained('care_pricing_agreements')->restrictOnDelete();
        });
        Schema::table('care_booking_payments', function (Blueprint $table): void {
            $table->foreignId('pricing_agreement_id')->nullable()->constrained('care_pricing_agreements')->restrictOnDelete();
            $table->string('caregiver_fee_policy')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('care_booking_payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pricing_agreement_id');
            $table->dropColumn('caregiver_fee_policy');
        });
        Schema::table('care_bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pricing_agreement_id');
        });
        Schema::dropIfExists('care_pricing_agreements');
    }
};
