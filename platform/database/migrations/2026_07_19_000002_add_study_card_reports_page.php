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

        DB::table('pages')->updateOrInsert(
            ['action_name' => 'exams.studyCardReports'],
            [
                'page_name' => 'Reported Study Cards',
                'icon' => 'ri-stack-line',
                'parent_id' => null,
                'ordering' => 17,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        if (! Schema::hasTable('page_rights')) {
            return;
        }

        $sourcePageId = DB::table('pages')->where('action_name', 'exams.reports')->value('id');
        $newPageId = DB::table('pages')->where('action_name', 'exams.studyCardReports')->value('id');

        if (! $sourcePageId || ! $newPageId) {
            return;
        }

        DB::table('page_rights')->where('page_id', $sourcePageId)->get()->each(function ($right) use ($newPageId) {
            DB::table('page_rights')->updateOrInsert(
                ['page_id' => $newPageId, 'ugroup_id' => $right->ugroup_id],
                [
                    'view_right' => $right->view_right,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        $pageId = DB::table('pages')->where('action_name', 'exams.studyCardReports')->value('id');
        if ($pageId && Schema::hasTable('page_rights')) {
            DB::table('page_rights')->where('page_id', $pageId)->delete();
        }

        DB::table('pages')->where('action_name', 'exams.studyCardReports')->delete();
    }
};