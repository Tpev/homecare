<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('care_arrangement_needs', fn (Blueprint $table) => $table->json('suppressed_plan_ids')->nullable());
    }

    public function down(): void
    {
        Schema::table('care_arrangement_needs', fn (Blueprint $table) => $table->dropColumn('suppressed_plan_ids'));
    }
};
