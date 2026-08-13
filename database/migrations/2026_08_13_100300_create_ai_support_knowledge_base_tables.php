<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_base_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('stable_id', 40)->unique();
            $table->boolean('ever_released')->default(false);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deleted_at')->nullable();
            $table->string('deletion_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['deleted_at', 'updated_at'], 'kbe_deleted_updated_idx');
        });

        Schema::create('knowledge_base_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('knowledge_base_entry_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->unsignedInteger('edit_version')->default(1);
            $table->string('status', 32)->default('draft');
            $table->string('type', 40);
            $table->string('title');
            $table->longText('answer_body');
            $table->string('sensitivity', 40)->default('authenticated');
            $table->string('product_area', 120);
            $table->string('locale', 16)->default('en-US');
            $table->json('roles');
            $table->json('membership_states')->nullable();
            $table->json('route_target_ids')->nullable();
            $table->json('capability_ids');
            $table->json('facts_may_state')->nullable();
            $table->json('facts_must_not_infer')->nullable();
            $table->json('next_actions')->nullable();
            $table->json('escalation_conditions')->nullable();
            $table->json('retrieval_examples_match')->nullable();
            $table->json('retrieval_examples_no_match')->nullable();
            $table->json('evaluation_ids');
            $table->string('change_note', 500);
            $table->date('review_by');
            $table->date('expires_on')->nullable();
            $table->json('validation_results')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('authored_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_for_review_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->foreignId('replaced_by_version_id')->nullable()->constrained('knowledge_base_versions')->nullOnDelete();
            $table->timestamp('full_content_retain_until')->nullable();
            $table->timestamp('content_deleted_at')->nullable();
            $table->timestamp('tombstone_retain_until')->nullable();
            $table->timestamps();

            $table->unique(['knowledge_base_entry_id', 'version_number'], 'kbv_entry_version_unique');
            $table->index(['status', 'review_by'], 'kbv_status_review_idx');
            $table->index(['status', 'published_at'], 'kbv_status_published_idx');
            $table->index('full_content_retain_until');
        });

        Schema::table('knowledge_base_entries', function (Blueprint $table): void {
            $table->foreignId('working_version_id')->nullable()->after('stable_id')->constrained('knowledge_base_versions')->nullOnDelete();
            $table->foreignId('published_version_id')->nullable()->after('working_version_id')->constrained('knowledge_base_versions')->nullOnDelete();
        });

        Schema::create('knowledge_base_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('knowledge_base_version_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('source_id', 120);
            $table->string('title');
            $table->string('url', 2048)->nullable();
            $table->string('section_anchor')->nullable();
            $table->text('fact_supported');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['knowledge_base_version_id', 'position'], 'kbs_version_position_idx');
            $table->index('source_id');
        });

        Schema::create('knowledge_base_version_dependencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('knowledge_base_version_id')->constrained()->cascadeOnDelete();
            $table->string('dependency_type', 80);
            $table->string('dependency_id', 120);
            $table->timestamp('protect_until')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['knowledge_base_version_id', 'dependency_type', 'dependency_id'],
                'kbvd_version_type_id_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_base_version_dependencies');
        Schema::dropIfExists('knowledge_base_sources');
        Schema::table('knowledge_base_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('published_version_id');
            $table->dropConstrainedForeignId('working_version_id');
        });
        Schema::dropIfExists('knowledge_base_versions');
        Schema::dropIfExists('knowledge_base_entries');
    }
};
