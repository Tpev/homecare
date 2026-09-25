<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_notification_deliveries', function (Blueprint $table): void {
            $table->index(['channel', 'status', 'created_at'], 'notification_pending_email_idx');
            $table->index(['user_id', 'channel', 'notifiable_type', 'notifiable_id', 'event_key', 'status'], 'notification_thread_email_idx');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_notification_deliveries', function (Blueprint $table): void {
            $table->dropIndex('notification_pending_email_idx');
            $table->dropIndex('notification_thread_email_idx');
        });
    }
};
