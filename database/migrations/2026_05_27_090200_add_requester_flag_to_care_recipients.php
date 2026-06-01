<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('care_request_recipients', function (Blueprint $table) {
            $table->boolean('recipient_is_requester')->default(false)->after('care_request_id');
        });

        Schema::table('family_recipient_profiles', function (Blueprint $table) {
            $table->boolean('recipient_is_requester')->default(false)->after('family_user_id');
        });

        DB::table('care_request_recipients')
            ->whereRaw('LOWER(relationship_to_family) = ?', ['self'])
            ->update(['recipient_is_requester' => true]);

        DB::table('family_recipient_profiles')
            ->whereRaw('LOWER(relationship_to_family) = ?', ['self'])
            ->update(['recipient_is_requester' => true]);
    }

    public function down(): void
    {
        Schema::table('family_recipient_profiles', function (Blueprint $table) {
            $table->dropColumn('recipient_is_requester');
        });

        Schema::table('care_request_recipients', function (Blueprint $table) {
            $table->dropColumn('recipient_is_requester');
        });
    }
};
