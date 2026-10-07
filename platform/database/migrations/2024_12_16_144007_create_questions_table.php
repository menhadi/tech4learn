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
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qtype_id')->constrained('qtypes');
            $table->foreignId('subject_id')->constrained('subjects');
            $table->foreignId('topic_id')->nullable()->constrained('topics');
            $table->foreignId('stopic_id')->nullable()->constrained('stopics');
            $table->foreignId('diff_id')->constrained('diffs');
            $table->foreignId('passage_id')->nullable()->constrained('passages');
            $table->text('question')->nullable();
            $table->text('option1')->nullable();
            $table->text('option2')->nullable();
            $table->text('option3')->nullable();
            $table->text('option4')->nullable();
            $table->text('option5')->nullable();
            $table->text('option6')->nullable();
            $table->decimal('marks', 5, 2)->nullable();
            $table->decimal('negative_marks', 5, 2)->nullable();
            $table->text('hint')->nullable();
            $table->text('explanation')->nullable();
            $table->string('answer', 15)->nullable();
            $table->string('true_false', 5)->nullable();
            $table->text('fill_blank')->nullable();
            $table->string('status', 3)->nullable()->default('Yes');
            $table->text('mi_answer1')->nullable();
            $table->text('mi_answer2')->nullable();
            $table->text('mi_answer3')->nullable();
            $table->text('mi_answer4')->nullable();
            $table->text('mi_answer5')->nullable();
            $table->text('mi_answer6')->nullable();
            $table->text('si_answer1')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
