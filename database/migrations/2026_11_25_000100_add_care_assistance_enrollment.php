<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('care_assistance_applications', function (Blueprint $table): void {
            $table->foreignId('family_account_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::table('care_pricing_agreements', function (Blueprint $table): void {
            // Program enrollment precedes the first caregiver and booking.
            $table->unsignedBigInteger('caregiver_user_id')->nullable()->change();
            $table->unsignedBigInteger('source_booking_id')->nullable()->change();
            $table->string('program')->nullable()->index();
            $table->foreignId('care_assistance_application_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('ended_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
        });
        foreach (['care_requests', 'care_plans', 'continuous_coverage_plans'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name): void {
                // NULL on an existing record deliberately retains its non-program terms.
                $table->foreignId('care_assistance_pricing_agreement_id')->nullable()
                    ->constrained('care_pricing_agreements', indexName: 'assistance_'.$name.'_fk')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['continuous_coverage_plans', 'care_plans', 'care_requests'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name): void {
                $table->dropForeign('assistance_'.$name.'_fk');
                $table->dropColumn('care_assistance_pricing_agreement_id');
            });
        }
        Schema::table('care_pricing_agreements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('care_assistance_application_id');
            $table->dropConstrainedForeignId('ended_by_user_id');
            $table->dropIndex(['program']);
            $table->dropColumn(['program', 'ended_at']);
            // Nullable provenance is retained so rollback cannot destroy enrollment history.
        });
        Schema::table('care_assistance_applications', fn (Blueprint $table) => $table->dropConstrainedForeignId('family_account_id'));
    }
};
