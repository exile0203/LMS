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
            if (! Schema::hasColumn('chat_messages', 'is_pinned')) {
                $table->boolean('is_pinned')->default(false)->after('deleted_by');
            }

            if (! Schema::hasColumn('chat_messages', 'pinned_at')) {
                $table->timestamp('pinned_at')->nullable()->after('is_pinned');
            }

            if (! Schema::hasColumn('chat_messages', 'pinned_by')) {
                $table->foreignId('pinned_by')
                    ->nullable()
                    ->after('pinned_at')
                    ->constrained('users')
                    ->nullOnDelete();
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
            if (Schema::hasColumn('chat_messages', 'pinned_by')) {
                $table->dropConstrainedForeignId('pinned_by');
            }
            if (Schema::hasColumn('chat_messages', 'pinned_at')) {
                $table->dropColumn('pinned_at');
            }
            if (Schema::hasColumn('chat_messages', 'is_pinned')) {
                $table->dropColumn('is_pinned');
            }
        });
    }
};
