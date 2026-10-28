<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_hour_gift_admin_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('care_hour_purchase_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500);
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('care_hour_gift_admin_checks')->exists()) {
            throw new RuntimeException('Care-hour gift checks exist; preserve the audit history.');
        }
        Schema::dropIfExists('care_hour_gift_admin_checks');
    }
};
