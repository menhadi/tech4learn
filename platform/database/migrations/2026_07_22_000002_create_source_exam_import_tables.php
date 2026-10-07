<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('source_exam_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('exam_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('status', 30)->default('queued')->index();
            $table->string('question_source_name')->nullable();
            $table->string('question_source_path');
            $table->string('question_source_type', 20);
            $table->string('answer_source_name')->nullable();
            $table->string('answer_source_path')->nullable();
            $table->string('answer_source_type', 20)->nullable();
            $table->string('solution_source_name')->nullable();
            $table->string('solution_source_path')->nullable();
            $table->string('solution_source_type', 20)->nullable();
            $table->json('settings');
            $table->unsignedInteger('detected_questions')->default(0);
            $table->unsignedInteger('ready_questions')->default(0);
            $table->unsignedInteger('review_questions')->default(0);
            $table->text('failure_message')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processing_completed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'created_at']);
        });

        Schema::create('source_exam_question_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_exam_import_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('paper_question_number');
            $table->string('printed_question_number')->nullable();
            $table->string('status', 30)->default('ready')->index();
            $table->json('payload');
            $table->json('source_evidence')->nullable();
            $table->json('ai_fields')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
            $table->unique(['source_exam_import_id', 'paper_question_number'], 'source_exam_draft_number_unique');
        });

        if (Schema::hasTable('pages')) {
            DB::table('pages')->updateOrInsert(
                ['action_name' => 'source-exams.index'],
                ['page_name' => 'Create Exam from Source', 'icon' => 'ri-file-upload-line', 'parent_id' => null, 'ordering' => 15, 'created_at' => now(), 'updated_at' => now()]
            );
            if (Schema::hasTable('page_rights')) {
                $newPage = DB::table('pages')->where('action_name', 'source-exams.index')->value('id');
                $examPage = DB::table('pages')->where('action_name', 'exams.index')->value('id');
                if ($newPage && $examPage) {
                    DB::table('page_rights')->where('page_id', $examPage)->get()->each(function ($right) use ($newPage) {
                        DB::table('page_rights')->updateOrInsert(
                            ['page_id' => $newPage, 'ugroup_id' => $right->ugroup_id],
                            ['view_right' => $right->view_right, 'add_right' => $right->add_right, 'edit_right' => $right->edit_right, 'delete_right' => $right->delete_right]
                        );
                    });
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pages')) {
            $pageId = DB::table('pages')->where('action_name', 'source-exams.index')->value('id');
            if ($pageId && Schema::hasTable('page_rights')) DB::table('page_rights')->where('page_id', $pageId)->delete();
            DB::table('pages')->where('action_name', 'source-exams.index')->delete();
        }
        Schema::dropIfExists('source_exam_question_drafts');
        Schema::dropIfExists('source_exam_imports');
    }
};
