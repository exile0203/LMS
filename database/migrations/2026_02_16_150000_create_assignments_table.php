<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('assignments')) {
            return;
        }

        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('section');
            $table->string('course');
            $table->timestamp('due_at')->nullable();
            $table->boolean('allow_file')->default(true);
            $table->boolean('allow_link')->default(true);
            $table->timestamps();

            $table->index(['section', 'course'], 'assignments_section_course_idx');
            $table->index(['created_by', 'created_at'], 'assignments_created_by_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
