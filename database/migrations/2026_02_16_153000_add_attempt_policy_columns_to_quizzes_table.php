<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('quizzes')) {
            return;
        }

        Schema::table('quizzes', function (Blueprint $table) {
            if (! Schema::hasColumn('quizzes', 'max_attempts')) {
                $table->unsignedInteger('max_attempts')->nullable()->after('quiz_set');
            }

            if (! Schema::hasColumn('quizzes', 'score_policy')) {
                $table->string('score_policy', 20)->default('latest')->after('max_attempts');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('quizzes')) {
            return;
        }

        Schema::table('quizzes', function (Blueprint $table) {
            if (Schema::hasColumn('quizzes', 'score_policy')) {
                $table->dropColumn('score_policy');
            }

            if (Schema::hasColumn('quizzes', 'max_attempts')) {
                $table->dropColumn('max_attempts');
            }
        });
    }
};
