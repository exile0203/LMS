<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('assignments')) {
            return;
        }

        Schema::table('assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('assignments', 'is_closed')) {
                $table->boolean('is_closed')->default(false)->after('allow_link');
            }

            if (! Schema::hasColumn('assignments', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('is_closed');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('assignments')) {
            return;
        }

        Schema::table('assignments', function (Blueprint $table) {
            if (Schema::hasColumn('assignments', 'closed_at')) {
                $table->dropColumn('closed_at');
            }
            if (Schema::hasColumn('assignments', 'is_closed')) {
                $table->dropColumn('is_closed');
            }
        });
    }
};
