<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('contact_role')->index();
            $table->timestamp('first_call_at')->nullable()->after('submitted_at')->index();
            $table->timestamp('first_connected_at')->nullable()->after('first_call_at')->index();
            $table->unsignedTinyInteger('call_attempt_count')->default(0)->after('first_connected_at')->index();
            $table->timestamp('do_not_contact_at')->nullable()->after('converted_at')->index();
        });

        Schema::create('marketing_spend_daily', function (Blueprint $table) {
            $table->id();
            $table->date('spend_date')->index();
            $table->string('channel', 40)->index();
            $table->string('campaign_id', 120)->index();
            $table->string('campaign_name');
            $table->string('ad_set_name')->nullable();
            $table->string('ad_name')->nullable();
            $table->unsignedBigInteger('spend_cents')->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->timestamps();

            $table->unique(['spend_date', 'channel', 'campaign_id'], 'marketing_spend_daily_campaign_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_spend_daily');

        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'submitted_at',
                'first_call_at',
                'first_connected_at',
                'call_attempt_count',
                'do_not_contact_at',
            ]);
        });
    }
};
