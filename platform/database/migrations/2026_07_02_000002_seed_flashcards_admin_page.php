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
            ['action_name' => 'flashcards.index'],
            [
                'page_name' => 'Flashcards',
                'icon' => 'ri-flashlight-line',
                'parent_id' => null,
                'ordering' => 14,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        $pageId = DB::table('pages')->where('action_name', 'flashcards.index')->value('id');

        if ($pageId && Schema::hasTable('page_rights')) {
            DB::table('page_rights')->where('page_id', $pageId)->delete();
        }

        DB::table('pages')->where('action_name', 'flashcards.index')->delete();
    }
};
