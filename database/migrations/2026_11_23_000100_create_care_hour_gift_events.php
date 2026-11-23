<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_hour_gift_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event', 40);
            $table->string('dedupe_key', 64)->unique();
            $table->uuid('visitor_id')->nullable();
            $table->uuid('verification_id')->nullable();
            $table->foreignId('care_hour_purchase_id')->nullable()->constrained()->nullOnDelete();
            // All analytics timestamps are UTC, independent of the application's timezone.
            $table->timestamp('occurred_at');
            $table->unsignedInteger('amount_cents')->nullable();
            $table->unsignedSmallInteger('hours')->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('slack_status', 20)->nullable();
            $table->unsignedInteger('slack_attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('sending_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
            $table->index(['event', 'occurred_at']);
            $table->index(['visitor_id', 'event', 'occurred_at']);
            $table->index(['slack_status', 'available_at']);
        });
        DB::table('care_hour_gift_events')->insert([
            'event' => 'tracking_started', 'dedupe_key' => hash('sha256', 'gift-tracking-started'),
            'occurred_at' => now('UTC')->format('Y-m-d H:i:s'), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('care_hour_gift_events');
    }
};
