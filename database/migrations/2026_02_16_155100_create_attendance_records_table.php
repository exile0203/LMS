<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_records')) {
            return;
        }

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20);
            $table->text('note')->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('marked_at')->nullable();
            $table->timestamps();

            $table->unique(['attendance_session_id', 'student_id'], 'attendance_records_unique_student');
            $table->index(['student_id', 'marked_at'], 'attendance_records_student_marked_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
