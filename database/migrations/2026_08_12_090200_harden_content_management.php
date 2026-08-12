<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->foreignId('submitted_by_user_id')->nullable()->after('updated_by_user_id')->constrained('users')->nullOnDelete();
            $table->foreignId('last_edited_by_user_id')->nullable()->after('submitted_by_user_id')->constrained('users')->nullOnDelete();
            $table->unsignedInteger('edit_version')->default(0)->after('revision_number');
        });

        Schema::table('content_categories', function (Blueprint $table): void {
            $table->foreignId('merged_into_id')->nullable()->after('is_active')->constrained('content_categories')->nullOnDelete();
        });
        Schema::table('content_tags', function (Blueprint $table): void {
            $table->foreignId('merged_into_id')->nullable()->after('description')->constrained('content_tags')->nullOnDelete();
        });

        DB::table('blog_posts')->update([
            'last_edited_by_user_id' => DB::raw('updated_by_user_id'),
            'edit_version' => DB::raw('revision_number'),
        ]);

        Schema::table('blog_post_sources', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->after('blog_post_id');
            $table->unsignedSmallInteger('position')->default(0)->after('uuid');
        });

        $positions = [];
        foreach (DB::table('blog_post_sources')->orderBy('blog_post_id')->orderBy('id')->get(['id', 'blog_post_id']) as $source) {
            $position = $positions[$source->blog_post_id] ?? 0;
            DB::table('blog_post_sources')->where('id', $source->id)->update([
                'uuid' => (string) Str::uuid(),
                'position' => $position,
            ]);
            $positions[$source->blog_post_id] = $position + 1;
        }

        Schema::table('blog_post_sources', function (Blueprint $table): void {
            $table->unique(['blog_post_id', 'uuid']);
            $table->index(['blog_post_id', 'position']);
        });

        Schema::table('blog_post_events', function (Blueprint $table): void {
            $table->string('dedupe_key', 64)->nullable()->after('session_hash')->unique();
        });
    }

    public function down(): void
    {
        Schema::table('blog_post_events', function (Blueprint $table): void {
            $table->dropUnique(['dedupe_key']);
            $table->dropColumn('dedupe_key');
        });

        Schema::table('blog_post_sources', function (Blueprint $table): void {
            $table->dropUnique(['blog_post_id', 'uuid']);
            $table->dropIndex(['blog_post_id', 'position']);
            $table->dropColumn(['uuid', 'position']);
        });

        Schema::table('content_tags', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('merged_into_id');
        });
        Schema::table('content_categories', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('merged_into_id');
        });

        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('submitted_by_user_id');
            $table->dropConstrainedForeignId('last_edited_by_user_id');
            $table->dropColumn('edit_version');
        });
    }
};
