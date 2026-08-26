<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('care_bookings', function (Blueprint $table): void {
            $table->string('financial_reference', 32)->nullable()->after('id');
            $table->string('pricing_version', 32)->nullable()->after('financial_reference');
            $table->unsignedInteger('family_care_rate_cents')->nullable()->after('pricing_version');
            $table->unsignedInteger('family_processing_fee_rate_cents')->nullable()->after('family_care_rate_cents');
            $table->unsignedInteger('caregiver_gross_rate_cents')->nullable()->after('family_processing_fee_rate_cents');
            $table->string('caregiver_fee_policy', 64)->nullable()->after('caregiver_gross_rate_cents');
            $table->timestamp('pricing_snapshotted_at')->nullable()->after('caregiver_fee_policy');

            $table->unique('financial_reference', 'cb_financial_reference_uq');
            $table->index('pricing_version', 'cb_pricing_version_idx');
        });

        Schema::table('care_booking_payments', function (Blueprint $table): void {
            $table->string('financial_reference', 32)->nullable()->after('care_booking_id');
            $table->string('pricing_version', 32)->nullable()->after('financial_reference');
            $table->unsignedInteger('worked_minutes')->nullable()->after('pricing_version');
            $table->unsignedInteger('family_care_rate_cents')->nullable()->after('worked_minutes');
            $table->unsignedInteger('family_processing_fee_rate_cents')->nullable()->after('family_care_rate_cents');
            $table->unsignedInteger('caregiver_gross_rate_cents')->nullable()->after('family_processing_fee_rate_cents');
            $table->unsignedInteger('family_care_amount_cents')->nullable()->after('amount_captured_cents');
            $table->unsignedInteger('family_processing_fee_cents')->nullable()->after('family_care_amount_cents');
            $table->unsignedInteger('caregiver_gross_amount_cents')->nullable()->after('platform_fee_cents');
            $table->unsignedInteger('stripe_processing_fee_cents')->default(0)->after('caregiver_gross_amount_cents');
            $table->string('fee_finalization_status', 24)->nullable()->after('stripe_processing_fee_cents');
            $table->timestamp('fee_finalized_at')->nullable()->after('fee_finalization_status');
            $table->string('stripe_primary_charge_id')->nullable()->after('stripe_overage_payment_intent_id');
            $table->string('stripe_overage_charge_id')->nullable()->after('stripe_primary_charge_id');
            $table->string('stripe_transfer_group', 64)->nullable()->after('stripe_overage_charge_id');

            $table->index(['pricing_version', 'status'], 'cbp_pricing_status_idx');
            $table->index('stripe_primary_charge_id', 'cbp_primary_charge_idx');
            $table->index('stripe_overage_charge_id', 'cbp_overage_charge_idx');
        });

        Schema::create('care_booking_payment_operations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('care_booking_payment_id')->constrained('care_booking_payments')->cascadeOnDelete();
            $table->foreignId('care_booking_id')->constrained('care_bookings')->cascadeOnDelete();
            $table->foreignId('family_account_id')->nullable()->constrained('family_accounts')->restrictOnDelete();
            $table->foreignId('parent_operation_id')->nullable()->constrained('care_booking_payment_operations')->nullOnDelete();
            $table->string('financial_reference', 32);
            $table->string('type', 32);
            $table->string('status', 24)->default('pending');
            $table->unsignedInteger('amount_cents')->default(0);
            $table->string('currency', 3)->default('usd');
            $table->string('stripe_object_id')->nullable();
            $table->string('stripe_parent_object_id')->nullable();
            $table->string('idempotency_key', 191)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['type', 'stripe_object_id'], 'cbpo_type_stripe_object_uq');
            $table->unique('idempotency_key', 'cbpo_idempotency_uq');
            $table->index(['care_booking_id', 'type', 'status'], 'cbpo_booking_type_status_idx');
            $table->index(['financial_reference', 'occurred_at'], 'cbpo_reference_time_idx');
        });

        Schema::create('stripe_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('stripe_event_id')->unique('swe_event_uq');
            $table->string('type', 96);
            $table->string('object_id')->nullable();
            $table->string('connected_account_id')->nullable();
            $table->boolean('livemode')->default(false);
            $table->string('status', 24)->default('received');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->longText('payload')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status'], 'swe_type_status_idx');
            $table->index(['connected_account_id', 'created_at'], 'swe_account_created_idx');
        });

        Schema::table('caregiver_payout_items', function (Blueprint $table): void {
            $table->foreignId('care_booking_payment_id')->nullable()->after('care_booking_id')->constrained('care_booking_payments')->nullOnDelete();
            $table->string('financial_reference', 32)->nullable()->after('care_booking_payment_id');
            $table->decimal('gross_amount', 10, 2)->default(0)->after('currency');
            $table->decimal('processing_fee_amount', 10, 2)->default(0)->after('gross_amount');
            $table->json('stripe_transfer_ids')->nullable()->after('amount');

            $table->index('financial_reference', 'cpi_financial_reference_idx');
        });

        DB::table('care_bookings')->orderBy('id')->chunkById(250, function ($bookings): void {
            foreach ($bookings as $booking) {
                DB::table('care_bookings')->where('id', $booking->id)->update([
                    'financial_reference' => 'SHIFT-'.str_pad((string) $booking->id, 10, '0', STR_PAD_LEFT),
                ]);
            }
        });

        DB::table('care_booking_payments')->orderBy('id')->chunkById(250, function ($payments): void {
            foreach ($payments as $payment) {
                $reference = DB::table('care_bookings')->where('id', $payment->care_booking_id)->value('financial_reference');
                DB::table('care_booking_payments')->where('id', $payment->id)->update([
                    'financial_reference' => $reference,
                ]);
            }
        });

        DB::table('caregiver_payout_items')->orderBy('id')->chunkById(250, function ($items): void {
            foreach ($items as $item) {
                $payment = DB::table('care_booking_payments')->where('care_booking_id', $item->care_booking_id)->first();
                DB::table('caregiver_payout_items')->where('id', $item->id)->update([
                    'care_booking_payment_id' => $payment?->id,
                    'financial_reference' => $payment?->financial_reference,
                    'gross_amount' => $item->amount,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('caregiver_payout_items', function (Blueprint $table): void {
            $table->dropForeign(['care_booking_payment_id']);
            $table->dropIndex('cpi_financial_reference_idx');
            $table->dropColumn([
                'care_booking_payment_id',
                'financial_reference',
                'gross_amount',
                'processing_fee_amount',
                'stripe_transfer_ids',
            ]);
        });

        Schema::dropIfExists('stripe_webhook_events');
        Schema::dropIfExists('care_booking_payment_operations');

        Schema::table('care_booking_payments', function (Blueprint $table): void {
            $table->dropIndex('cbp_pricing_status_idx');
            $table->dropIndex('cbp_primary_charge_idx');
            $table->dropIndex('cbp_overage_charge_idx');
            $table->dropColumn([
                'financial_reference',
                'pricing_version',
                'worked_minutes',
                'family_care_rate_cents',
                'family_processing_fee_rate_cents',
                'caregiver_gross_rate_cents',
                'family_care_amount_cents',
                'family_processing_fee_cents',
                'caregiver_gross_amount_cents',
                'stripe_processing_fee_cents',
                'fee_finalization_status',
                'fee_finalized_at',
                'stripe_primary_charge_id',
                'stripe_overage_charge_id',
                'stripe_transfer_group',
            ]);
        });

        Schema::table('care_bookings', function (Blueprint $table): void {
            $table->dropUnique('cb_financial_reference_uq');
            $table->dropIndex('cb_pricing_version_idx');
            $table->dropColumn([
                'financial_reference',
                'pricing_version',
                'family_care_rate_cents',
                'family_processing_fee_rate_cents',
                'caregiver_gross_rate_cents',
                'caregiver_fee_policy',
                'pricing_snapshotted_at',
            ]);
        });
    }
};
