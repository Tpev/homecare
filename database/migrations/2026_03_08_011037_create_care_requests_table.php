<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('additional_info')->nullable();
            $table->string('status')->default('draft'); // draft, open, filled, cancelled, expired
            $table->decimal('budget_min', 8, 2)->nullable();
            $table->decimal('budget_max', 8, 2)->nullable();
            $table->dateTime('requested_start_at')->nullable();
            $table->dateTime('requested_end_at')->nullable();
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('city');
            $table->string('state', 2);
            $table->string('zip', 15);
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->timestamps();

            $table->index(['status', 'requested_start_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_requests');
    }
};
