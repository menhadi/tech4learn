<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_answer_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->uuid('batch_token')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('queued')->index();
            $table->string('provider', 24)->default('auto');
            $table->text('additional_instructions')->nullable();
            $table->unsignedInteger('total_questions')->default(0);
            $table->unsignedInteger('processed_questions')->default(0);
            $table->unsignedInteger('ready_count')->default(0);
            $table->unsignedInteger('discrepancy_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->json('options')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'created_at']);
        });

        Schema::create('ai_answer_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('run_id')->constrained('ai_answer_runs')->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('status', 24)->default('queued')->index();
            $table->string('mode', 32);
            $table->json('original_payload');
            $table->json('proposed_payload')->nullable();
            $table->json('changed_fields')->nullable();
            $table->json('discrepancies')->nullable();
            $table->string('provider', 24)->nullable();
            $table->string('model')->nullable();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('question_updated_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['run_id', 'question_id']);
            $table->index(['organization_id', 'status']);
        });

        Schema::table('question_versions', function (Blueprint $table) {
            $table->foreignId('ai_answer_draft_id')->nullable()->after('repair_draft_id')
                ->constrained('ai_answer_drafts')->nullOnDelete();
        });

        if (Schema::hasTable('pages')) {
            DB::table('pages')->updateOrInsert(
                ['action_name' => 'ai-answers.index'],
                ['page_name' => 'AI Answers & Explanations', 'icon' => 'ri-lightbulb-flash-line',
                    'parent_id' => null, 'ordering' => 17, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pages')) DB::table('pages')->where('action_name', 'ai-answers.index')->delete();
        Schema::table('question_versions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ai_answer_draft_id');
        });
        Schema::dropIfExists('ai_answer_drafts');
        Schema::dropIfExists('ai_answer_runs');
    }
};
