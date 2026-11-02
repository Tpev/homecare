<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('access_revoked_at')->nullable()->index();
        });

        Schema::create('staff_access_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 10);
            $table->string('value');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('blocked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->timestamps();
            $table->unique(['kind', 'value']);
        });
    }

    public function down(): void
    {
        // Revocations are security records. Rolling back must not silently restore access.
        throw new RuntimeException('Staff access revocations must be retained. Use a forward migration.');
    }
};
