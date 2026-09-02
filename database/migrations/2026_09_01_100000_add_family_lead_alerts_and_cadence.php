<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->unsignedTinyInteger('unanswered_attempt_count')->default(0)->after('call_attempt_count')->index();
            $table->timestamp('new_lead_alerted_at')->nullable()->after('do_not_contact_at')->index();
            $table->timestamp('first_call_escalated_at')->nullable()->after('new_lead_alerted_at')->index();
        });

        Schema::create('family_acquisition_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('alerts_enabled')->default(true);
            $table->text('new_lead_alert_emails')->nullable();
            $table->text('escalation_alert_emails')->nullable();
            $table->unsignedSmallInteger('first_call_sla_minutes')->default(15);
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_acquisition_settings');

        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'unanswered_attempt_count',
                'new_lead_alerted_at',
                'first_call_escalated_at',
            ]);
        });
    }
};
