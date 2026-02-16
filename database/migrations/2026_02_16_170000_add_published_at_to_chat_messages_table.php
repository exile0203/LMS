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
            if (! Schema::hasColumn('chat_messages', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('created_at');
                $table->index(['chat_group_id', 'published_at'], 'chat_messages_group_published_idx');
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
            if (Schema::hasColumn('chat_messages', 'published_at')) {
                $table->dropIndex('chat_messages_group_published_idx');
                $table->dropColumn('published_at');
            }
        });
    }
};
