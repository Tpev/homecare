<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_arrangements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_care_request_id')->unique()->constrained('care_requests');
            $table->foreignId('family_account_id')->nullable()->constrained('family_accounts');
            $table->foreignId('family_user_id')->constrained('users');
            $table->string('title');
            $table->json('schedule_slots');
            $table->json('exceptions')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('timezone');
            $table->string('status')->default('active');
            $table->timestamps();
        });
        Schema::table('care_requests', fn (Blueprint $table) => $table->foreignId('care_arrangement_id')->nullable()->constrained('care_arrangements'));
        Schema::table('care_plans', fn (Blueprint $table) => $table->foreignId('care_arrangement_id')->nullable()->constrained('care_arrangements'));
        Schema::table('care_request_applications', function (Blueprint $table) {
            $table->json('selected_days')->nullable();
            $table->json('offered_schedule')->nullable();
            $table->string('schedule_fingerprint', 64)->nullable();
            $table->unsignedInteger('offer_version')->default(1);
        });
        Schema::create('care_arrangement_needs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_arrangement_id')->constrained('care_arrangements');
            $table->string('need_key');
            $table->string('kind')->default('regular');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status')->default('needed');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['care_arrangement_id', 'need_key'], 'care_need_identity_unique');
            $table->index(['care_arrangement_id', 'starts_at']);
        });
        Schema::table('care_bookings', fn (Blueprint $table) => $table->foreignId('care_arrangement_need_id')->nullable()->constrained('care_arrangement_needs'));
        Schema::table('care_plan_schedule_changes', fn (Blueprint $table) => $table->foreignId('care_arrangement_need_id')->nullable()->constrained('care_arrangement_needs'));
    }

    public function down(): void
    {
        Schema::table('care_plan_schedule_changes', fn (Blueprint $table) => $table->dropConstrainedForeignId('care_arrangement_need_id'));
        Schema::table('care_bookings', fn (Blueprint $table) => $table->dropConstrainedForeignId('care_arrangement_need_id'));
        Schema::dropIfExists('care_arrangement_needs');
        Schema::table('care_request_applications', fn (Blueprint $table) => $table->dropColumn(['selected_days', 'offered_schedule', 'schedule_fingerprint', 'offer_version']));
        Schema::table('care_plans', fn (Blueprint $table) => $table->dropConstrainedForeignId('care_arrangement_id'));
        Schema::table('care_requests', fn (Blueprint $table) => $table->dropConstrainedForeignId('care_arrangement_id'));
        Schema::dropIfExists('care_arrangements');
    }
};
