<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        $imageCleanup = DB::table('pages')->where('action_name', 'image-cleanup.index')->first();
        $ordering = ((int) ($imageCleanup->ordering ?? 32)) + 1;
        DB::table('pages')->updateOrInsert(
            ['action_name' => 'content-normalization.index'],
            [
                'page_name' => 'Math Content Cleanup',
                'icon' => 'ri-function-line',
                'parent_id' => null,
                'ordering' => $ordering,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        if (! Schema::hasTable('page_rights')) {
            return;
        }

        $pageId = DB::table('pages')->where('action_name', 'content-normalization.index')->value('id');
        $sourceId = $imageCleanup?->id;
        if (! $pageId || ! $sourceId) {
            return;
        }

        $rightColumns = collect(['view_right', 'add_right', 'edit_right', 'delete_right'])
            ->filter(fn ($column) => Schema::hasColumn('page_rights', $column));
        foreach (DB::table('page_rights')->where('page_id', $sourceId)->get() as $right) {
            $values = $rightColumns->mapWithKeys(fn ($column) => [$column => (int) $right->{$column}])->all();
            $values += ['created_at' => now(), 'updated_at' => now()];
            DB::table('page_rights')->updateOrInsert(
                ['page_id' => $pageId, 'ugroup_id' => $right->ugroup_id],
                $values
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        $pageId = DB::table('pages')->where('action_name', 'content-normalization.index')->value('id');
        if ($pageId && Schema::hasTable('page_rights')) {
            DB::table('page_rights')->where('page_id', $pageId)->delete();
        }
        DB::table('pages')->where('action_name', 'content-normalization.index')->delete();
    }
};
