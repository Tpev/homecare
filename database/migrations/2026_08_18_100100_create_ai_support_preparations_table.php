<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_support_preparations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('support_ticket_id');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->unsignedBigInteger('family_account_id')->nullable();
            $table->string('contract_id', 80);
            $table->string('contract_version', 32);
            $table->string('state', 24)->index();
            $table->string('navigation_target_id', 120);
            $table->string('resource_type', 64)->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->longText('payload');
            $table->string('fields_hash', 64);
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->index(['actor_user_id', 'contract_id', 'state'], 'asp_actor_contract_state_idx');
            $table->foreign('support_ticket_id', 'asp_ticket_fk')->references('id')->on('support_tickets')->cascadeOnDelete();
            $table->foreign('actor_user_id', 'asp_actor_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('family_account_id', 'asp_account_fk')->references('id')->on('family_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_support_preparations');
    }
};
