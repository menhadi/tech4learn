<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('url_redirects')) {
            Schema::create('url_redirects', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
                $table->string('source_path', 700);
                $table->string('target_path', 2048);
                $table->unsignedSmallInteger('status_code')->default(301);
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedBigInteger('hit_count')->default(0);
                $table->timestamp('last_hit_at')->nullable();
                $table->string('note', 500)->nullable();
                $table->timestamps();

                $table->unique(['organization_id', 'source_path'], 'url_redirects_org_source_unique');
                $table->index(['organization_id', 'is_active'], 'url_redirects_org_active_index');
            });
        }

        if (Schema::hasTable('pages')) {
            DB::table('pages')->updateOrInsert(
                ['action_name' => 'redirects.index'],
                [
                    'page_name' => 'URL Redirects',
                    'icon' => 'ri-route-line',
                    'parent_id' => null,
                    'ordering' => 28,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            if (Schema::hasTable('page_rights')) {
                $pageId = DB::table('pages')->where('action_name', 'redirects.index')->value('id');
                $sourcePageId = DB::table('pages')->where('action_name', 'websitepages.index')->value('id');

                if ($pageId && $sourcePageId) {
                    $rightColumns = collect(['view_right', 'add_right', 'edit_right', 'delete_right'])
                        ->filter(fn ($column) => Schema::hasColumn('page_rights', $column));

                    foreach (DB::table('page_rights')->where('page_id', $sourcePageId)->get() as $right) {
                        $values = $rightColumns
                            ->mapWithKeys(fn ($column) => [$column => (int) $right->{$column}])
                            ->all();
                        $values += ['created_at' => now(), 'updated_at' => now()];

                        DB::table('page_rights')->updateOrInsert(
                            ['page_id' => $pageId, 'ugroup_id' => $right->ugroup_id],
                            $values
                        );
                    }
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pages')) {
            $pageId = DB::table('pages')->where('action_name', 'redirects.index')->value('id');

            if ($pageId && Schema::hasTable('page_rights')) {
                DB::table('page_rights')->where('page_id', $pageId)->delete();
            }

            DB::table('pages')->where('action_name', 'redirects.index')->delete();
        }

        Schema::dropIfExists('url_redirects');
    }
};
