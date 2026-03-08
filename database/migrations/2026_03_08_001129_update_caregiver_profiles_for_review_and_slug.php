<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caregiver_profiles', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('user_id');
            $table->timestamp('review_submitted_at')->nullable()->after('status');
            $table->timestamp('reviewed_at')->nullable()->after('review_submitted_at');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable()->after('reviewed_by');
            $table->decimal('average_rating', 3, 2)->default(0)->after('rejection_reason');
            $table->unsignedInteger('reviews_count')->default(0)->after('average_rating');
        });
    }

    public function down(): void
    {
        Schema::table('caregiver_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'slug',
                'review_submitted_at',
                'reviewed_at',
                'rejection_reason',
                'average_rating',
                'reviews_count',
            ]);
        });
    }
};
