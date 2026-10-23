<?php

use App\Services\Messaging\TwilioSmsClient;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('sms_phone', 40)->nullable()->index();
        });

        DB::table('leads')->select(['id', 'phone'])->orderBy('id')->chunkById(500, function ($leads) {
            foreach ($leads as $lead) {
                $phone = TwilioSmsClient::normalizePhone($lead->phone);
                DB::table('leads')->where('id', $lead->id)->update([
                    'sms_phone' => preg_match('/^\+[1-9][0-9]{7,14}$/', $phone) ? $phone : null,
                ]);
            }
        });

        Schema::table('sms_messages', function (Blueprint $table) {
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('request_key')->nullable()->unique();
            $table->timestamp('inbound_notified_at')->nullable();
        });

        Schema::table('lead_activities', function (Blueprint $table) {
            $table->foreignId('sms_message_id')->nullable()->unique()->constrained()->nullOnDelete();
        });

        Schema::create('sms_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->text('body');
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_templates');
        Schema::table('lead_activities', function (Blueprint $table) {
            $table->dropUnique(['sms_message_id']);
            $table->dropConstrainedForeignId('sms_message_id');
        });
        Schema::table('sms_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lead_id');
            $table->dropUnique(['request_key']);
            $table->dropColumn(['request_key', 'inbound_notified_at']);
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['sms_phone']);
            $table->dropColumn('sms_phone');
        });
    }
};
