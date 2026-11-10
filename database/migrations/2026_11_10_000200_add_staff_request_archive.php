<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('care_requests', function (Blueprint $table): void {
            $table->timestamp('staff_archived_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('care_requests', function (Blueprint $table): void {
            $table->dropColumn('staff_archived_at');
        });
    }
};
