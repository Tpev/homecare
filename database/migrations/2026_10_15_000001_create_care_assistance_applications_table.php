<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_assistance_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('email');
            $table->string('phone', 30);
            $table->string('relationship', 20);
            $table->string('city', 120);
            $table->string('zip', 10);
            $table->json('care_needs');
            $table->string('weekly_hours', 20);
            $table->string('start_timeframe', 20);
            $table->text('hardship_details');
            $table->timestamp('consented_at');
            $table->string('status', 30)->default('new');
            $table->text('internal_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_assistance_applications');
    }
};
