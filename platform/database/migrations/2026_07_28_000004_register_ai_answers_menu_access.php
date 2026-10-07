<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pages')) return;

        DB::table('pages')->updateOrInsert(
            ['action_name' => 'ai-answers.index'],
            [
                'page_name' => 'AI Answers & Explanations',
                'icon' => 'ri-lightbulb-flash-line',
                'parent_id' => null,
                'ordering' => 30,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        if (! Schema::hasTable('page_rights')) return;
        $targetPageId = DB::table('pages')->where('action_name', 'ai-answers.index')->value('id');
        $sourcePageIds = DB::table('pages')
            ->whereIn('action_name', ['configurations.ai', 'exam-quality.index'])
            ->pluck('id');
        if (! $targetPageId || $sourcePageIds->isEmpty()) return;

        $rightColumns = collect(['view_right', 'add_right', 'edit_right', 'delete_right'])
            ->filter(fn ($column) => Schema::hasColumn('page_rights', $column))
            ->values();
        $rights = DB::table('page_rights')->whereIn('page_id', $sourcePageIds)
            ->get()->groupBy('ugroup_id');

        foreach ($rights as $ugroupId => $groupRights) {
            $values = $rightColumns->mapWithKeys(
                fn ($column) => [$column => $groupRights->max($column) ? 1 : 0]
            )->all();
            $values['updated_at'] = now();
            $values['created_at'] = now();
            DB::table('page_rights')->updateOrInsert(
                ['page_id' => $targetPageId, 'ugroup_id' => $ugroupId],
                $values
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('pages')) return;
        $pageId = DB::table('pages')->where('action_name', 'ai-answers.index')->value('id');
        if ($pageId && Schema::hasTable('page_rights')) {
            DB::table('page_rights')->where('page_id', $pageId)->delete();
        }
    }
};
