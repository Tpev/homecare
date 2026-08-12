<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->string('source', 30)->default('support_center')->after('family_visibility');
            $table->string('origin_route', 160)->nullable()->after('source');
            $table->string('origin_path', 1024)->nullable()->after('origin_route');
            $table->timestamp('claimed_at')->nullable()->after('assigned_admin_id');
            $table->uuid('initial_client_message_id')->nullable()->after('description')->unique();

            $table->index(['source', 'status', 'last_public_message_at'], 'st_source_status_activity_idx');
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropIndex('st_source_status_activity_idx');
            $table->dropUnique(['initial_client_message_id']);
            $table->dropColumn([
                'source',
                'origin_route',
                'origin_path',
                'claimed_at',
                'initial_client_message_id',
            ]);
        });
    }
};
