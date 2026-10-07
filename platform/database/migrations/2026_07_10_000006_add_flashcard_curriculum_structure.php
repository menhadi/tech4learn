<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['topics', 'stopics'] as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'display_order')) {
                Schema::table($tableName, fn (Blueprint $table) => $table->unsignedInteger('display_order')->nullable()->index());
            }
        }

        if (! Schema::hasColumn('flashcard_sets', 'display_order')) {
            Schema::table('flashcard_sets', fn (Blueprint $table) => $table->unsignedInteger('display_order')->nullable()->index());
        }

        if (! Schema::hasTable('flashcard_checks')) {
            Schema::create('flashcard_checks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('flashcard_id')->constrained('flashcards')->cascadeOnDelete();
                $table->foreignId('source_question_id')->nullable()->constrained('questions')->nullOnDelete();
                $table->string('difficulty', 20)->nullable();
                $table->text('question')->nullable();
                $table->json('options')->nullable();
                $table->text('correct_answer')->nullable();
                $table->text('explanation')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('status')->default(true);
                $table->timestamps();
                $table->index(['flashcard_id', 'status', 'sort_order']);
            });
        }

        if (! Schema::hasTable('student_flashcard_course_progress')) {
            Schema::create('student_flashcard_course_progress', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('student_id')->index();
                $table->unsignedBigInteger('package_id')->index();
                $table->unsignedBigInteger('current_flashcard_set_id')->nullable()->index();
                $table->unsignedBigInteger('current_flashcard_id')->nullable()->index();
                $table->timestamp('last_studied_at')->nullable();
                $table->timestamps();
                $table->unique(['student_id', 'package_id'], 'student_flashcard_course_progress_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_flashcard_course_progress');
        Schema::dropIfExists('flashcard_checks');
        foreach (['topics', 'stopics'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'display_order')) {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn('display_order'));
            }
        }
        if (Schema::hasColumn('flashcard_sets', 'display_order')) {
            Schema::table('flashcard_sets', fn (Blueprint $table) => $table->dropColumn('display_order'));
        }
    }
};
