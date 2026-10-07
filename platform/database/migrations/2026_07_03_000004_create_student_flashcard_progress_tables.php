<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_flashcard_progress')) {
            Schema::create('student_flashcard_progress', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('student_id')->index();
                $table->unsignedBigInteger('package_id')->nullable()->index();
                $table->unsignedBigInteger('flashcard_set_id')->index();
                $table->unsignedBigInteger('flashcard_id')->index();
                $table->timestamp('viewed_at')->nullable();
                $table->boolean('last_answer_correct')->nullable();
                $table->timestamp('correct_answered_at')->nullable();
                $table->unsignedInteger('wrong_attempts')->default(0);
                $table->unsignedInteger('correct_attempts')->default(0);
                $table->string('confidence')->nullable();
                $table->boolean('confidence_awarded')->default(false);
                $table->unsignedInteger('points_earned')->default(0);
                $table->timestamp('last_interaction_at')->nullable();
                $table->timestamps();

                $table->unique(['student_id', 'flashcard_id'], 'student_flashcard_progress_unique_card');
            });
        }

        if (! Schema::hasTable('student_flashcard_points')) {
            Schema::create('student_flashcard_points', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('student_id')->index();
                $table->unsignedBigInteger('package_id')->nullable()->index();
                $table->unsignedBigInteger('flashcard_set_id')->index();
                $table->unsignedInteger('total_points')->default(0);
                $table->unsignedInteger('cards_studied')->default(0);
                $table->unsignedInteger('correct_answers')->default(0);
                $table->timestamp('last_activity_at')->nullable();
                $table->timestamps();

                $table->unique(['student_id', 'flashcard_set_id'], 'student_flashcard_points_unique_set');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_flashcard_points');
        Schema::dropIfExists('student_flashcard_progress');
    }
};
