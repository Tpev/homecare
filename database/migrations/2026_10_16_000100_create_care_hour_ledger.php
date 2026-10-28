<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_hour_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('family_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('care_recipient_profile_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('display_name', 160);
            $table->string('currency', 3)->default('usd');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique('care_recipient_profile_id', 'care_hours_profile_unique');
        });
        Schema::create('care_hour_recipient_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('care_hour_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('care_recipient_id')->unique()->constrained('care_request_recipients')->restrictOnDelete();
            $table->foreignId('linked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('care_hour_lots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('care_hour_account_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('care_hour_purchase_id')->unique();
            $table->unsignedInteger('total_minutes');
            $table->unsignedInteger('paid_minutes');
            $table->unsignedInteger('bonus_minutes')->default(0);
            $table->unsignedBigInteger('processing_fee_cents')->nullable();
            $table->string('status', 20)->default('available');
            $table->string('hold_reason')->nullable();
            $table->timestamp('issued_at');
            $table->timestamps();
            $table->index(['care_hour_account_id', 'status', 'id'], 'care_hours_lots_account_status');
        });
        Schema::create('care_hour_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('care_hour_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('care_hour_lot_id')->constrained()->restrictOnDelete();
            $table->foreignId('care_booking_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('start_minute');
            $table->unsignedInteger('minutes');
            $table->string('status', 20);
            $table->timestamps();
            $table->index(['care_booking_id', 'status'], 'care_hours_allocation_booking_status');
            $table->index(['care_hour_lot_id', 'status', 'start_minute'], 'care_hours_allocation_lot_range');
        });
        Schema::create('care_hour_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('care_hour_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('care_hour_lot_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('care_booking_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('care_hour_purchase_id')->nullable()->index();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40);
            $table->unsignedInteger('minutes')->default(0);
            $table->integer('available_delta')->default(0);
            $table->integer('reserved_delta')->default(0);
            $table->integer('used_delta')->default(0);
            $table->integer('held_delta')->default(0);
            $table->string('description');
            $table->string('idempotency_key', 190)->unique();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['care_hour_account_id', 'id'], 'care_hours_ledger_account_history');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_hour_ledger_entries');
        Schema::dropIfExists('care_hour_allocations');
        Schema::dropIfExists('care_hour_lots');
        Schema::dropIfExists('care_hour_recipient_links');
        Schema::dropIfExists('care_hour_accounts');
    }
};
