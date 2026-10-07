<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('image_conversion_runs')) {
            Schema::create('image_conversion_runs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 24)->default('queued')->index();
                $table->unsignedInteger('total_images')->default(0);
                $table->unsignedInteger('processed_images')->default(0);
                $table->unsignedInteger('success_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->index(['organization_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('image_conversion_items')) {
            Schema::create('image_conversion_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('run_id')->constrained('image_conversion_runs')->cascadeOnDelete();
                $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
                $table->foreignId('question_id')->constrained()->cascadeOnDelete();
                $table->string('field', 24);
                $table->unsignedSmallInteger('image_index');
                $table->string('status', 24)->default('queued')->index();
                $table->text('original_src');
                $table->text('source_path')->nullable();
                $table->text('backup_path')->nullable();
                $table->text('png_path')->nullable();
                $table->text('new_src')->nullable();
                $table->text('failure_message')->nullable();
                $table->timestamp('converted_at')->nullable();
                $table->timestamps();
                $table->unique(['run_id', 'question_id', 'field', 'image_index'], 'image_conversion_item_unique');
                $table->index(['organization_id', 'status']);
            });
        }

        if (Schema::hasTable('pages')) {
            DB::table('pages')->updateOrInsert(
                ['action_name' => 'image-converter.index'],
                ['page_name' => 'Image Format Converter', 'icon' => 'ri-file-transfer-line', 'parent_id' => null,
                    'ordering' => 33, 'created_at' => now(), 'updated_at' => now()]
            );
            if (Schema::hasTable('page_rights')) {
                $pageId = DB::table('pages')->where('action_name', 'image-converter.index')->value('id');
                $sourceIds = DB::table('pages')->whereIn('action_name', ['image-cleanup.index', 'questions.index'])->pluck('id');
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
            $pageId = DB::table('pages')->where('action_name', 'image-converter.index')->value('id');
            if ($pageId && Schema::hasTable('page_rights')) DB::table('page_rights')->where('page_id', $pageId)->delete();
            DB::table('pages')->where('action_name', 'image-converter.index')->delete();
        }
        Schema::dropIfExists('image_conversion_items');
        Schema::dropIfExists('image_conversion_runs');
    }
};
