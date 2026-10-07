<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('languages')
            || ! Schema::hasTable('organizations')
            || ! Schema::hasColumn('languages', 'organization_id')
            || ! Schema::hasColumn('languages', 'is_enabled')
        ) {
            return;
        }

        $platformOrganizationId = DB::table('organizations')->where('slug', 'examelite')->value('id');

        if (! $platformOrganizationId) {
            return;
        }

        DB::table('languages')
            ->where('organization_id', '!=', $platformOrganizationId)
            ->update(['is_enabled' => false]);
    }

    public function down(): void
    {
        if (
            ! Schema::hasTable('languages')
            || ! Schema::hasTable('organizations')
            || ! Schema::hasColumn('languages', 'organization_id')
            || ! Schema::hasColumn('languages', 'is_enabled')
        ) {
            return;
        }

        $platformOrganizationId = DB::table('organizations')->where('slug', 'examelite')->value('id');

        if (! $platformOrganizationId) {
            return;
        }

        DB::table('languages')
            ->where('organization_id', '!=', $platformOrganizationId)
            ->whereRaw('LOWER(code) = ?', ['en'])
            ->update(['is_enabled' => true]);
    }
};
