<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_plan_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('care_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 100);
            $table->text('reason')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['care_plan_id', 'event_type'], 'care_plan_events_plan_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_plan_events');
    }
};
