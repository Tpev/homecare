<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('content_role', 32)->nullable()->after('role')->index();
        });

        Schema::create('media_assets', function (Blueprint $table): void {
            $table->id();
            $table->string('disk', 64)->default('public');
            $table->string('path')->unique();
            $table->string('original_filename');
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt_text')->nullable();
            $table->text('caption')->nullable();
            $table->string('credit')->nullable();
            $table->string('license')->nullable();
            $table->string('source_url')->nullable();
            $table->decimal('focal_x', 5, 4)->default(0.5000);
            $table->decimal('focal_y', 5, 4)->default(0.5000);
            $table->json('metadata')->nullable();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['mime_type', 'created_at']);
        });

        Schema::create('media_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_asset_id')->constrained()->cascadeOnDelete();
            $table->string('variant', 32);
            $table->string('disk', 64)->default('public');
            $table->string('path')->unique();
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->timestamps();
            $table->unique(['media_asset_id', 'variant']);
        });

        Schema::create('content_authors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('avatar_media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('headline')->nullable();
            $table->text('bio')->nullable();
            $table->text('credentials')->nullable();
            $table->string('profile_url')->nullable();
            $table->json('same_as')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique('user_id');
        });

        Schema::create('content_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('content_tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('blog_posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->json('content_json');
            $table->longText('body_html')->nullable();
            $table->longText('plain_text')->nullable();
            $table->json('table_of_contents')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('content_authors')->nullOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('content_authors')->nullOnDelete();
            $table->foreignId('featured_media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_for_review_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('scheduled_for')->nullable()->index();
            $table->timestamp('first_published_at')->nullable()->index();
            $table->timestamp('last_published_at')->nullable();
            $table->timestamp('content_review_due_at')->nullable()->index();
            $table->string('seo_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('social_title')->nullable();
            $table->string('social_description', 320)->nullable();
            $table->boolean('robots_index')->default(false);
            $table->string('robots_directives')->default('noindex,nofollow');
            $table->string('schema_type', 64)->default('BlogPosting');
            $table->string('content_type', 64)->default('guide');
            $table->string('locale', 16)->default('en-US');
            $table->unsignedInteger('revision_number')->default(0);
            $table->unsignedInteger('word_count')->default(0);
            $table->unsignedSmallInteger('read_minutes')->default(1);
            $table->json('editorial_checklist')->nullable();
            $table->text('review_notes')->nullable();
            $table->text('research_methodology')->nullable();
            $table->string('source_import')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'first_published_at']);
            $table->index(['robots_index', 'status']);
        });

        Schema::create('blog_post_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('blog_post_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->json('snapshot');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('change_summary')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['blog_post_id', 'revision_number']);
        });

        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->foreignId('published_revision_id')
                ->nullable()
                ->after('revision_number')
                ->constrained('blog_post_revisions')
                ->nullOnDelete();
        });

        Schema::create('blog_post_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('blog_post_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('publisher')->nullable();
            $table->string('url');
            $table->date('published_on')->nullable();
            $table->date('accessed_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['blog_post_id', 'created_at']);
        });

        Schema::create('blog_post_category', function (Blueprint $table): void {
            $table->foreignId('blog_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_category_id')->constrained()->cascadeOnDelete();
            $table->primary(['blog_post_id', 'content_category_id']);
        });

        Schema::create('blog_post_tag', function (Blueprint $table): void {
            $table->foreignId('blog_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['blog_post_id', 'content_tag_id']);
        });

        Schema::create('blog_post_related', function (Blueprint $table): void {
            $table->foreignId('blog_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_blog_post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->primary(['blog_post_id', 'related_blog_post_id']);
        });

        Schema::create('url_redirects', function (Blueprint $table): void {
            $table->id();
            $table->string('source_path')->unique();
            $table->string('destination_path');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('blog_post_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('blog_post_id')->constrained()->cascadeOnDelete();
            $table->string('event', 64);
            $table->string('session_hash', 64)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->useCurrent()->index();
            $table->index(['blog_post_id', 'event', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_post_events');
        Schema::dropIfExists('url_redirects');
        Schema::dropIfExists('blog_post_related');
        Schema::dropIfExists('blog_post_tag');
        Schema::dropIfExists('blog_post_category');
        Schema::dropIfExists('blog_post_sources');
        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('published_revision_id');
        });
        Schema::dropIfExists('blog_post_revisions');
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('content_tags');
        Schema::dropIfExists('content_categories');
        Schema::dropIfExists('content_authors');
        Schema::dropIfExists('media_variants');
        Schema::dropIfExists('media_assets');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('content_role');
        });
    }
};
