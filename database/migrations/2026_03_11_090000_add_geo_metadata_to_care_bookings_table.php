<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('care_bookings', function (Blueprint $table) {
            $table->decimal('check_in_accuracy_meters', 8, 2)->nullable()->after('check_in_lng');
            $table->string('check_in_source', 32)->nullable()->after('check_in_accuracy_meters');
            $table->decimal('check_out_accuracy_meters', 8, 2)->nullable()->after('check_out_lng');
            $table->string('check_out_source', 32)->nullable()->after('check_out_accuracy_meters');
        });
    }

    public function down(): void
    {
        Schema::table('care_bookings', function (Blueprint $table) {
            $table->dropColumn([
                'check_in_accuracy_meters',
                'check_in_source',
                'check_out_accuracy_meters',
                'check_out_source',
            ]);
        });
    }
};

