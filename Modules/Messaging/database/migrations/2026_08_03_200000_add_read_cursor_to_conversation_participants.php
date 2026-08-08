<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;



return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_participants', function (Blueprint $table) {
            // Authoritative per-participant read cursor. NULL = no cursor
            // recorded yet (do NOT infer from legacy messages.is_read).
            $table
                ->foreignId('last_read_message_id')
                ->nullable()
                ->after('user_id')
                ->constrained('messages')
                ->nullOnDelete();

            // (conversation_id, user_id) is already covered by the existing
            // UNIQUE constraint, so no extra participant lookup index is added.
        });

        Schema::table('messages', function (Blueprint $table) {
            // Supports the authoritative unread strategy
            // `WHERE conversation_id = ? AND id > ?` as a true range scan.
            $table->index(['conversation_id', 'id'], 'messages_conversation_id_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->dropForeign('conversation_participants_last_read_message_id_foreign');
            $table->dropColumn('last_read_message_id');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_conversation_id_id_index');
        });
    }
};
