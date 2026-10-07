<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_normalization_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('mode', 20);
            $table->string('status', 30)->default('pending');
            $table->json('filters');
            $table->unsignedBigInteger('cursor_question_id')->default(0);
            $table->unsignedInteger('total_questions')->default(0);
            $table->unsignedInteger('processed_questions')->default(0);
            $table->json('stats')->nullable();
            $table->json('child_run_ids')->nullable();
            $table->json('restored_child_run_ids')->nullable();
            $table->unsignedInteger('restore_skipped')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'created_at'], 'content_normalization_runs_org_created_idx');
            $table->index(['status', 'updated_at'], 'content_normalization_runs_status_updated_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_normalization_runs');
    }
};
