<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 30)->default('public');
            $table->text('body');
            $table->uuid('client_message_id')->nullable();
            $table->timestamps();

            $table->index(['support_ticket_id', 'kind', 'created_at'], 'stm_ticket_kind_created_idx');
            $table->unique(['support_ticket_id', 'client_message_id'], 'stm_ticket_client_unique');
        });

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->timestamp('last_public_message_at')->nullable()->after('assigned_admin_id')->index();
            $table->foreignId('last_public_message_sender_id')
                ->nullable()
                ->after('last_public_message_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('opener_last_read_at')->nullable()->after('last_public_message_sender_id');
            $table->timestamp('admin_last_read_at')->nullable()->after('opener_last_read_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_messages');

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_public_message_sender_id');
            $table->dropColumn([
                'last_public_message_at',
                'opener_last_read_at',
                'admin_last_read_at',
            ]);
        });
    }
};
