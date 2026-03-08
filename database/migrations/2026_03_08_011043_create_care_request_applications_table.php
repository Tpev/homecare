<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_request_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('caregiver_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('applied'); // applied, shortlisted, rejected, hired, withdrawn, not_selected
            $table->decimal('proposed_rate', 8, 2)->nullable();
            $table->text('cover_note')->nullable();
            $table->timestamps();

            // Explicit short names keep MySQL identifier length below 64 chars.
            $table->unique(['care_request_id', 'caregiver_user_id'], 'cr_app_req_cg_uniq');
            $table->index(['status', 'created_at'], 'cr_app_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_request_applications');
    }
};
