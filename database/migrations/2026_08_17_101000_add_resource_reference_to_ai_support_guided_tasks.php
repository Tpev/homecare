<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_support_guided_tasks', function (Blueprint $table): void {
            $table->string('resource_type', 64)->nullable()->after('navigation_target_id');
            $table->unsignedBigInteger('resource_id')->nullable()->after('resource_type');
            $table->index(['resource_type', 'resource_id'], 'asgt_resource_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ai_support_guided_tasks', function (Blueprint $table): void {
            $table->dropIndex('asgt_resource_idx');
            $table->dropColumn(['resource_type', 'resource_id']);
        });
    }
};
