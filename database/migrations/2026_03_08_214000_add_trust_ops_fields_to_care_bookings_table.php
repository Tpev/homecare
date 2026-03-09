<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('care_bookings', function (Blueprint $table) {
            $table->json('agreement_snapshot')->nullable()->after('caregiver_user_id');
            $table->timestamp('family_terms_accepted_at')->nullable()->after('agreement_snapshot');
            $table->timestamp('caregiver_terms_accepted_at')->nullable()->after('family_terms_accepted_at');

            $table->decimal('check_in_lat', 10, 7)->nullable()->after('started_at');
            $table->decimal('check_in_lng', 10, 7)->nullable()->after('check_in_lat');
            $table->text('check_in_note')->nullable()->after('check_in_lng');
            $table->timestamp('heartbeat_pinged_at')->nullable()->after('check_in_note');

            $table->decimal('check_out_lat', 10, 7)->nullable()->after('completed_at');
            $table->decimal('check_out_lng', 10, 7)->nullable()->after('check_out_lat');
            $table->text('check_out_note')->nullable()->after('check_out_lng');
            $table->timestamp('timesheet_submitted_at')->nullable()->after('check_out_note');
            $table->integer('expected_minutes')->nullable()->after('timesheet_submitted_at');
            $table->integer('worked_minutes')->nullable()->after('expected_minutes');

            $table->timestamp('family_confirmed_at')->nullable()->after('worked_minutes');
            $table->timestamp('dispute_opened_at')->nullable()->after('family_confirmed_at');
            $table->foreignId('dispute_opened_by_user_id')->nullable()->constrained('users')->nullOnDelete()->after('dispute_opened_at');
            $table->text('dispute_reason')->nullable()->after('dispute_opened_by_user_id');
            $table->string('dispute_status', 20)->default('none')->after('dispute_reason');

            $table->boolean('no_show_flag')->default(false)->after('dispute_status');
            $table->boolean('late_cancel_flag')->default(false)->after('no_show_flag');

            $table->index(['status', 'family_confirmed_at'], 'cb_status_confirm_idx');
            $table->index(['dispute_status', 'dispute_opened_at'], 'cb_dispute_idx');
        });
    }

    public function down(): void
    {
        Schema::table('care_bookings', function (Blueprint $table) {
            $table->dropIndex('cb_status_confirm_idx');
            $table->dropIndex('cb_dispute_idx');
            $table->dropConstrainedForeignId('dispute_opened_by_user_id');
            $table->dropColumn([
                'agreement_snapshot',
                'family_terms_accepted_at',
                'caregiver_terms_accepted_at',
                'check_in_lat',
                'check_in_lng',
                'check_in_note',
                'heartbeat_pinged_at',
                'check_out_lat',
                'check_out_lng',
                'check_out_note',
                'timesheet_submitted_at',
                'expected_minutes',
                'worked_minutes',
                'family_confirmed_at',
                'dispute_opened_at',
                'dispute_reason',
                'dispute_status',
                'no_show_flag',
                'late_cancel_flag',
            ]);
        });
    }
};
