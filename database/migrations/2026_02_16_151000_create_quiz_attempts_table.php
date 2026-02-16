<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('quiz_attempts')) {
            return;
        }

        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->json('answers')->nullable();
            $table->unsignedInteger('score')->default(0);
            $table->unsignedInteger('total_items')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->boolean('is_overridden')->default(false);
            $table->foreignId('overridden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('override_note')->nullable();
            $table->timestamps();

            $table->unique(['quiz_id', 'user_id'], 'quiz_attempts_quiz_user_unique');
            $table->index(['user_id', 'submitted_at'], 'quiz_attempts_user_submitted_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
    }
};
