<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funnel_events', function (Blueprint $table) {
            $table->id();
            $table->string('event', 80);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role', 20)->nullable();
            $table->string('entity_type', 120)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['event', 'occurred_at'], 'fe_event_occurred_idx');
            $table->index(['entity_type', 'entity_id'], 'fe_entity_idx');
            $table->index(['user_id', 'occurred_at'], 'fe_user_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funnel_events');
    }
};
