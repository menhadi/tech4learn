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
        Schema::create('exam_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_result_id')->constrained('exam_results')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->string('test_instructions');
            $table->string('language_of_questions');
            $table->string('text_experience');
            $table->text('feedback');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_feedbacks');
    }
};
