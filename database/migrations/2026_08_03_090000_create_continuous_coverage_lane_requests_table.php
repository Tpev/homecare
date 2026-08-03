<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('continuous_coverage_lane_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('continuous_coverage_plan_id');
            $table->foreignId('shift_template_id');
            $table->foreignId('roster_member_id');
            $table->foreignId('caregiver_user_id');
            $table->foreignId('responded_by_user_id')->nullable();
            $table->uuid('batch_uuid');
            $table->string('status', 24)->default('pending');
            $table->timestamp('requested_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->foreign('continuous_coverage_plan_id', 'cclr_plan_fk')->references('id')->on('continuous_coverage_plans')->cascadeOnDelete();
            $table->foreign('shift_template_id', 'cclr_template_fk')->references('id')->on('continuous_coverage_shift_templates')->cascadeOnDelete();
            $table->foreign('roster_member_id', 'cclr_member_fk')->references('id')->on('continuous_coverage_roster_members')->cascadeOnDelete();
            $table->foreign('caregiver_user_id', 'cclr_caregiver_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('responded_by_user_id', 'cclr_responder_fk')->references('id')->on('users')->nullOnDelete();
            $table->unique(['shift_template_id', 'roster_member_id'], 'cclr_template_member_uniq');
            $table->index(['continuous_coverage_plan_id', 'status', 'requested_at'], 'cclr_plan_status_requested_idx');
            $table->index(['caregiver_user_id', 'status', 'requested_at'], 'cclr_caregiver_status_requested_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('continuous_coverage_lane_requests');
    }
};
