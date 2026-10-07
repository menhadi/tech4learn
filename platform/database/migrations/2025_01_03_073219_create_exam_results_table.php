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
        Schema::create('exam_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->onDelete('cascade');
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->dateTime('start_time');
            $table->dateTime('end_time')->nullable();
            $table->dateTime('attempt_time')->nullable();
            $table->integer('total_test_time');
            $table->integer('test_time')->nullable();
            $table->dateTime('pause_time')->nullable();
            $table->integer('total_question');
            $table->integer('total_attempt')->default(0);
            $table->integer('total_answered')->default(0);
            $table->decimal('total_marks', 8, 2)->nullable();
            $table->decimal('obtained_marks', 8, 2)->nullable();
            $table->string('result')->nullable();
            $table->decimal('percent', 5, 2)->nullable();
            $table->dateTime('finalized_time')->nullable();
            $table->integer('tolerance_count')->nullable();
            $table->timestamps();

            $table->index('exam_id');
            $table->index('student_id');
            $table->index('start_time');
            $table->index('end_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_results');
    }
};
