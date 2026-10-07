<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->string('timer_mode', 20)->default('none')->after('is_subject_timer');
        });

        DB::table('exams')->where('is_subject_timer', 1)->update(['timer_mode' => 'subject']);

        Schema::create('exam_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('display_order')->default(0);
            $table->unsignedInteger('duration')->nullable()->comment('Minutes; null uses the automatic split');
            $table->timestamps();
            $table->unique(['exam_id', 'name']);
            $table->index(['exam_id', 'display_order']);
        });

        Schema::table('exam_questions', function (Blueprint $table) {
            $table->foreignId('exam_section_id')->nullable()->after('question_id')
                ->constrained('exam_sections')->nullOnDelete();
        });

        Schema::table('exam_stats', function (Blueprint $table) {
            $table->foreignId('exam_section_id')->nullable()->after('subject_id')
                ->constrained('exam_sections')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('exam_stats', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exam_section_id');
        });
        Schema::table('exam_questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exam_section_id');
        });
        Schema::dropIfExists('exam_sections');
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn('timer_mode');
        });
    }
};