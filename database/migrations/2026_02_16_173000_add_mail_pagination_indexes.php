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
        if (! Schema::hasTable('mail_messages')) {
            return;
        }

        Schema::table('mail_messages', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'mail_messages_user_created_idx');
            $table->index(['user_id', 'starred', 'folder'], 'mail_messages_user_starred_folder_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('mail_messages')) {
            return;
        }

        Schema::table('mail_messages', function (Blueprint $table) {
            $table->dropIndex('mail_messages_user_created_idx');
            $table->dropIndex('mail_messages_user_starred_folder_idx');
        });
    }
};
