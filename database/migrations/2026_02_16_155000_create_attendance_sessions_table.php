<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_sessions')) {
            return;
        }

        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('section');
            $table->string('course');
            $table->date('attendance_date');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['section', 'course', 'attendance_date'], 'attendance_sessions_unique_scope');
            $table->index(['created_by', 'attendance_date'], 'attendance_sessions_created_by_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
    }
};
