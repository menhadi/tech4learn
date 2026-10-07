<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_repair_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audit_id')->constrained('exam_quality_audits')->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status', 24)->default('published')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('restored_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'exam_id']);
        });

        Schema::create('question_repair_release_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_id')->constrained('question_repair_releases')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('repair_draft_id')->nullable()->constrained('question_repair_drafts')->nullOnDelete();
            $table->foreignId('published_version_id')->constrained('question_versions')->cascadeOnDelete();
            $table->foreignId('restored_version_id')->nullable()->constrained('question_versions')->nullOnDelete();
            $table->string('status', 24)->default('published')->index();
            $table->timestamp('restored_at')->nullable();
            $table->timestamps();
            $table->unique(['release_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_repair_release_items');
        Schema::dropIfExists('question_repair_releases');
    }
};
