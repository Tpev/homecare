<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('care_requests', function (Blueprint $table): void {
            $table->boolean('staff_managed')->default(false)->index();
            $table->uuid('staff_request_key')->nullable()->unique();
        });
        Schema::table('care_request_conversations', function (Blueprint $table): void {
            $table->timestamp('staff_last_read_at')->nullable();
        });
        Schema::table('care_hour_lots', function (Blueprint $table): void {
            $table->unsignedBigInteger('care_hour_purchase_id')->nullable()->change();
        });
        Schema::create('care_hour_manual_credits', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->foreignId('family_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('care_hour_lot_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('minutes');
            $table->unsignedInteger('amount_cents');
            $table->string('reference', 120);
            $table->text('reason');
            $table->timestamps();
            $table->unique(['family_account_id', 'reference'], 'manual_credit_family_reference');
        });
        Schema::create('customer_care_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('care_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 64);
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        // Financial credits and the durable silence marker must survive rollback.
        throw new RuntimeException('Customer care data cannot be rolled back. Deploy a compatible forward release.');
    }
};
