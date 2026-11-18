<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            // Retain the original source identity even after CRM deletion or a merge.
            $table->unsignedBigInteger('source_lead_id');
            $table->foreignId('partner_lead_credit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 64);
            $table->string('contact_name');
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->string('location')->nullable();
            $table->string('zip', 20)->nullable();
            $table->text('service_context');
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('sharing_confirmed_at');
            $table->timestamp('geography_confirmed_at');
            $table->timestamps();
            $table->unique(['source_lead_id', 'category']);
            $table->index(['partner_profile_id', 'assigned_at']);
        });

        Schema::create('partner_lead_claims', function (Blueprint $table) {
            $table->id();
            // Never cascade away an exclusivity claim when a contact/account is removed.
            $table->foreignId('partner_lead_id')->nullable()->constrained()->nullOnDelete();
            $table->string('identity_type', 12);
            $table->char('identity_hash', 64);
            $table->string('category', 64);
            $table->timestamp('created_at');
            $table->unique(['identity_type', 'identity_hash', 'category'], 'partner_lead_identity_category_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_lead_claims');
        Schema::dropIfExists('partner_leads');
    }
};
