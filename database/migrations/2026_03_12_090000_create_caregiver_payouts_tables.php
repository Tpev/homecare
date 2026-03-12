<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caregiver_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caregiver_user_id')->constrained('users')->cascadeOnDelete();
            $table->date('period_start_on');
            $table->date('period_end_on');
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('status', 24)->default('scheduled');
            $table->string('currency', 3)->default('USD');
            $table->decimal('gross_amount', 10, 2)->default(0);
            $table->decimal('adjustments_amount', 10, 2)->default(0);
            $table->decimal('net_amount', 10, 2)->default(0);
            $table->string('provider_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['caregiver_user_id', 'status'], 'cp_user_status_idx');
            $table->index(['caregiver_user_id', 'scheduled_for'], 'cp_user_schedule_idx');
        });

        Schema::create('caregiver_payout_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caregiver_payout_id')->constrained('caregiver_payouts')->cascadeOnDelete();
            $table->foreignId('caregiver_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('care_booking_id')->constrained('care_bookings')->cascadeOnDelete();
            $table->string('status', 24)->default('scheduled');
            $table->string('currency', 3)->default('USD');
            $table->decimal('amount', 10, 2);
            $table->timestamp('included_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('care_booking_id', 'cpi_booking_uq');
            $table->index(['caregiver_payout_id', 'status'], 'cpi_payout_status_idx');
            $table->index(['caregiver_user_id', 'status'], 'cpi_user_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caregiver_payout_items');
        Schema::dropIfExists('caregiver_payouts');
    }
};

