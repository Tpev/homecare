<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_api_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('token_prefix', 40)->unique();
            $table->char('token_hash', 64)->unique();
            $table->foreignId('actor_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('abilities');
            $table->timestamp('expires_at')->index();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['actor_user_id', 'revoked_at']);
        });

        Schema::create('content_api_idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_api_token_id')->constrained('content_api_tokens')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('blog_post_id')->nullable()->constrained('blog_posts')->nullOnDelete();
            $table->char('idempotency_key_hash', 64);
            $table->char('request_hash', 64);
            $table->string('http_method', 10);
            $table->string('route_name');
            $table->string('status', 20)->default('processing');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->longText('response_body')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->unique(
                ['content_api_token_id', 'idempotency_key_hash'],
                'content_api_idempotency_token_key_unique'
            );
            $table->index(['blog_post_id', 'created_at']);
        });

        Schema::create('content_api_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_api_token_id')->nullable()->constrained('content_api_tokens')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('blog_post_id')->nullable()->constrained('blog_posts')->nullOnDelete();
            $table->string('action', 64);
            $table->string('ability', 64)->nullable();
            $table->string('outcome', 32);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->uuid('request_id')->nullable()->index();
            $table->char('idempotency_key_hash', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->useCurrent();

            $table->index(['blog_post_id', 'occurred_at']);
            $table->index(['actor_user_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_api_audit_events');
        Schema::dropIfExists('content_api_idempotency_keys');
        Schema::dropIfExists('content_api_tokens');
    }
};
