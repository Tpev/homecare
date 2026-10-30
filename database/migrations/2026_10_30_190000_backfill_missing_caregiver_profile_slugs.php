<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('caregiver_profiles')
            ->where(fn ($query) => $query->whereNull('slug')->orWhereRaw("TRIM(slug) = ''"))
            ->select(['id', 'user_id'])
            ->chunkById(200, function ($profiles): void {
                foreach ($profiles as $profile) {
                    // Only repair the URL; model saves can change pricing and other state.
                    $name = (string) DB::table('users')->where('id', $profile->user_id)->value('name');
                    $base = (Str::slug(Str::substr($name, 0, 180)) ?: 'caregiver').'-'.$profile->user_id;
                    $slug = $base;
                    $suffix = 2;
                    while (DB::table('caregiver_profiles')->where('slug', $slug)->where('id', '!=', $profile->id)->exists()) {
                        $slug = $base.'-'.$suffix++;
                    }
                    DB::table('caregiver_profiles')->where('id', $profile->id)
                        ->where(fn ($query) => $query->whereNull('slug')->orWhereRaw("TRIM(slug) = ''"))
                        ->update(['slug' => $slug]);
                }
            });
    }

    public function down(): void
    {
        // Keep repaired public URLs stable on rollback.
    }
};
