<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'continuous_coverage_plans',
        'continuous_coverage_roster_members',
        'continuous_coverage_shift_templates',
        'continuous_coverage_shifts',
        'continuous_coverage_replacement_cases',
        'continuous_coverage_shift_offers',
        'continuous_coverage_handoffs',
        'continuous_coverage_events',
    ];

    public function up(): void
    {
        $this->recoverInterruptedEmptyInstall();

        Schema::create('continuous_coverage_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_user_id');
            $table->foreignId('created_by_user_id')->nullable();
            $table->string('status', 32)->default('active');
            $table->string('title');
            $table->string('timezone', 64)->default('America/New_York');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('coverage_pattern', 32)->default('24_7');
            $table->unsignedSmallInteger('shift_length_minutes')->default(720);
            $table->json('weekly_schedule')->nullable();
            $table->json('recipient_snapshot');
            $table->json('address_snapshot');
            $table->json('task_snapshot')->nullable();
            $table->text('care_notes')->nullable();
            $table->decimal('hourly_rate', 8, 2);
            $table->string('replacement_confirmation_mode', 32)->default('family_confirmation');
            $table->boolean('marketplace_applications_enabled')->default(false);
            $table->timestamp('last_generated_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('family_user_id', 'ccp_family_user_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('created_by_user_id', 'ccp_created_by_user_fk')->references('id')->on('users')->nullOnDelete();
            $table->index(['family_user_id', 'status'], 'ccp_family_status_idx');
            $table->index(['status', 'starts_on', 'ends_on'], 'ccp_status_dates_idx');
            $table->index(
                ['status', 'marketplace_applications_enabled', 'starts_on'],
                'ccp_marketplace_start_idx'
            );
        });

        Schema::create('continuous_coverage_roster_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('continuous_coverage_plan_id');
            $table->foreignId('caregiver_user_id');
            $table->foreignId('invited_by_user_id')->nullable();
            $table->string('status', 32)->default('family_approved');
            $table->string('role', 24)->default('backup');
            $table->boolean('replacement_opt_in')->default(false);
            $table->json('eligible_days')->nullable();
            $table->json('eligible_shift_types')->nullable();
            $table->timestamp('family_approved_at')->nullable();
            $table->timestamp('caregiver_accepted_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->foreign('continuous_coverage_plan_id', 'ccrm_plan_fk')->references('id')->on('continuous_coverage_plans')->cascadeOnDelete();
            $table->foreign('caregiver_user_id', 'ccrm_caregiver_user_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('invited_by_user_id', 'ccrm_invited_by_user_fk')->references('id')->on('users')->nullOnDelete();
            $table->unique(['continuous_coverage_plan_id', 'caregiver_user_id'], 'ccrm_plan_caregiver_uniq');
            $table->index(['caregiver_user_id', 'status'], 'ccrm_caregiver_status_idx');
            $table->index(['continuous_coverage_plan_id', 'status', 'role'], 'ccrm_plan_status_role_idx');
        });

        Schema::create('continuous_coverage_shift_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('continuous_coverage_plan_id');
            $table->foreignId('roster_member_id')->nullable();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->boolean('spans_next_day')->default(false);
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedInteger('schedule_version')->default(1);
            $table->string('status', 24)->default('uncovered');
            $table->date('effective_from');
            $table->dateTime('effective_start_at')->nullable();
            $table->date('effective_until')->nullable();
            $table->timestamp('offered_at')->nullable();
            $table->timestamp('offer_expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamps();

            $table->foreign('continuous_coverage_plan_id', 'ccst_plan_fk')->references('id')->on('continuous_coverage_plans')->cascadeOnDelete();
            $table->foreign('roster_member_id', 'ccst_roster_member_fk')->references('id')->on('continuous_coverage_roster_members')->nullOnDelete();
            $table->index(['continuous_coverage_plan_id', 'day_of_week'], 'ccst_plan_day_idx');
            $table->index(['roster_member_id', 'status'], 'ccst_roster_status_idx');
        });

        Schema::create('continuous_coverage_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('continuous_coverage_plan_id');
            $table->foreignId('shift_template_id')->nullable();
            $table->foreignId('assigned_caregiver_user_id')->nullable();
            $table->foreignId('care_booking_id')->nullable();
            $table->foreignId('released_by_user_id')->nullable();
            $table->string('occurrence_key', 160);
            $table->string('status', 32)->default('uncovered');
            $table->dateTime('scheduled_start_at');
            $table->dateTime('scheduled_end_at');
            $table->unsignedSmallInteger('scheduled_minutes');
            $table->timestamp('caregiver_accepted_at')->nullable();
            $table->timestamp('family_confirmed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->text('release_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('continuous_coverage_plan_id', 'ccs_plan_fk')->references('id')->on('continuous_coverage_plans')->cascadeOnDelete();
            $table->foreign('shift_template_id', 'ccs_template_fk')->references('id')->on('continuous_coverage_shift_templates')->nullOnDelete();
            $table->foreign('assigned_caregiver_user_id', 'ccs_assigned_caregiver_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('care_booking_id', 'ccs_booking_fk')->references('id')->on('care_bookings')->nullOnDelete();
            $table->foreign('released_by_user_id', 'ccs_released_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->unique(['continuous_coverage_plan_id', 'occurrence_key'], 'ccs_plan_occurrence_uniq');
            $table->unique('care_booking_id', 'ccs_booking_uniq');
            $table->index(['continuous_coverage_plan_id', 'scheduled_start_at', 'status'], 'ccs_plan_start_status_idx');
            $table->index(['assigned_caregiver_user_id', 'scheduled_start_at', 'status'], 'ccs_caregiver_start_status_idx');
        });

        Schema::create('continuous_coverage_replacement_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('continuous_coverage_shift_id');
            $table->foreignId('original_caregiver_user_id')->nullable();
            $table->unsignedBigInteger('winning_offer_id')->nullable();
            $table->string('status', 32)->default('open');
            $table->text('reason')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('continuous_coverage_shift_id', 'ccrc_shift_fk')->references('id')->on('continuous_coverage_shifts')->cascadeOnDelete();
            $table->foreign('original_caregiver_user_id', 'ccrc_original_caregiver_fk')->references('id')->on('users')->nullOnDelete();
            $table->index(
                ['continuous_coverage_shift_id', 'status', 'opened_at'],
                'ccrc_shift_status_opened_idx'
            );
            $table->index(['status', 'opened_at'], 'ccrc_status_opened_idx');
        });

        Schema::create('continuous_coverage_shift_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('replacement_case_id');
            $table->foreignId('continuous_coverage_shift_id');
            $table->foreignId('roster_member_id');
            $table->foreignId('caregiver_user_id');
            $table->string('status', 24)->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->foreign('replacement_case_id', 'ccso_case_fk')->references('id')->on('continuous_coverage_replacement_cases')->cascadeOnDelete();
            $table->foreign('continuous_coverage_shift_id', 'ccso_shift_fk')->references('id')->on('continuous_coverage_shifts')->cascadeOnDelete();
            $table->foreign('roster_member_id', 'ccso_roster_member_fk')->references('id')->on('continuous_coverage_roster_members')->cascadeOnDelete();
            $table->foreign('caregiver_user_id', 'ccso_caregiver_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['replacement_case_id', 'caregiver_user_id'], 'ccso_case_caregiver_uniq');
            $table->index(['caregiver_user_id', 'status', 'expires_at'], 'ccso_caregiver_status_exp_idx');
        });

        Schema::create('continuous_coverage_handoffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('continuous_coverage_shift_id');
            $table->foreignId('caregiver_user_id');
            $table->text('notes');
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->foreign('continuous_coverage_shift_id', 'cch_shift_fk')->references('id')->on('continuous_coverage_shifts')->cascadeOnDelete();
            $table->foreign('caregiver_user_id', 'cch_caregiver_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['continuous_coverage_shift_id', 'recorded_at'], 'cch_shift_recorded_idx');
        });

        Schema::create('continuous_coverage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('continuous_coverage_plan_id');
            $table->foreignId('continuous_coverage_shift_id')->nullable();
            $table->foreignId('actor_user_id')->nullable();
            $table->string('event_type', 64);
            $table->json('payload')->nullable();
            $table->timestamp('happened_at');
            $table->timestamps();

            $table->foreign('continuous_coverage_plan_id', 'cce_plan_fk')->references('id')->on('continuous_coverage_plans')->cascadeOnDelete();
            $table->foreign('continuous_coverage_shift_id', 'cce_shift_fk')->references('id')->on('continuous_coverage_shifts')->nullOnDelete();
            $table->foreign('actor_user_id', 'cce_actor_fk')->references('id')->on('users')->nullOnDelete();
            $table->index(['continuous_coverage_plan_id', 'happened_at'], 'cce_plan_happened_idx');
            $table->index(['continuous_coverage_shift_id', 'happened_at'], 'cce_shift_happened_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('continuous_coverage_events');
        Schema::dropIfExists('continuous_coverage_handoffs');
        Schema::dropIfExists('continuous_coverage_shift_offers');
        Schema::dropIfExists('continuous_coverage_replacement_cases');
        Schema::dropIfExists('continuous_coverage_shifts');
        Schema::dropIfExists('continuous_coverage_shift_templates');
        Schema::dropIfExists('continuous_coverage_roster_members');
        Schema::dropIfExists('continuous_coverage_plans');
    }

    private function recoverInterruptedEmptyInstall(): void
    {
        $existingTables = array_values(array_filter(
            self::TABLES,
            static fn (string $table): bool => Schema::hasTable($table),
        ));

        if ($existingTables === []) {
            return;
        }

        foreach ($existingTables as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException(
                    'Continuous Coverage migration found existing data in '.$table.'. Disable the feature and review the interrupted installation manually; no tables were removed.'
                );
            }
        }

        Schema::withoutForeignKeyConstraints(function () use ($existingTables): void {
            foreach (array_reverse(self::TABLES) as $table) {
                if (in_array($table, $existingTables, true)) {
                    Schema::drop($table);
                }
            }
        });
    }
};
