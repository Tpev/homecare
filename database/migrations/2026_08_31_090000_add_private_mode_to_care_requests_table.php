<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('care_requests', function (Blueprint $table) {
            $table->boolean('is_private')->default(false)->after('status');
            $table->index(['is_private', 'status'], 'care_requests_visibility_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('care_requests', function (Blueprint $table) {
            $table->dropIndex('care_requests_visibility_status_index');
            $table->dropColumn('is_private');
        });
    }
};
