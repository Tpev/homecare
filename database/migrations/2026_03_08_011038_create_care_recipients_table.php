<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_request_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_request_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->string('mobility_level')->nullable();
            $table->string('relationship_to_family');
            $table->text('care_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_request_recipients');
    }
};
