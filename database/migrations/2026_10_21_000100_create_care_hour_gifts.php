<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('care_hour_purchases', function (Blueprint $table): void {
            $table->unsignedBigInteger('care_hour_account_id')->nullable()->change();
            $table->unsignedBigInteger('family_account_id')->nullable()->change();
        });
        Schema::create('care_hour_gifts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('care_hour_purchase_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedInteger('welcome_bonus_minutes')->default(0);
            $table->string('code_hash', 64)->nullable()->unique();
            $table->text('encrypted_code')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->foreignId('claimed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable()->index();
            $table->string('claim_quote_key', 64)->nullable();
            $table->timestamps();
        });
        Schema::create('care_hour_gift_verifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('care_hour_purchase_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('email');
            $table->string('name', 120);
            $table->string('code_hash');
            $table->string('token_hash', 64)->nullable()->unique();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Do not erase purchased, unclaimed entitlements to restore the old NOT NULL shape.
        if (\Illuminate\Support\Facades\DB::table('care_hour_gifts')->exists()) {
            throw new RuntimeException('Care-hour gifts exist; preserve their payment and claim records.');
        }
        Schema::dropIfExists('care_hour_gift_verifications');
        Schema::dropIfExists('care_hour_gifts');
        Schema::table('care_hour_purchases', function (Blueprint $table): void {
            $table->unsignedBigInteger('care_hour_account_id')->nullable(false)->change();
            $table->unsignedBigInteger('family_account_id')->nullable(false)->change();
        });
    }
};
