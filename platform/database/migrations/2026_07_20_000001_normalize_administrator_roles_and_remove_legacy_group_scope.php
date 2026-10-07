<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('organization_users')) {
            return;
        }

        DB::table('users')
            ->whereNotNull('ugroup_id')
            ->where('ugroup_id', '>', 0)
            ->orderBy('id')
            ->select('id')
            ->chunkById(500, function ($users) {
                $userIds = $users->pluck('id')->all();

                DB::table('organization_users')
                    ->whereIn('user_id', $userIds)
                    ->where('role', 'admin')
                    ->update(['role' => 'staff', 'updated_at' => now()]);

                if (Schema::hasTable('user_groups')) {
                    DB::table('user_groups')->whereIn('user_id', $userIds)->delete();
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('organization_users')) {
            return;
        }

        DB::table('users')
            ->whereNotNull('ugroup_id')
            ->where('ugroup_id', '>', 0)
            ->orderBy('id')
            ->select('id')
            ->chunkById(500, function ($users) {
                DB::table('organization_users')
                    ->whereIn('user_id', $users->pluck('id')->all())
                    ->where('role', 'staff')
                    ->update(['role' => 'admin', 'updated_at' => now()]);
            });
    }
};