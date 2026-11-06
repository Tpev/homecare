<?php

use App\Models\SmsMessage;
use App\Services\Messaging\CrmSmsHistory;
use App\Services\Messaging\TwilioSmsClient;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('sms_phone', 40)->nullable()->index();
        });
        DB::table('users')->whereNotNull('phone')->select(['id', 'phone'])->chunkById(500, function ($users) {
            foreach ($users as $user) {
                DB::table('users')->where('id', $user->id)->where('phone', $user->phone)
                    ->update(['sms_phone' => TwilioSmsClient::validPhone($user->phone)]);
            }
        });

        Schema::table('sms_messages', function (Blueprint $table) {
            $table->foreignId('caregiver_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::table('caregiver_crm_activities', function (Blueprint $table) {
            $table->foreignId('sms_message_id')->nullable()->unique()->constrained()->nullOnDelete();
        });

        // Recover unassigned inbox history without resending a text or alerting staff.
        SmsMessage::whereNull('lead_id')->whereNull('caregiver_user_id')->chunkById(200, function ($messages) {
            foreach ($messages as $message) {
                app(CrmSmsHistory::class)->linkByPhone($message, caregiverOnly: true);
            }
        });
    }

    public function down(): void
    {
        DB::table('caregiver_crm_activities')->whereNotNull('sms_message_id')->delete();
        Schema::table('caregiver_crm_activities', function (Blueprint $table) {
            $table->dropForeign(['sms_message_id']);
            $table->dropUnique(['sms_message_id']);
            $table->dropColumn('sms_message_id');
        });
        Schema::table('sms_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('caregiver_user_id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['sms_phone']);
            $table->dropColumn('sms_phone');
        });
    }
};
