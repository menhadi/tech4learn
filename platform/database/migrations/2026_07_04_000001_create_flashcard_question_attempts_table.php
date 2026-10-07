<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('flashcard_question_attempts')) {
            return;
        }

        Schema::create('flashcard_question_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->unsignedBigInteger('student_id')->nullable()->index();
            $table->string('guest_id', 80)->nullable()->index();
            $table->unsignedBigInteger('flashcard_id')->index();
            $table->unsignedBigInteger('question_id')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('correct_attempts')->default(0);
            $table->unsignedInteger('wrong_attempts')->default(0);
            $table->boolean('last_is_correct')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamps();

            $table->index(['flashcard_id', 'question_id']);
            $table->index(['student_id', 'flashcard_id']);
            $table->index(['guest_id', 'flashcard_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flashcard_question_attempts');
    }
};
