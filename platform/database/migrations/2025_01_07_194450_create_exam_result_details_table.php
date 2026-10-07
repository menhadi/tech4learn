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
        Schema::create('exam_result_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('exam_result_id')->unique();
            $table->integer('total_students');
            $table->integer('correct_questions');
            $table->integer('incorrect_questions');
            $table->integer('right_marks');
            $table->integer('left_questions');
            $table->integer('left_question_marks');
            $table->integer('rank');
            $table->integer('negative_marks')->default(0);
            $table->string('formatted_total_test_time');
            $table->string('formatted_test_time');
            $table->json('subject_reports')->nullable();
            $table->json('question_reports')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_result_details');
    }
};