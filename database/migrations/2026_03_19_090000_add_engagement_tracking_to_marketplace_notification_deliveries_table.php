<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_notification_deliveries', function (Blueprint $table): void {
            $table->unsignedInteger('open_count')->default(0)->after('sent_at');
            $table->unsignedInteger('click_count')->default(0)->after('open_count');
            $table->timestamp('opened_at')->nullable()->after('click_count');
            $table->timestamp('clicked_at')->nullable()->after('opened_at');

            $table->index(['event_key', 'channel', 'sent_at'], 'notif_delivery_event_channel_sent_idx');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_notification_deliveries', function (Blueprint $table): void {
            $table->dropIndex('notif_delivery_event_channel_sent_idx');
            $table->dropColumn(['open_count', 'click_count', 'opened_at', 'clicked_at']);
        });
    }
};

