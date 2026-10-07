<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('exam_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_result_id')->constrained()->onDelete('cascade');
            $table->foreignId('exam_id')->constrained()->onDelete('cascade');
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('question_id')->constrained()->onDelete('cascade');
            $table->foreignId('subject_id')->constrained()->onDelete('cascade');
            $table->integer('subject_time')->nullable();
            $table->boolean('is_section')->nullable();
            $table->integer('ques_no');
            $table->string('options')->nullable();
            $table->dateTime('attempt_time')->nullable();
            $table->boolean('opened')->nullable();
            $table->boolean('answered')->nullable();
            $table->boolean('review')->nullable();
            $table->string('option_selected')->nullable();
            $table->text('answer')->nullable();
            $table->string('true_false')->nullable();
            $table->text('fill_blank')->nullable();
            $table->text('correct_answer')->nullable();
            $table->decimal('marks', 8, 2);
            $table->decimal('negative_marks', 5, 2)->nullable();
            $table->decimal('marks_obtained', 8, 2)->nullable();
            $table->char('ques_status', 1)->nullable();
            $table->boolean('closed')->nullable();
            $table->dateTime('checking_time')->nullable();
            $table->integer('time_taken')->nullable();
            $table->boolean('bookmark')->nullable();
            $table->text('mi_answers')->nullable();
            $table->text('si_answers')->nullable();
            $table->timestamps();

            $table->index('exam_result_id');
            $table->index('exam_id');
            $table->index('student_id');
            $table->index('question_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_stats');
    }
};
