<?php

namespace App\Services\Marketing;

use Illuminate\Support\Facades\DB;

class RegisteredCaregiverCount
{
    public function refresh(?string $connectionName = null): int
    {
        $database = DB::connection($connectionName);

        return $database->transaction(function () use ($database): int {
            // A no-op upsert acquires an exclusive lock without resetting the total.
            $database->table('marketplace_statistics')->upsert([[
                'name' => 'registered_caregivers',
                'value' => 0,
            ]], ['name'], ['name']);

            // Serialize refreshes before taking a fresh snapshot of committed accounts.
            $database->table('marketplace_statistics')
                ->where('name', 'registered_caregivers')->lockForUpdate()->first();

            $count = $database->table('users')->where('role', 'caregiver')->count();

            $database->table('marketplace_statistics')
                ->where('name', 'registered_caregivers')->update(['value' => $count]);

            return $count;
        }, 3);
    }
}
