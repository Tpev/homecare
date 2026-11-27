<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('care_hour_lots', function (Blueprint $table): void {
            $table->foreignId('care_assistance_pricing_agreement_id')->nullable()
                ->constrained('care_pricing_agreements')->restrictOnDelete();
        });
        Schema::table('care_hour_manual_credits', function (Blueprint $table): void {
            $table->string('external_payment_reference', 120)->nullable()->unique();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Prepaid pricing restrictions must survive rollback. Deploy a compatible forward release.');
    }
};
