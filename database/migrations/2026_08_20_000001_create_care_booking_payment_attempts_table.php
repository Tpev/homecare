<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_booking_payment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('care_booking_payment_id')->constrained('care_booking_payments')->cascadeOnDelete();
            $table->foreignId('care_booking_id')->constrained('care_bookings')->cascadeOnDelete();
            $table->foreignId('family_account_id')->nullable()->constrained('family_accounts')->restrictOnDelete();
            $table->unsignedBigInteger('care_booking_time_correction_id')->nullable();
            $table->foreign('care_booking_time_correction_id', 'cbpa_time_correction_fk')
                ->references('id')
                ->on('care_booking_time_corrections')
                ->nullOnDelete();
            $table->string('purpose', 32)->default('booking_authorization');
            $table->string('revision_key', 191);
            $table->string('authorization_key', 64);
            $table->string('stripe_payment_intent_id')->unique('cbpa_payment_intent_uq');
            $table->string('stripe_payment_method_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->unsignedInteger('amount_cents')->default(0);
            $table->string('currency', 3)->default('usd');
            $table->string('status', 32)->default('authorization_required');
            $table->boolean('is_active')->default(true);
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->timestamps();

            $table->unique(['care_booking_payment_id', 'authorization_key'], 'cbpa_payment_auth_key_uq');
            $table->index(['care_booking_id', 'is_active'], 'cbpa_booking_active_idx');
            $table->index(['family_account_id', 'status'], 'cbpa_family_status_idx');
        });

        DB::table('care_booking_payments')
            ->whereNotNull('stripe_payment_intent_id')
            ->orderBy('id')
            ->chunkById(200, function ($payments): void {
                $rows = [];
                foreach ($payments as $payment) {
                    $intentId = (string) $payment->stripe_payment_intent_id;
                    $revisionKey = 'legacy:payment:'.$payment->id;
                    $rows[] = [
                        'care_booking_payment_id' => $payment->id,
                        'care_booking_id' => $payment->care_booking_id,
                        'family_account_id' => $payment->family_account_id,
                        'care_booking_time_correction_id' => null,
                        'purpose' => 'legacy_authorization',
                        'revision_key' => $revisionKey,
                        'authorization_key' => hash('sha256', $revisionKey.'|'.$intentId),
                        'stripe_payment_intent_id' => $intentId,
                        'stripe_payment_method_id' => $payment->stripe_payment_method_id,
                        'client_secret' => $payment->stripe_payment_intent_client_secret,
                        'amount_cents' => (int) ($payment->amount_authorized_cents ?? 0),
                        'currency' => (string) ($payment->currency ?: 'usd'),
                        'status' => (string) $payment->status,
                        'is_active' => true,
                        'last_error' => $payment->last_error,
                        'metadata' => json_encode(['backfilled' => true]),
                        'authorized_at' => $payment->authorized_at,
                        'captured_at' => $payment->captured_at,
                        'canceled_at' => null,
                        'superseded_at' => null,
                        'created_at' => $payment->created_at,
                        'updated_at' => $payment->updated_at,
                    ];
                }

                if ($rows !== []) {
                    DB::table('care_booking_payment_attempts')->insertOrIgnore($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_booking_payment_attempts');
    }
};
