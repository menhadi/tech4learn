<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('configurations')) {
            $columns = [
                'image_cleanup_enabled' => fn (Blueprint $table) => $table->boolean('image_cleanup_enabled')->default(false),
                'image_cleanup_provider' => fn (Blueprint $table) => $table->string('image_cleanup_provider', 24)->default('openai'),
                'image_cleanup_openai_model' => fn (Blueprint $table) => $table->string('image_cleanup_openai_model')->default('gpt-image-2'),
                'image_cleanup_google_model' => fn (Blueprint $table) => $table->string('image_cleanup_google_model')->default('gemini-3.1-flash-image'),
                'image_cleanup_quality' => fn (Blueprint $table) => $table->string('image_cleanup_quality', 16)->default('medium'),
            ];
            foreach ($columns as $name => $definition) {
                if (! Schema::hasColumn('configurations', $name)) Schema::table('configurations', $definition);
            }
        }

        if (! Schema::hasTable('image_cleanup_runs')) {
            Schema::create('image_cleanup_runs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->uuid('batch_token')->index();
                $table->string('status', 24)->default('queued')->index();
                $table->string('provider', 24);
                $table->string('model');
                $table->string('quality', 16)->default('medium');
                $table->string('action', 32)->default('remove_watermark');
                $table->text('instructions')->nullable();
                $table->unsignedInteger('total_images')->default(0);
                $table->unsignedInteger('processed_images')->default(0);
                $table->unsignedInteger('ready_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->text('failure_message')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('stop_requested_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->index(['organization_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('image_cleanup_items')) {
            Schema::create('image_cleanup_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('run_id')->constrained('image_cleanup_runs')->cascadeOnDelete();
                $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
                $table->foreignId('question_id')->constrained()->cascadeOnDelete();
                $table->string('field', 24);
                $table->unsignedSmallInteger('image_index');
                $table->string('status', 24)->default('queued')->index();
                $table->text('original_src');
                $table->longText('original_field_html');
                $table->longText('proposed_field_html')->nullable();
                $table->string('output_path')->nullable();
                $table->string('provider', 24)->nullable();
                $table->string('model')->nullable();
                $table->string('request_id')->nullable();
                $table->text('failure_message')->nullable();
                $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->unique(['run_id', 'question_id', 'field', 'image_index'], 'image_cleanup_item_unique');
                $table->index(['organization_id', 'status']);
            });
        }

        if (Schema::hasTable('pages')) {
            DB::table('pages')->updateOrInsert(
                ['action_name' => 'image-cleanup.index'],
                ['page_name' => 'Image Cleanup Bot', 'icon' => 'ri-image-edit-line', 'parent_id' => null,
                    'ordering' => 32, 'created_at' => now(), 'updated_at' => now()]
            );
            if (Schema::hasTable('page_rights')) {
                $pageId = DB::table('pages')->where('action_name', 'image-cleanup.index')->value('id');
                $sourceIds = DB::table('pages')->whereIn('action_name', ['exam-quality.index', 'configurations.ai'])->pluck('id');
                $rightColumns = collect(['view_right', 'add_right', 'edit_right', 'delete_right'])
                    ->filter(fn ($column) => Schema::hasColumn('page_rights', $column));
                foreach (DB::table('page_rights')->whereIn('page_id', $sourceIds)->get()->groupBy('ugroup_id') as $ugroupId => $rights) {
                    $values = $rightColumns->mapWithKeys(fn ($column) => [$column => $rights->max($column) ? 1 : 0])->all();
                    $values += ['created_at' => now(), 'updated_at' => now()];
                    DB::table('page_rights')->updateOrInsert(['page_id' => $pageId, 'ugroup_id' => $ugroupId], $values);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pages')) {
            $pageId = DB::table('pages')->where('action_name', 'image-cleanup.index')->value('id');
            if ($pageId && Schema::hasTable('page_rights')) DB::table('page_rights')->where('page_id', $pageId)->delete();
            DB::table('pages')->where('action_name', 'image-cleanup.index')->delete();
        }
        Schema::dropIfExists('image_cleanup_items');
        Schema::dropIfExists('image_cleanup_runs');
        if (Schema::hasTable('configurations')) {
            $columns = collect(['image_cleanup_enabled', 'image_cleanup_provider', 'image_cleanup_openai_model', 'image_cleanup_google_model', 'image_cleanup_quality'])
                ->filter(fn ($column) => Schema::hasColumn('configurations', $column))->all();
            if ($columns) Schema::table('configurations', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
