<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caregiver_certifications', function (Blueprint $table): void {
            $table->index(
                ['caregiver_certification_type_id', 'verification_status', 'expires_at', 'caregiver_profile_id'],
                'cg_cert_type_status_exp_profile_ix',
            );
        });
    }

    public function down(): void
    {
        Schema::table('caregiver_certifications', function (Blueprint $table): void {
            $table->dropIndex('cg_cert_type_status_exp_profile_ix');
        });
    }
};
