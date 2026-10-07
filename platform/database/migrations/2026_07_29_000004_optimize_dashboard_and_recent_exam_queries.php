<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->index(['organization_id', 'created_at', 'id'], 'exams_tenant_created_idx');
            $table->index(['organization_id', 'status', 'end_date'], 'exams_tenant_status_end_idx');
        });

        Schema::table('exam_results', function (Blueprint $table) {
            $table->index(['organization_id', 'end_time'], 'exam_results_tenant_end_idx');
            $table->index(['organization_id', 'exam_id', 'percent'], 'exam_results_tenant_exam_score_idx');
            $table->index(['student_id', 'end_time'], 'exam_results_student_end_idx');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->index(['organization_id', 'created_at'], 'students_tenant_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex('students_tenant_created_idx');
        });

        Schema::table('exam_results', function (Blueprint $table) {
            $table->dropIndex('exam_results_tenant_end_idx');
            $table->dropIndex('exam_results_tenant_exam_score_idx');
            $table->dropIndex('exam_results_student_end_idx');
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->dropIndex('exams_tenant_created_idx');
            $table->dropIndex('exams_tenant_status_end_idx');
        });
    }
};