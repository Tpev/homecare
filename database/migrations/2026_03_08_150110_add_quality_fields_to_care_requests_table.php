<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('care_requests', function (Blueprint $table) {
            $table->text('scope_of_work')->nullable()->after('additional_info');
            $table->string('time_expectations', 255)->nullable()->after('scope_of_work');
            $table->text('home_access_notes')->nullable()->after('time_expectations');
            $table->unsignedTinyInteger('preferred_response_hours')->default(12)->after('home_access_notes');
        });
    }

    public function down(): void
    {
        Schema::table('care_requests', function (Blueprint $table) {
            $table->dropColumn([
                'scope_of_work',
                'time_expectations',
                'home_access_notes',
                'preferred_response_hours',
            ]);
        });
    }
};
