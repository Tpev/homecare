<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_key', 100);
            $table->string('channel', 20);
            $table->string('status', 32)->default('sent');
            $table->string('notifiable_type')->nullable();
            $table->unsignedBigInteger('notifiable_id')->nullable();
            $table->string('dedupe_key')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['event_key', 'channel'], 'notif_delivery_event_channel_idx');
            $table->index(['notifiable_type', 'notifiable_id'], 'notif_delivery_subject_idx');
            $table->unique(['user_id', 'dedupe_key'], 'notif_delivery_user_dedupe_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_notification_deliveries');
    }
};
