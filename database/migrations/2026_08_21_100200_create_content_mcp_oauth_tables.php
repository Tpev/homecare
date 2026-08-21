<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_api_tokens', function (Blueprint $table): void {
            $table->boolean('allows_actor_delegation')->default(false)->after('abilities')->index();
        });

        Schema::create('content_mcp_oauth_clients', function (Blueprint $table): void {
            $table->id();
            $table->string('client_id', 100)->unique();
            $table->string('name', 120);
            $table->json('redirect_uris');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('content_mcp_oauth_authorization_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('content_mcp_oauth_clients')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->char('code_hash', 64)->unique();
            $table->text('redirect_uri');
            $table->json('scopes');
            $table->string('resource', 255);
            $table->string('code_challenge', 128);
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('content_mcp_oauth_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->uuid('family_id')->index();
            $table->foreignId('client_id')->constrained('content_mcp_oauth_clients')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token_prefix', 48)->unique();
            $table->char('token_hash', 64)->unique();
            $table->json('scopes');
            $table->string('resource', 255);
            $table->timestamp('expires_at')->index();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'expires_at']);
            $table->index(['client_id', 'expires_at']);
        });

        Schema::create('content_mcp_oauth_refresh_tokens', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->uuid('family_id')->index();
            $table->foreignId('client_id')->constrained('content_mcp_oauth_clients')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('access_token_id')->nullable()->constrained('content_mcp_oauth_access_tokens')->nullOnDelete();
            $table->foreignId('replaced_by_id')->nullable()->constrained('content_mcp_oauth_refresh_tokens')->nullOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->json('scopes');
            $table->string('resource', 255);
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();

            $table->index(['client_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_mcp_oauth_refresh_tokens');
        Schema::dropIfExists('content_mcp_oauth_access_tokens');
        Schema::dropIfExists('content_mcp_oauth_authorization_codes');
        Schema::dropIfExists('content_mcp_oauth_clients');

        Schema::table('content_api_tokens', function (Blueprint $table): void {
            $table->dropColumn('allows_actor_delegation');
        });
    }
};
