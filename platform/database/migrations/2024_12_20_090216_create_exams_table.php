<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('passing_percentage')->unsigned()->max(100);
            $table->text('instruction')->nullable();
            $table->text('syllabus')->nullable();
            $table->integer('duration')->default(0);
            $table->integer('attempt_count')->default(0);
            $table->timestamp('start_date');
            $table->timestamp('end_date');
            $table->boolean('show_answer_sheet')->default(false);
            $table->boolean('negative_marking')->default(false);
            $table->boolean('random_question')->default(false);
            $table->boolean('result_after_finish')->default(false);
            $table->enum('mode', ['Exam', 'Preparation']);
            $table->boolean('instant_result')->default(false);
            $table->boolean('option_shuffle')->default(false);
            $table->boolean('multi_language')->default(false);
            $table->boolean('math_editor')->default(false);
            $table->boolean('browser_tolerance')->default(false);
            $table->boolean('is_subject_timer')->default(false);
            $table->boolean('proctor')->default(false);
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->integer('tolerance_count')->default(0);
            $table->timestamps();

            $table->index('start_date');
            $table->index('end_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};
