<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_quality_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('public_token')->unique();
            $table->string('status', 20)->default('queued')->index();
            $table->boolean('include_ai')->default(false);
            $table->boolean('include_visual')->default(false);
            $table->unsignedInteger('question_limit')->nullable();
            $table->unsignedInteger('total_questions')->default(0);
            $table->unsignedInteger('checked_questions')->default(0);
            $table->unsignedInteger('passed_questions')->default(0);
            $table->unsignedInteger('warning_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->json('options')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'created_at']);
        });

        Schema::create('exam_quality_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audit_id')->constrained('exam_quality_audits')->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 30);
            $table->string('issue_type', 60)->index();
            $table->string('severity', 15)->default('warning')->index();
            $table->string('status', 20)->default('open')->index();
            $table->string('title');
            $table->text('details')->nullable();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->json('evidence')->nullable();
            $table->string('screenshot_path')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['audit_id', 'question_id']);
        });

        if (Schema::hasTable('pages')) {
            DB::table('pages')->updateOrInsert(
                ['action_name' => 'exam-quality.index'],
                [
                    'page_name' => 'Exam Quality Audit',
                    'icon' => 'ri-shield-check-line',
                    'parent_id' => null,
                    'ordering' => 16,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pages')) {
            $pageId = DB::table('pages')->where('action_name', 'exam-quality.index')->value('id');
            if ($pageId && Schema::hasTable('page_rights')) {
                DB::table('page_rights')->where('page_id', $pageId)->delete();
            }
            DB::table('pages')->where('action_name', 'exam-quality.index')->delete();
        }

        Schema::dropIfExists('exam_quality_findings');
        Schema::dropIfExists('exam_quality_audits');
    }
};
