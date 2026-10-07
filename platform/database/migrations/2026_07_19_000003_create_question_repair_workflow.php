<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_repair_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audit_id')->constrained('exam_quality_audits')->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('status', 24)->default('queued')->index();
            $table->json('original_payload');
            $table->json('proposed_payload')->nullable();
            $table->json('changed_fields')->nullable();
            $table->json('evidence')->nullable();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('question_updated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['audit_id', 'question_id']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('question_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('repair_draft_id')->nullable()->constrained('question_repair_drafts')->nullOnDelete();
            $table->json('payload');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['question_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_versions');
        Schema::dropIfExists('question_repair_drafts');
    }
};