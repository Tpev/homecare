<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicatePlanSource = DB::table('care_plans')
            ->whereNotNull('source_care_request_id')
            ->select('source_care_request_id')
            ->groupBy('source_care_request_id')
            ->havingRaw('COUNT(*) > 1')
            ->first();
        if ($duplicatePlanSource) {
            throw new RuntimeException(
                'Regular-care migration stopped: source request #'.$duplicatePlanSource->source_care_request_id.' has multiple care plans. Resolve it before deploying.'
            );
        }

        $duplicateOccurrence = DB::table('care_bookings')
            ->whereNotNull('care_plan_id')
            ->whereNotNull('scheduled_start_at')
            ->select(['care_plan_id', 'scheduled_start_at'])
            ->groupBy(['care_plan_id', 'scheduled_start_at'])
            ->havingRaw('COUNT(*) > 1')
            ->first();
        if ($duplicateOccurrence) {
            throw new RuntimeException(
                'Regular-care migration stopped: care plan #'.$duplicateOccurrence->care_plan_id.' has duplicate bookings at '.$duplicateOccurrence->scheduled_start_at.'. Resolve them before deploying.'
            );
        }

        Schema::table('care_plans', function (Blueprint $table) {
            $table->string('timezone', 64)->default('America/New_York')->after('ends_on');
            $table->date('pause_starts_on')->nullable()->after('timezone');
            $table->date('resumes_on')->nullable()->after('pause_starts_on');
            $table->unsignedInteger('schedule_version')->default(1)->after('resumes_on');
            $table->unique('source_care_request_id', 'care_plans_source_request_unique');
        });

        Schema::table('care_requests', function (Blueprint $table) {
            $table->boolean('is_system_generated')->default(false)->after('care_plan_id');
            $table->index(['is_system_generated', 'status'], 'care_requests_system_status_idx');
        });

        Schema::table('care_bookings', function (Blueprint $table) {
            $table->string('occurrence_key', 160)->nullable()->after('care_plan_id');
            $table->string('plan_visit_kind', 24)->nullable()->after('occurrence_key');
            $table->unsignedInteger('plan_schedule_version')->nullable()->after('plan_visit_kind');
            $table->timestamp('check_in_override_at')->nullable()->after('caregiver_terms_accepted_at');
            $table->foreignId('check_in_override_by_user_id')
                ->nullable()
                ->after('check_in_override_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('check_in_override_reason')->nullable()->after('check_in_override_by_user_id');

            $table->unique('occurrence_key', 'care_bookings_occurrence_key_unique');
            $table->unique(
                ['care_plan_id', 'scheduled_start_at'],
                'care_bookings_plan_start_unique'
            );
            $table->index(
                ['care_plan_id', 'scheduled_start_at', 'status'],
                'care_bookings_plan_start_status_idx'
            );
        });

        Schema::create('care_plan_schedule_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_plan_id')->constrained('care_plans')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('responded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 32);
            $table->string('status', 24)->default('pending');
            $table->date('effective_on')->nullable();
            $table->json('current_schedule')->nullable();
            $table->json('proposed_schedule');
            $table->text('note')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['care_plan_id', 'status'], 'care_plan_changes_plan_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_plan_schedule_changes');

        Schema::table('care_requests', function (Blueprint $table) {
            $table->dropIndex('care_requests_system_status_idx');
            $table->dropColumn('is_system_generated');
        });

        Schema::table('care_bookings', function (Blueprint $table) {
            $table->dropIndex('care_bookings_plan_start_status_idx');
            $table->dropUnique('care_bookings_plan_start_unique');
            $table->dropUnique('care_bookings_occurrence_key_unique');
            $table->dropConstrainedForeignId('check_in_override_by_user_id');
            $table->dropColumn([
                'occurrence_key',
                'plan_visit_kind',
                'plan_schedule_version',
                'check_in_override_at',
                'check_in_override_reason',
            ]);
        });

        Schema::table('care_plans', function (Blueprint $table) {
            $table->dropUnique('care_plans_source_request_unique');
            $table->dropColumn([
                'timezone',
                'pause_starts_on',
                'resumes_on',
                'schedule_version',
            ]);
        });
    }
};
