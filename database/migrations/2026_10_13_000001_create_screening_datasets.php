<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screening_dataset_imports', function (Blueprint $table) {
            $table->id();
            $table->string('source', 30);
            $table->string('status', 20)->default('importing');
            $table->boolean('is_active')->default(false);
            $table->string('checksum', 64)->nullable();
            $table->json('source_urls')->nullable();
            $table->json('file_manifest')->nullable();
            $table->json('row_counts')->nullable();
            $table->unsignedBigInteger('record_count')->default(0);
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['source', 'is_active', 'status'], 'screening_import_current');
        });

        Schema::create('screening_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('screening_dataset_imports')->cascadeOnDelete();
            $table->string('record_type', 20);
            $table->string('source_record_id', 100);
            $table->string('parent_record_id', 100)->nullable();
            $table->json('data');
            $table->unique(['import_id', 'record_type', 'source_record_id'], 'screening_record_unique');
            $table->index(['import_id', 'parent_record_id'], 'screening_record_parent');
        });

        Schema::create('screening_record_names', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('screening_dataset_imports')->cascadeOnDelete();
            $table->foreignId('record_id')->constrained('screening_records')->cascadeOnDelete();
            $table->string('display_name', 250);
            $table->string('normalized_name', 250);
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->boolean('is_alias')->default(false);
            $table->index(['import_id', 'normalized_name'], 'screening_name_exact');
            $table->index(['import_id', 'first_name', 'last_name'], 'screening_name_parts');
            $table->unique(['record_id', 'normalized_name', 'first_name', 'last_name'], 'screening_name_per_profile');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screening_record_names');
        Schema::dropIfExists('screening_records');
        Schema::dropIfExists('screening_dataset_imports');
    }
};
