<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caregiver_profiles', function (Blueprint $table) {
            $table->string('identity_verification_status', 32)
                ->default('not_started')
                ->after('identity_verified_at');
            $table->string('identity_verification_session_id', 120)
                ->nullable()
                ->after('identity_verification_status');
            $table->timestamp('identity_verification_checked_at')
                ->nullable()
                ->after('identity_verification_session_id');

            $table->index(
                ['identity_verification_status', 'status'],
                'cg_identity_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('caregiver_profiles', function (Blueprint $table) {
            $table->dropIndex('cg_identity_status_idx');
            $table->dropColumn([
                'identity_verification_status',
                'identity_verification_session_id',
                'identity_verification_checked_at',
            ]);
        });
    }
};

