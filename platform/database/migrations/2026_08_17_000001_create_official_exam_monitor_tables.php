<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('official_exam_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('website_name', 120);
            $table->string('name', 160);
            $table->text('source_url');
            $table->string('driver', 20)->default('static');
            $table->unsignedInteger('check_interval_minutes')->default(360);
            $table->boolean('enabled')->default(true);
            $table->string('automation_mode', 20)->default('queue');
            $table->unsignedInteger('config_version')->default(1);
            $table->json('discovery_settings');
            $table->json('exam_defaults');
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('next_check_at')->nullable()->index();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'name'], 'official_sources_org_name_unique');
            $table->index(['enabled', 'next_check_at'], 'official_sources_due_index');
        });

        Schema::create('official_exam_source_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('official_exam_source_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->unsignedInteger('priority')->default(100);
            $table->text('match_pattern')->nullable();
            $table->string('language_mode', 20)->default('single');
            $table->foreignId('language_id')->nullable()->constrained()->nullOnDelete();
            $table->string('extractor_script', 255);
            $table->string('ready_policy', 30)->default('question_only');
            $table->string('exam_name_template', 255)->default('{detected_name}');
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->index(['official_exam_source_id', 'enabled', 'priority'], 'official_rules_match_index');
        });

        Schema::create('official_exam_source_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('official_exam_source_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('running')->index();
            $table->unsignedInteger('found_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('review_count')->default(0);
            $table->json('diagnostics')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('official_exam_discoveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('official_exam_source_id')->constrained()->cascadeOnDelete();
            $table->foreignId('official_exam_source_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('official_exam_source_run_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('exam_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('source_exam_import_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_exam_key', 64);
            $table->string('content_hash', 64)->nullable();
            $table->string('observed_content_hash', 64)->nullable();
            $table->string('status', 30)->default('detected')->index();
            $table->string('exam_name');
            $table->text('question_url')->nullable();
            $table->text('answer_url')->nullable();
            $table->text('combined_url')->nullable();
            $table->text('archive_url')->nullable();
            $table->json('document_hashes')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('source_config_version')->default(1);
            $table->unsignedInteger('rule_version')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('revised_at')->nullable();
            $table->timestamps();
            $table->unique(['official_exam_source_id', 'external_exam_key'], 'official_discovery_exam_unique');
            $table->index(['organization_id', 'status', 'last_seen_at'], 'official_discovery_status_index');
        });

        if (Schema::hasTable('pages')) {
            DB::table('pages')->updateOrInsert(
                ['action_name' => 'official-exam-sources.index'],
                ['page_name' => 'Official Exam Monitor', 'icon' => 'ri-radar-line', 'parent_id' => null, 'ordering' => 16, 'created_at' => now(), 'updated_at' => now()]
            );
            if (Schema::hasTable('page_rights')) {
                $newPage = DB::table('pages')->where('action_name', 'official-exam-sources.index')->value('id');
                $examPage = DB::table('pages')->where('action_name', 'source-exams.index')->value('id');
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
            $pageId = DB::table('pages')->where('action_name', 'official-exam-sources.index')->value('id');
            if ($pageId && Schema::hasTable('page_rights')) DB::table('page_rights')->where('page_id', $pageId)->delete();
            DB::table('pages')->where('action_name', 'official-exam-sources.index')->delete();
        }
        Schema::dropIfExists('official_exam_discoveries');
        Schema::dropIfExists('official_exam_source_runs');
        Schema::dropIfExists('official_exam_source_rules');
        Schema::dropIfExists('official_exam_sources');
    }
};
