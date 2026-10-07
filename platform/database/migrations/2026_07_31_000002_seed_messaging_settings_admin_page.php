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
            ['action_name' => 'configurations.messaging'],
            [
                'page_name' => 'Messaging Settings',
                'icon' => 'ri-message-3-line',
                'parent_id' => null,
                'ordering' => 33,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('pages')) {
            DB::table('pages')->where('action_name', 'configurations.messaging')->delete();
        }
    }
};
