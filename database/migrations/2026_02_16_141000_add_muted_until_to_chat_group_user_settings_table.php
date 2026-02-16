<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chat_group_user_settings')) {
            return;
        }

        Schema::table('chat_group_user_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('chat_group_user_settings', 'muted_until')) {
                $table->timestamp('muted_until')->nullable()->after('muted_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('chat_group_user_settings')) {
            return;
        }

        Schema::table('chat_group_user_settings', function (Blueprint $table) {
            if (Schema::hasColumn('chat_group_user_settings', 'muted_until')) {
                $table->dropColumn('muted_until');
            }
        });
    }
};
