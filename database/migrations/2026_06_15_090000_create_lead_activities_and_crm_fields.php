<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('assigned_admin_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->string('priority', 20)->default('normal')->after('assigned_admin_id')->index();
            $table->string('source', 80)->nullable()->after('priority')->index();
            $table->string('source_detail')->nullable()->after('source');
            $table->string('contact_role', 120)->nullable()->after('source_detail')->index();
            $table->timestamp('last_contacted_at')->nullable()->after('contact_role')->index();
            $table->timestamp('next_follow_up_at')->nullable()->after('last_contacted_at')->index();
            $table->timestamp('converted_at')->nullable()->after('next_follow_up_at')->index();
            $table->string('closed_reason')->nullable()->after('converted_at');
        });

        Schema::create('lead_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40)->index();
            $table->string('summary')->nullable();
            $table->text('body')->nullable();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_activities');

        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['assigned_admin_id']);
            $table->dropColumn([
                'assigned_admin_id',
                'priority',
                'source',
                'source_detail',
                'contact_role',
                'last_contacted_at',
                'next_follow_up_at',
                'converted_at',
                'closed_reason',
            ]);
        });
    }
};
