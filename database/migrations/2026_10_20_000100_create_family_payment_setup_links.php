<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('family_accounts', function (Blueprint $table): void {
            $table->string('payment_method_status', 16)->nullable()->index();
            $table->json('payment_method_summary')->nullable();
            $table->timestamp('payment_method_checked_at')->nullable();
            $table->timestamp('payment_method_refresh_attempted_at')->nullable()->index();
            $table->unsignedInteger('payment_method_snapshot_version')->default(0);
        });

        Schema::create('family_payment_setup_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('family_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->text('token');
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->string('consent_version')->nullable();
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_checkout_session_id')->nullable()->unique();
            $table->text('stripe_checkout_url')->nullable();
            $table->timestamp('stripe_checkout_expires_at')->nullable();
            $table->unsignedInteger('checkout_attempt')->default(0);
            $table->string('stripe_setup_intent_id')->nullable()->unique();
            $table->timestamps();
            $table->index(['family_account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_payment_setup_links');
        Schema::table('family_accounts', function (Blueprint $table): void {
            $table->dropColumn(['payment_method_status', 'payment_method_summary', 'payment_method_checked_at', 'payment_method_refresh_attempted_at', 'payment_method_snapshot_version']);
        });
    }
};
