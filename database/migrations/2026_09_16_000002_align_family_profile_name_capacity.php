<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Signup accepts names up to 255 characters. Self-care must preserve that
        // full name in both profiles, including on MySQL's strict varchar columns.
        foreach (['care_recipient_profiles', 'family_recipient_profiles'] as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->string('full_name', 255)->nullable()->change());
        }
    }

    public function down(): void
    {
        // Keep the expanded capacity on rollback rather than truncate saved names.
    }
};
