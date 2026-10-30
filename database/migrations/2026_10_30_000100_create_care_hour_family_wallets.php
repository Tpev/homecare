<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This mapping is additive. Original purchase, lot and booking references
        // stay intact; the new application groups their sources by family.
        Schema::create('care_hour_family_wallets', function (Blueprint $table): void {
            $table->foreignId('family_account_id')->primary()->constrained()->restrictOnDelete();
            $table->foreignId('care_hour_account_id')->unique()->constrained()->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // A wallet mapping can identify outstanding pooled obligations. Production
        // uses compatible forward releases; never remove this financial identity.
        throw new RuntimeException('Family prepaid wallets cannot be rolled back. Use a compatible forward release.');
    }
};
