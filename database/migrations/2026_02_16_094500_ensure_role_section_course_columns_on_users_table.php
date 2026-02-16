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
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('student')->after('password');
            }

            if (! Schema::hasColumn('users', 'section')) {
                $table->string('section')->nullable()->after('role');
            }

            if (! Schema::hasColumn('users', 'course')) {
                $table->string('course')->nullable()->after('section');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'course')) {
                $table->dropColumn('course');
            }

            if (Schema::hasColumn('users', 'section')) {
                $table->dropColumn('section');
            }

            if (Schema::hasColumn('users', 'role')) {
                $table->dropColumn('role');
            }
        });
    }
};
