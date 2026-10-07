<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('source_question_import_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('draft')->index();
            $table->string('adapter', 64)->nullable();
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('ready')->default(0);
            $table->unsignedInteger('published')->default(0);
            $table->unsignedInteger('duplicates')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->json('options')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamps();
        });

        Schema::create('source_question_import_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('source_question_import_runs')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->text('source_url');
            $table->string('adapter', 64)->nullable();
            $table->string('status', 32)->default('queued')->index();
            $table->json('metadata')->nullable();
            $table->json('payload')->nullable();
            $table->string('content_hash', 64)->nullable()->index();
            $table->text('error_message')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('question_id')->nullable()->constrained('questions')->nullOnDelete();
            $table->timestamps();
            $table->unique(['run_id', 'row_number']);
            $table->index(['run_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_question_import_items');
        Schema::dropIfExists('source_question_import_runs');
    }
};
