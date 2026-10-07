<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->index(['organization_id', 'created_at', 'id'], 'questions_tenant_latest_idx');
            $table->index(['organization_id', 'subject_id', 'id'], 'questions_tenant_subject_idx');
            $table->index(['organization_id', 'topic_id', 'id'], 'questions_tenant_topic_idx');
            $table->index(['organization_id', 'stopic_id', 'id'], 'questions_tenant_stopic_idx');
            $table->index(['organization_id', 'qtype_id', 'id'], 'questions_tenant_qtype_idx');
            $table->index(['organization_id', 'status', 'id'], 'questions_tenant_status_idx');
        });

        Schema::table('exam_questions', function (Blueprint $table) {
            $table->index(['exam_id', 'question_id'], 'exam_questions_exam_question_idx');
            $table->index(['question_id', 'exam_id'], 'exam_questions_question_exam_idx');
        });

        Schema::table('question_groups', function (Blueprint $table) {
            $table->index(['group_id', 'question_id'], 'question_groups_group_question_idx');
            $table->index(['question_id', 'group_id'], 'question_groups_question_group_idx');
        });

        Schema::table('question_question_tag', function (Blueprint $table) {
            $table->index(['question_tag_id', 'question_id'], 'question_tags_tag_question_idx');
        });

        Schema::table('exam_groups', function (Blueprint $table) {
            $table->index(['group_id', 'exam_id'], 'exam_groups_group_exam_idx');
            $table->index(['exam_id', 'group_id'], 'exam_groups_exam_group_idx');
        });

        Schema::table('exam_packages', function (Blueprint $table) {
            $table->index(['package_id', 'exam_id'], 'exam_packages_package_exam_idx');
            $table->index(['exam_id', 'package_id'], 'exam_packages_exam_package_idx');
        });

        Schema::table('package_groups', function (Blueprint $table) {
            $table->index(['group_id', 'package_id'], 'package_groups_group_package_idx');
            $table->index(['package_id', 'group_id'], 'package_groups_package_group_idx');
        });
    }

    public function down(): void
    {
        Schema::table('package_groups', function (Blueprint $table) {
            $table->dropIndex('package_groups_group_package_idx');
            $table->dropIndex('package_groups_package_group_idx');
        });

        Schema::table('exam_packages', function (Blueprint $table) {
            $table->dropIndex('exam_packages_package_exam_idx');
            $table->dropIndex('exam_packages_exam_package_idx');
        });

        Schema::table('exam_groups', function (Blueprint $table) {
            $table->dropIndex('exam_groups_group_exam_idx');
            $table->dropIndex('exam_groups_exam_group_idx');
        });

        Schema::table('question_question_tag', function (Blueprint $table) {
            $table->dropIndex('question_tags_tag_question_idx');
        });

        Schema::table('question_groups', function (Blueprint $table) {
            $table->dropIndex('question_groups_group_question_idx');
            $table->dropIndex('question_groups_question_group_idx');
        });

        Schema::table('exam_questions', function (Blueprint $table) {
            $table->dropIndex('exam_questions_exam_question_idx');
            $table->dropIndex('exam_questions_question_exam_idx');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex('questions_tenant_latest_idx');
            $table->dropIndex('questions_tenant_subject_idx');
            $table->dropIndex('questions_tenant_topic_idx');
            $table->dropIndex('questions_tenant_stopic_idx');
            $table->dropIndex('questions_tenant_qtype_idx');
            $table->dropIndex('questions_tenant_status_idx');
        });
    }
};
