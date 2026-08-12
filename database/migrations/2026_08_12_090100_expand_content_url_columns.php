<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->string('source_url', 2048)->nullable()->change();
        });
        Schema::table('blog_post_sources', function (Blueprint $table): void {
            $table->string('url', 2048)->change();
        });
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->string('source_url')->nullable()->change();
        });
        Schema::table('blog_post_sources', function (Blueprint $table): void {
            $table->string('url')->change();
        });
    }
};
