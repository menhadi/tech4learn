<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_curriculum_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('run_id')->constrained('ai_answer_runs')->cascadeOnDelete();
            $table->foreignId('draft_id')->constrained('ai_answer_drafts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type', 16);
            $table->unsignedBigInteger('entity_id');
            $table->string('name', 150);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['draft_id', 'entity_type', 'entity_id'], 'ai_curriculum_events_draft_entity_unique');
            $table->index(['organization_id', 'created_at']);
            $table->index(['run_id', 'entity_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_curriculum_events');
    }
};
