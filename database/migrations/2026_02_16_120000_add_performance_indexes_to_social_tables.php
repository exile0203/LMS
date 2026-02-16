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
        if (Schema::hasTable('chat_groups')) {
            Schema::table('chat_groups', function (Blueprint $table) {
                $table->index(['section', 'course'], 'chat_groups_section_course_idx');
            });
        }

        if (Schema::hasTable('chat_messages')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                $table->index(['chat_group_id', 'created_at'], 'chat_messages_group_created_idx');
                $table->index(['sender_id', 'created_at'], 'chat_messages_sender_created_idx');
            });
        }

        if (Schema::hasTable('quizzes')) {
            Schema::table('quizzes', function (Blueprint $table) {
                $table->index(['section', 'course', 'quiz_set'], 'quizzes_scope_idx');
                $table->index(['created_by', 'created_at'], 'quizzes_creator_created_idx');
            });
        }

        if (Schema::hasTable('quiz_questions')) {
            Schema::table('quiz_questions', function (Blueprint $table) {
                $table->index(['quiz_id', 'order'], 'quiz_questions_quiz_order_idx');
            });
        }

        if (Schema::hasTable('mail_messages')) {
            Schema::table('mail_messages', function (Blueprint $table) {
                $table->index(['user_id', 'folder', 'created_at'], 'mail_messages_user_folder_created_idx');
                $table->index(['user_id', 'unread'], 'mail_messages_user_unread_idx');
                $table->index(['user_id', 'starred'], 'mail_messages_user_starred_idx');
            });
        }

        if (Schema::hasTable('app_notifications')) {
            Schema::table('app_notifications', function (Blueprint $table) {
                $table->index(['user_id', 'is_read', 'created_at'], 'app_notifications_user_read_created_idx');
            });
        }

        if (Schema::hasTable('support_chat_messages')) {
            Schema::table('support_chat_messages', function (Blueprint $table) {
                $table->index(['user_id', 'created_at'], 'support_chat_messages_user_created_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('chat_groups')) {
            Schema::table('chat_groups', function (Blueprint $table) {
                $table->dropIndex('chat_groups_section_course_idx');
            });
        }

        if (Schema::hasTable('chat_messages')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                $table->dropIndex('chat_messages_group_created_idx');
                $table->dropIndex('chat_messages_sender_created_idx');
            });
        }

        if (Schema::hasTable('quizzes')) {
            Schema::table('quizzes', function (Blueprint $table) {
                $table->dropIndex('quizzes_scope_idx');
                $table->dropIndex('quizzes_creator_created_idx');
            });
        }

        if (Schema::hasTable('quiz_questions')) {
            Schema::table('quiz_questions', function (Blueprint $table) {
                $table->dropIndex('quiz_questions_quiz_order_idx');
            });
        }

        if (Schema::hasTable('mail_messages')) {
            Schema::table('mail_messages', function (Blueprint $table) {
                $table->dropIndex('mail_messages_user_folder_created_idx');
                $table->dropIndex('mail_messages_user_unread_idx');
                $table->dropIndex('mail_messages_user_starred_idx');
            });
        }

        if (Schema::hasTable('app_notifications')) {
            Schema::table('app_notifications', function (Blueprint $table) {
                $table->dropIndex('app_notifications_user_read_created_idx');
            });
        }

        if (Schema::hasTable('support_chat_messages')) {
            Schema::table('support_chat_messages', function (Blueprint $table) {
                $table->dropIndex('support_chat_messages_user_created_idx');
            });
        }
    }
};
