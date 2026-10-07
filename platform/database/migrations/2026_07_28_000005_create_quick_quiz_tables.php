<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL may leave the first table behind when a later foreign-key
        // statement fails. Since the migration is still pending, clear that
        // incomplete table before retrying.
        if (! Schema::hasTable('quick_quiz_answers') && Schema::hasTable('quick_quiz_sessions')) {
            Schema::drop('quick_quiz_sessions');
        }

        Schema::create('quick_quiz_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('guest_id')->nullable()->index();
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            // The legacy category table uses a different integer type between
            // installations, so keep these indexed and enforce scope in code.
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->unsignedBigInteger('subcategory_id')->nullable()->index();
            $table->foreignId('package_id')->nullable()->constrained('packages')->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->json('question_ids');
            $table->unsignedSmallInteger('question_count');
            $table->unsignedSmallInteger('answered_count')->default(0);
            $table->unsignedSmallInteger('correct_count')->default(0);
            $table->string('status', 24)->default('started');
            $table->string('source', 40)->default('homepage');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'started_at'], 'quick_quiz_org_status_idx');
            $table->index(['student_id', 'started_at'], 'quick_quiz_student_idx');
        });

        Schema::create('quick_quiz_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quick_quiz_session_id')->constrained('quick_quiz_sessions')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->json('answer_payload')->nullable();
            $table->text('correct_answer')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->timestamp('answered_at')->useCurrent();
            $table->timestamps();

            $table->unique(['quick_quiz_session_id', 'question_id'], 'quick_quiz_session_question_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_quiz_answers');
        Schema::dropIfExists('quick_quiz_sessions');
    }
};
