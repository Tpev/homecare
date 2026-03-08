<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('caregiver_profiles', 'languages')) {
            Schema::table('caregiver_profiles', function (Blueprint $table) {
                $table->dropColumn('languages');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('caregiver_profiles', 'languages')) {
            Schema::table('caregiver_profiles', function (Blueprint $table) {
                $table->string('languages')->nullable()->after('years_experience');
            });
        }
    }
};
