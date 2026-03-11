<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('care_requests', function (Blueprint $table) {
            $table->timestamp('first_applicant_at')->nullable()->after('status');
            $table->timestamp('first_shortlist_at')->nullable()->after('first_applicant_at');
            $table->timestamp('first_hire_at')->nullable()->after('first_shortlist_at');

            $table->index('first_applicant_at');
            $table->index('first_hire_at');
        });
    }

    public function down(): void
    {
        Schema::table('care_requests', function (Blueprint $table) {
            $table->dropIndex(['first_applicant_at']);
            $table->dropIndex(['first_hire_at']);
            $table->dropColumn([
                'first_applicant_at',
                'first_shortlist_at',
                'first_hire_at',
            ]);
        });
    }
};
