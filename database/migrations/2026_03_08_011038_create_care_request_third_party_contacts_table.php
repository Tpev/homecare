<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_request_third_party_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_request_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('relationship_to_recipient');
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_request_third_party_contacts');
    }
};
