<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('family')->after('password');
            $table->string('phone', 30)->nullable()->after('role');
            $table->string('city')->nullable()->after('phone');
            $table->string('state', 2)->nullable()->after('city');
            $table->timestamp('onboarding_completed_at')->nullable()->after('remember_token');
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn(['role', 'phone', 'city', 'state', 'onboarding_completed_at']);
        });
    }
};
