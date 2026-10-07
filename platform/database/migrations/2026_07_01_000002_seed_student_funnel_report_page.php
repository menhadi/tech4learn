<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $actionName = 'results.student-funnel';

    public function up(): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        DB::table('pages')->updateOrInsert(
            ['action_name' => $this->actionName],
            [
                'page_name' => 'Student Funnel',
                'icon' => 'ri-route-line',
                'parent_id' => null,
                'ordering' => 18,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        // Custom roles can be granted access from Users & Roles > Role Permissions.
    }

    public function down(): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        $pageId = DB::table('pages')->where('action_name', $this->actionName)->value('id');

        if ($pageId && Schema::hasTable('page_rights')) {
            DB::table('page_rights')->where('page_id', $pageId)->delete();
        }

        DB::table('pages')->where('action_name', $this->actionName)->delete();
    }
};
