<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_welcome_emails', function (Blueprint $table) {
            $table->dropForeign(['lead_id']);
        });
        Schema::table('lead_welcome_emails', function (Blueprint $table) {
            $table->unsignedBigInteger('lead_id')->nullable()->change();
            $table->foreign('lead_id')->references('id')->on('leads')->nullOnDelete();
            $table->index('email');
        });
    }

    public function down(): void
    {
        // Keep nullable orphan reservations; discarding them would permit duplicate sends.
        Schema::table('lead_welcome_emails', function (Blueprint $table) {
            $table->dropIndex(['email']);
        });
    }
};
