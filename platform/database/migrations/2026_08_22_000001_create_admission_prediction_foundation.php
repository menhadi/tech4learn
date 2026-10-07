<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('admission_exam_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name', 180);
            $table->string('country_code', 2)->default('IN');
            $table->json('authorities');
            $table->json('official_domains');
            $table->json('document_delivery_domains');
            $table->json('score_schema');
            $table->json('rank_dimensions');
            $table->json('admission_dimensions');
            $table->json('resource_kinds');
            $table->unsignedInteger('config_version')->default(1);
            $table->boolean('enabled')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('admission_official_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_exam_definition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('supersedes_resource_id')->nullable()->constrained('admission_official_resources')->nullOnDelete();
            $table->string('title');
            $table->string('resource_kind', 60);
            $table->unsignedSmallInteger('exam_year')->nullable()->index();
            $table->text('source_url');
            $table->text('listing_url')->nullable();
            $table->string('source_host', 190);
            $table->string('listing_host', 190)->nullable();
            $table->string('source_fingerprint', 64);
            $table->string('file_disk', 40)->nullable();
            $table->string('file_path', 1024)->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->string('sha256', 64)->nullable()->index();
            $table->string('status', 30)->default('registered')->index();
            $table->date('published_on')->nullable();
            $table->timestamp('extracted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->json('extraction_metadata')->nullable();
            $table->json('metadata')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
            $table->unique(['admission_exam_definition_id', 'source_fingerprint'], 'admission_resource_exam_source_unique');
            $table->index(['admission_exam_definition_id', 'resource_kind', 'exam_year'], 'admission_resource_lookup_index');
        });

        Schema::create('admission_dataset_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_exam_definition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('version', 60);
            $table->unsignedInteger('schema_version')->default(1);
            $table->string('status', 30)->default('draft')->index();
            $table->json('validation_summary')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['admission_exam_definition_id', 'version'], 'admission_dataset_exam_version_unique');
        });

        Schema::create('admission_dataset_resources', function (Blueprint $table) {
            $table->foreignId('admission_dataset_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admission_official_resource_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['admission_dataset_version_id', 'admission_official_resource_id'], 'admission_dataset_resource_primary');
        });

        Schema::create('admission_rank_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_dataset_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admission_official_resource_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('exam_year')->index();
            $table->string('session', 40)->nullable();
            $table->string('shift', 80)->nullable();
            $table->string('category', 60)->nullable();
            $table->decimal('marks', 8, 3)->nullable();
            $table->decimal('max_marks', 8, 3)->nullable();
            $table->decimal('percentile', 10, 7)->nullable();
            $table->unsignedInteger('rank_min')->nullable();
            $table->unsignedInteger('rank_max')->nullable();
            $table->unsignedInteger('candidate_count')->nullable();
            $table->json('subject_marks')->nullable();
            $table->json('dimensions')->nullable();
            $table->json('provenance')->nullable();
            $table->timestamps();
            $table->index(['admission_dataset_version_id', 'exam_year', 'category', 'marks'], 'admission_rank_lookup_index');
        });

        Schema::create('admission_cutoff_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_dataset_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admission_official_resource_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('exam_year')->index();
            $table->string('counselling_body', 120)->nullable();
            $table->string('round', 60)->nullable();
            $table->string('institution_code', 100)->nullable();
            $table->string('institution_name');
            $table->string('program_code', 100)->nullable();
            $table->string('program_name');
            $table->string('category', 60)->nullable();
            $table->string('quota', 100)->nullable();
            $table->string('gender_pool', 100)->nullable();
            $table->string('domicile_state', 100)->nullable();
            $table->unsignedInteger('opening_rank')->nullable();
            $table->unsignedInteger('closing_rank')->nullable();
            $table->unsignedInteger('seat_count')->nullable();
            $table->json('dimensions')->nullable();
            $table->json('provenance')->nullable();
            $table->timestamps();
            $table->index(['admission_dataset_version_id', 'exam_year', 'category', 'closing_rank'], 'admission_cutoff_lookup_index');
            $table->index(['institution_code', 'program_code'], 'admission_cutoff_program_index');
        });

        Schema::create('admission_prediction_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_exam_definition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admission_dataset_version_id')->constrained()->restrictOnDelete();
            $table->string('model_kind', 40);
            $table->string('version', 60);
            $table->string('algorithm', 120);
            $table->string('status', 30)->default('training')->index();
            $table->string('artifact_disk', 40)->nullable();
            $table->string('artifact_path', 1024)->nullable();
            $table->json('feature_schema');
            $table->json('metrics')->nullable();
            $table->timestamp('trained_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
            $table->unique(['admission_exam_definition_id', 'model_kind', 'version'], 'admission_model_exam_kind_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_prediction_models');
        Schema::dropIfExists('admission_cutoff_observations');
        Schema::dropIfExists('admission_rank_observations');
        Schema::dropIfExists('admission_dataset_resources');
        Schema::dropIfExists('admission_dataset_versions');
        Schema::dropIfExists('admission_official_resources');
        Schema::dropIfExists('admission_exam_definitions');
    }
};
