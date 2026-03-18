<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_view_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_name', 120)->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('anon_id')->nullable()->index();
            $table->string('url', 2048);
            $table->string('referrer', 2048)->nullable();
            $table->string('utm_source', 255)->nullable();
            $table->string('utm_medium', 255)->nullable();
            $table->string('utm_campaign', 255)->nullable();
            $table->string('utm_term', 255)->nullable();
            $table->string('utm_content', 255)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['event_name', 'created_at'], 'pve_name_created_idx');
            $table->index(['user_id', 'created_at'], 'pve_user_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_view_events');
    }
};

