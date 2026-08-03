<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caregiver_profiles', function (Blueprint $table) {
            $table->unsignedTinyInteger('care_background_schema_version')->nullable()->after('years_experience');
            $table->text('care_experience_notes')->nullable()->after('care_background_schema_version');
            $table->timestamp('care_experience_answered_at')->nullable()->after('care_experience_notes');
            $table->timestamp('certifications_answered_at')->nullable()->after('care_experience_answered_at');
        });

        Schema::create('caregiver_experience_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80);
            $table->string('label', 160);
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique('slug', 'cg_exp_type_slug_uq');
            $table->index(['active', 'sort_order'], 'cg_exp_type_active_ix');
        });

        Schema::create('caregiver_profile_experience', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('caregiver_profile_id');
            $table->unsignedBigInteger('caregiver_experience_type_id');
            $table->timestamps();

            $table->foreign('caregiver_profile_id', 'cg_exp_profile_fk')
                ->references('id')->on('caregiver_profiles')->cascadeOnDelete();
            $table->foreign('caregiver_experience_type_id', 'cg_exp_type_fk')
                ->references('id')->on('caregiver_experience_types')->cascadeOnDelete();
            $table->unique(
                ['caregiver_profile_id', 'caregiver_experience_type_id'],
                'cg_exp_profile_type_uq'
            );
        });

        Schema::create('caregiver_certification_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80);
            $table->string('label', 160);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique('slug', 'cg_cert_type_slug_uq');
            $table->index(['active', 'sort_order'], 'cg_cert_type_active_ix');
        });

        Schema::create('caregiver_certifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('caregiver_profile_id');
            $table->unsignedBigInteger('caregiver_certification_type_id');
            $table->string('custom_name', 160)->nullable();
            $table->string('issuer', 160)->nullable();
            $table->string('issuing_state', 2)->nullable();
            $table->date('expires_at')->nullable();
            $table->string('document_path')->nullable();
            $table->string('document_original_name')->nullable();
            $table->string('document_mime', 100)->nullable();
            $table->unsignedBigInteger('document_size')->nullable();
            $table->string('verification_status', 24)->default('self_reported');
            $table->unsignedBigInteger('verified_by_user_id')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->foreign('caregiver_profile_id', 'cg_cert_profile_fk')
                ->references('id')->on('caregiver_profiles')->cascadeOnDelete();
            $table->foreign('caregiver_certification_type_id', 'cg_cert_type_fk')
                ->references('id')->on('caregiver_certification_types')->cascadeOnDelete();
            $table->foreign('verified_by_user_id', 'cg_cert_verifier_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->unique(
                ['caregiver_profile_id', 'caregiver_certification_type_id'],
                'cg_cert_profile_type_uq'
            );
            $table->index(['verification_status', 'expires_at'], 'cg_cert_status_exp_ix');
        });

        $now = now();
        DB::table('caregiver_experience_types')->upsert([
            ['slug' => 'memory-care', 'label' => 'Memory loss, dementia, or Alzheimer\'s support', 'sort_order' => 10, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'mobility-fall-risk', 'label' => 'Limited mobility or fall-risk support', 'sort_order' => 20, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'movement-conditions', 'label' => 'Parkinson\'s or other movement-related conditions', 'sort_order' => 30, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'stroke-support', 'label' => 'Support following a stroke', 'sort_order' => 40, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'post-hospital', 'label' => 'Post-hospital recovery and daily-routine support', 'sort_order' => 50, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'end-of-life', 'label' => 'Hospice or end-of-life companionship', 'sort_order' => 60, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'developmental-disabilities', 'label' => 'Intellectual or developmental disabilities', 'sort_order' => 70, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'sensory-loss', 'label' => 'Vision or hearing loss', 'sort_order' => 80, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'chronic-condition-routines', 'label' => 'Chronic-condition routine support', 'sort_order' => 90, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'other', 'label' => 'Other care experience', 'sort_order' => 100, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
        ], ['slug'], ['label', 'sort_order', 'active', 'updated_at']);

        DB::table('caregiver_certification_types')->upsert([
            ['slug' => 'cpr', 'label' => 'CPR', 'sort_order' => 10, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'first-aid', 'label' => 'First Aid', 'sort_order' => 20, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'bls', 'label' => 'Basic Life Support (BLS)', 'sort_order' => 30, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'cna', 'label' => 'Certified Nursing Assistant (CNA)', 'sort_order' => 40, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'hha', 'label' => 'Home Health Aide (HHA)', 'sort_order' => 50, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'pca', 'label' => 'Personal Care Aide (PCA)', 'sort_order' => 60, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'medication-aide', 'label' => 'Medication Aide or Technician', 'sort_order' => 70, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'dementia-training', 'label' => 'Dementia care training', 'sort_order' => 80, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'other', 'label' => 'Other certification or training', 'sort_order' => 90, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
        ], ['slug'], ['label', 'sort_order', 'active', 'updated_at']);
    }

    public function down(): void
    {
        Schema::dropIfExists('caregiver_certifications');
        Schema::dropIfExists('caregiver_certification_types');
        Schema::dropIfExists('caregiver_profile_experience');
        Schema::dropIfExists('caregiver_experience_types');

        Schema::table('caregiver_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'care_background_schema_version',
                'care_experience_notes',
                'care_experience_answered_at',
                'certifications_answered_at',
            ]);
        });
    }
};
