<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereRaw('LOWER(email) = ?', ['test@test.com'])
            ->update([
                'role' => 'admin',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // This corrects production authorization data and must not demote the account on rollback.
    }
};
