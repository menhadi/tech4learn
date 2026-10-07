<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('configurations', 'pyp_settings')) {
            Schema::table('configurations', function (Blueprint $table) {
                $table->json('pyp_settings')->nullable()->after('source_extractor_mappings');
            });
        }

        if (! Schema::hasTable('pyp_package_settings')) {
            Schema::create('pyp_package_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('package_id')->constrained()->cascadeOnDelete();
                $table->boolean('enabled')->default(true);
                $table->string('analysis_mode', 24)->default('historical');
                $table->boolean('indexable')->default(true);
                $table->string('meta_title')->nullable();
                $table->text('meta_description')->nullable();
                $table->timestamps();
                $table->unique(['organization_id', 'package_id'], 'pyp_package_settings_org_package_unique');
                $table->index(['organization_id', 'enabled']);
            });
        }

        if (! Schema::hasTable('pages')) {
            return;
        }

        $pageId = DB::table('pages')->where('action_name', 'pyp-pages.index')->value('id');
        if (! $pageId) {
            $pageId = DB::table('pages')->insertGetId([
                'page_name' => 'PYP Pages & Analysis',
                'action_name' => 'pyp-pages.index',
                'icon' => 'ri-line-chart-line',
                'parent_id' => null,
                'ordering' => 24,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('page_rights')) {
            $sourcePageId = DB::table('pages')->where('action_name', 'configurations.website')->value('id');
            $groups = $sourcePageId
                ? DB::table('page_rights')->where('page_id', $sourcePageId)->get()
                : DB::table('ugroups')->selectRaw('id as ugroup_id')->get();

            foreach ($groups as $group) {
                $rights = [
                    'view_right' => $group->view_right ?? 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                foreach (['add_right', 'edit_right', 'delete_right'] as $column) {
                    if (Schema::hasColumn('page_rights', $column)) {
                        $rights[$column] = $group->{$column} ?? 0;
                    }
                }
                DB::table('page_rights')->updateOrInsert(
                    ['page_id' => $pageId, 'ugroup_id' => $group->ugroup_id],
                    $rights
                );
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pages')) {
            $pageId = DB::table('pages')->where('action_name', 'pyp-pages.index')->value('id');
            if ($pageId && Schema::hasTable('page_rights')) {
                DB::table('page_rights')->where('page_id', $pageId)->delete();
            }
            DB::table('pages')->where('action_name', 'pyp-pages.index')->delete();
        }

        Schema::dropIfExists('pyp_package_settings');
        if (Schema::hasColumn('configurations', 'pyp_settings')) {
            Schema::table('configurations', fn (Blueprint $table) => $table->dropColumn('pyp_settings'));
        }
    }
};
