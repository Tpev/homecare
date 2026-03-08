<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_request_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('family_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('caregiver_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('care_request_application_id')
                ->nullable()
                ->constrained('care_request_applications')
                ->nullOnDelete();
            $table->string('status', 20)->default('pending'); // pending, accepted, declined, expired, cancelled
            $table->text('message')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['care_request_id', 'caregiver_user_id'], 'cr_inv_req_cg_uniq');
            $table->index(['caregiver_user_id', 'status', 'expires_at'], 'cr_inv_cg_status_exp_idx');
            $table->index(['family_user_id', 'status', 'created_at'], 'cr_inv_family_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_request_invitations');
    }
};
