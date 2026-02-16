<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('chat_messages')) {
            return;
        }

        Schema::table('chat_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('chat_messages', 'reply_to_message_id')) {
                $table->foreignId('reply_to_message_id')
                    ->nullable()
                    ->after('chat_group_id')
                    ->constrained('chat_messages')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('chat_messages', 'reactions')) {
                $table->json('reactions')->nullable()->after('file_size');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('chat_messages')) {
            return;
        }

        Schema::table('chat_messages', function (Blueprint $table) {
            if (Schema::hasColumn('chat_messages', 'reply_to_message_id')) {
                $table->dropConstrainedForeignId('reply_to_message_id');
            }

            if (Schema::hasColumn('chat_messages', 'reactions')) {
                $table->dropColumn('reactions');
            }
        });
    }
};
