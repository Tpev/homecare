<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL DDL is not transactional: an earlier deployment may have
        // created this table before failing to create its former triggers.
        if (! Schema::hasTable('marketplace_statistics')) {
            Schema::create('marketplace_statistics', function (Blueprint $table): void {
                $table->string('name')->primary();
                $table->bigInteger('value')->default(0);
            });
        }

        DB::table('marketplace_statistics')->updateOrInsert(
            ['name' => 'registered_caregivers'],
            ['value' => DB::table('users')->where('role', 'caregiver')->count()],
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_statistics');
    }
};
