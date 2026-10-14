<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('sms_opt_in')->default(false);
            $table->timestamp('sms_opted_in_at')->nullable();
            $table->string('sms_opt_in_phone', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['sms_opt_in', 'sms_opted_in_at', 'sms_opt_in_phone']);
        });
    }
};
