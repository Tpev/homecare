<?php

use App\Services\CareRecipientProfiles\CareRecipientProfileBackfill;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(CareRecipientProfileBackfill::class)->run();
    }

    public function down(): void
    {
        // Backfilled profile history is intentionally retained for rollback safety.
    }
};
